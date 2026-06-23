<?php
require '../config/database.php';
include '../includes/verif_connexion.php';
require '../includes/promotion.php';

$inscriptionId = (int) $_GET['inscription'];
$filiereId     = (int) $_GET['filiere'];
$concoursId    = (int) $_GET['concours'];
$agentId       = (int) $_SESSION['utilisateur_id'];

// 1) Enregistrer le désistement (rattaché à sa filière pour les statistiques).
$pdo->prepare("INSERT INTO desistements (motif, agent_id, filiere_id) VALUES ('Désistement enregistré', ?, ?)")
    ->execute([$agentId, $filiereId]);

// 2) Retirer le candidat désisté de la liste principale.
retirerInscription($pdo, $inscriptionId);

// 3) Promouvoir automatiquement le premier candidat de la liste d'attente
//    (met à jour les rangs, les effectifs et envoie la notification e-mail).
$promu = promouvoirPremierAttente($pdo, $filiereId, $agentId);

// 4) Refermer les éventuels trous de rang en liste principale (cas liste d'attente vide).
[$idPrincipale] = listesFiliere($pdo, $filiereId);
if ($idPrincipale) {
    recalculerRangs($pdo, $idPrincipale);
    majEffectif($pdo, $idPrincipale);
}

// 5) Message de retour pour l'agent.
if ($promu) {
    $message = "Désistement enregistré. " . $promu['prenom'] . " " . $promu['nom']
             . " a été promu(e) en liste principale.";
    $message .= $promu['notifie']
        ? " Un e-mail de notification lui a été envoyé."
        : " (Attention : l'e-mail de notification n'a pas pu être envoyé.)";
} else {
    $message = "Désistement enregistré. La liste d'attente est vide : la place reste vacante.";
}

header("Location: desistement.php?concours=$concoursId&filiere=$filiereId&message=" . urlencode($message));
exit;
