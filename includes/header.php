<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Page courante (pour surligner le lien actif dans la navigation).
$pageCourante = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des listes d'attente - ESP Dakar</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='6' fill='%230E4D8B'/><text x='16' y='21' font-size='12' font-family='Arial' font-weight='bold' fill='white' text-anchor='middle'>ESP</text></svg>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/Gestion_Liste_attente/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark navbar-esp">
        <div class="container">
            <a class="navbar-brand" href="/Gestion_Liste_attente/index.php">
                <span class="esp-logo">ESP</span>
                <span class="esp-brand-text">Listes d'attente<small>École Supérieure Polytechnique — Dakar</small></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#espNav" aria-controls="espNav" aria-expanded="false" aria-label="Menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="espNav">
                <div class="navbar-nav ms-auto align-items-lg-center">
                    <?php if (isset($_SESSION['utilisateur_id'])): ?>
                        <a class="nav-link <?= $pageCourante === 'listes.php' ? 'actif' : '' ?>" href="/Gestion_Liste_attente/pages/listes.php"><i class="bi bi-list-ol"></i> Listes</a>
                        <a class="nav-link <?= $pageCourante === 'desistement.php' ? 'actif' : '' ?>" href="/Gestion_Liste_attente/pages/desistement.php"><i class="bi bi-person-dash"></i> Désistement</a>
                        <?php if (in_array($_SESSION['role'], ['agent', 'administrateur'], true)): ?>
                            <a class="nav-link <?= $pageCourante === 'import.php' ? 'actif' : '' ?>" href="/Gestion_Liste_attente/pages/import.php"><i class="bi bi-upload"></i> Importer</a>
                            <a class="nav-link <?= $pageCourante === 'historique.php' ? 'actif' : '' ?>" href="/Gestion_Liste_attente/pages/historique.php"><i class="bi bi-clock-history"></i> Historique</a>
                        <?php endif; ?>
                        <a class="nav-link <?= $pageCourante === 'dashboard.php' ? 'actif' : '' ?>" href="/Gestion_Liste_attente/pages/dashboard.php"><i class="bi bi-speedometer2"></i> Tableau de bord</a>
                        <?php if ($_SESSION['role'] === 'administrateur'): ?>
                            <a class="nav-link <?= $pageCourante === 'concours.php' ? 'actif' : '' ?>" href="/Gestion_Liste_attente/pages/concours.php"><i class="bi bi-mortarboard"></i> Concours</a>
                            <a class="nav-link <?= $pageCourante === 'filieres.php' ? 'actif' : '' ?>" href="/Gestion_Liste_attente/pages/filieres.php"><i class="bi bi-diagram-3"></i> Filières</a>
                        <?php endif; ?>
                        <a class="nav-link <?= $pageCourante === 'mot_de_passe.php' ? 'actif' : '' ?>" href="/Gestion_Liste_attente/pages/mot_de_passe.php"><i class="bi bi-key"></i> Mot de passe</a>
                        <a class="nav-link" href="/Gestion_Liste_attente/auth/logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
                        <span class="navbar-text ms-lg-3">
                            <?= htmlspecialchars($_SESSION['nom']) ?>
                            <span class="esp-badge-role"><?= htmlspecialchars($_SESSION['role']) ?></span>
                        </span>
                    <?php else: ?>
                        <a class="nav-link" href="/Gestion_Liste_attente/pages/consultation.php"><i class="bi bi-search"></i> Consulter ma situation</a>
                        <a class="nav-link" href="/Gestion_Liste_attente/auth/login.php"><i class="bi bi-box-arrow-in-right"></i> Connexion</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <main class="container mt-4 mb-4">
