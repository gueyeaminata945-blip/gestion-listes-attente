<?php
require '../config/database.php';
include '../includes/verif_connexion.php';
require '../includes/import_pgi.php';

// Réservé aux agents et administrateurs (l'agent importe les candidats — cf. cahier des charges).
if (!in_array($_SESSION['role'], ['agent', 'administrateur'], true)) {
    header("Location: /Gestion_Liste_attente/index.php");
    exit;
}

$resultat = null;
$erreur = '';

// Sélection courante (conservée entre l'affichage du formulaire et le POST).
$concoursChoisi = (int) ($_POST['concours'] ?? $_GET['concours'] ?? 0);
$filiereChoisie = (int) ($_POST['filiere']  ?? $_GET['filiere']  ?? 0);
$typeListe      = $_POST['type_liste'] ?? 'principale';

// Traitement de l'upload.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['fichier'])) {
    try {
        if ($_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Le fichier n'a pas pu être chargé (taille trop grande ou champ vide).");
        }
        $ext = strtolower(pathinfo($_FILES['fichier']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt'], true)) {
            throw new Exception("Format non pris en charge : veuillez fournir un fichier CSV "
                . "(depuis Excel : Fichier ▸ Enregistrer sous ▸ CSV).");
        }

        // On retrouve la liste cible (principale/attente) de la filière choisie.
        $req = $pdo->prepare("SELECT id FROM listes WHERE filiere_id = ? AND type = ?");
        $req->execute([$filiereChoisie, $typeListe]);
        $listeId = (int) $req->fetchColumn();
        if (!$listeId) {
            throw new Exception("Aucune liste « $typeListe » trouvée pour cette filière.");
        }

        $resultat = importerCandidats($pdo, $_FILES['fichier']['tmp_name'], $listeId, (int) $_SESSION['utilisateur_id']);
    } catch (Exception $e) {
        $erreur = $e->getMessage();
    }
}

include '../includes/header.php';

$concours = $pdo->query("SELECT * FROM concours ORDER BY type, departement")->fetchAll();
if (!$concoursChoisi && $concours) {
    $concoursChoisi = $concours[0]['id'];
}

$reqFilieres = $pdo->prepare("SELECT * FROM filieres WHERE concours_id = ? ORDER BY nom");
$reqFilieres->execute([$concoursChoisi]);
$filieres = $reqFilieres->fetchAll();

// On s'assure que la filière choisie appartient bien au concours sélectionné.
$filiereValide = false;
foreach ($filieres as $f) {
    if ($f['id'] == $filiereChoisie) { $filiereValide = true; break; }
}
if (!$filiereValide) {
    $filiereChoisie = $filieres[0]['id'] ?? 0;
}
?>

<h2 class="page-title">Importer des candidats depuis le PGI</h2>

<p class="text-muted">
    Chargez le fichier <strong>CSV</strong> exporté depuis le PGI de l'ESP. Les candidats sont créés
    automatiquement (ou mis à jour s'ils existent déjà), puis placés dans la liste choisie.
    <a href="/Gestion_Liste_attente/assets/modele_import_candidats.csv" download>Télécharger le modèle CSV</a>.
</p>

<?php if ($erreur): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($erreur) ?></div>
<?php endif; ?>

<?php if ($resultat): ?>
    <div class="alert alert-success">
        <strong>Import terminé.</strong>
        <?= $resultat['importes'] ?> candidat(s) traité(s)
        (<?= $resultat['crees'] ?> créé(s), <?= $resultat['maj'] ?> mis à jour).
    </div>
    <?php if (!empty($resultat['avertissements'])): ?>
        <div class="alert alert-warning">
            <strong>Lignes ignorées :</strong>
            <ul class="mb-0">
                <?php foreach ($resultat['avertissements'] as $a): ?>
                    <li><?= htmlspecialchars($a) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Étape 1 : choix du concours et de la filière (rechargement automatique) -->
<form method="GET" class="row g-3 mb-4">
    <div class="col-md-6">
        <label class="form-label fw-bold">1. Concours</label>
        <select name="concours" class="form-select" onchange="this.form.submit()">
            <?php foreach ($concours as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($c['id'] == $concoursChoisi) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['type'] . ' - ' . $c['departement'] . ' (' . $c['annee'] . ')') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-bold">2. Filière</label>
        <select name="filiere" class="form-select" onchange="this.form.submit()">
            <?php foreach ($filieres as $f): ?>
                <option value="<?= $f['id'] ?>" <?= ($f['id'] == $filiereChoisie) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($f['nom']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<!-- Étape 2 : choix de la liste et chargement du fichier -->
<div class="card">
    <div class="card-body">
        <h5 class="card-title">3. Choisir la liste et charger le fichier</h5>

        <?php if (empty($filieres)): ?>
            <div class="alert alert-warning mb-0">
                Aucune filière pour ce concours. Créez d'abord une filière dans
                <a href="/Gestion_Liste_attente/pages/filieres.php">Gestion des filières</a>.
            </div>
        <?php else: ?>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="concours" value="<?= $concoursChoisi ?>">
                <input type="hidden" name="filiere" value="<?= $filiereChoisie ?>">

                <div class="mb-3">
                    <label class="form-label">Type de liste</label>
                    <div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="type_liste"
                                   id="lp" value="principale" <?= $typeListe === 'principale' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="lp">Liste principale</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="type_liste"
                                   id="la" value="attente" <?= $typeListe === 'attente' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="la">Liste d'attente</label>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Fichier CSV</label>
                    <input type="file" name="fichier" class="form-control" accept=".csv,.txt" required>
                </div>

                <button type="submit" class="btn btn-primary">Importer les candidats</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
