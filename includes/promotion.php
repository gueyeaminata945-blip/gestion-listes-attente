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
