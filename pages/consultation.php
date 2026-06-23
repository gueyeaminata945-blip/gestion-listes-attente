<?php
require '../config/database.php';
include '../includes/header.php';

$resultat = null;
$erreur = "";

// Si le candidat a soumis son numéro
if (isset($_GET['numero']) && $_GET['numero'] !== '') {
    $numero = $_GET['numero'];

    // Chercher le candidat et son inscription
    $requete = $pdo->prepare("
        SELECT u.nom, u.prenom, u.numero_candidat,
               i.rang, l.type AS type_liste,
               f.nom AS filiere, c.type AS concours_type, c.departement
        FROM utilisateurs u
        JOIN inscriptions i ON i.candidat_id = u.id
        JOIN listes l ON i.liste_id = l.id
        JOIN filieres f ON l.filiere_id = f.id
        JOIN concours c ON f.concours_id = c.id
        WHERE u.numero_candidat = ? AND u.role = 'candidat'
    ");
    $requete->execute([$numero]);
    $resultat = $requete->fetch();

    if (!$resultat) {
        $erreur = "Aucun candidat trouvé avec ce numéro.";
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-7">
        <h2 class="page-title">Consulter ma situation</h2>
        <p class="text-muted">Entrez votre numéro de candidat pour connaître votre rang et votre statut.</p>

        <form method="GET" class="mb-4">
            <div class="input-group">
                <input type="text" name="numero" class="form-control"
                       placeholder="Exemple : C001" value="<?= htmlspecialchars($_GET['numero'] ?? '') ?>" required>
                <button type="submit" class="btn btn-primary">Consulter</button>
            </div>
        </form>

        <?php if ($erreur): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <?php if ($resultat): ?>
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">
                        <?= htmlspecialchars($resultat['prenom'] . ' ' . $resultat['nom']) ?>
                        (<?= htmlspecialchars($resultat['numero_candidat']) ?>)
                    </h5>
                    <p class="mb-1"><strong>Concours :</strong> <?= htmlspecialchars($resultat['concours_type'] . ' - ' . $resultat['departement']) ?></p>
                    <p class="mb-1"><strong>Filière :</strong> <?= htmlspecialchars($resultat['filiere']) ?></p>
                    <p class="mb-1"><strong>Rang :</strong> <?= $resultat['rang'] ?></p>
                    <p class="mb-0">
                        <strong>Statut :</strong>
                        <?php if ($resultat['type_liste'] === 'principale'): ?>
                            <span class="badge bg-success">Liste principale (Admis)</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Liste d'attente</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>