-- ============================================================
--  ShopFlow — Base de données MySQL
--  Compatible : MySQL 5.7+ / MariaDB 10.3+ (XAMPP / phpMyAdmin)
-- ============================================================
--
--  STRUCTURE GÉNÉRALE DU FICHIER :
--
--    0. Création de la base de données
--    1. Création des tables (la structure, le "squelette")
--       ├── categories  → les types d'articles (Fruits, Viandes…)
--       ├── listes      → les listes de courses (Supermarché, Maison…)
--       └── articles    → les articles eux-mêmes (Tomates, Pain…)
--    2. INSERT  → remplir les tables avec des données de test
--    3. UPDATE  → modifier des données existantes
--    4. DELETE  → supprimer des données
--    5. SELECT  → lire / afficher les données (avec JOIN et calculs)
--
--  RELATION ENTRE LES TABLES :
--
--    categories ◄──── articles ────► listes
--
--    Un article appartient à UNE liste et UNE catégorie.
--    Une liste peut avoir PLUSIEURS articles.
--    Une catégorie peut regrouper PLUSIEURS articles.
-- ============================================================



-- ============================================================
--  0. CRÉATION ET SÉLECTION DE LA BASE DE DONNÉES
-- ============================================================

-- Crée la base "shopflow" si elle n'existe pas encore.
-- IF NOT EXISTS évite une erreur si on relance ce script une 2e fois.
CREATE DATABASE IF NOT EXISTS shopflow

  -- utf8mb4 = encodage UTF-8 étendu.
  -- Indispensable pour stocker les accents (é, è, ç) ET les emojis (🛒📷).
  -- Sans ça, les emojis et certains caractères spéciaux seraient corrompus.
  CHARACTER SET utf8mb4

  -- COLLATE = règles de tri et de comparaison du texte.
  -- unicode_ci = compare les textes sans tenir compte de la casse (majuscule/minuscule).
  -- Ex : "Pain" et "pain" seront considérés comme identiques dans les recherches.
  COLLATE utf8mb4_unicode_ci;


-- Sélectionne la base "shopflow" comme base active.
-- Toutes les commandes qui suivent s'appliqueront à cette base.
-- Sans ce USE, MySQL ne saurait pas dans quelle base travailler.
USE shopflow;



-- ============================================================
--  1. CREATE TABLE — Création de la structure des tables
-- ============================================================
--
--  Une table = un tableau avec des colonnes (propriétés) et des lignes (données).
--  CREATE TABLE définit le "squelette" AVANT d'insérer des données.
--  On crée d'abord les tables indépendantes (categories, listes),
--  puis la table dépendante (articles) qui fait référence aux deux autres.
-- ============================================================


-- ── Table : categories ──────────────────────────────────────
--  Contient les types d'articles : Fruits & Légumes, Viandes, Épicerie…
--  Cette table n'a aucune dépendance → on la crée EN PREMIER.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (

    -- Identifiant unique de la catégorie.
    -- INT = nombre entier.
    -- NOT NULL = ce champ ne peut jamais être vide.
    -- AUTO_INCREMENT = MySQL attribue automatiquement 1, 2, 3… à chaque nouvelle ligne.
    --   On n'a jamais besoin de gérer les IDs manuellement.
    id         INT            NOT NULL AUTO_INCREMENT,

    -- Nom de la catégorie (ex : "Fruits & Légumes").
    -- VARCHAR(80) = texte de 80 caractères maximum.
    -- NOT NULL = obligatoire, impossible de créer une catégorie sans nom.
    nom        VARCHAR(80)    NOT NULL,

    -- Couleur associée à la catégorie, stockée en code hexadécimal (ex : #5A9E2F).
    -- VARCHAR(7) = exactement 7 caractères pour le format #RRGGBB.
    -- DEFAULT '#888780' = si on ne précise pas de couleur, MySQL met du gris automatiquement.
    couleur    VARCHAR(7)     NOT NULL DEFAULT '#888780',

    -- Déclaration de la clé primaire.
    -- CONSTRAINT pk_categories = nom donné à cette règle (utile pour les messages d'erreur).
    -- PRIMARY KEY (id) = "id" identifie chaque ligne de façon unique.
    --   → Impossible d'avoir deux catégories avec le même id.
    --   → MySQL crée automatiquement un index (recherche ultra-rapide) sur cette colonne.
    CONSTRAINT pk_categories PRIMARY KEY (id),

    -- Contrainte d'unicité sur le nom.
    -- UNIQUE (nom) = impossible d'insérer deux catégories avec exactement le même nom.
    -- Ex : si "Épicerie" existe déjà, un 2e INSERT avec "Épicerie" sera bloqué.
    CONSTRAINT uq_categories_nom UNIQUE (nom)

-- ENGINE=InnoDB = moteur de stockage utilisé par MySQL.
-- InnoDB est OBLIGATOIRE pour pouvoir utiliser les clés étrangères (FOREIGN KEY).
-- C'est le moteur par défaut depuis MySQL 5.5, et le plus robuste.
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ── Table : listes ──────────────────────────────────────────
--  Contient les listes de courses : Supermarché, Maison, Photo…
--  Indépendante de categories → on la crée EN DEUXIÈME.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS listes (

    -- Identifiant unique auto-géré (même principe que categories.id).
    id           INT            NOT NULL AUTO_INCREMENT,

    -- Nom de la liste (ex : "Supermarché du samedi").
    -- VARCHAR(120) = jusqu'à 120 caractères (noms potentiellement plus longs).
    nom          VARCHAR(120)   NOT NULL,

    -- Emoji affiché à côté du nom de la liste dans l'interface.
    -- VARCHAR(10) car un emoji peut occuper jusqu'à 4 octets en UTF-8.
    -- DEFAULT '🛒' = si on ne précise pas d'icône, le caddie s'affiche par défaut.
    icone        VARCHAR(10)    NOT NULL DEFAULT '🛒',

    -- Date ET heure de création de la liste.
    -- DATETIME stocke : AAAA-MM-JJ HH:MM:SS
    -- DEFAULT CURRENT_TIMESTAMP = MySQL remplit ce champ automatiquement
    --   avec la date/heure exacte au moment où la ligne est créée (INSERT).
    --   On n'a jamais besoin de passer cette valeur depuis le code PHP.
    cree_le      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Date ET heure de la dernière modification de la liste.
    -- ON UPDATE CURRENT_TIMESTAMP = MySQL met à jour ce champ AUTOMATIQUEMENT
    --   à chaque fois qu'on modifie cette ligne (UPDATE).
    --   Très pratique pour savoir "quand a été faite la dernière modification".
    modifie_le   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT pk_listes PRIMARY KEY (id)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- ── Table : articles ────────────────────────────────────────
--  Contient les articles de chaque liste (Tomates, Pain, Trépied…).
--  DÉPEND de "listes" et "categories" → on la crée EN DERNIER.
--  C'est la table centrale de l'application.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS articles (

    -- Identifiant unique auto-géré.
    id            INT            NOT NULL AUTO_INCREMENT,

    -- Référence vers la liste à laquelle appartient cet article.
    -- Stocke l'id d'une ligne de la table "listes".
    -- Ex : liste_id = 1 signifie que cet article est dans la liste n°1 (Supermarché).
    liste_id      INT            NOT NULL,

    -- Référence vers la catégorie de cet article.
    -- Stocke l'id d'une ligne de la table "categories".
    -- Ex : categorie_id = 1 = "Fruits & Légumes".
    categorie_id  INT            NOT NULL,

    -- Nom de l'article (ex : "Tomates cerises bio").
    -- VARCHAR(200) = noms potentiellement longs pour des articles détaillés.
    nom           VARCHAR(200)   NOT NULL,

    -- Quantité sous forme de texte libre (ex : "500 g", "x1", "2 L", "x6").
    -- On utilise VARCHAR et non INT car la quantité peut avoir des unités (kg, L…).
    quantite      VARCHAR(30)    NOT NULL DEFAULT 'x1',

    -- Indique si l'article a été acheté ou non.
    -- TINYINT(1) = entier sur 1 bit → c'est la façon MySQL de stocker un booléen.
    --   0 = non acheté (false)
    --   1 = acheté     (true)
    -- DEFAULT 0 = tout nouvel article commence comme "non acheté".
    achete        TINYINT(1)     NOT NULL DEFAULT 0,

    -- Date/heure de création et de modification (même principe que dans "listes").
    cree_le       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    modifie_le    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,

    -- Clé primaire sur id.
    CONSTRAINT pk_articles      PRIMARY KEY (id),

    -- ── Clé étrangère vers "listes" ──────────────────────────
    -- FOREIGN KEY (liste_id) = la colonne liste_id de cette table…
    -- REFERENCES listes(id)  = …doit correspondre à un id existant dans "listes".
    -- MySQL vérifie cette cohérence à chaque INSERT et UPDATE.
    --   → Impossible de créer un article avec un liste_id qui n'existe pas.
    --
    -- ON DELETE CASCADE :
    --   Si on supprime une LISTE, tous ses articles sont supprimés automatiquement.
    --   Ex : DELETE FROM listes WHERE id=1 → efface aussi tous les articles de la liste 1.
    --
    -- ON UPDATE CASCADE :
    --   Si l'id d'une liste change (rare), les liste_id dans articles sont mis à jour auto.
    CONSTRAINT fk_art_liste     FOREIGN KEY (liste_id)
        REFERENCES listes(id)     ON DELETE CASCADE ON UPDATE CASCADE,

    -- ── Clé étrangère vers "categories" ──────────────────────
    -- Même principe que pour liste_id.
    --
    -- ON DELETE RESTRICT (différent de CASCADE !) :
    --   Si on essaie de supprimer une CATÉGORIE qui possède encore des articles,
    --   MySQL BLOQUE l'opération et affiche une erreur.
    --   → Protection contre les suppressions accidentelles de catégories utilisées.
    --   → Pour supprimer une catégorie, il faut d'abord supprimer/recatégoriser ses articles.
    --
    -- ON UPDATE CASCADE :
    --   Si l'id d'une catégorie change, les categorie_id dans articles suivent automatiquement.
    CONSTRAINT fk_art_categorie FOREIGN KEY (categorie_id)
        REFERENCES categories(id) ON DELETE RESTRICT ON UPDATE CASCADE

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;



-- ============================================================
--  2. INSERT — Insertion des données de test
-- ============================================================
--
--  INSERT INTO table (col1, col2) VALUES (val1, val2), (val1, val2)…;
--
--  ORDRE IMPORTANT : il faut insérer dans le même ordre que la création :
--    1. categories  (aucune dépendance)
--    2. listes      (aucune dépendance)
--    3. articles    (dépend de categories ET listes → ceux-ci doivent déjà exister)
-- ============================================================


-- ── Catégories ──────────────────────────────────────────────
-- On insère 10 catégories en une seule commande (plus efficace que 10 INSERT séparés).
-- On ne précise PAS "id" car AUTO_INCREMENT le gère seul → MySQL attribue 1, 2, 3…
-- MySQL attribuera :  id=1 → Fruits & Légumes,  id=2 → Viandes,  id=3 → Épicerie…  etc.
INSERT INTO categories (nom, couleur) VALUES
    ('Fruits & Légumes',   '#5A9E2F'),   -- id = 1  (vert végétal)
    ('Viandes & Poissons', '#C25430'),   -- id = 2  (rouge-orangé viande)
    ('Épicerie',           '#B87215'),   -- id = 3  (brun épices)
    ('Boissons',           '#2878C8'),   -- id = 4  (bleu liquide)
    ('Hygiène',            '#7B45C8'),   -- id = 5  (violet)
    ('Maison',             '#1E8E68'),   -- id = 6  (vert maison)
    ('Électronique',       '#1A5FA8'),   -- id = 7  (bleu tech)
    ('Matériel Photo',     '#8A3050'),   -- id = 8  (bordeaux)
    ('Vêtements',          '#C84878'),   -- id = 9  (rose)
    ('Autre',              '#7A7870');   -- id = 10 (gris neutre)


-- ── Listes ──────────────────────────────────────────────────
-- Même principe : id non précisé, AUTO_INCREMENT l'attribue.
-- MySQL attribuera :  id=1 → Supermarché,  id=2 → Matériel Photo,  id=3 → Maison.
INSERT INTO listes (nom, icone) VALUES
    ('Supermarché',    '🛒'),   -- id = 1
    ('Matériel Photo', '📷'),   -- id = 2
    ('Maison',         '🏠');   -- id = 3


-- ── Articles — Liste 1 : Supermarché ────────────────────────
-- liste_id=1 car ces articles vont dans "Supermarché".
-- Rappel des ids catégories : 1=Fruits&Légumes  2=Viandes  3=Épicerie  4=Boissons
-- achete=0 = pas encore acheté  |  achete=1 = déjà acheté
INSERT INTO articles (liste_id, categorie_id, nom, quantite, achete) VALUES
    (1, 1, 'Tomates',        '500 g', 0),   -- Fruits & Légumes, restant
    (1, 2, 'Poulet fermier', '1 kg',  0),   -- Viandes, restant
    (1, 3, 'Pain de mie',    'x1',    1),   -- Épicerie, DÉJÀ ACHETÉ
    (1, 4, 'Jus d''orange',  '2 L',   0),   -- Boissons, restant
    --   ↑ d''orange : l'apostrophe dans le texte doit être doublée en SQL
    (1, 3, 'Yaourts nature', 'x6',    0),   -- Épicerie, restant
    (1, 1, 'Carottes',       '1 kg',  0),   -- Fruits & Légumes, restant
    (1, 4, 'Eau minérale',   'x6',    1);   -- Boissons, DÉJÀ ACHETÉ


-- ── Articles — Liste 2 : Matériel Photo ─────────────────────
-- liste_id=2 car ces articles vont dans "Matériel Photo".
-- 8=Matériel Photo  |  7=Électronique
INSERT INTO articles (liste_id, categorie_id, nom, quantite, achete) VALUES
    (2, 8, 'Trépied compact',        'x1', 0),   -- Photo, restant
    (2, 8, 'Carte SD 128 Go',        'x2', 0),   -- Photo, restant
    (2, 8, 'Filtre polarisant 67mm', 'x1', 1),   -- Photo, DÉJÀ ACHETÉ
    (2, 7, 'Télécommande Bluetooth', 'x1', 0);   -- Électronique, restant


-- ── Articles — Liste 3 : Maison ─────────────────────────────
-- liste_id=3 car ces articles vont dans "Maison".
-- 6=Maison  |  5=Hygiène
INSERT INTO articles (liste_id, categorie_id, nom, quantite, achete) VALUES
    (3, 6, 'Ampoules LED',      'x4', 0),   -- Maison, restant
    (3, 5, 'Liquide vaisselle', 'x2', 1),   -- Hygiène, DÉJÀ ACHETÉ
    (3, 6, 'Brosse WC',         'x1', 0),   -- Maison, restant
    (3, 5, 'Savon liquide',     'x3', 0);   -- Hygiène, restant



-- ============================================================
--  3. UPDATE — Modification de données existantes
-- ============================================================
--
--  Structure d'un UPDATE :
--    UPDATE [table]         → quelle table on modifie
--    SET [colonne] = [val]  → quelle(s) colonne(s) et nouvelle(s) valeur(s)
--    WHERE [condition];     → quelles lignes sont concernées
--
--  ⚠️  RÈGLE D'OR : toujours mettre un WHERE !
--      Un UPDATE sans WHERE modifie TOUTES les lignes de la table.
--      C'est l'erreur la plus fréquente et la plus destructrice.
-- ============================================================


-- Marquer l'article id=1 (Tomates) comme acheté.
-- SET achete = 1  →  passe de "restant" à "acheté".
-- WHERE id = 1    →  cible UNE seule ligne précise.
UPDATE articles
    SET achete = 1
    WHERE id = 1;


-- Corriger la quantité du Poulet fermier (id=2).
-- On peut corriger une faute de saisie ou mettre à jour une valeur facilement.
UPDATE articles
    SET quantite = '1,5 kg'
    WHERE id = 2;


-- Renommer la liste Supermarché (id=1).
-- Le champ "modifie_le" sera mis à jour automatiquement par MySQL (ON UPDATE CURRENT_TIMESTAMP).
UPDATE listes
    SET nom = 'Courses du samedi'
    WHERE id = 1;


-- Changer la couleur de la catégorie "Épicerie".
-- On peut cibler une ligne par une colonne autre que id (ici par nom).
-- ⚠️  Fonctionne UNIQUEMENT parce qu'il y a une contrainte UNIQUE sur "nom".
--     Sans UNIQUE, ce WHERE pourrait toucher plusieurs lignes si des doublons existaient.
UPDATE categories
    SET couleur = '#D4831A'
    WHERE nom = 'Épicerie';


-- Remettre à zéro tous les articles de la liste 2 (décocher tous les achats).
-- WHERE liste_id = 2 → cible PLUSIEURS lignes à la fois (tous les articles de la liste 2).
-- Utile pour "recommencer" une liste sans la supprimer.
UPDATE articles
    SET achete = 0
    WHERE liste_id = 2;



-- ============================================================
--  4. DELETE — Suppression de données
-- ============================================================
--
--  Structure d'un DELETE :
--    DELETE FROM [table]
--    WHERE [condition];
--
--  ⚠️  Même règle qu'UPDATE : toujours mettre un WHERE !
--      Un DELETE sans WHERE supprime TOUTES les lignes de la table.
--      Cette action est IRRÉVERSIBLE.
-- ============================================================


-- Supprimer un article précis par son id.
-- id=14 = "Brosse WC" (15e INSERT dans articles, mais id=14 car on a supprimé avant).
-- WHERE id = 14 → exactement une ligne supprimée.
DELETE FROM articles
    WHERE id = 14;


-- Supprimer tous les articles ACHETÉS de la liste 1.
-- AND combine deux conditions → les DEUX doivent être vraies.
--   liste_id = 1 → dans la liste Supermarché
--   achete = 1   → qui sont cochés comme achetés
-- Utile pour "vider le panier" des articles déjà pris.
DELETE FROM articles
    WHERE liste_id = 1
      AND achete = 1;


-- Supprimer une liste entière ET tous ses articles d'un coup.
-- Grâce à ON DELETE CASCADE défini dans la table "articles",
-- supprimer une liste supprime automatiquement tous ses articles liés.
-- Cette ligne est commentée (--) pour éviter une suppression accidentelle.
-- Pour l'activer : retirer les deux tirets du début.
-- DELETE FROM listes WHERE id = 3;



-- ============================================================
--  5. SELECT — Lecture et affichage des données
-- ============================================================
--
--  SELECT récupère les données pour les afficher dans l'interface.
--  C'est la commande la plus utilisée dans les pages PHP du site.
--
--  Nouveauté par rapport au CRUD simple : les JOIN.
--  JOIN "colle" deux tables ensemble via leurs colonnes en commun.
--  Sans JOIN, on n'aurait que des IDs (1, 2, 3…) au lieu des vrais noms.
-- ============================================================


-- ── Vue complète : tous les articles avec leur liste et catégorie ──
--
--  Cette requête assemble les 3 tables pour obtenir un tableau lisible.
--  Résultat : liste | catégorie | article | quantité | statut
SELECT
    l.nom           AS liste,       -- Nom de la liste (via JOIN listes)
    c.nom           AS categorie,   -- Nom de la catégorie (via JOIN categories)
    a.nom           AS article,     -- Nom de l'article
    a.quantite,                     -- Quantité (ex : "500 g")

    -- IF(condition, valeur_si_vrai, valeur_si_faux) = équivalent d'un if/else en SQL.
    -- Si achete = 1 (vrai) → affiche "Acheté", sinon → affiche "Restant".
    IF(a.achete, 'Acheté', 'Restant') AS statut

-- On part de la table "articles" qu'on appelle "a" (alias raccourci pour éviter de répéter).
FROM articles a

-- JOIN listes : on "colle" la table listes là où les IDs correspondent.
-- ON l.id = a.liste_id signifie : "la ligne de listes dont l'id = le liste_id de l'article".
-- Grâce à ce JOIN, on peut afficher l.nom (le vrai nom) au lieu de a.liste_id (juste un chiffre).
JOIN listes     l ON l.id = a.liste_id

-- Même principe pour les catégories.
JOIN categories c ON c.id = a.categorie_id

-- Tri du résultat : d'abord par liste, puis par catégorie dans chaque liste, puis par nom d'article.
ORDER BY l.id, c.nom, a.nom;


-- ── Progression par liste ────────────────────────────────────
--
--  Calcule pour chaque liste : total d'articles, combien achetés, et le % d'avancement.
--  Utile pour afficher une barre de progression dans l'interface.
SELECT
    l.icone,
    l.nom                                                     AS liste,

    -- COUNT(a.id) = compte le nombre de lignes dans le GROUP (= nombre d'articles de la liste).
    COUNT(a.id)                                               AS total,

    -- SUM(a.achete) = additionne toutes les valeurs de "achete" dans le groupe.
    -- Comme achete vaut 0 ou 1, cette somme = le nombre d'articles achetés.
    SUM(a.achete)                                             AS achetes,

    -- Soustraction : total - achetés = restants.
    COUNT(a.id) - SUM(a.achete)                               AS restants,

    -- Calcul du pourcentage d'avancement :
    --   SUM(achete) / COUNT(id) * 100  →  ex : 3 achetés / 7 total * 100 = 42.857…
    --   ROUND(…, 0)                    →  arrondi à 0 décimale → 43
    --   CONCAT(…, '%')                 →  colle le symbole % → "43%"
    CONCAT(ROUND(SUM(a.achete) / COUNT(a.id) * 100, 0), '%') AS progression

FROM listes l

-- LEFT JOIN (différent de JOIN simple) :
--   JOIN simple  → exclut les listes vides (sans aucun article) du résultat.
--   LEFT JOIN    → GARDE les listes vides, avec des valeurs NULL/0 pour les colonnes d'articles.
--   On utilise LEFT JOIN ici pour voir TOUTES les listes, même celles sans articles.
LEFT JOIN articles a ON a.liste_id = l.id

-- GROUP BY = regroupe les lignes par liste avant d'appliquer COUNT et SUM.
-- OBLIGATOIRE dès qu'on utilise des fonctions d'agrégation (COUNT, SUM, AVG, MAX, MIN…).
-- Sans GROUP BY, COUNT compterait TOUS les articles de toutes les listes en même temps.
GROUP BY l.id, l.icone, l.nom

ORDER BY l.id;


-- ── Articles encore à acheter (toutes listes) ───────────────
--
--  Même structure que la vue complète, mais filtrée sur achete = 0 uniquement.
--  WHERE s'applique APRÈS les JOIN → on filtre sur le résultat assemblé.
SELECT
    l.nom      AS liste,
    c.nom      AS categorie,
    a.nom      AS article,
    a.quantite
FROM articles a
JOIN listes     l ON l.id = a.liste_id
JOIN categories c ON c.id = a.categorie_id

-- Filtre : on ne veut QUE les articles non encore achetés.
WHERE a.achete = 0

ORDER BY l.id, c.nom, a.nom;