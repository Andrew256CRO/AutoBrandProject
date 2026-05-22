<?php
class ProductsView
{

    private static function highlightSearchTerm($text, $searchTerm)
    {
        if (empty($searchTerm) || empty($text)) {
            return htmlspecialchars($text);
        }
        $pattern = '/' . preg_quote($searchTerm, '/') . '/i';
        $highlighted = preg_replace($pattern, '<mark>$0</mark>', $text);
        return $highlighted;
    }

    public static function renderProductsTable($products, $searchTerm = '')
    {
        if (empty($products)) {
            return '<p class="text-center">No products found</p>';
        }

        //Acum construiesc efectiv tabelul cu coloanele respective
        $html = '<table class="products-table">';
        $html .= '<thead><tr>';
        $html .= '<th>Image</th>';
        $html .= '<th>Name</th>';
        $html .= '<th>Description</th>';
        $html .= '<th>Price</th>';
        $html .= '<th>Currency</th>';
        $html .= '<th>Price (RON)</th>';
        $html .= '<th>Exchange Rate</th>';
        $html .= '<th>Actions</th>';
        $html .= '</tr></thead>';
        $html .= '<tbody>';

        foreach ($products as $product) {
            $highlightedName = self::highlightSearchTerm($product['Name'], $searchTerm);
            $highlightedDesc = self::highlightSearchTerm($product['Description'] ?? 'N/A', $searchTerm);

            $html .= '<tr>';
            $html .= '<td data-label="Image"><img src="' . htmlspecialchars($product['Image'] ?? '') . '" alt="' . htmlspecialchars($product['Name']) . '" class="product-img"></td>';
            $html .= '<td data-label="Name">' . $highlightedName . '</td>';
            $html .= '<td data-label="Description" title="' . htmlspecialchars($product['Description'] ?? 'N/A') . '">' . $highlightedDesc . '</td>';
            $html .= '<td data-label="Price">' . htmlspecialchars($product['Price']) . '</td>';
            $html .= '<td data-label="Currency">' . htmlspecialchars($product['Currency'] ?? 'N/A') . '</td>';
            $html .= '<td data-label="Price (RON)">' . htmlspecialchars($product['Price_RON'] ?? 'N/A') . '</td>';
            $html .= '<td data-label="Exchange Rate">' . htmlspecialchars($product['Exchange_rate'] ?? 'N/A') . '</td>';
            $html .= '<td data-label="Actions">';
            $html .= '<button class="btn-edit" onclick="openEditModal(' . $product['Id'] . ')">Edit</button>';
            $html .= '<button class="btn-delete" onclick="confirmDelete(' . $product['Id'] . ')">Delete</button>';
            $html .= '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    public static function renderPagination($currentPage, $totalPages)
    {
        if ($totalPages <= 1) {
            return '';
        }

        $html = '<div class="pagination">';

        if ($currentPage <= 1) {
            $html .= '<button class="btn-pagination" disabled>Previous</button>';
        } else {
            $html .= '<button class="btn-pagination" onclick="loadProducts(' . ($currentPage - 1) . ')">Previous</button>';
        }

        for ($i = 1; $i <= $totalPages; $i++) {
            $active = ($i === $currentPage) ? ' active' : '';
            $html .= '<button class="btn-pagination' . $active . '" onclick="loadProducts(' . $i . ')">' . $i . '</button>';
        }

        if ($currentPage >= $totalPages) {
            $html .= '<button class="btn-pagination" disabled>Next</button>';
        } else {
            $html .= '<button class="btn-pagination" onclick="loadProducts(' . ($currentPage + 1) . ')">Next</button>';
        }

        $html .= '</div>';
        return $html;
    }

    public static function renderProductForm($product = null)
    {
        $product = $product ?? [];

        $html = '<div class="form-group">';
        $html .= '<label for="productName">Name *</label>';
        $html .= '<input type="text" id="productName" name="name" value="' . htmlspecialchars($product['Name'] ?? '') . '" required>';
        $html .= '<span class="error-message" id="error-name"></span>';
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