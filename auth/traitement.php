<?php
session_start();
require '../config/database.php';

$email = $_POST['email'];
$motDePasse = $_POST['mot_de_passe'];

$requete = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ?");
$requete->execute([$email]);
$utilisateur = $requete->fetch();

if ($utilisateur && password_verify($motDePasse, $utilisateur['mot_de_passe'])) {
    $_SESSION['utilisateur_id'] = $utilisateur['id'];
    $_SESSION['nom'] = $utilisateur['nom'];
    $_SESSION['role'] = $utilisateur['role'];
    header("Location: ../index.php");
    exit;
} else {
    header("Location: login.php?erreur=1");
    exit;
}
?>