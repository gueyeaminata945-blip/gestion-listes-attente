<?php
/**
 * Connexion à la base de données.
 *
 * Chaque machine (XAMPP, MAMP, MySQL Linux…) a des identifiants MySQL
 * différents. Pour que l'application marche partout SANS modifier ce fichier,
 * on essaie automatiquement plusieurs identifiants courants jusqu'à ce que
 * l'un fonctionne.
 *
 * Si AUCUN ne marche (cas d'un MySQL où root est protégé — erreur 1698),
 * créez une fois un utilisateur dédié dans phpMyAdmin (onglet SQL) :
 *
 *     CREATE USER 'esp'@'localhost' IDENTIFIED BY 'esp123';
 *     GRANT ALL PRIVILEGES ON gestion_listes_attente.* TO 'esp'@'localhost';
 *     FLUSH PRIVILEGES;
 */

$hote    = "localhost";
$nomBase = "gestion_listes_attente";

// Liste des identifiants essayés, dans l'ordre : [utilisateur, mot de passe].
$identifiants = [
    ['root', ''],        // XAMPP (Windows) — par défaut
    ['root', 'root'],    // MAMP (Mac) — par défaut
    ['esp',  'esp123'],  // utilisateur dédié (à créer si root est protégé)
];

$pdo = null;
$derniereErreur = '';

foreach ($identifiants as [$utilisateur, $motDePasse]) {
    try {
        $pdo = new PDO("mysql:host=$hote;dbname=$nomBase;charset=utf8mb4", $utilisateur, $motDePasse);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        break; // connexion réussie
    } catch (PDOException $e) {
        $derniereErreur = $e->getMessage();
    }
}

if (!$pdo) {
    die("Erreur de connexion à la base de données.<br>"
        . "Vérifiez que <strong>MySQL est démarré</strong> et que la base "
        . "<strong>gestion_listes_attente</strong> a bien été importée.<br>"
        . "Si l'erreur mentionne « Access denied » (1698), créez l'utilisateur "
        . "<code>esp</code> (voir les instructions en haut de config/database.php).<br>"
        . "<small>Détail technique : " . htmlspecialchars($derniereErreur) . "</small>");
}
?>
