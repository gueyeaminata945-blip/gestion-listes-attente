<?php
$hote = "localhost";
$nomBase = "gestion_listes_attente";
$utilisateur = "root";
$motDePasse = "";

try {
    $pdo = new PDO("mysql:host=$hote;dbname=$nomBase;charset=utf8mb4", $utilisateur, $motDePasse);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>