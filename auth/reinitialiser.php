<?php
require '../config/database.php';
require '../includes/codes.php';

$message = '';
$typeMessage = 'info';
$emailSaisi = trim($_GET['email'] ?? '');
$termine = false;

// Petit message d'information quand on arrive depuis l'étape 1.
if (isset($_GET['envoye'])) {
    $message = "Si un compte correspond à cette adresse, un code vient d'être envoyé par e-mail.";
    $typeMessage = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailSaisi = trim($_POST['email'] ?? '');
    $code       = trim($_POST['code'] ?? '');
    $nouveau    = $_POST['nouveau_mot_de_passe'] ?? '';
    $confirme   = $_POST['confirmation'] ?? '';

    $req = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
    $req->execute([$emailSaisi]);
    $utilisateur = $req->fetch();

    if (!$utilisateur) {
        $message = "Code invalide ou expiré.";   // message volontairement générique
        $typeMessage = 'danger';
    } elseif (strlen($nouveau) < 6) {
        $message = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
        $typeMessage = 'danger';
    } elseif ($nouveau !== $confirme) {
        $message = "Les deux mots de passe ne correspondent pas.";
        $typeMessage = 'danger';
    } else {
        $ligne = verifierCodeVerification($pdo, (int) $utilisateur['id'], $code, 'reinitialisation');
        if (!$ligne) {
            $message = "Code invalide ou expiré.";
            $typeMessage = 'danger';
        } else {
            $hash = password_hash($nouveau, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?")
                ->execute([$hash, $utilisateur['id']]);
            marquerCodeUtilise($pdo, (int) $ligne['id']);

            $pdo->prepare("INSERT INTO historiques (operation, cible, acteur_id)
                           VALUES ('Réinitialisation du mot de passe', 'Compte personnel', ?)")
                ->execute([$utilisateur['id']]);

            $message = "Votre mot de passe a été réinitialisé. Vous pouvez maintenant vous connecter.";
            $typeMessage = 'success';
            $termine = true;
        }
    }
}

include '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <h2 class="mb-4">Réinitialiser le mot de passe</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?= $typeMessage ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($termine): ?>
            <a href="login.php" class="btn btn-primary">Se connecter</a>
        <?php else: ?>
            <p class="text-muted">Saisissez le code reçu par e-mail et votre nouveau mot de passe.</p>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control"
                           value="<?= htmlspecialchars($emailSaisi) ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Code de vérification</label>
                    <input type="text" name="code" class="form-control" inputmode="numeric"
                           pattern="[0-9]{6}" maxlength="6" placeholder="000000" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nouveau mot de passe</label>
                    <input type="password" name="nouveau_mot_de_passe" class="form-control" minlength="6" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Confirmer le mot de passe</label>
                    <input type="password" name="confirmation" class="form-control" minlength="6" required>
                </div>
                <button type="submit" class="btn btn-success">Réinitialiser</button>
                <a href="mot_de_passe_oublie.php" class="btn btn-link">Recevoir un nouveau code</a>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
