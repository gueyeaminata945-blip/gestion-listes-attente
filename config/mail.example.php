<?php
/**
 * Configuration de l'envoi des e-mails (SMTP Gmail).
 *
 * --- COMMENT OBTENIR UN MOT DE PASSE D'APPLICATION GMAIL ---
 * 1. Active la validation en 2 étapes sur le compte Google :
 *    https://myaccount.google.com/security
 * 2. Crée un « mot de passe d'application » (rubrique « Mots de passe des applications »).
 *    Google te donne un code de 16 lettres : c'est lui qu'on met dans 'mot_de_passe'.
 *    (Ce n'est PAS le mot de passe habituel de la boîte Gmail.)
 *
 * Astuce : pendant le développement, mets 'mode' => 'log' pour écrire les e-mails
 * dans logs/emails.log au lieu de les envoyer réellement.
 */

return [
    // 'smtp' = envoi réel via Gmail ; 'log' = écriture dans un fichier (aucun envoi réel)
    'mode' => 'smtp',

    'hote'        => 'smtp.gmail.com',
    'port'        => 587,            // 587 = STARTTLS (tls) | 465 = SSL (ssl)
    'securite'    => 'tls',          // 'tls' pour le port 587, 'ssl' pour le port 465

    'utilisateur' => 'ton.adresse@gmail.com',   // l'adresse Gmail expéditrice
    'mot_de_passe'=> 'xxxx xxxx xxxx xxxx',      // le MOT DE PASSE D'APPLICATION (16 lettres)

    'expediteur_email' => 'ton.adresse@gmail.com',
    'expediteur_nom'   => "ESP Dakar - Listes d'attente",

    // Délai de validité d'un code de vérification, en minutes
    'duree_code_minutes' => 15,
];
