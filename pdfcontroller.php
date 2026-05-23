<?php
require_once 'vendor/autoload.php';

use Smalot\PdfParser\Parser;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['pdf'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['pdf'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Upload error']);
    exit;
}

if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'pdf') {
    echo json_encode(['success' => false, 'message' => 'File must be a PDF']);
    exit;
}

try {
    $parser = new Parser();
    $pdf = $parser->parseFile($file['tmp_name']);
    $text = $pdf->getText();

    $products = extractProductsFromInvoice($text);

    if (empty($products)) {
        echo json_encode(['success' => false, 'message' => 'No products found in PDF']);
        exit;
    }

    $csv = generateCSV($products);

    echo json_encode([
        'success' => true,
        'products' => $products,
        'csv' => $csv
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error parsing PDF: ' . $e->getMessage()]);
}

function extractProductsFromInvoice($text)
{
    $products = [];

    // Curatam textul - inlocuim newlines cu spatiu
    $text = preg_replace('/\s+/', ' ', $text);

    // Din textul brut, linia produsului arata asa:
    // "251.96 RON -1    -1 H87 19 -251.96172812F COMUTATOR PORNIRE FEBI1 172812F"
    // Deci: [pret] [moneda] [cantitate] ... [cod_produs][denumire][linie_nr] [cod_produs]

    // Pattern: pret RON/EUR/USD cantitate ... cod denumire
    // Codul e format din litere si cifre lipit de denumire: "172812F COMUTATOR PORNIRE FEBI"
    $pattern = '/([\d]+\.[\d]{2})\s+(RON|EUR|USD)\s+(-?\d+)[\s\S]*?(-?\d+)\s+[A-Z0-9]+\s+\d+\s+[-\d.]+([A-Z0-9]{5,})\s+([A-Z][A-Z0-9\s]+?)\d+\s+\5/';

    if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $products[] = [
                'cod_produs' => trim($match[5]),
                'denumire' => trim($match[6]),
                'pret_unitar' => (float) $match[1],
                'moneda' => $match[2],
                'cantitate' => (int) $match[3]
            ];
        }
    }

    // Daca nu gaseste cu pattern complex, folosim pattern simplu direct pe date cunoscute
    if (empty($products)) {
        // Pattern simplificat: cauta [pret] [RON/EUR/USD] [cantitate negativa sau pozitiva]
        // si [cod alfanumeric] [denumire majuscule]
        $simplePattern = '/([\d]+\.[\d]{2})\s+(RON|EUR|USD)\s+(-?\d+)\s+.*?([A-Z0-9]{5,})\s+([A-Z][A-Z\s]+?)(?=\d|\s*[A-Z0-9]{5,}|\s*Identificator)/';

        if (preg_match_all($simplePattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $products[] = [
                    'cod_produs' => trim($match[4]),
                    'denumire' => trim($match[5]),
                    'pret_unitar' => (float) $match[1],
                    'moneda' => $match[2],
                    'cantitate' => (int) $match[3]
                ];
            }
        }
    }

    // Fallback - hardcodat pe structura exacta a acestei facturi
    if (empty($products)) {
        // Stim exact ce contine factura din debug
        // "251.96 RON -1 -1 H87 19 -251.96172812F COMUTATOR PORNIRE FEBI1 172812F"
        $fallbackPattern = '/([\d]+\.[\d]{2})\s+(RON|EUR|USD)\s+(-?\d+).*?([\d]+[A-Z]+|[A-Z]+[\d]+[A-Z]*)\s+([A-Z][A-Z0-9\s]+?)(?=\d+\s+\4|Identificator)/';

        if (preg_match_all($fallbackPattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $products[] = [
                    'cod_produs' => trim($match[4]),
                    'denumire' => trim($match[5]),
                    'pret_unitar' => (float) $match[1],
                    'moneda' => $match[2],
                    'cantitate' => (int) $match[3]
                ];
            }
        }
    }

    return $products;
}

function generateCSV($products)
{
    $lines = [];
    $lines[] = 'Cod Produs,Denumire,Pret Unitar,Moneda,Cantitate';

    foreach ($products as $product) {
        $lines[] = implode(',', [
            $product['cod_produs'],
            '"' . str_replace('"', '""', $product['denumire']) . '"',
            $product['pret_unitar'],
            $product['moneda'],
            $product['cantitate']
        ]);
    }

    return implode("\n", $lines);
}
?>