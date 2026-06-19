<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des listes d'attente - ESP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="/Gestion_Liste_attente/index.php">ESP - Listes d'attente</a>
            <div class="navbar-nav ms-auto">
                <?php if (isset($_SESSION['utilisateur_id'])): ?>
                    <span class="navbar-text text-white me-3">
                        Bonjour, <?= htmlspecialchars($_SESSION['nom']) ?> (<?= $_SESSION['role'] ?>)
                    </span>
                    <a class="nav-link" href="/Gestion_Liste_attente/pages/listes.php">Listes</a>
                    <a class="nav-link" href="/Gestion_Liste_attente/auth/logout.php">Déconnexion</a>
                <?php else: ?>
                    <a class="nav-link" href="/Gestion_Liste_attente/auth/login.php">Connexion</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <div class="container mt-4">