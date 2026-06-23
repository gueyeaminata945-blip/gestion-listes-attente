<?php
/**
 * Fonctions utilitaires pour les codes de vérification à 6 chiffres
 * envoyés par e-mail (changement et réinitialisation de mot de passe).
 */

require_once __DIR__ . '/mailer.php';

/** Génère un code aléatoire à 6 chiffres (ex : "048213"). */
function genererCode(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Crée un code en base pour un utilisateur, après avoir invalidé ses
 * éventuels codes encore valides du même type.
 *
 * @param string|null $nouveauHash  hash du futur mot de passe (cas 'changement'),
 *                                   ou null (cas 'reinitialisation', défini plus tard).
 * @return string  le code généré.
 */
function creerCodeVerification(PDO $pdo, int $utilisateurId, string $type, ?string $nouveauHash = null): string
{
    $config = require __DIR__ . '/../config/mail.php';
    $duree  = (int) ($config['duree_code_minutes'] ?? 15);

    // On invalide les anciens codes non utilisés du même type.
    $req = $pdo->prepare("UPDATE codes_verification SET utilise = 1
                          WHERE utilisateur_id = ? AND type = ? AND utilise = 0");
    $req->execute([$utilisateurId, $type]);

    $code = genererCode();
    $req = $pdo->prepare("INSERT INTO codes_verification
            (utilisateur_id, code, type, nouveau_hash, expire_le)
            VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))");
    $req->execute([$utilisateurId, $code, $type, $nouveauHash, $duree]);

    return $code;
}

/**
 * Vérifie un code : doit exister, correspondre au type, ne pas être utilisé
 * ni expiré. Renvoie la ligne du code si valide, sinon false.
 */
function verifierCodeVerification(PDO $pdo, int $utilisateurId, string $code, string $type)
{
    $req = $pdo->prepare("SELECT * FROM codes_verification
            WHERE utilisateur_id = ? AND code = ? AND type = ?
              AND utilise = 0 AND expire_le >= NOW()
            ORDER BY id DESC LIMIT 1");
    $req->execute([$utilisateurId, $code, $type]);
    return $req->fetch();
}

/** Marque un code comme utilisé (à appeler après validation réussie). */
function marquerCodeUtilise(PDO $pdo, int $codeId): void
{
    $pdo->prepare("UPDATE codes_verification SET utilise = 1 WHERE id = ?")->execute([$codeId]);
}

/** Envoie le code de vérification par e-mail à l'utilisateur. */
function envoyerCodeParEmail(string $email, string $prenom, string $code, string $contexte): bool
{
    $config = require __DIR__ . '/../config/mail.php';
    $duree  = (int) ($config['duree_code_minutes'] ?? 15);

    $contenu = "
        <p>Bonjour " . htmlspecialchars($prenom) . ",</p>
        <p>Vous avez demandé <strong>" . htmlspecialchars($contexte) . "</strong>.</p>
        <p>Votre code de vérification est :</p>
        <p style=\"font-size: 30px; font-weight: bold; letter-spacing: 6px; color: #0d6efd; text-align: center; margin: 24px 0;\">
            " . htmlspecialchars($code) . "
        </p>
        <p>Ce code est valable pendant <strong>" . $duree . " minutes</strong>.</p>
        <p style=\"color: #888; font-size: 13px;\">Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet e-mail.</p>
    ";

    return envoyerEmail($email, "Code de vérification — ESP Listes d'attente",
                        gabaritEmail("Code de vérification", $contenu));
}
