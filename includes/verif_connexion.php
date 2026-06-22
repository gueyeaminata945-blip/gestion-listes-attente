<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si l'utilisateur n'est pas connecté, on le renvoie vers la page de connexion
if (!isset($_SESSION['utilisateur_id'])) {
    header("Location: /Gestion_Liste_attente/auth/login.php");
    exit;
}
?>