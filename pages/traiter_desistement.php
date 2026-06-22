<?php
require '../config/database.php';
include '../includes/verif_connexion.php';

$inscriptionId = (int) $_GET['inscription'];
$filiereId = (int) $_GET['filiere'];
$concoursId = (int) $_GET['concours'];

// Récupérer le candidat qui se désiste (pour la trace)
$req = $pdo->prepare("SELECT candidat_id FROM inscriptions WHERE id = ?");
$req->execute([$inscriptionId]);
$desiste = $req->fetch();

// Enregistrer le désistement
$req = $pdo->prepare("INSERT INTO desistements (motif, agent_id) VALUES ('Désistement enregistré', ?)");
$req->execute([$_SESSION['utilisateur_id']]);

// Retirer le candidat de la liste principale
$req = $pdo->prepare("DELETE FROM inscriptions WHERE id = ?");
$req->execute([$inscriptionId]);

// Récupérer les ids des listes de la filière
$req = $pdo->prepare("SELECT id, type FROM listes WHERE filiere_id = ?");
$req->execute([$filiereId]);
$idPrincipale = null; $idAttente = null;
foreach ($req->fetchAll() as $l) {
    if ($l['type'] === 'principale') $idPrincipale = $l['id'];
    if ($l['type'] === 'attente') $idAttente = $l['id'];
}

// Trouver le premier de la liste d'attente
$req = $pdo->prepare("SELECT id, candidat_id FROM inscriptions WHERE liste_id = ? ORDER BY rang ASC LIMIT 1");
$req->execute([$idAttente]);
$premier = $req->fetch();

$message = "Désistement enregistré.";

if ($premier) {
    // Nouveau rang en principale = dernier rang + 1
    $req = $pdo->prepare("SELECT COALESCE(MAX(rang),0)+1 AS nr FROM inscriptions WHERE liste_id = ?");
    $req->execute([$idPrincipale]);
    $nouveauRang = $req->fetch()['nr'];

    // Promouvoir le candidat
    $req = $pdo->prepare("UPDATE inscriptions SET liste_id = ?, rang = ? WHERE id = ?");
    $req->execute([$idPrincipale, $nouveauRang, $premier['id']]);

    // Recalculer les rangs de la liste d'attente
    $req = $pdo->prepare("SELECT id FROM inscriptions WHERE liste_id = ? ORDER BY rang ASC");
    $req->execute([$idAttente]);
    $rang = 1;
    foreach ($req->fetchAll() as $r) {
        $maj = $pdo->prepare("UPDATE inscriptions SET rang = ? WHERE id = ?");
        $maj->execute([$rang, $r['id']]);
        $rang++;
    }
// Nom du promu
    $req = $pdo->prepare("SELECT nom, prenom FROM utilisateurs WHERE id = ?");
    $req->execute([$premier['candidat_id']]);
    $promu = $req->fetch();
    $message = "Désistement enregistré. " . $promu['prenom'] . " " . $promu['nom'] . " a été promu(e) en liste principale.";
}

// Recalculer les rangs de la liste principale (pour éviter les trous)
$req = $pdo->prepare("SELECT id FROM inscriptions WHERE liste_id = ? ORDER BY rang ASC");
$req->execute([$idPrincipale]);
$rang = 1;
foreach ($req->fetchAll() as $r) {
    $maj = $pdo->prepare("UPDATE inscriptions SET rang = ? WHERE id = ?");
    $maj->execute([$rang, $r['id']]);
    $rang++;

}

header("Location: desistement.php?concours=$concoursId&filiere=$filiereId&message=" . urlencode($message));
exit;
?>