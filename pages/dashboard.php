<?php
require '../config/database.php';
include '../includes/verif_connexion.php';
include '../includes/header.php';

// --- Statistiques par filière (base de tous les calculs) ---
$statsParFiliere = $pdo->query("
    SELECT f.id, f.nom AS filiere, f.departement, f.capacite_accueil,
        (SELECT COUNT(*) FROM inscriptions i JOIN listes l ON i.liste_id = l.id
         WHERE l.filiere_id = f.id AND l.type = 'principale') AS nb_principale,
        (SELECT COUNT(*) FROM inscriptions i JOIN listes l ON i.liste_id = l.id
         WHERE l.filiere_id = f.id AND l.type = 'attente') AS nb_attente,
        (SELECT COUNT(*) FROM desistements d WHERE d.filiere_id = f.id) AS nb_desistements
    FROM filieres f
    ORDER BY f.departement, f.nom
")->fetchAll();

// --- Totaux globaux + agrégation par département ---
$totalCandidats   = (int) $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'candidat'")->fetchColumn();
$totalCapacite = 0; $totalPourvues = 0; $totalAttente = 0; $totalDesistements = 0;
$parDepartement = [];

foreach ($statsParFiliere as $s) {
    $totalCapacite     += $s['capacite_accueil'];
    $totalPourvues     += $s['nb_principale'];
    $totalAttente      += $s['nb_attente'];
    $totalDesistements += $s['nb_desistements'];

    $dep = $s['departement'];
    if (!isset($parDepartement[$dep])) {
        $parDepartement[$dep] = ['capacite' => 0, 'pourvues' => 0, 'attente' => 0, 'desistements' => 0];
    }
    $parDepartement[$dep]['capacite']     += $s['capacite_accueil'];
    $parDepartement[$dep]['pourvues']     += $s['nb_principale'];
    $parDepartement[$dep]['attente']      += $s['nb_attente'];
    $parDepartement[$dep]['desistements'] += $s['nb_desistements'];
}

$totalRestantes = max(0, $totalCapacite - $totalPourvues);

// Taux de désistement = désistements / (places pourvues + désistements).
// Il mesure la part des places initialement attribuées qui ont été libérées.
function tauxDesistement(int $desistements, int $pourvues): float
{
    $base = $pourvues + $desistements;
    return $base > 0 ? round($desistements / $base * 100, 1) : 0.0;
}
$tauxGlobal = tauxDesistement($totalDesistements, $totalPourvues);

// Taux de remplissage global = places pourvues / capacité.
$tauxRemplissage = $totalCapacite > 0 ? round($totalPourvues / $totalCapacite * 100, 1) : 0.0;

// --- Suivi des notifications (lien avec BM-05) ---
$notifs = $pdo->query("
    SELECT
        SUM(statut = 'envoyee')  AS envoyees,
        SUM(statut = 'confirmee') AS confirmees,
        SUM(statut = 'echouee')  AS echouees,
        COUNT(*) AS total
    FROM notifications
")->fetch();
?>

<h2 class="page-title">Tableau de bord</h2>

<!-- Cartes de statistiques globales -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card text-white bg-primary h-100">
            <div class="card-body text-center">
                <h1 class="mb-0"><?= $totalCandidats ?></h1>
                <p class="mb-0">Candidats</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card text-white bg-success h-100">
            <div class="card-body text-center">
                <h1 class="mb-0"><?= $totalPourvues ?></h1>
                <p class="mb-0">Places pourvues</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card text-white bg-info h-100">
            <div class="card-body text-center">
                <h1 class="mb-0"><?= $totalRestantes ?></h1>
                <p class="mb-0">Places restantes</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card text-white bg-danger h-100">
            <div class="card-body text-center">
                <h1 class="mb-0"><?= $totalDesistements ?></h1>
                <p class="mb-0">Désistements</p>
            </div>
        </div>
    </div>
</div>

<!-- Indicateurs clés : taux de désistement, remplissage, notifications -->
<div class="row g-3 mb-5">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="text-muted">Taux de désistement global</h6>
                <h3 class="text-danger"><?= $tauxGlobal ?> %</h3>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-danger" style="width: <?= $tauxGlobal ?>%"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="text-muted">Taux de remplissage</h6>
                <h3 class="text-success"><?= $tauxRemplissage ?> %</h3>
                <div class="progress" style="height: 8px;">
                    <div class="progress-bar bg-success" style="width: <?= $tauxRemplissage ?>%"></div>
                </div>
                <small class="text-muted"><?= $totalPourvues ?> / <?= $totalCapacite ?> places</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="text-muted">Notifications</h6>
                <p class="mb-1"><span class="badge bg-primary"><?= (int) $notifs['envoyees'] ?></span> en attente de confirmation</p>
                <p class="mb-1"><span class="badge bg-success"><?= (int) $notifs['confirmees'] ?></span> confirmées</p>
                <p class="mb-0"><span class="badge bg-secondary"><?= (int) $notifs['echouees'] ?></span> en échec</p>
            </div>
        </div>
    </div>
</div>

<!-- Synthèse par département -->
<h4 class="mb-3">Par département</h4>
<table class="table table-bordered align-middle mb-5">
    <thead class="table-dark">
        <tr>
            <th>Département</th>
            <th class="text-center">Capacité</th>
            <th class="text-center">Pourvues</th>
            <th class="text-center">Restantes</th>
            <th class="text-center">Désistements</th>
            <th class="text-center">Taux de désistement</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($parDepartement as $dep => $d): ?>
            <?php $restantes = max(0, $d['capacite'] - $d['pourvues']); ?>
            <tr>
                <td><?= htmlspecialchars($dep) ?></td>
                <td class="text-center"><?= $d['capacite'] ?></td>
                <td class="text-center"><?= $d['pourvues'] ?></td>
                <td class="text-center"><?= $restantes ?></td>
                <td class="text-center"><?= $d['desistements'] ?></td>
                <td class="text-center"><?= tauxDesistement($d['desistements'], $d['pourvues']) ?> %</td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- Détail par filière -->
<h4 class="mb-3">Détail par filière</h4>
<table class="table table-striped table-bordered align-middle">
    <thead class="table-primary">
        <tr>
            <th>Filière</th>
            <th>Département</th>
            <th class="text-center">Capacité</th>
            <th class="text-center">Liste principale</th>
            <th class="text-center">Liste d'attente</th>
            <th class="text-center">Places restantes</th>
            <th class="text-center">Désistements</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($statsParFiliere) === 0): ?>
            <tr><td colspan="7" class="text-center text-muted">Aucune filière</td></tr>
        <?php else: ?>
            <?php foreach ($statsParFiliere as $s): ?>
                <?php $restantes = $s['capacite_accueil'] - $s['nb_principale']; ?>
                <tr>
                    <td><?= htmlspecialchars($s['filiere']) ?></td>
                    <td><?= htmlspecialchars($s['departement']) ?></td>
                    <td class="text-center"><?= $s['capacite_accueil'] ?></td>
                    <td class="text-center"><span class="badge bg-success"><?= $s['nb_principale'] ?></span></td>
                    <td class="text-center"><span class="badge bg-warning text-dark"><?= $s['nb_attente'] ?></span></td>
                    <td class="text-center">
                        <?php if ($restantes > 0): ?>
                            <span class="badge bg-info"><?= $restantes ?> place(s)</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Complet</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?= $s['nb_desistements'] ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<p>
    <a href="/Gestion_Liste_attente/pages/historique.php" class="btn btn-outline-secondary">
        Voir l'historique des opérations
    </a>
</p>

<?php include '../includes/footer.php'; ?>
