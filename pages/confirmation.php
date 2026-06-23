<?php
require '../config/database.php';
include '../includes/header.php';

$token = $_GET['token'] ?? '';
$titre = '';
$texte = '';
$classe = 'info';

if ($token === '') {
    $titre = "Lien invalide";
    $texte = "Aucun jeton de confirmation n'a été fourni.";
    $classe = 'danger';
} else {
    // On recherche la notification correspondant au jeton.
    $req = $pdo->prepare("
        SELECT n.id, n.statut, u.prenom, u.nom, f.nom AS filiere
        FROM notifications n
        JOIN inscriptions i ON n.inscription_id = i.id
        JOIN utilisateurs u ON i.candidat_id = u.id
        JOIN listes l ON i.liste_id = l.id
        JOIN filieres f ON l.filiere_id = f.id
        WHERE n.token = ?
    ");
    $req->execute([$token]);
    $notif = $req->fetch();

    if (!$notif) {
        $titre = "Lien invalide";
        $texte = "Cette convocation est introuvable. Le lien est peut-être erroné.";
        $classe = 'danger';
    } elseif ($notif['statut'] === 'confirmee') {
        $titre = "Réception déjà confirmée";
        $texte = "Bonjour " . htmlspecialchars($notif['prenom'] . ' ' . $notif['nom'])
               . ", votre réception a déjà été enregistrée. Aucune action supplémentaire n'est nécessaire.";
        $classe = 'success';
    } else {
        // On enregistre la confirmation avec horodatage (BM-05 / UC-02).
        $pdo->prepare("UPDATE notifications
                       SET statut = 'confirmee', date_confirmation = NOW()
                       WHERE id = ?")
            ->execute([$notif['id']]);

        // Traçabilité (BNF-05). acteur_id = NULL : action du candidat (non connecté).
        $pdo->prepare("INSERT INTO historiques (operation, cible, acteur_id)
                       VALUES ('Confirmation de réception', ?, NULL)")
            ->execute(['Candidat ' . $notif['prenom'] . ' ' . $notif['nom']]);

        $titre = "Réception confirmée — merci !";
        $texte = "Bonjour " . htmlspecialchars($notif['prenom'] . ' ' . $notif['nom'])
               . ", votre admission en liste principale pour la filière « "
               . htmlspecialchars($notif['filiere'])
               . " » est bien confirmée. L'administration a été informée.";
        $classe = 'success';
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-7">
        <h2 class="mb-4">Confirmation de réception</h2>
        <div class="alert alert-<?= $classe ?>">
            <h5><?= htmlspecialchars($titre) ?></h5>
            <p class="mb-0"><?= $texte ?></p>
        </div>
        <a href="/Gestion_Liste_attente/index.php" class="btn btn-primary">Accueil</a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
