# CLAUDE.md

Conventions de ce projet. Valables pour un humain comme pour un assistant de code.
Adapté du guide des projets aurox (`osd84/aurox_boilerplate`). Doc du framework : https://aurox.fr.
En cas de doute sur ce que fait une classe aurox : lire son code dans `vendor/osd84/aurox/src/OsdAurox/`, il est court et commenté.

---

## 1. Le code qu'on attend

PHP + jQuery + la lib aurox, le moins de dépendances possible, esprit old school et minimaliste. **Pas d'ORM.**

Le code doit être **simple, efficace et ultra lisible, quitte à être un peu verbeux. Brutaliste.**

- Un fichier se comprend en le lisant de haut en bas, sans en ouvrir un autre.
- Pas de magie, pas d'abstraction ingénieuse, pas de helper générique pour économiser trois lignes.
- Le **schéma SQL fait foi** (`migrations/`) : les colonnes sont recopiées telles quelles dans le code.
- Une répétition lisible vaut mieux qu'une abstraction qui cache ce qui se passe.

### Écrire à plat, pas de code généré

- **SQL** : `SELECT` / `INSERT` / `UPDATE` écrits en entier, chaque colonne et chaque paramètre nommés un par un.
  Pas de `implode(array_map(...))` pour fabriquer colonnes, placeholders ou `SET`.
- **Vues** : un champ = une ligne `$form->input(...)` / `select(...)` / `checkbox(...)`. Pas de `foreach` qui génère des champs.
- **Selects** : une méthode nommée par liste dans le modèle (`PostModel::getSelectStatus()`), une variable par select dans la page (`$optionsForSelectStatus`).
- Les `foreach` qui **affichent** des lignes, des options ou des liens sont normaux. Ce sont les boucles qui **génèrent du code** qui sont interdites.

### Ne pas réécrire le framework

Avant d'écrire un helper, chercher dans `vendor/osd84/aurox/src/OsdAurox/` s'il existe déjà
(lecture du POST, unicité, pagination, flash, CSRF, i18n, cache, rate-limit...). Ne jamais modifier `vendor/` à la main.

### Nommage

- **SQL** : table au singulier (`post`), colonnes `snake_case`, clé primaire `id`, clé étrangère `<table>_id`, horodatage `xxx_at`,
  booléen `is_xxx` en `smallint` 0/1, socle `id, created_at, updated_at, created_by, updated_by`.
  Exception : `users` (et non `user`, mot réservé en PostgreSQL).
- **PHP** : `$maVariable`, `MA_CONSTANTE`, `MaClasse`, `maMethode()`, `True` / `False`.
- **JS** : `maVariable`, `$maVariable` pour un objet jQuery.
- **DOM** : `id="mon-id"`, `class="ma-classe"`, `name="mon_nom"` (le `name` suit la colonne SQL). Classes JS : `js-xxx`.
- **Méthodes de modèle** : `getXxx`, `getAllForXxx`, `getSelectXxx`, `countXxx`, `listQueries`, `insert`, `update`, `deleteSafe`.

### Quand on modifie le code

- Faire ce qui est demandé, sans élargir le périmètre. Commentaires en français.
- Dire ce qui n'a pas pu être testé.
- `php -l` sur chaque fichier touché.

---

## 2. Structure

```
aurox.php               bootstrap, en tête de chaque page
conf.php                config locale (modèle : conf_example.php), jamais versionnée
app/AppUrls.php         TOUTES les URLs ; une URL absente = 404
app/Auth.php            fin de connexion (session + redirection), seule copie de ce code
app/Models/             un modèle par table, méthodes statiques
public/                 racine web : une URL = un fichier
  auth/ member/ admin/ ajax/
templates/layout/       header.php, footer.php, flash.php
templates/admin/        sidebar.php
migrations/             AAAA-MM-JJ-nom-mysql.sql / -pgsql.sql
scripts/                CLI : php scripts/xxx.php [--dry-run]
translations/           fr.php, en.php
```

### Une page

```php
<?php
require_once __DIR__ . '/../../aurox.php';

use ...;

Sec::isAdminOrDie();                 // ou Sec::isLogged(), ou rien pour une page publique
$csrf = Csrf::protect();             // toute page qui reçoit un POST

$title = AppConfig::get('appName') . ' - Xxx';
$navActive = AppUrls::XXX;           // lien actif du menu

// -----------------------------------------------------------------------------
// Controller
// -----------------------------------------------------------------------------
$pdo = Dbo::getPdo();
...

// -----------------------------------------------------------------------------
// View
// -----------------------------------------------------------------------------
require_once APP_ROOT . '/templates/layout/header.php';
?>
...
<?php
require_once APP_ROOT . '/templates/layout/footer.php';
```

Ajouter une page : le fichier, sa constante dans `AppUrls`, le lien dans le menu (`$navLinks` du header ou `$sidebarLinks` de l'admin).

### Un modèle

- `const TABLE = 'post';`, méthodes statiques qui reçoivent `PDO $pdo`, des tableaux en entrée et en sortie.
- `BaseModel` fournit `get`, `getBy`, `getAll`, `getAllBy`, `exist`, `count`, `delete`, `check_uniq`. **Pas d'`insert()` ni d'`update()`** : on les écrit.
- `insert()` : `RETURNING id` en PostgreSQL (`Dbo::isPgsql()`), `lastInsertId()` en MySQL.
- `deleteSafe()` : rend `false` si une clé étrangère retient la ligne (SQLSTATE 23000 / 23503).
- `listQueries(?string $searchLike)` : `['query', 'count_query', 'bind_params']` pour le `Paginator`.
- Dates posées par PHP (`date('Y-m-d H:i:s')`) pour rester identiques en MySQL et PostgreSQL.

### Un formulaire d'édition (recette : `public/admin/post_edit.php`)

```php
$errors = [];
if (Sec::isPost()) {
    $postData = [                                                    // UNE ligne par colonne
        'title' => (string) Sec::getParam('title', 'nohtml', source: 1),
        'is_active' => Sec::getParam('is_active', 'bool_int', source: 1),   // case absente = 0
    ];
    if ($postData['title'] === '') {                                 // UNE règle = un if
        $errors['title'][] = 'Le titre est obligatoire';
    }
    if (empty($errors)) {
        XxxModel::update($pdo, $id, $postData, Sec::getUserIdOrDie());
        Flash::success('Mis à jour');
        Base::redirect(AppUrls::ADMIN_XXX_EDIT . '?id=' . (int) $id);   // POST puis redirect
    }
    Flash::error('Le formulaire contient des erreurs');
    $xxx = array_merge(['id' => $id ?: null], $xxx ?? [], $postData);    // on réaffiche la saisie
}
$validator = new FormValidator();                                    // APRÈS le POST
$validator->addErrors($errors);
$form = new Forms(AppUrls::ADMIN_XXX_EDIT, validator: $validator, entity: $xxx);
```

- Mots de passe lus bruts (`$_POST[...]` + `is_string`), jamais réaffichés, hachés par `password_hash()`.
- Chaînes vides en `NULL` pour les colonnes nullables.
- Ajouter une colonne = migration + `insert()` + `update()` + ligne `$postData` (+ validation) + champ dans la vue.

### Une liste admin (recette : `public/admin/posts.php`)

`Paginator` en pagination SQL, recherche `?search_text=` passée par `Sec::safeForLikeStrong()`,
une colonne = un `<th>` et un `<td>` écrits à la main, suppression par `<form method="post" data-confirm="...">` + `Csrf::inputHtml()`.

### Ajax (recette : `public/ajax/post_toggle_status.php`)

`Base::blockNoAjax()`, garde d'accès avec `redirect: false`, `Csrf::protect()`, réponse `OsdAurox\Api` (`status`, `errors[]`, `success[]`, `datas[]`) puis `returnJsonResponse()`.
Côté page : `$.post(url, {_csrf: ..., ...}, null, 'json')` dans un `<script nonce="<?= Sec::noneCsp() ?>">`.

---

## 3. Sécurité : jamais

- Jamais de saisie dans un nom de table, de colonne, un `ORDER BY` ou le `$select` de `BaseModel`. Les valeurs passent par des paramètres PDO.
- Jamais une donnée affichée sans **`Sec::hNoHtml()`** (`Sec::h()` seulement pour montrer des balises telles quelles, comme le contenu d'un article).
- Jamais de modification en GET. POST + CSRF + redirect.
- Jamais de `<script>` inline sans `nonce`, jamais d'attribut `onclick=` / `onchange=` (bloqués par la CSP) : du jQuery dans `public/js/script.js` ou dans un script avec nonce.
- Jamais `'debug' => true` en production (le lien « mot de passe oublié » y est affiché à l'écran).
- Jamais de jeton secret stocké en clair : on stocke son empreinte (`hash('sha256', ...)`), on compare l'empreinte.
- Jamais un message qui révèle si un email est inscrit (connexion, mot de passe oublié).
- `Ban::banIfHackAttempt()` n'est pas appelé sur les formulaires de mot de passe ou de texte libre : ses motifs (`;` en fin de chaîne, `OR`...) bannissent l'IP d'un utilisateur honnête.
