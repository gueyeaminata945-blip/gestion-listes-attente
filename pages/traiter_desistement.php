<?php
require '../config/database.php';
include '../includes/verif_connexion.php';
require '../includes/promotion.php';

$inscriptionId = (int) $_GET['inscription'];
$filiereId     = (int) $_GET['filiere'];
$concoursId    = (int) $_GET['concours'];
$agentId       = (int) $_SESSION['utilisateur_id'];

// Enregistre le désistement et promeut automatiquement le suivant si besoin
// (logique partagée avec les demandes de désistement et les relances).
$res = enregistrerDesistement($pdo, $inscriptionId, $filiereId, $agentId);

if ($res === null) {
    $message = "Ce candidat n'est plus inscrit.";
} elseif ($res['promu']) {
    $message = "Désistement enregistré. " . $res['promu']['prenom'] . " " . $res['promu']['nom']
             . " a été promu(e) en liste principale.";
    $message .= $res['promu']['notifie']
        ? " Un e-mail de notification lui a été envoyé."
        : " (Attention : l'e-mail de notification n'a pas pu être envoyé.)";
} else {
    $message = "Désistement enregistré. La liste d'attente est vide : la place reste vacante.";
}

header("Location: desistement.php?concours=$concoursId&filiere=$filiereId&message=" . urlencode($message));
exit;
