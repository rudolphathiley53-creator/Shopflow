# ShopFlow
 
## 📋 Description
 
**ShopFlow** est une application web de gestion de listes de courses. Elle permet de créer plusieurs listes (Supermarché, Maison, Matériel Photo…), d'y ajouter des articles classés par catégorie, de cocher les articles achetés et de suivre la progression de chaque liste en temps réel.
 
## 🛠️ Stack technique
 
- **Frontend** : HTML / CSS / JavaScript vanilla (une seule page, `templates/index.html`), communication avec le backend via `fetch()`
- **Backend** : API REST en PHP (fichiers dans `api/`)
- **Base de données** : MySQL / MariaDB (via XAMPP), accès avec PDO
> ⚠️ **Note** : le dépôt contient aussi `app.py` et `requirements.txt` (Flask + flask-mysqldb), mais `app.py` correspond en réalité au code source de la librairie Flask elle-même (pas à une application), et rien dans `templates/index.html` n'appelle un serveur Flask — tout passe par les fichiers PHP. Ces deux fichiers semblent avoir été ajoutés par erreur et peuvent être ignorés (voire supprimés) sans impact sur le fonctionnement du projet.
 
## 📁 Structure du projet
 
```
Shopflow/
├── api/
│   ├── listes.php        # CRUD des listes de courses
│   ├── articles.php      # CRUD des articles
│   └── categories.php    # Lecture des catégories
├── config.php            # Connexion PDO à la base MySQL
├── shopflow.sql           # Schéma + données de test
└── templates/
    └── index.html         # Interface (HTML + CSS + JS)
```
 
## 🗄️ Base de données
 
Trois tables (`shopflow.sql`) :
 
| Table | Rôle |
|---|---|
| `categories` | Types d'articles (Fruits & Légumes, Viandes, Épicerie…), avec une couleur associée |
| `listes` | Les listes de courses (nom, icône, dates de création/modification) |
| `articles` | Les articles de chaque liste (nom, quantité, statut acheté), liés à une liste et une catégorie |
 
- `articles.liste_id` → `ON DELETE CASCADE` (supprimer une liste supprime ses articles)
- `articles.categorie_id` → `ON DELETE RESTRICT` (impossible de supprimer une catégorie encore utilisée)
Le fichier inclut aussi des données de test (10 catégories, 3 listes, plusieurs articles).
 
## 🔌 API
 
**Listes** (`api/listes.php`)
 
| Méthode | Endpoint | Action |
|---|---|---|
| GET | `/api/listes.php` | Toutes les listes + progression |
| POST | `/api/listes.php` | Créer une liste |
| DELETE | `/api/listes.php?id=X` | Supprimer une liste (et ses articles) |
 
**Articles** (`api/articles.php`)
 
| Méthode | Endpoint | Action |
|---|---|---|
| GET | `/api/articles.php?liste_id=1` | Articles d'une liste |
| POST | `/api/articles.php` | Créer un article |
| PUT | `/api/articles.php?id=5` | Modifier un article |
| PATCH | `/api/articles.php?id=5` | Cocher/décocher "acheté" |
| DELETE | `/api/articles.php?id=5` | Supprimer un article |
| DELETE | `/api/articles.php?liste_id=1&achetes=1` | Supprimer tous les articles achetés d'une liste |
 
**Catégories** (`api/categories.php`)
 
| Méthode | Endpoint | Action |
|---|---|---|
| GET | `/api/categories.php` | Toutes les catégories |
 
## 🚀 Installation (XAMPP)
 
1. Placer le dossier du projet dans `htdocs/` de XAMPP.
2. Démarrer Apache et MySQL depuis le panneau XAMPP.
3. Créer la base de données en important `shopflow.sql` via phpMyAdmin (ou `mysql -u root shopflow < shopflow.sql`).
4. Vérifier les identifiants dans `config.php` (par défaut : `localhost`, base `shopflow`, utilisateur `root`, mot de passe vide).
5. Ouvrir `http://localhost/Shopflow/templates/index.html` dans le navigateur.
