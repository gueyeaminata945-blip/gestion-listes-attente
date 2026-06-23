<?php
/**
 * Script de relances automatiques (BM-05).
 *
 * À exécuter périodiquement pour traiter les relances et expirations dues.
 *
 *  - En ligne de commande :   php cron/relances.php
 *  - Dans le navigateur :      http://localhost/Gestion_Liste_attente/cron/relances.php
 *
 * --- PLANIFICATION AUTOMATIQUE (Windows) ---
 * Ouvre le « Planificateur de tâches » et crée une tâche qui lance, par ex.
 * toutes les heures :
 *     Programme : C:\xampp\php\php.exe
 *     Arguments : C:\xampp\htdocs\Gestion_Liste_attente\cron\relances.php
 */

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/relances.php';

$resultat = traiterRelances($pdo);

$enLigneCommande = (php_sapi_name() === 'cli');
$saut = $enLigneCommande ? "\n" : "<br>";

if (!$enLigneCommande) {
    header('Content-Type: text/html; charset=UTF-8');
    echo "<pre style='font-family:monospace'>";
}

echo "[" . date('Y-m-d H:i:s') . "] Traitement des relances" . $saut;
echo "Relances envoyées   : " . $resultat['relances'] . $saut;
echo "Places expirées     : " . $resultat['expirations'] . $saut;
foreach ($resultat['details'] as $d) {
    echo "  - " . $d . $saut;
}
if (!$resultat['relances'] && !$resultat['expirations']) {
    echo "Rien à traiter pour le moment." . $saut;
}

if (!$enLigneCommande) {
    echo "</pre>";
}
