<?php
require_once 'db_connection.php'; //importare conexiune la DB

class ProductsModel
{
    private $con;//conexiune PDO, accesibila doar in interiorul clasei

    public function __construct()
    {
        global $con; //variabila globala "con" definita in fisierul de conexiune .php   
        $this->con = $con;
    }

    // whitelist de coloane si directii permise pentru sortare
    private $allowedSortColumns = ['Name', 'Price', 'Currency', 'Price_RON', 'Exchange_rate'];
    private $allowedSortDirections = ['ASC', 'DESC'];

    //contruire ORDER BY
    private function buildOrderClause($sortBy, $sortDir)
    {
        //daca valorile nu sunt in whitelist, fallback la ceva default si sigur
        $column = in_array($sortBy, $this->allowedSortColumns) ? $sortBy : 'Name';
        $direction = in_array(strtoupper($sortDir), $this->allowedSortDirections) ? strtoupper($sortDir) : 'ASC';
        return "ORDER BY $column $direction";
    }

    // returneaza produsele pentru o pagina specifica + sortare
    public function getProducts($page, $limit, $sortBy = 'Name', $sortDir = 'ASC')
    {
        try {
            $offset = ($page - 1) * $limit;//calcul de unde se incepe in offset
            $orderClause = $this->buildOrderClause($sortBy, $sortDir);

            //Aici vine avantajul la PDO: Prepared statement, parametrii sunt legati separat, nu concatenati in query
            //Previne SQL injection
            $query = $this->con->prepare("SELECT * FROM products $orderClause LIMIT :limit OFFSET :offset");
            $query->bindValue(':limit', $limit, PDO::PARAM_INT);//aici ii dau valoarea lui ":limit" ca fiind $limit ce vine si tot asa la restul cu bindValue
            $query->bindValue(':offset', $offset, PDO::PARAM_INT);
            $query->execute();//executa interogarea
            return $query->fetchAll(PDO::FETCH_ASSOC);// fetchAll returneaza toate randurile ca array
        } catch (Exception $e) {
            return [];//in caz de eroare, returnez un tablou gol, nu opesc executia aplicatiei
        }
    }

    //numara cate produse sunt in total ca sa aflu paginarea
    public function getTotalProducts()
    {
        try {
            $query = $this->con->prepare("SELECT COUNT(*) as total FROM products");
            $query->execute();
            $result = $query->fetch(PDO::FETCH_ASSOC);//// fetch == un singur rand, fata de "fetchAll"
            return (int) $result['total'];
        } catch (Exception $e) {
            return 0;
        }
    }

    //gasire produs dupa Id, folosit la editare
    public function getProductById($id)
    {
        try {
            $query = $this->con->prepare("SELECT * FROM products WHERE Id = :id");
            $query->bindValue(':id', $id, PDO::PARAM_INT);
            $query->execute();
            return $query->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }

    //Asta este search-ul, cauta produse dupa "Name" si "Description", dar pastreaza si paginarea si sortarea
    public function searchProducts($searchTerm, $page, $limit, $sortBy = 'Name', $sortDir = 'ASC')
    {
        try {
            $offset = ($page - 1) * $limit;
            $searchTerm = "%" . $searchTerm . "%";//LIKE %termen%
            $orderClause = $this->buildOrderClause($sortBy, $sortDir);

            $query = $this->con->prepare("SELECT * FROM products WHERE Name LIKE :search OR Description LIKE :search $orderClause LIMIT :limit OFFSET :offset");
            $query->bindValue(':search', $searchTerm, PDO::PARAM_STR);
            $query->bindValue(':limit', $limit, PDO::PARAM_INT);
            $query->bindValue(':offset', $offset, PDO::PARAM_INT);
            $query->execute();
            return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    //Nuamr rezultatele din search pentru paginare
    public function getTotalSearchResults($searchTerm)
    {
        try {
            $searchTerm = '%' . $searchTerm . '%';
            $query = $this->con->prepare("SELECT COUNT(*) AS total FROM products WHERE Name LIKE :search OR Description LIKE :search");
            $query->bindValue(':search', $searchTerm, PDO::PARAM_STR);
            $query->execute();
            $result = $query->fetch(PDO::FETCH_ASSOC);
            return (int) $result['total'];
        } catch (Exception $e) {
            return 0;
        }
    }

    //creare produs nou in DB
    public function createProduct($data)
    {
        try {
            $query = $this->con->prepare("
                INSERT INTO products (Name, Description, Price, Currency, Image, Price_RON, Exchange_rate) 
                VALUES (:name, :description, :price, :currency, :image, :price_ron, :exchange_rate)
            ");
            $query->bindValue(':name', $data['name'], PDO::PARAM_STR);
            $query->bindValue(':description', $data['description'], PDO::PARAM_STR);
            $query->bindValue(':price', $data['price'], PDO::PARAM_STR);
            $query->bindValue(':currency', $data['currency'], PDO::PARAM_STR);
            $query->bindValue(':image', $data['image'], PDO::PARAM_STR);
            $query->bindValue(':price_ron', $data['price_ron'], PDO::PARAM_STR);
            $query->bindValue(':exchange_rate', $data['exchange_rate'], PDO::PARAM_STR);
            return $query->execute();
        } catch (PDOException $e) {
            return false;
        }
    }

    //actualizare produs dupa Id
    public function updateProduct($id, $data)
    {
        try {
            $query = $this->con->prepare("
                UPDATE products 
                SET Name = :name, 
                    Description = :description, 
                    Price = :price,
                    Currency = :currency,
                    Image = :image,
                    Price_RON = :price * :exchange_rate,
                    Exchange_rate = :exchange_rate
                WHERE Id = :id
            ");
            $query->bindValue(':id', $id, PDO::PARAM_INT);
            $query->bindValue(':name', $data['name'], PDO::PARAM_STR);
            $query->bindValue(':description', $data['description'], PDO::PARAM_STR);
            $query->bindValue(':price', $data['price'], PDO::PARAM_STR);
            $query->bindValue(':currency', $data['currency'], PDO::PARAM_STR);
            $query->bindValue(':image', $data['image'], PDO::PARAM_STR);
            $query->bindValue(':price_ron', $data['price_ron'], PDO::PARAM_STR);
            $query->bindValue(':exchange_rate', $data['exchange_rate'], PDO::PARAM_STR);
            return $query->execute();
        } catch (Exception $e) {
            return false;
        }
    }

    //stergere produs dupa Id
    public function deleteProduct($id)
    {
        try {
            $query = $this->con->prepare("DELETE FROM products WHERE Id = :id");
            $query->bindValue(':id', $id, PDO::PARAM_INT);
            return $query->execute();
        } catch (Exception $e) {
            return false;
        }
    }
}
?>