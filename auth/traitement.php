<?php
session_start();
require '../config/database.php';

$email = $_POST['email'];
$motDePasse = $_POST['mot_de_passe'];

$requete = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ?");
$requete->execute([$email]);
$utilisateur = $requete->fetch();

if ($utilisateur && password_verify($motDePasse, $utilisateur['mot_de_passe'])) {
    // Régénération de l'identifiant de session après authentification
    // (protection contre la fixation de session — BNF-01 Sécurité).
    session_regenerate_id(true);
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