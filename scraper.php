<?php
require_once 'db_connection.php';

// URL-urile folosite pentru scraping
define('BASE_URL', 'https://www.web-scraping.dev');
define('LOGIN_URL', BASE_URL . '/login');
define('PRODUCTS_URL', BASE_URL . '/products?category=consumables');

//Obtin cursul valutar live de la frankfurter.app (API gratuit, fara key). Cel putin asa am gasit, sper ca am citit si inteles bine :)))
function getExchangeRate($currency = 'USD')
{
    $url = 'https://api.frankfurter.app/latest?from=' . $currency . '&to=RON';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);// returnez raspunsul in loc sa il afisez
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);// urmaresc redirecturile (301), altfel imi dadea 301, de aia ma pus asta aici
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if (!$response) {
        echo "Exchange rate error: " . $error . "\n";
        return null;
    }

    $data = json_decode($response, true);
    return $data['rates']['RON'] ?? null; // ?? null = daca cheia nu exista returnez null
}

// Fac login pe site si returnez fisierul cu cookies pentru sesiunea autentificata
function loginAndGetCookies()
{
    //fisier temporar pentru stocarea cookies intre request-uri
    $cookieFile = tempnam(sys_get_temp_dir(), 'cookies_');

    $ch = curl_init();

    // Primul request GET, incarc pagina de login ca sa obtin CSRF token
    curl_setopt($ch, CURLOPT_URL, LOGIN_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);// salvez cookies in fisier
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);// trimit cookies din fisier
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $loginPage = curl_exec($ch);

    // extrag CSRF token din HTML-ul paginii de login
    // CSRF token previne atacuri cross-site deoarece e ultra secret si generat pe moment
    // pentru sesiunea X din prezent a utilizatorului Y din prezent din ce stiu si ca trebuie trimis cu POST-ul de login.
    $csrfToken = '';
    if (preg_match('/<input[^>]+name=["\']csrf_token["\'][^>]+value=["\']([^"\']+)["\']/', $loginPage, $matches)) {
        $csrfToken = $matches[1];
    }
    // incerc si formatul alternativ al input-ului
    if (empty($csrfToken) && preg_match('/<input[^>]+value=["\']([^"\']+)["\'][^>]+name=["\']csrf_token["\']/', $loginPage, $matches)) {
        $csrfToken = $matches[1];
    }

    //al doilea request POST -> trimit credentialele si CSRF token
    $postData = http_build_query([
        'username' => 'admin',
        'password' => 'admin',
        'csrf_token' => $csrfToken
    ]);

    curl_setopt($ch, CURLOPT_URL, LOGIN_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);// urmarire redirect dupa login
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);

    curl_close($ch);

    return $cookieFile;// returnare calea fisierului cu cookies pentru request-urile urmatoare
}

// Extrag toate produsele din categoria consumables, pagina cu pagina
function scrapeProducts($cookieFile)
{
    $products = [];
    $page = 1;

    while (true) {
        $url = PRODUCTS_URL . '&page=' . $page;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);//trimitere cookies pentru autentificare
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html)
            break;

        // parsare HTML cu DOMDocument
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);//suprimez atentionarile de html invalid
        $dom->loadHTML($html);
        libxml_clear_errors();

        // XPath imi permite sa selectez elemente din DOM cu query-uri
        $xpath = new DOMXPath($dom);

        //Aici selectez toate elementele care au clasa "product"
        $productCards = $xpath->query('//*[contains(@class, "product")]');

        if ($productCards->length === 0)// nu mai sunt produse, ies
            break;

        $foundOnPage = 0;

        foreach ($productCards as $card) {
            // Cautare elementul cu clasa "name" sau un heading in cardul curent
            $nameNode = $xpath->query('.//*[contains(@class, "name") or self::h2 or self::h3]', $card)->item(0);
            $name = $nameNode ? trim($nameNode->textContent) : '';

            // extrag pretul si il parsez
            $priceNode = $xpath->query('.//*[contains(@class, "price")]', $card)->item(0);
            $priceText = $priceNode ? trim($priceNode->textContent) : '0';
            preg_match('/[\d,]+\.?\d*/', $priceText, $priceMatch);// extrag doar numerele
            $price = isset($priceMatch[0]) ? (float) str_replace(',', '', $priceMatch[0]) : 0;

            //detectare moneda din simbolul din textul pretului
            $currency = 'USD';
            if (strpos($priceText, '€') !== false)
                $currency = 'EUR';
            elseif (strpos($priceText, '£') !== false)
                $currency = 'GBP';
            elseif (strpos($priceText, '$') !== false)
                $currency = 'USD';

            //extrag URL-ul imaginii
            $descNode = $xpath->query('.//*[contains(@class, "description") or contains(@class, "desc")]', $card)->item(0);
            $description = $descNode ? trim($descNode->textContent) : '';

            $imgNode = $xpath->query('.//img', $card)->item(0);
            $image = '';
            if ($imgNode) {
                $src = $imgNode->getAttribute('src');
                //daca e URL relativ (ex: /assets/img.jpg), adaug un simplu base URL
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

        //verific daca exista buton/link pentru pagina urmatoare
        if ($foundOnPage === 0)
            break;

        $nextBtn = $xpath->query('//*[contains(@class, "next") or contains(@href, "page=' . ($page + 1) . '")]');
        if ($nextBtn->length === 0)// s-a ajuns la ultima pagina
            break;

        $page++;
    }

    return $products;
}

//eliminare produsele duplicate dupa Name inainte de salvare in DB
function deduplicateProducts($products)
{
    $unique = [];
    $seenNames = [];

    foreach ($products as $product) {
        if (!in_array($product['name'], $seenNames)) {
            $seenNames[] = $product['name'];
            $unique[] = $product;
        }
    }

    return $unique;
}

// salvez produsele in DB
function saveProducts($products, $exchangeRate)
{
    global $con;

    $inserted = 0;
    $skipped = 0;

    foreach ($products as $product) {
        //calcul pret in RON folosind cursul valutar
        $priceRON = $exchangeRate ? round($product['price'] * $exchangeRate, 2) : null;

        try {
            // INSERT IGNORE == daca exista deja un produs cu acelasi Name (UNIQUE),
            // query-ul e ignorat fara eroare in loc sa arunce exceptie, ca programul sa tot mearga inainte si sa nu crape
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

            // rowCount() returneaza numarul de randuri afectate
            // 0 = produsul exista deja (IGNORE a sarit peste el)
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

//executia principala a fisierului
//bag mana in foc sa zic ca e un fel de "main" camuflat
echo "Starting scraper...\n";

echo "Fetching exchange rate...\n";
$exchangeRate = getExchangeRate('USD');
echo "Exchange rate USD/RON: " . ($exchangeRate ?? 'unavailable') . "\n";

echo "Logging in...\n";
$cookieFile = loginAndGetCookies();
echo "Login done.\n";

echo "Scraping products...\n";
$products = scrapeProducts($cookieFile);
echo "Found (raw): " . count($products) . " products.\n";

// deduplicare inainte de salvare
$products = deduplicateProducts($products);
echo "After deduplication: " . count($products) . " unique products.\n";

if (!empty($products)) {
    $result = saveProducts($products, $exchangeRate);
    echo "Inserted: {$result['inserted']}, Skipped (duplicate in DB): {$result['skipped']}\n";
} else {
    echo "No products found.\n";
}

// sterg fisierul temporar de cookies deoarece nu mai e nevoie de el, se finito
@unlink($cookieFile);

echo "Done.\n";
?>