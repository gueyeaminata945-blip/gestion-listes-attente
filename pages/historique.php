<?php
require '../config/database.php';
include '../includes/verif_connexion.php';

// Réservé aux agents et administrateurs.
if (!in_array($_SESSION['role'], ['agent', 'administrateur'], true)) {
    header("Location: /Gestion_Liste_attente/index.php");
    exit;
}

include '../includes/header.php';

// Filtre optionnel par mot-clé sur l'opération ou la cible.
$recherche = trim($_GET['q'] ?? '');

$sql = "
    SELECT h.operation, h.cible, h.date_heure,
           u.prenom, u.nom, u.role
    FROM historiques h
    LEFT JOIN utilisateurs u ON h.acteur_id = u.id
";
$params = [];
if ($recherche !== '') {
    $sql .= " WHERE h.operation LIKE ? OR h.cible LIKE ? ";
    $params[] = "%$recherche%";
    $params[] = "%$recherche%";
}
$sql .= " ORDER BY h.date_heure DESC, h.id DESC LIMIT 300";

$req = $pdo->prepare($sql);
$req->execute($params);
$lignes = $req->fetchAll();
?>

<h2 class="page-title">Historique des opérations</h2>
<p class="text-muted">
    Journal des actions effectuées sur le système (désistements, promotions, notifications,
    imports, modifications de mot de passe). 300 dernières opérations.
</p>

<form method="GET" class="mb-4">
    <div class="input-group" style="max-width: 480px;">
        <input type="text" name="q" class="form-control" placeholder="Rechercher une opération ou une cible…"
               value="<?= htmlspecialchars($recherche) ?>">
        <button type="submit" class="btn btn-primary">Rechercher</button>
        <?php if ($recherche !== ''): ?>
            <a href="historique.php" class="btn btn-outline-secondary">Réinitialiser</a>
        <?php endif; ?>
    </div>
</form>

<table class="table table-striped table-bordered align-middle">
    <thead class="table-secondary">
        <tr>
            <th>Date et heure</th>
            <th>Acteur</th>
            <th>Opération</th>
            <th>Cible</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($lignes) === 0): ?>
            <tr><td colspan="4" class="text-center text-muted">Aucune opération enregistrée.</td></tr>
        <?php else: ?>
            <?php foreach ($lignes as $l): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($l['date_heure'])) ?></td>
                    <td>
                        <?php if ($l['prenom']): ?>
                            <?= htmlspecialchars($l['prenom'] . ' ' . $l['nom']) ?>
                            <span class="badge bg-light text-dark"><?= htmlspecialchars($l['role']) ?></span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($l['operation']) ?></td>
                    <td><?= htmlspecialchars($l['cible'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php include '../includes/footer.php'; ?>
