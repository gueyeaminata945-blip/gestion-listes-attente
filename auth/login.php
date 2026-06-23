<?php include '../includes/header.php'; ?>

<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-head">
            <div class="esp-logo">ESP</div>
            <h1>Connexion</h1>
            <p>Espace administration &amp; agents</p>
        </div>
        <div class="auth-body">

            <?php if (isset($_GET['erreur'])): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> Email ou mot de passe incorrect.
                </div>
            <?php endif; ?>

            <form action="traitement.php" method="POST">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control" required autofocus>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mot de passe</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="mot_de_passe" class="form-control" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-box-arrow-in-right"></i> Se connecter
                </button>
            </form>

            <p class="text-center mt-3 mb-0">
                <a href="mot_de_passe_oublie.php">Mot de passe oublié ?</a>
            </p>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
