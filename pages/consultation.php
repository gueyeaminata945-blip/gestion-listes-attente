<?php
require '../config/database.php';
require '../includes/promotion.php';   // pour notifierAgentsDesistement()
include '../includes/header.php';

$resultat = null;
$erreur = "";
$message = "";       // message de confirmation de la demande de désistement
$demandeEnCours = false;

// Numéro saisi (depuis le formulaire de consultation OU la demande de désistement).
$numero = $_POST['numero'] ?? $_GET['numero'] ?? '';

if ($numero !== '') {
    // Recherche du candidat et de son inscription active.
    $requete = $pdo->prepare("
        SELECT u.id AS candidat_id, u.nom, u.prenom, u.numero_candidat,
               i.id AS inscription_id, i.rang, l.type AS type_liste,
               f.id AS filiere_id, f.nom AS filiere, f.departement AS filiere_dep,
               c.type AS concours_type, c.departement
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
    } else {
        // Une demande de désistement est-elle déjà en attente pour ce candidat ?
        $req = $pdo->prepare("SELECT COUNT(*) FROM demandes_desistement
                              WHERE candidat_id = ? AND statut = 'en_attente'");
        $req->execute([$resultat['candidat_id']]);
        $demandeEnCours = $req->fetchColumn() > 0;

        // Traitement de la demande de désistement envoyée par le candidat.
        if (($_POST['action'] ?? '') === 'desister' && !$demandeEnCours) {
            $motif = trim($_POST['motif'] ?? '');

            $pdo->prepare("INSERT INTO demandes_desistement (inscription_id, candidat_id, filiere_id, motif)
                           VALUES (?, ?, ?, ?)")
                ->execute([$resultat['inscription_id'], $resultat['candidat_id'], $resultat['filiere_id'], $motif]);

            // Notification des agents du département concerné.
            notifierAgentsDesistement($pdo, [
                'prenom'      => $resultat['prenom'],
                'nom'         => $resultat['nom'],
                'numero'      => $resultat['numero_candidat'],
                'filiere'     => $resultat['filiere'],
                'departement' => $resultat['filiere_dep'],
                'motif'       => $motif,
            ]);

            // Traçabilité (BNF-05).
            $pdo->prepare("INSERT INTO historiques (operation, cible, acteur_id)
                           VALUES ('Demande de désistement (candidat)', ?, NULL)")
                ->execute(['Candidat ' . $resultat['prenom'] . ' ' . $resultat['nom']]);

            $message = "Votre demande de désistement a bien été transmise à l'administration. "
                     . "Un agent va la traiter.";
            $demandeEnCours = true;
        }
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
                       placeholder="Exemple : C001" value="<?= htmlspecialchars($numero) ?>" required>
                <button type="submit" class="btn btn-primary">Consulter</button>
            </div>
        </form>

        <?php if ($erreur): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($resultat): ?>
            <div class="card mb-4">
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

            <!-- Demande de désistement -->
            <div class="card border-danger">
                <div class="card-body">
                    <h5 class="card-title text-danger">Se désister</h5>
                    <?php if ($demandeEnCours): ?>
                        <p class="mb-0 text-muted">
                            <i class="bi bi-clock-history"></i>
                            Une demande de désistement est <strong>en cours de traitement</strong> par l'administration.
                        </p>
                    <?php else: ?>
                        <p class="text-muted">
                            Si vous ne souhaitez plus votre place, envoyez une demande de désistement.
                            Un agent du département la validera, et le candidat suivant pourra être admis.
                        </p>
                        <form method="POST"
                              onsubmit="return confirm('Confirmer l\'envoi de votre demande de désistement ?');">
                            <input type="hidden" name="numero" value="<?= htmlspecialchars($resultat['numero_candidat']) ?>">
                            <input type="hidden" name="action" value="desister">
                            <div class="mb-3">
                                <label class="form-label">Motif (facultatif)</label>
                                <input type="text" name="motif" class="form-control" maxlength="255"
                                       placeholder="Ex : admis ailleurs">
                            </div>
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-person-dash"></i> Envoyer ma demande de désistement
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
