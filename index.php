<?php include 'includes/header.php'; ?>

<?php $connecte = isset($_SESSION['utilisateur_id']); $role = $_SESSION['role'] ?? ''; ?>

<div class="hero">
    <div class="esp-gold-bar"></div>
    <h1>Gestion des listes d'attente</h1>
    <p>
        Plateforme de l'École Supérieure Polytechnique de Dakar pour le suivi automatisé
        des listes d'attente aux concours et tests d'entrée.
    </p>
    <?php if (!$connecte): ?>
        <div class="mt-4">
            <a href="/Gestion_Liste_attente/auth/login.php" class="btn btn-light fw-semibold me-2">
                <i class="bi bi-box-arrow-in-right"></i> Se connecter
            </a>
            <a href="/Gestion_Liste_attente/pages/consultation.php" class="btn btn-outline-light">
                <i class="bi bi-search"></i> Consulter ma situation
            </a>
        </div>
    <?php endif; ?>
</div>

<?php if ($connecte): ?>
    <h4 class="page-title">Accès rapide</h4>
    <div class="row g-3">
        <?php
        // Cartes affichées selon le rôle de l'utilisateur connecté.
        $cartes = [
            ['listes.php',      'bi-list-ol',      'Listes',          'Consulter les listes principale et d\'attente par filière.', true],
            ['desistement.php', 'bi-person-dash',  'Désistements',    'Enregistrer un désistement et promouvoir le suivant.', true],
            ['dashboard.php',   'bi-speedometer2', 'Tableau de bord', 'Statistiques, taux de désistement et places restantes.', true],
            ['import.php',      'bi-upload',       'Importer',        'Importer les candidats depuis le PGI (fichier CSV).', in_array($role, ['agent','administrateur'], true)],
            ['historique.php',  'bi-clock-history','Historique',      'Journal complet des opérations effectuées.', in_array($role, ['agent','administrateur'], true)],
            ['concours.php',    'bi-mortarboard',  'Concours',        'Paramétrer les concours par département.', $role === 'administrateur'],
            ['filieres.php',    'bi-diagram-3',    'Filières',        'Définir les filières et leurs capacités d\'accueil.', $role === 'administrateur'],
        ];
        foreach ($cartes as [$page, $ico, $titre, $desc, $visible]):
            if (!$visible) continue; ?>
            <div class="col-md-4 col-sm-6">
                <a class="card feature-card" href="/Gestion_Liste_attente/pages/<?= $page ?>">
                    <div class="card-body">
                        <div class="feature-ico"><i class="bi <?= $ico ?>"></i></div>
                        <h5><?= $titre ?></h5>
                        <p><?= $desc ?></p>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
