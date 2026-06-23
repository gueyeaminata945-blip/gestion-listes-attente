<?php
require '../config/database.php';
require '../includes/codes.php';

$message = '';
$typeMessage = 'info';
$emailSaisi = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailSaisi = trim($_POST['email'] ?? '');

    $req = $pdo->prepare("SELECT id, prenom, email FROM utilisateurs WHERE email = ?");
    $req->execute([$emailSaisi]);
    $utilisateur = $req->fetch();

    // On envoie le code seulement si le compte existe, mais on affiche toujours
    // le même message pour ne pas révéler quelles adresses sont enregistrées.
    if ($utilisateur) {
        $code = creerCodeVerification($pdo, (int) $utilisateur['id'], 'reinitialisation');
        envoyerCodeParEmail($utilisateur['email'], $utilisateur['prenom'],
                            $code, "la réinitialisation de votre mot de passe");
    }

    // On redirige vers l'étape 2 en pré-remplissant l'e-mail.
    header("Location: reinitialiser.php?email=" . urlencode($emailSaisi) . "&envoye=1");
    exit;
}

include '../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <h2 class="mb-4">Mot de passe oublié</h2>
        <p class="text-muted">
            Saisissez l'adresse e-mail de votre compte. Si elle existe, vous recevrez
            un code de vérification à 6 chiffres pour définir un nouveau mot de passe.
        </p>

        <?php if ($message): ?>
            <div class="alert alert-<?= $typeMessage ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control"
                       value="<?= htmlspecialchars($emailSaisi) ?>" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary">Envoyer le code</button>
            <a href="login.php" class="btn btn-link">Retour à la connexion</a>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
