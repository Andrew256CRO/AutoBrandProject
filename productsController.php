<?php
require_once 'productsModel.php';//importare model
require_once 'productsView.php';//importare view

//Controlerul e creierul in arhitectura MVC, primeste requesturile si le apeleaza metode din Model. Apoi preia datele intoarse de catre
//Model si te transmite mai departe catre View care afiseaza.
class ProductsController
{
    private $model;

    public function __construct()
    {
        $this->model = new ProductsModel();//creare obiect de tip model prin care o sa apelez metodele din Model.
    }

    // router principal, apeleaza in functie de GET si apeleaza metoda corespunzatoare
    public function handleRequest()
    {
        $action = $_GET['action'] ?? 'read';// Default == citeste produsele only

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
                $this->handleGetForm();// Returneaza HTML-ul formularului gol
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

    //extrage si valideaza parametrii de sortare din URL
    private function getSortParams()
    {
        $allowedColumns = ['Name', 'Price', 'Currency', 'Price_RON', 'Exchange_rate'];
        $allowedDirections = ['ASC', 'DESC'];

        $sortBy = $_GET['sortBy'] ?? 'Name';
        $sortDir = strtoupper($_GET['sortDir'] ?? 'ASC');

        // validare, adica daca valorile nu sunt permise, folosesc default
        if (!in_array($sortBy, $allowedColumns))
            $sortBy = 'Name';
        if (!in_array($sortDir, $allowedDirections))
            $sortDir = 'ASC';

        return [$sortBy, $sortDir];
    }

    // citeste produsele paginate si le returneaza ca JSON cu HTML pre-randat
    private function handleRead()
    {
        header('Content-Type: application/json');

        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $limit = 5;//limita de produse ce pot exista pe o pagina maxim
        [$sortBy, $sortDir] = $this->getSortParams();

        $products = $this->model->getProducts($page, $limit, $sortBy, $sortDir);
        $totalProducts = $this->model->getTotalProducts();
        $totalPages = ceil($totalProducts / $limit);// "ceil" rotunjeste catre un numar intreg, catre valoarea cea mai apropiata 

        // View-ul genereaza HTML-ul care va fi injectat in DOM de script.js
        $tableHtml = ProductsView::renderProductsTable($products, '', $sortBy, $sortDir);
        $paginationHtml = ProductsView::renderPagination($page, $totalPages);

        echo json_encode([
            'success' => true,
            'tableHtml' => $tableHtml,
            'paginationHtml' => $paginationHtml,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalProducts' => $totalProducts,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir
        ]);
    }

    // cauta produse dupa termen, daca termenul e gol, redirecteaza la handleRead()
    private function handleSearch()
    {
        header('Content-Type: application/json');

        $searchTerm = $_GET['search'] ?? '';
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $limit = 5;
        [$sortBy, $sortDir] = $this->getSortParams();

        if (empty($searchTerm)) {
            $this->handleRead();
            return;
        }

        $products = $this->model->searchProducts($searchTerm, $page, $limit, $sortBy, $sortDir);
        $totalProducts = $this->model->getTotalSearchResults($searchTerm);
        $totalPages = ceil($totalProducts / $limit);

        $tableHtml = ProductsView::renderProductsTable($products, $searchTerm, $sortBy, $sortDir);
        $paginationHtml = ProductsView::renderPagination($page, $totalPages);

        echo json_encode([
            'success' => true,
            'tableHtml' => $tableHtml,
            'paginationHtml' => $paginationHtml,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalProducts' => $totalProducts,
            'searchTerm' => $searchTerm,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir
        ]);
    }

    // returneaza datele unui produs + formularul pre-populat pentru editare
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
            $formHtml = ProductsView::renderProductForm($product);// Formular cu date pre-populate
            echo json_encode(['success' => true, 'formHtml' => $formHtml, 'product' => $product]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
        }
    }

    // returneaza formularul gol pentru adaugare produs nou
    private function handleGetForm()
    {
        header('Content-Type: application/json');

        $formHtml = ProductsView::renderProductForm();// fara argument == formular gol
        echo json_encode(['success' => true, 'formHtml' => $formHtml]);
    }

    // creeaza un produs nou din datele trimise ca JSON in body-ul POST
    private function handleCreate()
    {
        header('Content-Type: application/json');

        //citire JSON din body-ul requestului
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

    // actualizare produs existent
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

    // stergere produs dupa ID primit din GET
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

    // validez totusi cum e si normal datele inainte de insert/update
    private function validateProduct($data)
    {
        $errors = [];

        // Name e obligatoriu
        if (empty($data['name']) || trim($data['name']) === '') {
            $errors['name'] = 'Name is required';
        }

        // Price e obligatoriu si trebuie sa fie numar pozitiv
        if (empty($data['price'])) {
            $errors['price'] = 'Price is required';
        } elseif (!is_numeric($data['price']) || $data['price'] < 0) {
            $errors['price'] = 'Price must be a valid positive number';
        }

        // Image e optional, dar daca e completat trebuie sa fie URL valid
        if (!empty($data['image'])) {
            if (!filter_var($data['image'], FILTER_VALIDATE_URL)) {
                $errors['image'] = 'Image must be a valid URL';
            }
        }

        return $errors;// array gol == fara erori, good to go
    }
}

//Aici instantiez controller-ul si procesez requestul primit
$controller = new ProductsController();
$controller->handleRequest();
?>