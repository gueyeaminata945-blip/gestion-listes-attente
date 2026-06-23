<?php
require '../config/database.php';
include '../includes/verif_connexion.php';
require '../includes/relances.php';

// Traitement manuel des relances/expirations dues (BM-05), via le bouton dédié.
$messageRelance = '';
if (($_GET['action'] ?? '') === 'relances') {
    $r = traiterRelances($pdo);
    $messageRelance = "Relances traitées : " . $r['relances'] . " rappel(s) envoyé(s), "
                    . $r['expirations'] . " place(s) expirée(s) et réattribuée(s).";
}

include '../includes/header.php';

// Choix concours -> filière (même logique que la page des listes)
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
// Récupérer les candidats de la liste principale
$requete = $pdo->prepare("
    SELECT i.id AS inscription_id, i.rang, u.nom, u.prenom, u.numero_candidat
    FROM inscriptions i
    JOIN utilisateurs u ON i.candidat_id = u.id
    JOIN listes l ON i.liste_id = l.id
    WHERE l.filiere_id = ? AND l.type = 'principale'
    ORDER BY i.rang ASC
");
$requete->execute([$filiereChoisie]);
$principale = $requete->fetchAll();

$message = $_GET['message'] ?? '';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Enregistrer un désistement</h2>
    <a href="desistement.php?action=relances&concours=<?= $concoursChoisi ?>&filiere=<?= $filiereChoisie ?>"
       class="btn btn-outline-warning"
       onclick="return confirm('Traiter maintenant les relances et expirations en attente ?')">
        Traiter les relances en attente
    </a>
</div>

<?php if ($messageRelance): ?>
    <div class="alert alert-warning"><?= htmlspecialchars($messageRelance) ?></div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<!-- Choix concours et filière -->
<form method="GET" class="mb-4">
    <div class="row g-3">
        <div class="col-md-5">
            <label class="form-label fw-bold">Concours :</label>
            <select name="concours" class="form-select" onchange="this.form.submit()">
                <?php foreach ($concours as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($c['id'] == $concoursChoisi) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['type'] . ' - ' . $c['departement']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-5">
            <label class="form-label fw-bold">Filière :</label>
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
<p class="text-muted">Cliquez sur « Désister » pour retirer un candidat. Le premier de la liste d'attente sera automatiquement promu.</p>

<table class="table table-striped table-bordered">
    <thead class="table-success">
        <tr><th>Rang</th><th>N° candidat</th><th>Nom</th><th>Prénom</th><th>Action</th></tr>
    </thead>
    <tbody>
        <?php if (count($principale) === 0): ?>
            <tr><td colspan="5" class="text-center text-muted">Aucun candidat</td></tr>
        <?php else: ?>
            <?php foreach ($principale as $c): ?>
                <tr>
                    <td><?= $c['rang'] ?></td>
                    <td><?= htmlspecialchars($c['numero_candidat']) ?></td>
                    <td><?= htmlspecialchars($c['nom']) ?></td>
                    <td><?= htmlspecialchars($c['prenom']) ?></td>
                    <td>
                        <a href="traiter_desistement.php?inscription=<?= $c['inscription_id'] ?>&filiere=<?= $filiereChoisie ?>&concours=<?= $concoursChoisi ?>"
                           class="btn btn-danger btn-sm"
                           onclick="return confirm('Confirmer le désistement de ce candidat ?')">
                            Désister
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php include '../includes/footer.php'; ?>