<?php
/**
 * Logique partagée de promotion d'un candidat depuis la liste d'attente
 * vers la liste principale, avec notification e-mail (BM-04 / BM-05).
 *
 * Utilisée à la fois par le traitement manuel d'un désistement
 * (pages/traiter_desistement.php) et par la relance automatique
 * (includes/relances.php).
 */

require_once __DIR__ . '/mailer.php';

/** URL de base de l'application (pour construire les liens des e-mails). */
function urlBaseApp(): string
{
    $config = require __DIR__ . '/../config/app.php';
    return rtrim($config['url_base'], '/');
}

/** Renvoie [idListePrincipale, idListeAttente] pour une filière donnée. */
function listesFiliere(PDO $pdo, int $filiereId): array
{
    $req = $pdo->prepare("SELECT id, type FROM listes WHERE filiere_id = ?");
    $req->execute([$filiereId]);
    $idP = null; $idA = null;
    foreach ($req->fetchAll() as $l) {
        if ($l['type'] === 'principale') $idP = (int) $l['id'];
        if ($l['type'] === 'attente')    $idA = (int) $l['id'];
    }
    return [$idP, $idA];
}

/**
 * Retire une inscription (candidat retiré d'une liste). Détache au préalable
 * les notifications qui la référencent pour respecter la clé étrangère
 * (un candidat promu puis retiré possède une notification liée à son inscription).
 */
function retirerInscription(PDO $pdo, int $inscriptionId): void
{
    $pdo->prepare("UPDATE notifications SET inscription_id = NULL WHERE inscription_id = ?")
        ->execute([$inscriptionId]);
    $pdo->prepare("UPDATE demandes_desistement SET inscription_id = NULL WHERE inscription_id = ?")
        ->execute([$inscriptionId]);
    $pdo->prepare("DELETE FROM inscriptions WHERE id = ?")->execute([$inscriptionId]);
}

/** Renumérote les rangs d'une liste de 1 à N (supprime les trous). */
function recalculerRangs(PDO $pdo, int $listeId): void
{
    $req = $pdo->prepare("SELECT id FROM inscriptions WHERE liste_id = ? ORDER BY rang ASC");
    $req->execute([$listeId]);
    $rang = 1;
    foreach ($req->fetchAll() as $r) {
        $pdo->prepare("UPDATE inscriptions SET rang = ? WHERE id = ?")->execute([$rang, $r['id']]);
        $rang++;
    }
}

/** Met à jour le compteur d'effectif (nombre_etudiants) d'une liste. */
function majEffectif(PDO $pdo, int $listeId): void
{
    $req = $pdo->prepare("SELECT COUNT(*) FROM inscriptions WHERE liste_id = ?");
    $req->execute([$listeId]);
    $pdo->prepare("UPDATE listes SET nombre_etudiants = ? WHERE id = ?")
        ->execute([(int) $req->fetchColumn(), $listeId]);
}

/**
 * Crée une notification d'admission et envoie l'e-mail correspondant au candidat.
 * Renvoie true si l'e-mail est parti, false sinon (statut passé à « echouee »).
 */
function envoyerNotificationAdmission(PDO $pdo, array $promu, int $inscriptionId, int $filiereId): bool
{
    $token = bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO notifications (type, inscription_id, token) VALUES ('admission', ?, ?)")
        ->execute([$inscriptionId, $token]);
    $notificationId = (int) $pdo->lastInsertId();

    if (empty($promu['email'])) {
        return false;
    }

    $req = $pdo->prepare("SELECT nom FROM filieres WHERE id = ?");
    $req->execute([$filiereId]);
    $nomFiliere = $req->fetchColumn() ?: 'votre filière';

    $lien = urlBaseApp() . "/pages/confirmation.php?token=" . $token;
    $contenu = "
        <p>Bonjour " . htmlspecialchars($promu['prenom'] . ' ' . $promu['nom']) . ",</p>
        <p>Bonne nouvelle ! Suite à un désistement, vous êtes désormais
           <strong>admis(e) en liste principale</strong> pour la filière
           <strong>" . htmlspecialchars($nomFiliere) . "</strong>.</p>
        <p>Merci de <strong>confirmer la réception</strong> de cette convocation :</p>
        " . boutonEmail($lien, "Confirmer ma réception") . "
        <p style=\"color:#888; font-size:13px;\">Sans confirmation de votre part dans le délai imparti,
           votre place pourra être proposée au candidat suivant.</p>
    ";

    $ok = envoyerEmail($promu['email'], "Admission en liste principale — ESP Dakar",
                       gabaritEmail("Vous êtes admis(e) !", $contenu));
    if (!$ok) {
        $pdo->prepare("UPDATE notifications SET statut = 'echouee' WHERE id = ?")->execute([$notificationId]);
    }
    return $ok;
}

/**
 * Construit un bouton HTML « bulletproof » (compatible Gmail) + le lien en texte.
 */
function boutonEmail(string $url, string $libelle): string
{
    return "
        <table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" style=\"margin:24px auto;\">
            <tr><td align=\"center\" bgcolor=\"#0d6efd\" style=\"border-radius:6px;\">
                <a href=\"" . $url . "\" style=\"display:inline-block; padding:12px 28px;
                   font-family:Arial,sans-serif; font-size:15px; color:#ffffff;
                   text-decoration:none; font-weight:bold;\">" . htmlspecialchars($libelle) . "</a>
            </td></tr>
        </table>
        <p style=\"font-size:13px; text-align:center;\">
            Si le bouton ne s'affiche pas, copiez ce lien :<br>
            <a href=\"" . $url . "\">" . $url . "</a>
        </p>";
}

/**
 * Promeut le premier candidat de la liste d'attente d'une filière vers la liste
 * principale, recalcule les rangs et les effectifs, envoie la notification et
 * trace l'opération.
 *
 * @param int|null $agentId  agent à l'origine (null = action automatique du système).
 * @return array|null  infos du promu (nom, prenom, email, notifie) ou null si la
 *                      liste d'attente est vide.
 */
function promouvoirPremierAttente(PDO $pdo, int $filiereId, ?int $agentId): ?array
{
    [$idPrincipale, $idAttente] = listesFiliere($pdo, $filiereId);
    if (!$idPrincipale || !$idAttente) {
        return null;
    }

    // Premier candidat de la liste d'attente.
    $req = $pdo->prepare("SELECT id, candidat_id FROM inscriptions WHERE liste_id = ? ORDER BY rang ASC LIMIT 1");
    $req->execute([$idAttente]);
    $premier = $req->fetch();
    if (!$premier) {
        return null;
    }

    // Nouveau rang en liste principale = dernier rang + 1.
    $req = $pdo->prepare("SELECT COALESCE(MAX(rang), 0) + 1 FROM inscriptions WHERE liste_id = ?");
    $req->execute([$idPrincipale]);
    $nouveauRang = (int) $req->fetchColumn();

    $pdo->prepare("UPDATE inscriptions SET liste_id = ?, rang = ? WHERE id = ?")
        ->execute([$idPrincipale, $nouveauRang, $premier['id']]);

    recalculerRangs($pdo, $idAttente);
    recalculerRangs($pdo, $idPrincipale);
    majEffectif($pdo, $idAttente);
    majEffectif($pdo, $idPrincipale);

    // Infos du candidat promu + envoi de la notification.
    $req = $pdo->prepare("SELECT nom, prenom, email FROM utilisateurs WHERE id = ?");
    $req->execute([$premier['candidat_id']]);
    $promu = $req->fetch();

    $notifie = envoyerNotificationAdmission($pdo, $promu, (int) $premier['id'], $filiereId);

    // Traçabilité (BNF-05). acteur_id = NULL pour une promotion automatique.
    $pdo->prepare("INSERT INTO historiques (operation, cible, acteur_id) VALUES ('Promotion + notification', ?, ?)")
        ->execute(['Candidat ' . $promu['prenom'] . ' ' . $promu['nom'], $agentId]);

    return [
        'nom'     => $promu['nom'],
        'prenom'  => $promu['prenom'],
        'email'   => $promu['email'],
        'notifie' => $notifie,
    ];
}

/** Renvoie le type de liste ('principale' ou 'attente') d'une inscription, ou null. */
function typeListeInscription(PDO $pdo, int $inscriptionId): ?string
{
    $req = $pdo->prepare("SELECT l.type FROM inscriptions i JOIN listes l ON i.liste_id = l.id WHERE i.id = ?");
    $req->execute([$inscriptionId]);
    return $req->fetchColumn() ?: null;
}

/**
 * Enregistre un désistement pour une inscription (liste principale OU attente) :
 * trace l'opération, retire le candidat, recalcule les rangs, et — si la place
 * était en liste principale — promeut automatiquement le premier en attente.
 *
 * @return array{type:string, promu:?array}|null  null si l'inscription est introuvable.
 */
function enregistrerDesistement(PDO $pdo, int $inscriptionId, int $filiereId, ?int $agentId, string $motif = 'Désistement enregistré'): ?array
{
    $type = typeListeInscription($pdo, $inscriptionId);
    if ($type === null) {
        return null; // déjà retiré entre-temps
    }

    [$idPrincipale, $idAttente] = listesFiliere($pdo, $filiereId);

    // Trace du désistement (rattaché à la filière pour les statistiques).
    $pdo->prepare("INSERT INTO desistements (motif, agent_id, filiere_id) VALUES (?, ?, ?)")
        ->execute([$motif, $agentId, $filiereId]);

    // Retrait du candidat de sa liste.
    retirerInscription($pdo, $inscriptionId);

    $promu = null;
    if ($type === 'principale') {
        if ($idPrincipale) { recalculerRangs($pdo, $idPrincipale); majEffectif($pdo, $idPrincipale); }
        // Une place se libère en principale -> promotion du premier de la liste d'attente.
        $promu = promouvoirPremierAttente($pdo, $filiereId, $agentId);
    } elseif ($idAttente) {
        // Désistement depuis la liste d'attente : pas de promotion, juste renumérotation.
        recalculerRangs($pdo, $idAttente);
        majEffectif($pdo, $idAttente);
    }

    return ['type' => $type, 'promu' => $promu];
}

/**
 * Notifie par e-mail les agents du département concerné qu'un candidat a demandé
 * à se désister. Repli sur tous les agents/admins si aucun agent pour ce département.
 *
 * @return int  nombre d'e-mails envoyés.
 */
function notifierAgentsDesistement(PDO $pdo, array $infos): int
{
    $req = $pdo->prepare("SELECT email, prenom FROM utilisateurs
                          WHERE role = 'agent' AND departement = ? AND email IS NOT NULL AND email <> ''");
    $req->execute([$infos['departement']]);
    $agents = $req->fetchAll();

    if (!$agents) {
        $agents = $pdo->query("SELECT email, prenom FROM utilisateurs
                               WHERE role IN ('agent', 'administrateur') AND email IS NOT NULL AND email <> ''")->fetchAll();
    }

    $lien = urlBaseApp() . "/pages/demandes_desistement.php";
    $contenu = "
        <p>Bonjour,</p>
        <p>Le candidat <strong>" . htmlspecialchars($infos['prenom'] . ' ' . $infos['nom'])
        . "</strong> (n° " . htmlspecialchars($infos['numero']) . ") demande à <strong>se désister</strong>
           de la filière <strong>" . htmlspecialchars($infos['filiere']) . "</strong>.</p>"
        . ($infos['motif'] !== '' ? "<p><em>Motif : " . htmlspecialchars($infos['motif']) . "</em></p>" : "")
        . "<p>Merci de traiter cette demande dans l'application :</p>"
        . boutonEmail($lien, "Voir les demandes de désistement");

    $n = 0;
    foreach ($agents as $a) {
        if (envoyerEmail($a['email'], "Demande de désistement — ESP Dakar",
                         gabaritEmail("Nouvelle demande de désistement", $contenu))) {
            $n++;
        }
    }
    return $n;
}
