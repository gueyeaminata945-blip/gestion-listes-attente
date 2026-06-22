<?php
require '../config/database.php';
include '../includes/verif_connexion.php';
include '../includes/header.php';

// Statistiques globales
$totalCandidats = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'candidat'")->fetchColumn();
$totalDesistements = $pdo->query("SELECT COUNT(*) FROM desistements")->fetchColumn();
$totalFilieres = $pdo->query("SELECT COUNT(*) FROM filieres")->fetchColumn();

// Statistiques par filière
$statsParFiliere = $pdo->query("
    SELECT f.nom AS filiere, f.capacite_accueil,
        (SELECT COUNT(*) FROM inscriptions i JOIN listes l ON i.liste_id = l.id
         WHERE l.filiere_id = f.id AND l.type = 'principale') AS nb_principale,
        (SELECT COUNT(*) FROM inscriptions i JOIN listes l ON i.liste_id = l.id
         WHERE l.filiere_id = f.id AND l.type = 'attente') AS nb_attente
    FROM filieres f
    ORDER BY f.nom
")->fetchAll();
?>

<h2 class="mb-4">Tableau de bord</h2>

<!-- Cartes de statistiques globales -->
<div class="row mb-5">
    <div class="col-md-4">
        <div class="card text-white bg-primary mb-3">
            <div class="card-body text-center">
                <h1><?= $totalCandidats ?></h1>
                <p class="mb-0">Candidats au total</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-danger mb-3">
            <div class="card-body text-center">
                <h1><?= $totalDesistements ?></h1>
                <p class="mb-0">Désistements</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-success mb-3">
            <div class="card-body text-center">
                <h1><?= $totalFilieres ?></h1>
                <p class="mb-0">Filières</p>
            </div>
        </div>
    </div>
</div>

<!-- Tableau détaillé par filière -->
<h4 class="mb-3">Détail par filière</h4>
<table class="table table-striped table-bordered">
    <thead class="table-primary">
        <tr>
            <th>Filière</th>
            <th>Capacité</th>
            <th>Liste principale</th>
            <th>Liste d'attente</th>
            <th>Places restantes</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($statsParFiliere as $s): ?>
            <?php $restantes = $s['capacite_accueil'] - $s['nb_principale']; ?>
            <tr>
                <td><?= htmlspecialchars($s['filiere']) ?></td>
                <td><?= $s['capacite_accueil'] ?></td>
                <td><span class="badge bg-success"><?= $s['nb_principale'] ?></span></td>
                <td><span class="badge bg-warning text-dark"><?= $s['nb_attente'] ?></span></td>
                <td>
                    <?php if ($restantes > 0): ?>
                        <span class="badge bg-info"><?= $restantes ?> place(s)</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Complet</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include '../includes/footer.php'; ?>