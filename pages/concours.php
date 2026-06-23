<?php
require '../config/database.php';
include '../includes/verif_connexion.php';

if ($_SESSION['role'] !== 'administrateur') {
    header("Location: /Gestion_Liste_attente/index.php");
    exit;
}

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'];
    $annee = (int) $_POST['annee'];
    $departement = $_POST['departement'];

    $req = $pdo->prepare("INSERT INTO concours (type, annee, departement) VALUES (?, ?, ?)");
    $req->execute([$type, $annee, $departement]);

    // Traçabilité (BNF-05).
    $pdo->prepare("INSERT INTO historiques (operation, cible, acteur_id) VALUES ('Création concours', ?, ?)")
        ->execute(["$type $annee - $departement", $_SESSION['utilisateur_id']]);

    $message = "Concours ajouté avec succès.";
}

include '../includes/header.php';

$concours = $pdo->query("SELECT * FROM concours ORDER BY annee DESC, type")->fetchAll();
?>

<h2 class="page-title">Gestion des concours</h2>

<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title">Ajouter un concours</h5>
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select" required>
                        <option value="DUT">DUT</option>
                        <option value="DIC">DIC</option>
                        <option value="LICENCE">LICENCE</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Année</label>
                    <input type="number" name="annee" class="form-control" value="2026" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Département</label>
                    <input type="text" name="departement" class="form-control" placeholder="Ex : Génie Informatique" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Ajouter</button>
                </div>
            </div>
        </form>
    </div>
</div>

<h4 class="mb-3">Concours existants</h4>
<table class="table table-striped table-bordered">
    <thead class="table-primary">
        <tr>
            <th>Type</th>
            <th>Année</th>
            <th>Département</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($concours) === 0): ?>
            <tr><td colspan="3" class="text-center text-muted">Aucun concours enregistré</td></tr>
        <?php else: ?>
            <?php foreach ($concours as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['type']) ?></td>
                    <td><?= $c['annee'] ?></td>
                    <td><?= htmlspecialchars($c['departement']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php include '../includes/footer.php'; ?>