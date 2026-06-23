<?php
/**
 * Petit client SMTP autonome (sans dépendance, sans Composer).
 *
 * Utilise l'extension openssl déjà présente dans XAMPP pour dialoguer
 * directement avec le serveur d'envoi (Gmail par défaut).
 *
 * Fonction principale : envoyerEmail($destinataire, $sujet, $corpsHtml)
 *   -> renvoie true si l'e-mail est parti, false sinon.
 *   -> en cas d'échec, le détail est écrit dans logs/emails.log.
 */

/**
 * Envoie un e-mail (HTML). Selon la configuration (config/mail.php) :
 *   - mode 'smtp' : envoi réel via le serveur SMTP configuré ;
 *   - mode 'log'  : aucun envoi, l'e-mail est seulement écrit dans logs/emails.log.
 */
function envoyerEmail(string $destinataire, string $sujet, string $corpsHtml): bool
{
    $config = require __DIR__ . '/../config/mail.php';

    // Mode développement : on n'envoie rien, on journalise.
    if (($config['mode'] ?? 'smtp') === 'log') {
        journaliserEmail("MODE LOG (non envoyé)", $destinataire, $sujet, $corpsHtml);
        return true;
    }

    try {
        envoyerViaSmtp($config, $destinataire, $sujet, $corpsHtml);
        journaliserEmail("ENVOYÉ", $destinataire, $sujet, "(corps HTML)");
        return true;
    } catch (Exception $e) {
        journaliserEmail("ÉCHEC : " . $e->getMessage(), $destinataire, $sujet, $corpsHtml);
        return false;
    }
}

/**
 * Dialogue SMTP complet : connexion, éventuel STARTTLS, authentification,
 * puis envoi du message. Lève une Exception au moindre code de réponse inattendu.
 */
function envoyerViaSmtp(array $config, string $destinataire, string $sujet, string $corpsHtml): void
{
    $hote     = $config['hote'];
    $port     = (int) $config['port'];
    $securite = $config['securite'] ?? 'tls';

    // Pour le port 465, la connexion est chiffrée dès le départ (ssl://).
    $prefixe = ($securite === 'ssl') ? 'ssl://' : 'tcp://';

    $contexte = stream_context_create([
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);

    $fp = @stream_socket_client(
        $prefixe . $hote . ':' . $port,
        $noErreur, $messageErreur, 15, STREAM_CLIENT_CONNECT, $contexte
    );
    if (!$fp) {
        throw new Exception("Connexion impossible à $hote:$port ($messageErreur)");
    }
    stream_set_timeout($fp, 15);

    lireReponseSmtp($fp, 220);

    $domaine = $config['expediteur_email'] ?? 'localhost';
    $domaine = substr(strrchr($domaine, "@") ?: "@localhost", 1);

    envoyerCommandeSmtp($fp, "EHLO $domaine", 250);

    // STARTTLS pour le port 587 : on chiffre la connexion après le EHLO initial.
    if ($securite === 'tls') {
        envoyerCommandeSmtp($fp, "STARTTLS", 220);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception("Activation du chiffrement TLS impossible");
        }
        envoyerCommandeSmtp($fp, "EHLO $domaine", 250);
    }

    // Authentification (AUTH LOGIN : identifiants encodés en base64).
    // On retire les espaces du mot de passe d'application Gmail : il est affiché
    // par groupes de 4 (« abcd efgh ... ») mais doit être transmis collé.
    $motDePasse = str_replace(' ', '', $config['mot_de_passe']);
    envoyerCommandeSmtp($fp, "AUTH LOGIN", 334);
    envoyerCommandeSmtp($fp, base64_encode($config['utilisateur']), 334);
    envoyerCommandeSmtp($fp, base64_encode($motDePasse), 235);

    $deEmail = $config['expediteur_email'];
    $deNom   = $config['expediteur_nom'] ?? '';

    envoyerCommandeSmtp($fp, "MAIL FROM:<$deEmail>", 250);
    envoyerCommandeSmtp($fp, "RCPT TO:<$destinataire>", 250);
    envoyerCommandeSmtp($fp, "DATA", 354);

    // En-têtes + corps. Les lignes commençant par "." sont protégées (point doublé).
    $entetes  = "From: =?UTF-8?B?" . base64_encode($deNom) . "?= <$deEmail>\r\n";
    $entetes .= "To: <$destinataire>\r\n";
    $entetes .= "Subject: =?UTF-8?B?" . base64_encode($sujet) . "?=\r\n";
    $entetes .= "MIME-Version: 1.0\r\n";
    $entetes .= "Content-Type: text/html; charset=UTF-8\r\n";
    $entetes .= "Date: " . date('r') . "\r\n";

    $corps = preg_replace('/^\./m', '..', $corpsHtml);
    fwrite($fp, $entetes . "\r\n" . $corps . "\r\n.\r\n");
    lireReponseSmtp($fp, 250);

    envoyerCommandeSmtp($fp, "QUIT", 221);
    fclose($fp);
}

/** Envoie une commande SMTP et vérifie le code de réponse attendu. */
function envoyerCommandeSmtp($fp, string $commande, int $codeAttendu): void
{
    fwrite($fp, $commande . "\r\n");
    lireReponseSmtp($fp, $codeAttendu);
}

/** Lit la réponse SMTP (gère les réponses multi-lignes) et contrôle le code. */
function lireReponseSmtp($fp, int $codeAttendu): string
{
    $reponse = '';
    while ($ligne = fgets($fp, 515)) {
        $reponse .= $ligne;
        // Une réponse multi-lignes a un '-' en 4e caractère ; la dernière a un espace.
        if (isset($ligne[3]) && $ligne[3] === ' ') {
            break;
        }
    }
    $code = (int) substr($reponse, 0, 3);
    if ($code !== $codeAttendu) {
        throw new Exception("Réponse SMTP inattendue (attendu $codeAttendu) : " . trim($reponse));
    }
    return $reponse;
}

/** Écrit une trace dans logs/emails.log (créé le dossier si besoin). */
function journaliserEmail(string $statut, string $destinataire, string $sujet, string $corps): void
{
    $dossier = __DIR__ . '/../logs';
    if (!is_dir($dossier)) {
        @mkdir($dossier, 0777, true);
    }
    $ligne = "[" . date('Y-m-d H:i:s') . "] [$statut] À : $destinataire | Sujet : $sujet\n";
    $ligne .= $corps . "\n" . str_repeat('-', 60) . "\n";
    @file_put_contents($dossier . '/emails.log', $ligne, FILE_APPEND);
}

/**
 * Gabarit HTML commun à tous les e-mails de l'application.
 * Donne une présentation propre et cohérente autour du contenu fourni.
 */
function gabaritEmail(string $titre, string $contenuHtml): string
{
    return '
    <div style="font-family: Arial, sans-serif; max-width: 560px; margin: auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;">
        <div style="background-color: #0d6efd; color: #ffffff; padding: 20px 24px;">
            <h2 style="margin: 0; font-size: 18px;">ESP Dakar — Listes d\'attente</h2>
        </div>
        <div style="padding: 24px; color: #333333; line-height: 1.6;">
            <h3 style="margin-top: 0; color: #0d6efd;">' . htmlspecialchars($titre) . '</h3>
            ' . $contenuHtml . '
        </div>
        <div style="background-color: #f8f9fa; color: #888888; padding: 14px 24px; font-size: 12px; text-align: center;">
            Message automatique — École Supérieure Polytechnique de Dakar
        </div>
    </div>';
}
