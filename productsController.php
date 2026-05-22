<?php
require_once 'productsModel.php';
require_once 'productsView.php';

class ProductsController
{
    private $model;

    public function __construct()
    {
        $this->model = new ProductsModel();//aici imi initializez obiectul ca sa pot accesa metodele
    }

    public function handleRequest()
    {
        $action = $_GET['action'] ?? 'read';

        switch ($action) {
            case 'read':
                $this->handleRead();
                break;
            case 'search':
                $this->handleSearch();
                break;
            case 'getOne':
                $this->handleGetOne();
                break;
            case 'getForm':
                $this->handleGetForm();
                break;
            case 'create':
                $this->handleCreate();
                break;
            case 'update':
                $this->handleUpdate();
                break;
            case 'delete':
                $this->handleDelete();
                break;
            default:
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                break;
        }
    }

    private function handleRead()
    {
        header('Content-Type: application/json');

        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $limit = 5;

        $products = $this->model->getProducts($page, $limit);
        $totalProducts = $this->model->getTotalProducts();
        $totalPages = ceil($totalProducts / $limit);

        //De asta le-am facut metodele statice, ca sa nu mai trebuiasca sa initializez un obiect, dar se poate si invers, desigur
        $tableHtml = ProductsView::renderProductsTable($products, '');
        $paginationHtml = ProductsView::renderPagination($page, $totalPages);

        echo json_encode([
            'success' => true,
            'tableHtml' => $tableHtml,
            'paginationHtml' => $paginationHtml,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalProducts' => $totalProducts
        ]);
    }

    private function handleSearch()
    {
        header('Content-Type: application/json');

        $searchTerm = $_GET['search'] ?? '';
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $limit = 5;

        if (empty($searchTerm)) {
            $this->handleRead();
            return;
        }

        $products = $this->model->searchProducts($searchTerm, $page, $limit);
        $totalProducts = $this->model->getTotalSearchResults($searchTerm);
        $totalPages = ceil($totalProducts / $limit);

        $tableHtml = ProductsView::renderProductsTable($products, $searchTerm);
        $paginationHtml = ProductsView::renderPagination($page, $totalPages);

        echo json_encode([
            'success' => true,
            'tableHtml' => $tableHtml,
            'paginationHtml' => $paginationHtml,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalProducts' => $totalProducts,
            'searchTerm' => $searchTerm
        ]);
    }

    private function handleGetOne()
    {
        header('Content-Type: application/json');

        $id = $_GET['id'] ?? 0;

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Product ID required']);
            return;
        }

        $product = $this->model->getProductById($id);

        if ($product) {
            $formHtml = ProductsView::renderProductForm($product);
            echo json_encode(['success' => true, 'formHtml' => $formHtml, 'product' => $product]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
        }
    }

    private function handleGetForm()
    {
        header('Content-Type: application/json');

        $formHtml = ProductsView::renderProductForm();
        echo json_encode(['success' => true, 'formHtml' => $formHtml]);
    }

    private function handleCreate()
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);
        $errors = $this->validateProduct($data);

        if (!empty($errors)) {
            echo json_encode(['success' => false, 'errors' => $errors]);
            return;
        }

        $result = $this->model->createProduct($data);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Product created successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create product']);
        }
    }

    private function handleUpdate()
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? 0;

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Product ID required']);
            return;
        }

        $errors = $this->validateProduct($data);

        if (!empty($errors)) {
            echo json_encode(['success' => false, 'errors' => $errors]);
            return;
        }

        $result = $this->model->updateProduct($id, $data);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update product']);
        }
    }

    private function handleDelete()
    {
        header('Content-Type: application/json');

        $id = $_GET['id'] ?? 0;

        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'Product ID required']);
            return;
        }

        $result = $this->model->deleteProduct($id);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete product']);
        }
    }

    private function validateProduct($data)
    {
        $errors = [];

        if (empty($data['name']) || trim($data['name']) === '') {
            $errors['name'] = 'Name is required';
        }

        if (empty($data['price'])) {
            $errors['price'] = 'Price is required';
        } elseif (!is_numeric($data['price']) || $data['price'] < 0) {
            $errors['price'] = 'Price must be a valid positive number';
        }

        if (!empty($data['image'])) {
            if (!filter_var($data['image'], FILTER_VALIDATE_URL)) {
                $errors['image'] = 'Image must be a valid URL';
            }
        }

        return $errors;
    }
}

$controller = new ProductsController();
$controller->handleRequest();
?>