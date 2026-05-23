<?php
require_once 'db_connection.php';

// ===== CONFIGURARE =====
define('BASE_URL', 'https://www.web-scraping.dev');
define('LOGIN_URL', BASE_URL . '/login');
define('PRODUCTS_URL', BASE_URL . '/products?category=consumables');

// ===== CURSUL VALUTAR (bonus) =====
function getExchangeRate($currency = 'USD')
{
    $url = 'https://api.frankfurter.app/latest?from=' . $currency . '&to=RON';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // <-- asta lipsea
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if (!$response) {
        echo "Exchange rate error: " . $error . "\n";
        return null;
    }

    $data = json_decode($response, true);
    return $data['rates']['RON'] ?? null;
}

// ===== LOGIN CU cURL =====
function loginAndGetCookies()
{
    $cookieFile = tempnam(sys_get_temp_dir(), 'cookies_');

    $ch = curl_init();

    // Primul request - GET pe pagina de login ca sa luam CSRF token
    curl_setopt($ch, CURLOPT_URL, LOGIN_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $loginPage = curl_exec($ch);

    // Extragem CSRF token
    $csrfToken = '';
    if (preg_match('/<input[^>]+name=["\']csrf_token["\'][^>]+value=["\']([^"\']+)["\']/', $loginPage, $matches)) {
        $csrfToken = $matches[1];
    }
    if (empty($csrfToken) && preg_match('/<input[^>]+value=["\']([^"\']+)["\'][^>]+name=["\']csrf_token["\']/', $loginPage, $matches)) {
        $csrfToken = $matches[1];
    }

    // Al doilea request - POST cu credentialele
    $postData = http_build_query([
        'username' => 'admin',
        'password' => 'admin',
        'csrf_token' => $csrfToken
    ]);

    curl_setopt($ch, CURLOPT_URL, LOGIN_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);

    curl_close($ch);

    return $cookieFile;
}

// ===== SCRAPING PRODUSE =====
function scrapeProducts($cookieFile)
{
    $products = [];
    $page = 1;

    while (true) {
        $url = PRODUCTS_URL . '&page=' . $page;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html)
            break;

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        $productCards = $xpath->query('//*[contains(@class, "product")]');

        if ($productCards->length === 0)
            break;

        $foundOnPage = 0;

        foreach ($productCards as $card) {
            // Denumire
            $nameNode = $xpath->query('.//*[contains(@class, "name") or self::h2 or self::h3]', $card)->item(0);
            $name = $nameNode ? trim($nameNode->textContent) : '';

            // Pret
            $priceNode = $xpath->query('.//*[contains(@class, "price")]', $card)->item(0);
            $priceText = $priceNode ? trim($priceNode->textContent) : '0';
            preg_match('/[\d,]+\.?\d*/', $priceText, $priceMatch);
            $price = isset($priceMatch[0]) ? (float) str_replace(',', '', $priceMatch[0]) : 0;

            // Moneda
            $currency = 'USD';
            if (strpos($priceText, '€') !== false)
                $currency = 'EUR';
            elseif (strpos($priceText, '£') !== false)
                $currency = 'GBP';
            elseif (strpos($priceText, '$') !== false)
                $currency = 'USD';

            // Descriere
            $descNode = $xpath->query('.//*[contains(@class, "description") or contains(@class, "desc")]', $card)->item(0);
            $description = $descNode ? trim($descNode->textContent) : '';

            // Imagine
            $imgNode = $xpath->query('.//img', $card)->item(0);
            $image = '';
            if ($imgNode) {
                $src = $imgNode->getAttribute('src');
                $image = (strpos($src, 'http') === 0) ? $src : BASE_URL . $src;
            }

            if (!empty($name)) {
                $products[] = [
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'currency' => $currency,
                    'image' => $image
                ];
                $foundOnPage++;
            }
        }

        if ($foundOnPage === 0)
            break;

        $nextBtn = $xpath->query('//*[contains(@class, "next") or contains(@href, "page=' . ($page + 1) . '")]');
        if ($nextBtn->length === 0)
            break;

        $page++;
    }

    return $products;
}

// ===== SALVARE IN DB =====
function saveProducts($products, $exchangeRate)
{
    global $con;

    $inserted = 0;
    $skipped = 0;

    foreach ($products as $product) {
        $priceRON = $exchangeRate ? round($product['price'] * $exchangeRate, 2) : null;

        try {
            $query = $con->prepare("
                INSERT IGNORE INTO products (Name, Description, Price, Currency, Image, Price_RON, Exchange_rate)
                VALUES (:name, :description, :price, :currency, :image, :price_ron, :exchange_rate)
            ");

            $query->bindValue(':name', $product['name'], PDO::PARAM_STR);
            $query->bindValue(':description', $product['description'], PDO::PARAM_STR);
            $query->bindValue(':price', $product['price'], PDO::PARAM_STR);
            $query->bindValue(':currency', $product['currency'], PDO::PARAM_STR);
            $query->bindValue(':image', $product['image'], PDO::PARAM_STR);
            $query->bindValue(':price_ron', $priceRON, PDO::PARAM_STR);
            $query->bindValue(':exchange_rate', $exchangeRate, PDO::PARAM_STR);
            $query->execute();

            if ($query->rowCount() > 0) {
                $inserted++;
            } else {
                $skipped++;
            }
        } catch (PDOException $e) {
            echo "Error saving product '{$product['name']}': " . $e->getMessage() . "\n";
        }
    }

    return ['inserted' => $inserted, 'skipped' => $skipped];
}

// ===== MAIN =====
echo "Starting scraper...\n";

// 1. Test cURL extern
echo "Testing external cURL...\n";
$testCurl = curl_init('https://api.frankfurter.app/latest?from=USD&to=RON');
curl_setopt($testCurl, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($testCurl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($testCurl, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($testCurl, CURLOPT_TIMEOUT, 10);

$testResult = curl_exec($testCurl);
$testError = curl_error($testCurl);
curl_close($testCurl);
echo "cURL test: " . ($testResult ?: "FAILED - " . $testError) . "\n";

// 2. Cursul valutar
echo "Fetching exchange rate...\n";
$exchangeRate = getExchangeRate('USD');
echo "Exchange rate USD/RON: " . ($exchangeRate ?? 'unavailable') . "\n";

// 3. Login
echo "Logging in...\n";
$cookieFile = loginAndGetCookies();
echo "Login done.\n";

// 4. Scraping
echo "Scraping products...\n";
$products = scrapeProducts($cookieFile);
echo "Found " . count($products) . " products.\n";

// 5. Salvare
if (!empty($products)) {
    $result = saveProducts($products, $exchangeRate);
    echo "Inserted: {$result['inserted']}, Skipped (duplicate): {$result['skipped']}\n";
} else {
    echo "No products found. Check selectors.\n";
}

@unlink($cookieFile);

echo "Done.\n";
?>