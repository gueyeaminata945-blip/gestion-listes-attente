<?php
require '../config/database.php';
include '../includes/verif_connexion.php';
require '../includes/codes.php';

$utilisateurId = (int) $_SESSION['utilisateur_id'];

// On récupère l'e-mail et le prénom de l'utilisateur connecté.
$req = $pdo->prepare("SELECT prenom, email, mot_de_passe FROM utilisateurs WHERE id = ?");
$req->execute([$utilisateurId]);
$utilisateur = $req->fetch();

$message = '';
$typeMessage = 'info';
$etape = 'demande';   // 'demande' = saisie du nouveau mdp ; 'confirme' = saisie du code

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Étape 1 : l'utilisateur saisit son mot de passe actuel + le nouveau ---
    if (($_POST['action'] ?? '') === 'demande') {
        $actuel   = $_POST['mot_de_passe_actuel'] ?? '';
        $nouveau  = $_POST['nouveau_mot_de_passe'] ?? '';
        $confirme = $_POST['confirmation'] ?? '';

        if (!password_verify($actuel, $utilisateur['mot_de_passe'])) {
            $message = "Le mot de passe actuel est incorrect.";
            $typeMessage = 'danger';
        } elseif (strlen($nouveau) < 6) {
            $message = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
            $typeMessage = 'danger';
        } elseif ($nouveau !== $confirme) {
            $message = "Les deux nouveaux mots de passe ne correspondent pas.";
            $typeMessage = 'danger';
        } else {
            // On enregistre le futur mot de passe (hashé) en attente de confirmation.
            $hash = password_hash($nouveau, PASSWORD_DEFAULT);
            $code = creerCodeVerification($pdo, $utilisateurId, 'changement', $hash);
            $envoye = envoyerCodeParEmail($utilisateur['email'], $utilisateur['prenom'],
                                          $code, "la modification de votre mot de passe");

            if ($envoye) {
                $message = "Un code de vérification a été envoyé à " . htmlspecialchars($utilisateur['email']) . ".";
                $typeMessage = 'success';
                $etape = 'confirme';
            } else {
                $message = "L'envoi de l'e-mail a échoué. Vérifiez la configuration SMTP (config/mail.php).";
                $typeMessage = 'danger';
            }
        }
    }

    // --- Étape 2 : l'utilisateur saisit le code reçu par e-mail ---
    elseif (($_POST['action'] ?? '') === 'confirme') {
        $code = trim($_POST['code'] ?? '');
        $ligne = verifierCodeVerification($pdo, $utilisateurId, $code, 'changement');

        if (!$ligne) {
            $message = "Code invalide ou expiré. Veuillez recommencer la procédure.";
            $typeMessage = 'danger';
            $etape = 'confirme';
        } else {
            // Le code est bon : on applique le nouveau mot de passe enregistré.
            $maj = $pdo->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?");
            $maj->execute([$ligne['nouveau_hash'], $utilisateurId]);
            marquerCodeUtilise($pdo, (int) $ligne['id']);

            // Traçabilité (BNF-05).
            $pdo->prepare("INSERT INTO historiques (operation, cible, acteur_id)
                           VALUES ('Modification du mot de passe', 'Compte personnel', ?)")
                ->execute([$utilisateurId]);

            $message = "Votre mot de passe a été modifié avec succès.";
            $typeMessage = 'success';
            $etape = 'termine';
        }
    }
}

include '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <h2 class="page-title">Modifier mon mot de passe</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?= $typeMessage ?>"><?= $message ?></div>
        <?php endif; ?>

        <?php if ($etape === 'demande'): ?>
            <p class="text-muted">
                Pour des raisons de sécurité, un code de vérification vous sera envoyé
                par e-mail afin de confirmer la modification.
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="demande">
                <div class="mb-3">
                    <label class="form-label">Mot de passe actuel</label>
                    <input type="password" name="mot_de_passe_actuel" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nouveau mot de passe</label>
                    <input type="password" name="nouveau_mot_de_passe" class="form-control" minlength="6" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirmer le nouveau mot de passe</label>
                    <input type="password" name="confirmation" class="form-control" minlength="6" required>
                </div>
                <button type="submit" class="btn btn-primary">Envoyer le code de vérification</button>
            </form>

        <?php elseif ($etape === 'confirme'): ?>
            <p class="text-muted">
                Saisissez le code à 6 chiffres reçu par e-mail pour valider le changement.
            </p>
            <form method="POST">
                <input type="hidden" name="action" value="confirme">
                <div class="mb-3">
                    <label class="form-label">Code de vérification</label>
                    <input type="text" name="code" class="form-control" inputmode="numeric"
                           pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autofocus>
                </div>
                <button type="submit" class="btn btn-success">Valider le nouveau mot de passe</button>
                <a href="mot_de_passe.php" class="btn btn-link">Recommencer</a>
            </form>

        <?php else: /* termine */ ?>
            <a href="/Gestion_Liste_attente/index.php" class="btn btn-primary">Retour à l'accueil</a>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
