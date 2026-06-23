<?php
require '../config/database.php';
include '../includes/verif_connexion.php';

if ($_SESSION['role'] !== 'administrateur') {
    header("Location: /Gestion_Liste_attente/index.php");
    exit;
}

$message = "";

// Enregistrer une nouvelle filière
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = $_POST['nom'];
    $departement = $_POST['departement'];
    $capacite = (int) $_POST['capacite'];
    $concoursId = (int) $_POST['concours_id'];

    // Ajouter la filière
    $req = $pdo->prepare("INSERT INTO filieres (nom, departement, capacite_accueil, concours_id) VALUES (?, ?, ?, ?)");
    $req->execute([$nom, $departement, $capacite, $concoursId]);
    $filiereId = $pdo->lastInsertId();

    // Créer automatiquement les deux listes (principale + attente) pour cette filière
    $req = $pdo->prepare("INSERT INTO listes (type, nombre_etudiants, filiere_id) VALUES ('principale', 0, ?), ('attente', 0, ?)");
    $req->execute([$filiereId, $filiereId]);

    // Traçabilité (BNF-05).
    $pdo->prepare("INSERT INTO historiques (operation, cible, acteur_id) VALUES ('Création filière', ?, ?)")
        ->execute([$nom . ' (' . $departement . ')', $_SESSION['utilisateur_id']]);

    $message = "Filière ajoutée avec succès (listes principale et d'attente créées).";
}

include '../includes/header.php';

// Récupérer les concours pour le menu déroulant
$concours = $pdo->query("SELECT * FROM concours ORDER BY type, departement")->fetchAll();

// Récupérer les filières avec le nom de leur concours
$filieres = $pdo->query("
    SELECT f.*, c.type AS concours_type, c.departement AS concours_dep
    FROM filieres f
    JOIN concours c ON f.concours_id = c.id
    ORDER BY f.nom
")->fetchAll();
?>

<h2 class="page-title">Gestion des filières</h2>

<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title">Ajouter une filière</h5>
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Nom de la filière</label>
                    <input type="text" name="nom" class="form-control" placeholder="Ex : Génie Informatique" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Département</label>
                    <input type="text" name="departement" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Capacité</label>
                    <input type="number" name="capacite" class="form-control" value="30" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Concours</label>
                    <select name="concours_id" class="form-select" required>
                        <?php foreach ($concours as $c): ?>
                            <option value="<?= $c['id'] ?>">
                                <?= htmlspecialchars($c['type'] . ' - ' . $c['departement']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Ajouter la filière</button>
                </div>
            </div>
        </form>
    </div>
</div>

<h4 class="mb-3">Filières existantes</h4>
<table class="table table-striped table-bordered">
    <thead class="table-primary">
        <tr>
            <th>Filière</th>
            <th>Département</th>
            <th>Capacité</th>
            <th>Concours</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($filieres) === 0): ?>
            <tr><td colspan="4" class="text-center text-muted">Aucune filière enregistrée</td></tr>
        <?php else: ?>
            <?php foreach ($filieres as $f): ?>
                <tr>
                    <td><?= htmlspecialchars($f['nom']) ?></td>
                    <td><?= htmlspecialchars($f['departement']) ?></td>
                    <td><?= $f['capacite_accueil'] ?></td>
                    <td><?= htmlspecialchars($f['concours_type'] . ' - ' . $f['concours_dep']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php include '../includes/footer.php'; ?>