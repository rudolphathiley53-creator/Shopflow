<?php
// ============================================================
//  api/articles.php — CRUD complet des articles
//
//  Ce fichier gère TOUTES les opérations sur les articles.
//  Il lit la méthode HTTP et l'id éventuel dans l'URL.
//
//  GET    /api/articles.php?liste_id=1  → articles d'une liste
//  POST   /api/articles.php             → créer un article
//  PUT    /api/articles.php?id=5        → modifier un article
//  PATCH  /api/articles.php?id=5        → cocher/décocher "acheté"
//  DELETE /api/articles.php?id=5        → supprimer un article
// ============================================================

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Réponse aux pré-requêtes OPTIONS envoyées automatiquement par les navigateurs
// avant une vraie requête (comportement CORS standard). On répond juste "OK".
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . "/../config.php";

$methode = $_SERVER['REQUEST_METHOD'];

match($methode) {
    'GET'    => getArticles(),
    'POST'   => creerArticle(),
    'PUT'    => modifierArticle(),
    'PATCH'  => toggleAchete(),    // PATCH = modification partielle (juste "achete")
    'DELETE' => supprimerArticle(),
    default  => reponseErreur(405, "Méthode non autorisée.")
};


// ============================================================
//  FONCTIONS CRUD
// ============================================================

// ── getArticles() ────────────────────────────────────────────
// Retourne tous les articles d'une liste donnée.
// Le JS passe l'id de la liste dans l'URL : GET /api/articles.php?liste_id=1
function getArticles() {
    global $pdo;

    // Validation de liste_id dans l'URL.
    if (!isset($_GET['liste_id']) || !is_numeric($_GET['liste_id'])) {
        reponseErreur(400, "liste_id manquant ou invalide.");
        return;
    }

    $listeId = (int)$_GET['liste_id'];

    // Requête avec JOIN pour récupérer le nom et la couleur de la catégorie.
    // Sans JOIN on n'aurait que categorie_id = 3, pas le nom "Épicerie".
    $sql = "
        SELECT
            a.id,
            a.nom,
            a.quantite,
            a.achete,
            a.cree_le,
            a.modifie_le,
            c.id       AS categorie_id,
            c.nom      AS categorie_nom,    -- Nom lisible de la catégorie
            c.couleur  AS categorie_couleur -- Couleur hex pour l'affichage
        FROM articles a
        JOIN categories c ON c.id = a.categorie_id
        WHERE a.liste_id = ?               -- Filtre sur la liste demandée
        ORDER BY a.achete ASC, c.nom, a.nom
        -- Tri : les non-achetés (0) avant les achetés (1), puis par catégorie, puis par nom.
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$listeId]);
    $articles = $stmt->fetchAll();

    // Conversion du champ "achete" de "0"/"1" (string MySQL) en true/false (booléen PHP).
    // PHP et JS comprennent mieux les vrais booléens que des strings "0" et "1".
    foreach ($articles as &$article) {
        // & = passage par référence → on modifie directement $article dans le tableau.
        $article['achete'] = (bool)$article['achete'];
    }

    reponseSucces($articles);
}


// ── creerArticle() ───────────────────────────────────────────
// Crée un nouvel article dans une liste.
// Le JS envoie un JSON dans le body : { nom, quantite, liste_id, categorie_id }
function creerArticle() {
    global $pdo;

    // Lecture et décodage du JSON envoyé par le JS.
    $d = json_decode(file_get_contents("php://input"), true);

    // ── Validation des champs obligatoires ───────────────────
    // On vérifie que tous les champs nécessaires sont présents et valides.
    // On accumule les erreurs dans un tableau pour les envoyer toutes à la fois.
    $erreurs = [];

    if (!isset($d['nom']) || trim($d['nom']) === '') {
        $erreurs[] = "Le nom de l'article est obligatoire.";
    }
    if (!isset($d['liste_id']) || !is_numeric($d['liste_id'])) {
        $erreurs[] = "liste_id invalide ou manquant.";
    }
    if (!isset($d['categorie_id']) || !is_numeric($d['categorie_id'])) {
        $erreurs[] = "categorie_id invalide ou manquant.";
    }

    // Si des erreurs → on répond avec la liste des erreurs et on stoppe.
    if (!empty($erreurs)) {
        reponseErreur(400, implode(" | ", $erreurs));
        // implode(" | ", $erreurs) = joint les erreurs en une seule string.
        return;
    }

    $sql = "
        INSERT INTO articles (liste_id, categorie_id, nom, quantite)
        VALUES (?, ?, ?, ?)
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        (int)$d['liste_id'],
        (int)$d['categorie_id'],
        trim($d['nom']),
        $d['quantite'] ?? 'x1' // Quantité optionnelle → 'x1' par défaut
    ]);

    $nouvelId = $pdo->lastInsertId();
    reponseSucces(["id" => $nouvelId, "message" => "Article créé."], 201);
}


// ── modifierArticle() ────────────────────────────────────────
// Modifie le nom, la quantité et/ou la catégorie d'un article.
// PUT /api/articles.php?id=5
// Body JSON : { nom, quantite, categorie_id }
function modifierArticle() {
    global $pdo;

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        reponseErreur(400, "ID d'article manquant ou invalide.");
        return;
    }

    $id = (int)$_GET['id'];
    $d  = json_decode(file_get_contents("php://input"), true);

    // ── Construction dynamique de la requête UPDATE ───────────
    // On ne modifie QUE les champs envoyés par le JS.
    // Si le JS n'envoie que "quantite", on ne touche pas aux autres colonnes.
    $champs  = [];   // Contiendra les "colonne = ?" à mettre dans le SET
    $valeurs = [];   // Contiendra les vraies valeurs dans le même ordre

    // isset + trim → on vérifie que le champ existe ET n'est pas vide.
    if (isset($d['nom']) && trim($d['nom']) !== '') {
        $champs[]  = "nom = ?";
        $valeurs[] = trim($d['nom']);
    }
    if (isset($d['quantite'])) {
        $champs[]  = "quantite = ?";
        $valeurs[] = trim($d['quantite']);
    }
    if (isset($d['categorie_id']) && is_numeric($d['categorie_id'])) {
        $champs[]  = "categorie_id = ?";
        $valeurs[] = (int)$d['categorie_id'];
    }

    // Si aucun champ envoyé → rien à modifier.
    if (empty($champs)) {
        reponseErreur(400, "Aucun champ à modifier.");
        return;
    }

    // On assemble la requête : "nom = ?, quantite = ?" etc.
    // implode(", ", $champs) = joint les éléments avec ", ".
    $setClause = implode(", ", $champs);

    // On ajoute l'id EN DERNIER dans $valeurs (il correspond au ? du WHERE).
    $valeurs[] = $id;

    $sql  = "UPDATE articles SET $setClause WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($valeurs);

    if ($stmt->rowCount() === 0) {
        reponseErreur(404, "Article introuvable.");
        return;
    }

    reponseSucces(["message" => "Article modifié."]);
}


// ── toggleAchete() ───────────────────────────────────────────
// Coche ou décoche un article (achete 0↔1).
// PATCH /api/articles.php?id=5
// Body JSON : { achete: true } ou { achete: false }
// On utilise PATCH (modification PARTIELLE) et non PUT (modification complète).
function toggleAchete() {
    global $pdo;

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        reponseErreur(400, "ID d'article manquant ou invalide.");
        return;
    }

    $id = (int)$_GET['id'];
    $d  = json_decode(file_get_contents("php://input"), true);

    // isset($d['achete']) → vérifie que le champ 'achete' est présent dans le JSON.
    // Note : on ne fait pas trim() car c'est un booléen, pas du texte.
    if (!isset($d['achete'])) {
        reponseErreur(400, "Le champ 'achete' est obligatoire.");
        return;
    }

    // (int)(bool) = double conversion :
    //   1. (bool) → force en booléen (true/false)
    //   2. (int)  → convertit en 0 ou 1 pour MySQL
    // Ex : "true" → true → 1  |  "" → false → 0  |  0 → false → 0
    $achete = (int)(bool)$d['achete'];

    $sql  = "UPDATE articles SET achete = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$achete, $id]);

    if ($stmt->rowCount() === 0) {
        reponseErreur(404, "Article introuvable.");
        return;
    }

    reponseSucces(["message" => "Statut mis à jour.", "achete" => (bool)$achete]);
}


// ── supprimerArticle() ───────────────────────────────────────
// Supprime un article précis.
// DELETE /api/articles.php?id=5
function supprimerArticle() {
    global $pdo;

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        reponseErreur(400, "ID d'article manquant ou invalide.");
        return;
    }

    $id   = (int)$_GET['id'];
    $sql  = "DELETE FROM articles WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        reponseErreur(404, "Article introuvable.");
        return;
    }

    reponseSucces(["message" => "Article supprimé."]);
}


// ── supprimerAchetes() ───────────────────────────────────────
// Supprime tous les articles achetés d'une liste.
// Utile pour le bouton "Vider le panier".
// DELETE /api/articles.php?liste_id=1&achetes=1
function supprimerAchetes() {
    global $pdo;

    if (!isset($_GET['liste_id']) || !is_numeric($_GET['liste_id'])) {
        reponseErreur(400, "liste_id manquant ou invalide.");
        return;
    }

    $listeId = (int)$_GET['liste_id'];
    $sql     = "DELETE FROM articles WHERE liste_id = ? AND achete = 1";
    $stmt    = $pdo->prepare($sql);
    $stmt->execute([$listeId]);

    // rowCount() peut être 0 si aucun article n'était acheté → ce n'est pas une erreur.
    reponseSucces([
        "message"  => "Articles achetés supprimés.",
        "supprimes" => $stmt->rowCount()
    ]);
}


// ============================================================
//  FONCTIONS UTILITAIRES
// ============================================================

function reponseSucces($donnees, $code = 200) {
    http_response_code($code);
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE);
}

function reponseErreur($code, $message) {
    http_response_code($code);
    echo json_encode(["erreur" => $message], JSON_UNESCAPED_UNICODE);
}