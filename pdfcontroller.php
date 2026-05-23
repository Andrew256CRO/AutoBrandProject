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

    // Curatam textul
    $text = preg_replace('/\s+/', ' ', $text);

    // Pattern pentru linia de produs din factura:
    // Format: [numar_linie] [cod_produs] [denumire] [pret] [moneda] [cantitate]
    // Ex: "1 172812F COMUTATOR PORNIRE FEBI 251.96 RON -1"
    $pattern = '/(\d+)\s+([A-Z0-9]+)\s+([A-Z][A-Z0-9\s]+?)\s+([\d]+\.[\d]{2})\s+(RON|EUR|USD)\s+(-?\d+)/i';

    if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $products[] = [
                'cod_produs' => trim($match[2]),
                'denumire' => trim($match[3]),
                'pret_unitar' => (float) $match[4],
                'moneda' => strtoupper($match[5]),
                'cantitate' => (int) $match[6]
            ];
        }
    }

    // Daca pattern-ul principal nu gaseste nimic, incercam alternativ
    if (empty($products)) {
        // Cautam codul de produs cunoscut si extragem contextul
        $altPattern = '/([A-Z0-9]{5,})\s+([A-Z][A-Z\s]+?)\s+([\d]+\.[\d]{2})\s+(RON|EUR|USD)\s+(-?\d+)/i';
        if (preg_match_all($altPattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $products[] = [
                    'cod_produs' => trim($match[1]),
                    'denumire' => trim($match[2]),
                    'pret_unitar' => (float) $match[3],
                    'moneda' => strtoupper($match[4]),
                    'cantitate' => (int) $match[5]
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