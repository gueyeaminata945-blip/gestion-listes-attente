<?php include '../includes/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <h2 class="mb-4">Connexion</h2>

        <?php if (isset($_GET['erreur'])): ?>
            <div class="alert alert-danger">Email ou mot de passe incorrect.</div>
        <?php endif; ?>

        <form action="traitement.php" method="POST">
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Mot de passe</label>
                <input type="password" name="mot_de_passe" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Se connecter</button>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>