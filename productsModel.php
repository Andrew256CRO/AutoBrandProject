<?php
require_once 'db_connection.php';

class ProductsModel
{
    private $con;

    public function __construct()
    {
        global $con;
        $this->con = $con;
    }

    public function getProducts($page, $limit)
    {
        try {
            $offset = ($page - 1) * $limit;

            /* Execute a prepared statement by binding PHP variables */
            $query = $this->con->prepare("SELECT * FROM products LIMIT :limit OFFSET :offset");

            /* Sets a parameter value using its name. Optionally, parameter names can also be prefixed with colons ":" */
            $query->bindValue(':limit', $limit, PDO::PARAM_INT);
            $query->bindValue(':offset', $offset, PDO::PARAM_INT);
            $query->execute();
            return $query->fetchAll(PDO::FETCH_ASSOC); //returns an array, "fetch" == Fetches the remaining rows from a result set
        } catch (Exception $e) {
            return [];
        }
    }

    public function getTotalProducts()
    {
        try {
            $query = $this->con->prepare("SELECT COUNT(*) as total FROM products");
            $query->execute();
            $result = $query->fetch(PDO::FETCH_ASSOC);//fetch == Fetches the next row from a result set
            return (int) $result['total'];
        } catch (Exception $e) {
            return 0;
        }

    }

    public function getProductsById($id)
    {
        try {
            $query = $this->con->prepare("SELECT * FROM products WHERE Id= :$id");
            $query->bindValue(':id', $id, PDO::PARAM_INT);
            $query->execute();
            return $query->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }

    public function searchProducts($searchTerm, $page, $limit)
    {
        try {
            $offset = ($page - 1) * $limit;
            $searchTerm = "%" . $searchTerm . "%";//concatenare stringuri, gaseste orice contine termenul cautat oriunde in string
            $query = $this->con->prepare("SELECT * FROM products WHERE Name LIKE :search OR Description LIKE :search LIMIT :limit OFFSET :offset");
            $query->bindValue(':search', $searchTerm, PDO::PARAM_STR);//practic search primeste valoarea din searchterm (o atribui pe romaneste)
            $query->bindValue(':limit', $limit, PDO::PARAM_INT);
            $query->bindValue(':offset', $offset, PDO::PARAM_INT);
            $query->execute();
            return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

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
                    Price_RON = :price_ron,
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