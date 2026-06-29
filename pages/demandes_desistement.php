<?php
require '../config/database.php';
include '../includes/verif_connexion.php';
require '../includes/promotion.php';

// Réservé aux agents et administrateurs.
if (!in_array($_SESSION['role'], ['agent', 'administrateur'], true)) {
    header("Location: /Gestion_Liste_attente/index.php");
    exit;
}

$agentId = (int) $_SESSION['utilisateur_id'];

// --- Traitement d'une action (validation ou rejet d'une demande) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $demandeId = (int) ($_POST['demande_id'] ?? 0);
    $action    = $_POST['action'] ?? '';

    // On récupère la demande (si elle est toujours en attente).
    $req = $pdo->prepare("SELECT d.*, u.prenom, u.nom FROM demandes_desistement d
                          JOIN utilisateurs u ON d.candidat_id = u.id
                          WHERE d.id = ? AND d.statut = 'en_attente'");
    $req->execute([$demandeId]);
    $demande = $req->fetch();

    $message = "Demande introuvable ou déjà traitée.";

    if ($demande) {
        if ($action === 'traiter') {
            $nom = $demande['prenom'] . ' ' . $demande['nom'];
            if ($demande['inscription_id'] === null) {
                $message = "Ce candidat n'est plus inscrit ; la demande est marquée comme traitée.";
            } else {
                $res = enregistrerDesistement($pdo, (int) $demande['inscription_id'], (int) $demande['filiere_id'],
                                              $agentId, 'Désistement (demande candidat)');
                if ($res && $res['promu']) {
                    $message = "Désistement de $nom enregistré. " . $res['promu']['prenom'] . " "
                             . $res['promu']['nom'] . " a été promu(e) et notifié(e).";
                } elseif ($res) {
                    $message = "Désistement de $nom enregistré.";
                } else {
                    $message = "Ce candidat n'est plus inscrit.";
                }
            }
            $pdo->prepare("UPDATE demandes_desistement SET statut = 'traitee', date_traitement = NOW(), agent_id = ? WHERE id = ?")
                ->execute([$agentId, $demandeId]);

        } elseif ($action === 'rejeter') {
            $pdo->prepare("UPDATE demandes_desistement SET statut = 'rejetee', date_traitement = NOW(), agent_id = ? WHERE id = ?")
                ->execute([$agentId, $demandeId]);
            $message = "Demande de désistement rejetée.";
        }
    }

    header("Location: demandes_desistement.php?message=" . urlencode($message));
    exit;
}

include '../includes/header.php';

// Liste des demandes en attente.
$demandes = $pdo->query("
    SELECT d.id, d.motif, d.date_demande, d.inscription_id,
           u.prenom, u.nom, u.numero_candidat,
           f.nom AS filiere, f.departement,
           l.type AS type_liste, i.rang
    FROM demandes_desistement d
    JOIN utilisateurs u ON d.candidat_id = u.id
    JOIN filieres f ON d.filiere_id = f.id
    LEFT JOIN inscriptions i ON d.inscription_id = i.id
    LEFT JOIN listes l ON i.liste_id = l.id
    WHERE d.statut = 'en_attente'
    ORDER BY d.date_demande ASC
")->fetchAll();
?>

<h2 class="page-title">Demandes de désistement</h2>
<p class="text-muted">
    Demandes envoyées par les candidats depuis leur espace de consultation.
    En validant, le candidat est retiré et le suivant de la liste d'attente est automatiquement promu et notifié.
</p>

<?php if (isset($_GET['message'])): ?>
    <div class="alert alert-info"><?= htmlspecialchars($_GET['message']) ?></div>
<?php endif; ?>

<table class="table table-striped table-bordered align-middle">
    <thead class="table-warning">
        <tr>
            <th>Date</th>
            <th>Candidat</th>
            <th>Filière</th>
            <th>Département</th>
            <th>Statut actuel</th>
            <th>Motif</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($demandes) === 0): ?>
            <tr><td colspan="7" class="text-center text-muted">Aucune demande en attente.</td></tr>
        <?php else: ?>
            <?php foreach ($demandes as $d): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($d['date_demande'])) ?></td>
                    <td>
                        <?= htmlspecialchars($d['prenom'] . ' ' . $d['nom']) ?>
                        <span class="text-muted">(<?= htmlspecialchars($d['numero_candidat']) ?>)</span>
                    </td>
                    <td><?= htmlspecialchars($d['filiere']) ?></td>
                    <td><?= htmlspecialchars($d['departement']) ?></td>
                    <td>
                        <?php if ($d['type_liste'] === 'principale'): ?>
                            <span class="badge bg-success">Principale (rang <?= $d['rang'] ?>)</span>
                        <?php elseif ($d['type_liste'] === 'attente'): ?>
                            <span class="badge bg-warning text-dark">Attente (rang <?= $d['rang'] ?>)</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Plus inscrit</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($d['motif'] ?: '—') ?></td>
                    <td class="text-nowrap">
                        <form method="POST" class="d-inline"
                              onsubmit="return confirm('Enregistrer le désistement de ce candidat ?');">
                            <input type="hidden" name="demande_id" value="<?= $d['id'] ?>">
                            <input type="hidden" name="action" value="traiter">
                            <button class="btn btn-danger btn-sm">Enregistrer le désistement</button>
                        </form>
                        <form method="POST" class="d-inline"
                              onsubmit="return confirm('Rejeter cette demande ?');">
                            <input type="hidden" name="demande_id" value="<?= $d['id'] ?>">
                            <input type="hidden" name="action" value="rejeter">
                            <button class="btn btn-outline-secondary btn-sm">Rejeter</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php include '../includes/footer.php'; ?>
