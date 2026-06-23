<?php
/**
 * Relances automatiques des notifications d'admission (BM-05).
 *
 * Deux comportements, selon le temps écoulé sans confirmation du candidat :
 *   1. RELANCE  : après « delai_relance_minutes », un e-mail de rappel est renvoyé.
 *   2. EXPIRATION : après « delai_expiration_minutes », la place est abandonnée
 *      et automatiquement proposée au candidat suivant de la liste d'attente,
 *      l'agent étant informé via l'historique.
 *
 * À déclencher périodiquement (cron/relances.php) ou via le bouton de la page
 * « Désistement ».
 */

require_once __DIR__ . '/promotion.php';

/**
 * Traite toutes les relances et expirations dues.
 *
 * @return array{relances:int, expirations:int, details:string[]}
 */
function traiterRelances(PDO $pdo): array
{
    $config       = require __DIR__ . '/../config/app.php';
    $delaiRelance = (int) $config['delai_relance_minutes'];
    $delaiExpir   = (int) $config['delai_expiration_minutes'];

    $relances = 0; $expirations = 0; $details = [];

    // ============ 1) RELANCES (rappel) ============
    // Notifications encore en attente, jamais relancées, dont le délai de relance
    // est dépassé mais pas encore le délai d'expiration.
    $req = $pdo->prepare("
        SELECT n.id, n.token, u.nom, u.prenom, u.email, f.nom AS filiere
        FROM notifications n
        JOIN inscriptions i ON n.inscription_id = i.id
        JOIN utilisateurs u ON i.candidat_id = u.id
        JOIN listes l ON i.liste_id = l.id
        JOIN filieres f ON l.filiere_id = f.id
        WHERE n.type = 'admission' AND n.statut = 'envoyee' AND n.nb_relances = 0
          AND n.date_envoi <= DATE_SUB(NOW(), INTERVAL ? MINUTE)
          AND n.date_envoi >  DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ");
    $req->execute([$delaiRelance, $delaiExpir]);

    foreach ($req->fetchAll() as $n) {
        if (!empty($n['email'])) {
            $lien = urlBaseApp() . "/pages/confirmation.php?token=" . $n['token'];
            $contenu = "
                <p>Bonjour " . htmlspecialchars($n['prenom'] . ' ' . $n['nom']) . ",</p>
                <p><strong>Rappel</strong> : vous avez été admis(e) en liste principale pour la filière
                   <strong>" . htmlspecialchars($n['filiere']) . "</strong>, mais nous n'avons pas
                   encore reçu votre confirmation.</p>
                <p>Merci de confirmer rapidement, sans quoi votre place sera proposée au candidat suivant :</p>
                " . boutonEmail($lien, "Confirmer ma réception") . "
            ";
            envoyerEmail($n['email'], "Rappel : confirmez votre admission — ESP Dakar",
                         gabaritEmail("Rappel de confirmation", $contenu));
        }

        $pdo->prepare("UPDATE notifications SET nb_relances = nb_relances + 1, date_relance = NOW() WHERE id = ?")
            ->execute([$n['id']]);
        $pdo->prepare("INSERT INTO historiques (operation, cible, acteur_id) VALUES ('Relance automatique', ?, NULL)")
            ->execute(['Candidat ' . $n['prenom'] . ' ' . $n['nom']]);

        $relances++;
        $details[] = "Relance envoyée à " . $n['prenom'] . " " . $n['nom'] . " (" . $n['filiere'] . ").";
    }

    // ============ 2) EXPIRATIONS (passage au suivant) ============
    $req = $pdo->prepare("
        SELECT n.id, n.inscription_id, i.id AS insc_id, i.liste_id,
               l.filiere_id, u.nom, u.prenom
        FROM notifications n
        JOIN inscriptions i ON n.inscription_id = i.id
        JOIN listes l ON i.liste_id = l.id
        JOIN utilisateurs u ON i.candidat_id = u.id
        WHERE n.type = 'admission' AND n.statut = 'envoyee'
          AND n.date_envoi <= DATE_SUB(NOW(), INTERVAL ? MINUTE)
    ");
    $req->execute([$delaiExpir]);

    foreach ($req->fetchAll() as $n) {
        $filiereId = (int) $n['filiere_id'];

        // La notification est considérée comme échouée (pas de confirmation à temps).
        $pdo->prepare("UPDATE notifications SET statut = 'echouee' WHERE id = ?")->execute([$n['id']]);

        // Le candidat libère sa place : désistement automatique enregistré.
        $pdo->prepare("INSERT INTO desistements (motif, agent_id, filiere_id)
                       VALUES ('Non-confirmation (automatique)', NULL, ?)")
            ->execute([$filiereId]);
        retirerInscription($pdo, (int) $n['insc_id']);

        // On referme les trous de rang en liste principale.
        [$idPrincipale] = listesFiliere($pdo, $filiereId);
        if ($idPrincipale) {
            recalculerRangs($pdo, $idPrincipale);
            majEffectif($pdo, $idPrincipale);
        }

        // Trace de l'alerte pour l'agent.
        $pdo->prepare("INSERT INTO historiques (operation, cible, acteur_id)
                       VALUES ('Expiration sans confirmation', ?, NULL)")
            ->execute(['Candidat ' . $n['prenom'] . ' ' . $n['nom']]);

        // On promeut automatiquement le candidat suivant de la liste d'attente.
        $suivant = promouvoirPremierAttente($pdo, $filiereId, null);

        $expirations++;
        if ($suivant) {
            $details[] = "Place de " . $n['prenom'] . " " . $n['nom']
                       . " expirée → " . $suivant['prenom'] . " " . $suivant['nom'] . " promu(e).";
        } else {
            $details[] = "Place de " . $n['prenom'] . " " . $n['nom']
                       . " expirée → liste d'attente vide, place vacante.";
        }
    }

    return ['relances' => $relances, 'expirations' => $expirations, 'details' => $details];
}
