<?php
require_once 'vendor/autoload.php';// Autoloader-ul Composer incarca libraria smalot/pdfparser
//Practic PHP nu poate sa parseze nativ, de aia folosesc libraria asta, din ce stiu

use Smalot\PdfParser\Parser;

header('Content-Type: application/json');

//verific ca request-ul e POST si ca a fost trimis un fisier
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['pdf'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['pdf'];

//verific ca upload-ul s-a facut fara erori
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Upload error']);
    exit;
}

//verific extensia fisierului sa fie DOAR "PDF"
if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'pdf') {
    echo json_encode(['success' => false, 'message' => 'File must be a PDF']);
    exit;
}

try {
    $parser = new Parser();
    //parsez fisierul din locatia temporara unde PHP l-a salvat la upload
    $pdf = $parser->parseFile($file['tmp_name']);
    //extrag tot textul din document ca un string
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

    // Normalizare whitespace -> inlocuiesc orice spatii multiple/newlines cu un singur spatiu
    $text = preg_replace('/\s+/', ' ', $text);

    //pattern principal bazat pe structura exacta a facturii:
    //"251.96 RON -1 -1 H87 19 -251.96172812F COMUTATOR PORNIRE FEBI1 172812F"
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

    // Fallback 1, un fel de pattern mai simplu daca primul nu gaseste nimic
    if (empty($products)) {
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

    // Fallback 2, un pattern si mai SIMPLU fata de ultima solutie
    if (empty($products)) {
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

// generare continut CSV din array-ul de produse
function generateCSV($products)
{
    $lines = [];
    $lines[] = 'Cod Produs,Denumire,Pret Unitar,Moneda,Cantitate';// Header CSV

    foreach ($products as $product) {
        $lines[] = implode(',', [
            $product['cod_produs'],
            '"' . str_replace('"', '""', $product['denumire']) . '"',
            $product['pret_unitar'],
            $product['moneda'],
            $product['cantitate']
        ]);
    }

    return implode("\n", $lines);//fiecare linie separata de newline
}
?>