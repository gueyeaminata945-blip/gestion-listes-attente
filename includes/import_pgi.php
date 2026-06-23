<?php
/**
 * Fonctions de traitement de l'import des candidats (BM-03).
 * Lecture d'un fichier CSV exporté depuis le PGI / Excel, sans dépendance externe.
 */

/**
 * Normalise un nom de colonne : minuscules, sans accents ni espaces,
 * pour reconnaître les en-têtes quelle que soit leur écriture exacte.
 */
function normaliserEntete(string $valeur): string
{
    $valeur = mb_strtolower(trim($valeur), 'UTF-8');
    $remplacements = ['é','è','ê','ë','à','â','î','ï','ô','û','ù','ç',' ','-','_','°','.'];
    $par           = ['e','e','e','e','a','a','i','i','o','u','u','c','','','','','',''];
    return str_replace($remplacements, $par, $valeur);
}

/** Associe un en-tête normalisé au nom de champ interne, ou null si inconnu. */
function champPourEntete(string $entete): ?string
{
    $entete = normaliserEntete($entete);
    $carte = [
        'numerocandidat' => 'numero_candidat', 'numero' => 'numero_candidat', 'ncandidat' => 'numero_candidat',
        'nom'      => 'nom',
        'prenom'   => 'prenom',
        'email'    => 'email', 'mail' => 'email', 'courriel' => 'email',
        'telephone'=> 'telephone', 'tel' => 'telephone', 'portable' => 'telephone',
        'datenaissance' => 'date_naissance', 'naissance' => 'date_naissance', 'datedenaissance' => 'date_naissance',
        'rang'     => 'rang',
    ];
    return $carte[$entete] ?? null;
}

/**
 * Convertit une date saisie (JJ/MM/AAAA ou AAAA-MM-JJ) au format SQL AAAA-MM-JJ.
 * Renvoie null si la valeur est vide ou non reconnue.
 */
function convertirDate(string $valeur): ?string
{
    $valeur = trim($valeur);
    if ($valeur === '') return null;

    if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $valeur, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})$#', $valeur, $m)) {
        return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    }
    return null;
}

/**
 * Détecte le séparateur le plus probable d'une ligne CSV (';' ou ',' ou tabulation).
 */
function detecterSeparateur(string $ligne): string
{
    $candidats = [';' => substr_count($ligne, ';'),
                  ',' => substr_count($ligne, ','),
                  "\t" => substr_count($ligne, "\t")];
    arsort($candidats);
    return array_key_first($candidats) ?: ';';
}

/**
 * Lit le fichier CSV et renvoie un tableau de lignes associatives
 * (champ interne => valeur). Lève une Exception en cas de fichier illisible
 * ou de colonnes obligatoires manquantes.
 *
 * @return array{0: array<int, array<string,string>>, 1: string[]} [lignes, avertissements]
 */
function lireFichierCsv(string $chemin): array
{
    $contenu = file_get_contents($chemin);
    if ($contenu === false) {
        throw new Exception("Impossible de lire le fichier.");
    }

    // Conversion en UTF-8 si le fichier vient d'Excel (souvent Windows-1252).
    if (!mb_check_encoding($contenu, 'UTF-8')) {
        $contenu = mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
    }
    // Retrait d'un éventuel BOM en début de fichier.
    $contenu = preg_replace('/^\xEF\xBB\xBF/', '', $contenu);

    $lignesBrutes = preg_split('/\r\n|\r|\n/', trim($contenu));
    if (!$lignesBrutes || count($lignesBrutes) < 2) {
        throw new Exception("Le fichier doit contenir une ligne d'en-tête et au moins un candidat.");
    }

    $separateur = detecterSeparateur($lignesBrutes[0]);

    // En-têtes -> position de chaque champ interne.
    $entetes = str_getcsv($lignesBrutes[0], $separateur);
    $positions = [];
    foreach ($entetes as $i => $entete) {
        $champ = champPourEntete($entete);
        if ($champ !== null) {
            $positions[$champ] = $i;
        }
    }

    foreach (['numero_candidat', 'nom', 'prenom'] as $obligatoire) {
        if (!isset($positions[$obligatoire])) {
            throw new Exception("Colonne obligatoire manquante : « $obligatoire ». "
                . "Vérifiez la ligne d'en-tête (voir le modèle CSV).");
        }
    }

    $lignes = [];
    $avertissements = [];
    for ($n = 1; $n < count($lignesBrutes); $n++) {
        if (trim($lignesBrutes[$n]) === '') continue;
        $cellules = str_getcsv($lignesBrutes[$n], $separateur);

        $ligne = [];
        foreach ($positions as $champ => $pos) {
            $ligne[$champ] = isset($cellules[$pos]) ? trim($cellules[$pos]) : '';
        }

        if ($ligne['numero_candidat'] === '' || $ligne['nom'] === '' || $ligne['prenom'] === '') {
            $avertissements[] = "Ligne " . ($n + 1) . " ignorée : numéro, nom ou prénom manquant.";
            continue;
        }
        $lignes[] = $ligne;
    }

    return [$lignes, $avertissements];
}

/**
 * Importe les candidats d'un fichier CSV dans une liste donnée.
 * Crée les candidats absents (par numéro), met à jour ceux qui existent,
 * puis crée/actualise leur inscription dans la liste cible.
 *
 * @return array{importes:int, crees:int, maj:int, avertissements:string[]}
 */
function importerCandidats(PDO $pdo, string $chemin, int $listeId, int $acteurId): array
{
    [$lignes, $avertissements] = lireFichierCsv($chemin);

    $importes = 0; $crees = 0; $maj = 0;

    $pdo->beginTransaction();
    try {
        // Rang automatique si la colonne « rang » est absente : on part du max existant.
        $req = $pdo->prepare("SELECT COALESCE(MAX(rang), 0) FROM inscriptions WHERE liste_id = ?");
        $req->execute([$listeId]);
        $rangAuto = (int) $req->fetchColumn();

        foreach ($lignes as $ligne) {
            // 1) Candidat : recherche par numéro, sinon création.
            $req = $pdo->prepare("SELECT id FROM utilisateurs WHERE numero_candidat = ? AND role = 'candidat'");
            $req->execute([$ligne['numero_candidat']]);
            $candidatId = $req->fetchColumn();

            $dateNaiss = isset($ligne['date_naissance']) ? convertirDate($ligne['date_naissance']) : null;
            $email     = $ligne['email']     ?? '';
            $telephone = $ligne['telephone'] ?? '';

            if ($candidatId) {
                $req = $pdo->prepare("UPDATE utilisateurs
                        SET nom = ?, prenom = ?, email = ?, telephone = ?, date_naissance = ?
                        WHERE id = ?");
                $req->execute([$ligne['nom'], $ligne['prenom'], $email, $telephone, $dateNaiss, $candidatId]);
                $maj++;
            } else {
                $req = $pdo->prepare("INSERT INTO utilisateurs
                        (nom, prenom, email, telephone, mot_de_passe, role, numero_candidat, date_naissance)
                        VALUES (?, ?, ?, ?, '-', 'candidat', ?, ?)");
                $req->execute([$ligne['nom'], $ligne['prenom'], $email, $telephone,
                               $ligne['numero_candidat'], $dateNaiss]);
                $candidatId = $pdo->lastInsertId();
                $crees++;
            }

            // 2) Rang : valeur du fichier si présente et valide, sinon incrément automatique.
            if (!empty($ligne['rang']) && ctype_digit($ligne['rang'])) {
                $rang = (int) $ligne['rang'];
            } else {
                $rang = ++$rangAuto;
            }

            // 3) Inscription : mise à jour si le candidat est déjà dans cette liste, sinon insertion.
            $req = $pdo->prepare("SELECT id FROM inscriptions WHERE candidat_id = ? AND liste_id = ?");
            $req->execute([$candidatId, $listeId]);
            $inscriptionId = $req->fetchColumn();

            if ($inscriptionId) {
                $pdo->prepare("UPDATE inscriptions SET rang = ? WHERE id = ?")
                    ->execute([$rang, $inscriptionId]);
            } else {
                $pdo->prepare("INSERT INTO inscriptions (rang, date_inscription, candidat_id, liste_id)
                               VALUES (?, CURDATE(), ?, ?)")
                    ->execute([$rang, $candidatId, $listeId]);
            }
            $importes++;
        }

        // 4) Effectif de la liste.
        $req = $pdo->prepare("SELECT COUNT(*) FROM inscriptions WHERE liste_id = ?");
        $req->execute([$listeId]);
        $effectif = (int) $req->fetchColumn();
        $pdo->prepare("UPDATE listes SET nombre_etudiants = ? WHERE id = ?")
            ->execute([$effectif, $listeId]);

        // 5) Traçabilité (BNF-05).
        $pdo->prepare("INSERT INTO historiques (operation, cible, acteur_id)
                       VALUES (?, ?, ?)")
            ->execute(["Import de $importes candidat(s) (PGI)", "Liste #$listeId", $acteurId]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    return ['importes' => $importes, 'crees' => $crees, 'maj' => $maj, 'avertissements' => $avertissements];
}
