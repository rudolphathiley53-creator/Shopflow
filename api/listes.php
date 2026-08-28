<?php
// ============================================================
//  api/listes.php — Gestion des listes de courses
//
//  Ce fichier répond aux requêtes HTTP du navigateur (JavaScript fetch).
//  Il lit la méthode HTTP utilisée et exécute l'action correspondante.
//
//  GET    /api/listes.php        → retourne toutes les listes + progression
//  POST   /api/listes.php        → crée une nouvelle liste
//  DELETE /api/listes.php?id=X   → supprime la liste X et ses articles (CASCADE)
// ============================================================


// ── 1. En-têtes HTTP ─────────────────────────────────────────
// Ces lignes s'écrivent AVANT tout autre contenu (avant même les espaces).
// Elles configurent ce que le navigateur recevra.

// On dit au navigateur que la réponse est du JSON (pas du HTML).
// Content-Type: application/json = "ce que je t'envoie, c'est du JSON".
header("Content-Type: application/json; charset=utf-8");

// CORS = Cross-Origin Resource Sharing.
// Autorise le JavaScript (qui tourne sur http://localhost)
// à faire des requêtes vers ce fichier PHP.
// Sans cette ligne, le navigateur bloquerait les requêtes fetch() par sécurité.
header("Access-Control-Allow-Origin: *");

// On autorise les méthodes HTTP que ce fichier accepte.
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");


// ── 2. Connexion à la base de données ────────────────────────
// On inclut config.php qui crée la variable $pdo.
// require_once = inclus le fichier UNE seule fois, et stoppe si le fichier est introuvable.
// __DIR__ = chemin absolu du dossier actuel → évite les problèmes de chemin relatif.
require_once __DIR__ . "/../config.php";


// ── 3. Lecture de la méthode HTTP ────────────────────────────
// $_SERVER['REQUEST_METHOD'] = la méthode HTTP utilisée par le navigateur.
// Quand JS fait fetch('/api/listes.php')                → "GET"
// Quand JS fait fetch('/api/listes.php', {method:'POST'}) → "POST"
// Etc.
$methode = $_SERVER['REQUEST_METHOD'];


// ── 4. Routage : on exécute l'action selon la méthode ────────
// match() = équivalent moderne de switch() en PHP 8.
// Chaque "cas" appelle une fonction dédiée.

match($methode) {
    'GET'    => getListes(),
    'POST'   => creerListe(),
    'DELETE' => supprimerListe(),

    // Par défaut : méthode non supportée → erreur 405.
    default  => reponseErreur(405, "Méthode non autorisée.")
};


// ============================================================
//  FONCTIONS
// ============================================================

// ── getListes() ──────────────────────────────────────────────
// Récupère toutes les listes avec leur progression (% d'articles achetés).
// Appelée quand le JS charge la page d'accueil.
function getListes() {
    global $pdo; // On rend $pdo accessible dans cette fonction.

    // Requête SQL avec LEFT JOIN et GROUP BY pour calculer la progression.
    // On utilise une requête préparée ($pdo->prepare) même sans paramètre
    // par cohérence et bonne pratique.
    $sql = "
        SELECT
            l.id,
            l.icone,
            l.nom,
            l.cree_le,
            COUNT(a.id)                                               AS total,
            COALESCE(SUM(a.achete), 0)                                AS achetes,
            -- COALESCE remplace NULL par 0 si la liste est vide (pas d'articles).
            COUNT(a.id) - COALESCE(SUM(a.achete), 0)                  AS restants,
            -- Progression en % :  si 0 article (COUNT=0), on affiche '0%' pour éviter une division par 0.
            IF(COUNT(a.id) = 0, '0%',
                CONCAT(ROUND(SUM(a.achete) / COUNT(a.id) * 100, 0), '%')
            ) AS progression
        FROM listes l
        LEFT JOIN articles a ON a.liste_id = l.id
        GROUP BY l.id, l.icone, l.nom, l.cree_le
        ORDER BY l.id
    ";

    // prepare() = prépare la requête (MySQL la valide et la compile).
    $stmt = $pdo->prepare($sql);

    // execute() = exécute la requête.
    $stmt->execute();

    // fetchAll() = récupère TOUTES les lignes de résultat dans un tableau PHP.
    // PDO::FETCH_ASSOC (défini dans config.php) = tableaux associatifs.
    $listes = $stmt->fetchAll();

    // On renvoie le résultat en JSON au navigateur.
    reponseSucces($listes);
}


// ── creerListe() ─────────────────────────────────────────────
// Crée une nouvelle liste à partir des données envoyées par le JS.
// Le JS envoie un JSON dans le body de la requête POST.
function creerListe() {
    global $pdo;

    // php://input = flux brut du corps de la requête HTTP.
    // C'est ici que le JSON envoyé par fetch() arrive.
    $donnees = json_decode(file_get_contents("php://input"), true);
    // json_decode(..., true) = convertit le JSON en tableau PHP associatif.

    // Validation : on vérifie que les champs obligatoires sont présents.
    // isset() = vérifie qu'une variable existe et n'est pas null.
    // trim() = supprime les espaces au début et à la fin.
    if (!isset($donnees['nom']) || trim($donnees['nom']) === '') {
        reponseErreur(400, "Le nom de la liste est obligatoire.");
        return;
    }

    // Requête INSERT préparée.
    // Les ? sont des PARAMÈTRES — jamais remplacés directement par des variables.
    // C'est ce qui protège contre les injections SQL.
    $sql = "INSERT INTO listes (nom, icone) VALUES (?, ?)";
    $stmt = $pdo->prepare($sql);

    // execute([...]) = envoie les vraies valeurs qui remplaceront les ?.
    // PDO les échappe automatiquement → sécurisé.
    $stmt->execute([
        trim($donnees['nom']),
        $donnees['icone'] ?? '🛒' // ?? = opérateur "null coalescing" : si 'icone' absent → '🛒'
    ]);

    // lastInsertId() = retourne l'id AUTO_INCREMENT de la ligne qu'on vient d'insérer.
    $nouvelId = $pdo->lastInsertId();

    // Code HTTP 201 = "Created" (ressource créée avec succès).
    // C'est plus précis que 200 qui veut dire juste "OK".
    reponseSucces(["id" => $nouvelId, "message" => "Liste créée."], 201);
}


// ── supprimerListe() ─────────────────────────────────────────
// Supprime une liste ET tous ses articles (grâce à ON DELETE CASCADE dans la BDD).
// Le JS passe l'id dans l'URL : DELETE /api/listes.php?id=2
function supprimerListe() {
    global $pdo;

    // $_GET = tableau des paramètres passés dans l'URL (?id=2 → $_GET['id'] = '2').
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        reponseErreur(400, "ID de liste manquant ou invalide.");
        return;
    }

    // (int) = cast (conversion forcée) en entier.
    // Sécurité supplémentaire : même si quelqu'un passe "1; DROP TABLE listes",
    // (int) le transforme en 1.
    $id = (int)$_GET['id'];

    $sql  = "DELETE FROM listes WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);

    // rowCount() = nombre de lignes affectées par le DELETE.
    // Si 0 → la liste n'existait pas.
    if ($stmt->rowCount() === 0) {
        reponseErreur(404, "Liste introuvable.");
        return;
    }

    reponseSucces(["message" => "Liste et ses articles supprimés."]);
}


// ============================================================
//  FONCTIONS UTILITAIRES — Réponses JSON standardisées
// ============================================================

// ── reponseSucces() ──────────────────────────────────────────
// Envoie une réponse JSON avec un code HTTP de succès.
// $donnees = les données à envoyer (tableau PHP → converti en JSON).
// $code    = code HTTP (200 par défaut, ou 201 pour une création).
function reponseSucces($donnees, $code = 200) {
    // http_response_code() = définit le code de statut HTTP de la réponse.
    http_response_code($code);

    // json_encode() = convertit un tableau PHP en chaîne JSON.
    // JSON_UNESCAPED_UNICODE = les caractères spéciaux (accents, emojis) ne sont pas
    //   transformés en \uXXXX → on garde les vrais caractères lisibles.
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE);
}

// ── reponseErreur() ──────────────────────────────────────────
// Envoie une réponse JSON d'erreur avec le code HTTP approprié.
// $code    = code HTTP d'erreur (400, 404, 405…).
// $message = message d'erreur lisible par le développeur.
function reponseErreur($code, $message) {
    http_response_code($code);
    echo json_encode(["erreur" => $message], JSON_UNESCAPED_UNICODE);
}