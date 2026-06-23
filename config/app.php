<?php
/**
 * Configuration générale de l'application (non sensible — versionnée sur Git).
 * Les identifiants e-mail, eux, sont dans config/mail.php (non versionné).
 */

return [
    // URL de base de l'application. Sert à construire les liens des e-mails
    // (notamment le lien de confirmation de réception). À adapter en production.
    'url_base' => 'http://localhost/Gestion_Liste_attente',

    // --- Relances automatiques (BM-05) ---
    // Délai (en minutes) sans confirmation avant d'envoyer une relance au candidat.
    'delai_relance_minutes' => 1440,      // 24 h

    // Délai (en minutes) sans confirmation avant d'abandonner et de passer
    // automatiquement au candidat suivant de la liste d'attente.
    'delai_expiration_minutes' => 4320,   // 72 h

    // Astuce démo : pour tester rapidement, mets des petites valeurs
    // (ex. 1 et 2 minutes) puis déclenche les relances via cron/relances.php.
];
