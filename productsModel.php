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
            $query->bindValue(':search', $searchTerm, PDO::PARAM_STR);
            $query->bindValue(':limit', $limit, PDO::PARAM_INT);
            $query->bindValue(':offset', $offset, PDO::PARAM_INT);
            $query->execute();
            return $query->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}

?>