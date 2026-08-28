<?php
// ============================================================
//  config.php — Connexion à la base de données MySQL
//  À inclure en haut de CHAQUE fichier PHP qui a besoin de la BDD.
//  On le fait UNE seule fois ici, et on réutilise partout.
// ============================================================

// ── Paramètres de connexion ──────────────────────────────────
// Ces 4 variables définissent "où est la base" et "comment s'y connecter".

// Adresse du serveur MySQL.
// "localhost" = sur la même machine (XAMPP tourne sur ton PC).
// Si la BDD était sur un serveur distant, tu mettrais son IP ici.
$hote = "localhost";

// Nom de la base de données qu'on veut utiliser.
// Doit correspondre exactement au nom créé dans le fichier SQL.
$base = "shopflow";

// Nom d'utilisateur MySQL.
// Sous XAMPP, l'utilisateur par défaut s'appelle "root".
$utilisateur = "root";

// Mot de passe MySQL.
// Sous XAMPP, le mot de passe de "root" est VIDE par défaut → "".
// Si tu as défini un mot de passe, mets-le ici entre les guillemets.
$motdepasse = "";


// ── Connexion avec PDO ───────────────────────────────────────
// PDO = PHP Data Objects.
// C'est la façon MODERNE et SÉCURISÉE de se connecter à MySQL en PHP.
// Alternative à l'ancienne méthode "mysql_connect()" qui est obsolète.
//
// On utilise un bloc try/catch pour attraper les erreurs proprement.
// try   → essaie d'exécuter le code
// catch → si une erreur survient, on l'intercepte au lieu de planter brutalement

try {

    // Création de la connexion PDO.
    // new PDO(...) crée un objet de connexion qu'on stocke dans $pdo.
    // On réutilisera $pdo dans tous les autres fichiers PHP.
    $pdo = new PDO(

        // 1er argument : le DSN (Data Source Name) = "où se connecter".
        // Format : "mysql:host=HOTE;dbname=NOM_BDD;charset=ENCODAGE"
        // charset=utf8mb4 = même encodage que la BDD → les emojis et accents passent bien.
        "mysql:host=$hote;dbname=$base;charset=utf8mb4",

        // 2e argument : le nom d'utilisateur MySQL.
        $utilisateur,

        // 3e argument : le mot de passe MySQL.
        $motdepasse,

        // 4e argument : tableau d'options pour configurer le comportement de PDO.
        [
            // ERRMODE_EXCEPTION = si une requête SQL échoue,
            // PDO lance une Exception (erreur attrapable) au lieu de retourner false silencieusement.
            // Indispensable pour déboguer et gérer les erreurs proprement.
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

            // DEFAULT_FETCH_MODE = format par défaut des résultats de requêtes.
            // FETCH_ASSOC = chaque ligne est retournée comme un tableau associatif.
            // Ex : $ligne['nom'] au lieu de $ligne[1] → beaucoup plus lisible.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // EMULATE_PREPARES = false = on désactive l'émulation des requêtes préparées.
            // Avec false, PDO utilise les VRAIES requêtes préparées de MySQL.
            // → Meilleure sécurité contre les injections SQL.
            // → Les types de données sont respectés (un INT reste un INT, pas une string).
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

// Si la connexion échoue (mauvais mot de passe, MySQL éteint, base inexistante…),
// PDO lance une exception PDOException qu'on attrape ici.
} catch (PDOException $e) {

    // On arrête tout le script PHP avec die().
    // On affiche un message d'erreur clair en JSON (pratique pour le débogage en API).
    // $e->getMessage() retourne le message d'erreur précis de MySQL.
    // ⚠️  En production (site en ligne), on n'afficherait PAS ce message à l'utilisateur
    //     (risque de sécurité). On le logguerait dans un fichier de log à la place.
    die(json_encode([
        "erreur" => "Impossible de se connecter à la base de données.",
        "detail" => $e->getMessage()
    ]));
}