<?php

//credentiale pentru conectarea la baza de date
$host = 'localhost';
$dbname = 'autobrand_management';
$username = 'root';
$password = '';

try {
    //conexiune PDO, mai sigura
    $con = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);

    //asa setez ca sa imi arunce exceptii decat sa dea fail fara sa spune nimic
    $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection error: " . $e->getMessage());//afisez mesajul de eroare daca conexiunea esueaza
}
?>