<?php
require '../config/database.php';
include '../includes/verif_connexion.php';
include '../includes/header.php';

$concours = $pdo->query("SELECT * FROM concours ORDER BY type, departement")->fetchAll();
$concoursChoisi = isset($_GET['concours']) ? (int) $_GET['concours'] : ($concours[0]['id'] ?? 0);

$reqFilieres = $pdo->prepare("SELECT * FROM filieres WHERE concours_id = ? ORDER BY nom");
$reqFilieres->execute([$concoursChoisi]);
$filieres = $reqFilieres->fetchAll();
// Vérifier que la filière choisie appartient bien au concours sélectionné
$filiereChoisie = isset($_GET['filiere']) ? (int) $_GET['filiere'] : ($filieres[0]['id'] ?? 0);

$filiereValide = false;
foreach ($filieres as $f) {
    if ($f['id'] == $filiereChoisie) {
        $filiereValide = true;
        break;
    }
}
// Si la filière ne correspond pas au concours, prendre la première du concours
if (!$filiereValide) {
    $filiereChoisie = $filieres[0]['id'] ?? 0;
}
function getCandidats($pdo, $filiereId, $typeListe) {
    $requete = $pdo->prepare("
        SELECT i.rang, u.nom, u.prenom, u.numero_candidat
        FROM inscriptions i
        JOIN utilisateurs u ON i.candidat_id = u.id
        JOIN listes l ON i.liste_id = l.id
        WHERE l.filiere_id = ? AND l.type = ?
        ORDER BY i.rang ASC
    ");
    $requete->execute([$filiereId, $typeListe]);
    return $requete->fetchAll();
}

$principale = getCandidats($pdo, $filiereChoisie, 'principale');
$attente = getCandidats($pdo, $filiereChoisie, 'attente');
?>

<h2 class="page-title">Gestion des listes</h2>

<form method="GET" class="mb-4">
    <div class="row g-3">
        <div class="col-md-5">
            <label class="form-label fw-bold">1. Choisir un concours :</label>
            <select name="concours" class="form-select" onchange="this.form.submit()">
                <?php foreach ($concours as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($c['id'] == $concoursChoisi) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['type'] . ' - ' . $c['departement'] . ' (' . $c['annee'] . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label fw-bold">2. Choisir une filière :</label>
            <select name="filiere" class="form-select" onchange="this.form.submit()">
                <?php foreach ($filieres as $f): ?>
                    <option value="<?= $f['id'] ?>" <?= ($f['id'] == $filiereChoisie) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($f['nom']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</form>

<h4 class="text-success">Liste principale</h4>
<table class="table table-striped table-bordered mb-5">
    <thead class="table-success">
        <tr><th>Rang</th><th>N° candidat</th><th>Nom</th><th>Prénom</th></tr>
    </thead>
    <tbody>
        <?php if (count($principale) === 0): ?>
            <tr><td colspan="4" class="text-center text-muted">Aucun candidat</td></tr>
        <?php else: ?>
            <?php foreach ($principale as $c): ?>
                <tr>
                    <td><?= $c['rang'] ?></td>
                    <td><?= htmlspecialchars($c['numero_candidat']) ?></td>
                    <td><?= htmlspecialchars($c['nom']) ?></td>
                    <td><?= htmlspecialchars($c['prenom']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<h4 class="text-warning">Liste d'attente</h4>
<table class="table table-striped table-bordered">
    <thead class="table-warning">
        <tr><th>Rang</th><th>N° candidat</th><th>Nom</th><th>Prénom</th></tr>
    </thead>
    <tbody>
        <?php if (count($attente) === 0): ?>
            <tr><td colspan="4" class="text-center text-muted">Aucun candidat</td></tr>
        <?php else: ?>
            <?php foreach ($attente as $c): ?>
                <tr>
                    <td><?= $c['rang'] ?></td>
                    <td><?= htmlspecialchars($c['numero_candidat']) ?></td>
                    <td><?= htmlspecialchars($c['nom']) ?></td>
                    <td><?= htmlspecialchars($c['prenom']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php include '../includes/footer.php'; ?>