<?php

//// View-ul se ocupa doar de randarea HTML, nu face logica
class ProductsView
{

    // Marcheaza termenul cautat in text cu <mark> pentru highlight
    private static function highlightSearchTerm($text, $searchTerm)
    {
        if (empty($searchTerm) || empty($text)) {
            return htmlspecialchars($text);// no no la XSS
        }
        $pattern = '/' . preg_quote($searchTerm, '/') . '/i';// i = case insensitive
        $highlighted = preg_replace($pattern, '<mark>$0</mark>', $text);
        return $highlighted;
    }

    // Construieste header-ul de coloana cu sageata de sortare
    private static function renderSortableHeader($label, $column, $currentSortBy, $currentSortDir)
    {
        $isActive = ($currentSortBy === $column);// Coloana e activa daca e cea sortata acum
        $newDir = ($isActive && $currentSortDir === 'ASC') ? 'DESC' : 'ASC';// Daca e activa si ASC, urmatorul click va fi DESC si invers
        //Many thanks operatorului ternar ca am scris mai putine linii de cod

        $arrow = '';
        if ($isActive) {
            $arrow = $currentSortDir === 'ASC' ? ' ▲' : ' ▼';
        }

        // onclick apeleaza functia sortBy din script.js cu coloana si noua directie
        return '<th class="sortable' . ($isActive ? ' sort-active' : '') . '" 
                    onclick="sortBy(\'' . $column . '\', \'' . $newDir . '\')">'
            . $label . $arrow .
            '</th>';
    }

    // randeaza tabelul complet cu produsele primite
    public static function renderProductsTable($products, $searchTerm = '', $sortBy = 'Name', $sortDir = 'ASC')
    {
        if (empty($products)) {
            return '<p class="text-center">No products found</p>';
        }

        $html = '<table class="products-table">';
        $html .= '<thead><tr>';
        $html .= '<th>Image</th>';
        // coloanele sortabile au "onclick", Image si Description nu au
        $html .= self::renderSortableHeader('Name', 'Name', $sortBy, $sortDir);
        $html .= '<th>Description</th>';
        $html .= self::renderSortableHeader('Price', 'Price', $sortBy, $sortDir);
        $html .= self::renderSortableHeader('Currency', 'Currency', $sortBy, $sortDir);
        $html .= self::renderSortableHeader('Price (RON)', 'Price_RON', $sortBy, $sortDir);
        $html .= self::renderSortableHeader('Exchange Rate', 'Exchange_rate', $sortBy, $sortDir);
        $html .= '<th>Actions</th>';
        $html .= '</tr></thead>';
        $html .= '<tbody>';

        foreach ($products as $product) {
            //aplicare higlight doar pe coloanele de putem cauta
            $highlightedName = self::highlightSearchTerm($product['Name'], $searchTerm);
            $highlightedDesc = self::highlightSearchTerm($product['Description'] ?? 'N/A', $searchTerm);

            $html .= '<tr>';
            $html .= '<td data-label="Image"><img src="' . htmlspecialchars($product['Image'] ?? '') . '" alt="' . htmlspecialchars($product['Name']) . '" class="product-img"></td>';
            $html .= '<td data-label="Name">' . $highlightedName . '</td>';

            //tooltip cu descrierea completa la hover cu ajutorul la "title" din HTML
            $html .= '<td data-label="Description" title="' . htmlspecialchars($product['Description'] ?? 'N/A') . '">' . $highlightedDesc . '</td>';
            $html .= '<td data-label="Price">' . htmlspecialchars($product['Price']) . '</td>';
            $html .= '<td data-label="Currency">' . htmlspecialchars($product['Currency'] ?? 'N/A') . '</td>';
            $html .= '<td data-label="Price (RON)">' . htmlspecialchars($product['Price_RON'] ?? 'N/A') . '</td>';
            $html .= '<td data-label="Exchange Rate">' . htmlspecialchars($product['Exchange_rate'] ?? 'N/A') . '</td>';
            $html .= '<td data-label="Actions">';

            //butoanele apeleaza functii din script.js cu ID-ul produsului
            $html .= '<button class="btn-edit" onclick="openEditModal(' . $product['Id'] . ')">Edit</button>';
            $html .= '<button class="btn-delete" onclick="confirmDelete(' . $product['Id'] . ')">Delete</button>';
            $html .= '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    // randare butoane de paginare
    public static function renderPagination($currentPage, $totalPages)
    {
        if ($totalPages <= 1) {
            return '';// nu afisez paginarea daca exista DOAR O SINGURA PAGINA
        }

        $html = '<div class="pagination">';

        //butonul de Previous e disabled pe prima pagina, cum ar fi si normal
        if ($currentPage <= 1) {
            $html .= '<button class="btn-pagination" disabled>Previous</button>';
        } else {
            $html .= '<button class="btn-pagination" onclick="loadProducts(' . ($currentPage - 1) . ')">Previous</button>';
        }

        //  butoane numerice pentru fiecare pagina
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = ($i === $currentPage) ? ' active' : '';
            $html .= '<button class="btn-pagination' . $active . '" onclick="loadProducts(' . $i . ')">' . $i . '</button>';
        }

        // butonul de Next e disabled pe ultima pagina
        if ($currentPage >= $totalPages) {
            $html .= '<button class="btn-pagination" disabled>Next</button>';
        } else {
            $html .= '<button class="btn-pagination" onclick="loadProducts(' . ($currentPage + 1) . ')">Next</button>';
        }

        $html .= '</div>';
        return $html;
    }

    // randeaza formularul de add/edit, primind produsul existent la edit, null la add
    public static function renderProductForm($product = null)
    {
        $product = $product ?? [];// daca e null, folosesc array gol

        $html = '<div class="form-group">';
        $html .= '<label for="productName">Name *</label>';
        // La edit, value e pre-populat cu datele existente. La add e gol golut
        $html .= '<input type="text" id="productName" name="name" value="' . htmlspecialchars($product['Name'] ?? '') . '" required>';
        $html .= '<span class="error-message" id="error-name"></span>';// span pentru erori de validare
        $html .= '</div>';

        $html .= '<div class="form-group">';
        $html .= '<label for="productDescription">Description</label>';
        $html .= '<textarea id="productDescription" name="description" rows="4">' . htmlspecialchars($product['Description'] ?? '') . '</textarea>';
        $html .= '<span class="error-message" id="error-description"></span>';
        $html .= '</div>';

        $html .= '<div class="form-group">';
        $html .= '<label for="productPrice">Price *</label>';
        $html .= '<input type="number" id="productPrice" name="price" value="' . htmlspecialchars($product['Price'] ?? '') . '" step="0.01" min="0" required>';
        $html .= '<span class="error-message" id="error-price"></span>';
        $html .= '</div>';

        $html .= '<div class="form-group">';
        $html .= '<label for="productCurrency">Currency</label>';
        $html .= '<input type="text" id="productCurrency" name="currency" value="' . htmlspecialchars($product['Currency'] ?? '') . '" placeholder="USD">';
        $html .= '<span class="error-message" id="error-currency"></span>';
        $html .= '</div>';

        $html .= '<div class="form-group">';
        $html .= '<label for="productImage">Image URL</label>';
        $html .= '<input type="url" id="productImage" name="image" value="' . htmlspecialchars($product['Image'] ?? '') . '" placeholder="https://example.com/image.jpg">';
        $html .= '<span class="error-message" id="error-image"></span>';
        $html .= '</div>';

        $html .= '<div class="form-group">';
        $html .= '<label for="productPriceRON">Price (RON)</label>';
        $html .= '<input type="number" id="productPriceRON" name="price_ron" value="' . htmlspecialchars($product['Price_RON'] ?? '') . '" step="0.01" min="0">';
        $html .= '<span class="error-message" id="error-price_ron"></span>';
        $html .= '</div>';

        $html .= '<div class="form-group">';
        $html .= '<label for="productExchangeRate">Exchange Rate</label>';
        $html .= '<input type="number" id="productExchangeRate" name="exchange_rate" value="' . htmlspecialchars($product['Exchange_rate'] ?? '') . '" step="0.0001" min="0">';
        $html .= '<span class="error-message" id="error-exchange_rate"></span>';
        $html .= '</div>';

        $html .= '<div class="form-actions">';
        $html .= '<button type="submit" class="btn-primary">Save</button>';
        $html .= '<button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>';
        $html .= '</div>';

        return $html;
    }
}
?>