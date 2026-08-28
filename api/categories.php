<?php
// ============================================================
//  api/categories.php — Lecture des catégories
//
//  GET /api/categories.php → retourne toutes les catégories
//  (utilisé pour remplir les <select> dans les formulaires)
// ============================================================

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");

require_once __DIR__ . "/../config.php";

// Ce fichier ne supporte que GET (lecture seule).
// Les catégories sont fixes, on ne les crée pas depuis l'interface.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["erreur" => "Méthode non autorisée."]);
    exit();
}

// Récupère toutes les catégories triées par nom alphabétique.
$stmt = $pdo->query("SELECT id, nom, couleur FROM categories ORDER BY nom");
// query() = raccourci pour prepare() + execute() quand il n'y a PAS de paramètre variable.
// À n'utiliser QUE pour des requêtes sans données extérieures (pas de valeurs utilisateur).

$categories = $stmt->fetchAll();

http_response_code(200);
echo json_encode($categories, JSON_UNESCAPED_UNICODE);