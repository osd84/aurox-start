# Aurox Start

**Une mini application complète pour apprendre [Aurox](https://aurox.fr), le framework PHP brutaliste, et démarrer un projet avec.**

Authentification (avec double authentification et mot de passe oublié), blog avec flux RSS, espace membre, administration, un appel ajax. Chaque fichier se lit de haut en bas, commentaires compris : c'est le code qui sert de tutoriel.

PHP + PDO + jQuery + [osd84/aurox](https://github.com/osd84/aurox-dist). Pas d'ORM, pas de routeur, pas de moteur de template, pas de build front.

Licence : **MIT**.

---

## Ce qu'il y a dedans

| | |
|---|---|
| **Authentification** | connexion / déconnexion, inscription publique avec captcha, rôles `admin` / `user`, blocage du compte après 5 échecs, rate-limit par IP, jeton CSRF renouvelé à chaque requête |
| **Double authentification** | TOTP (`OsdAurox\Otp`) : enrôlement par QR code dessiné dans le navigateur, étape de code à la connexion, anti-rejeu, désactivation par un admin si le téléphone est perdu |
| **Mot de passe oublié** | lien par mail (`Mailer`), jeton à usage unique valable 1 h, seule son empreinte SHA-256 est stockée, captcha + rate-limit ; en debug le lien s'affiche à l'écran |
| **Blog** | liste paginée (`Paginator`), article par slug, brouillons invisibles du public, flux RSS 2.0 (`/rss.php`) |
| **Espace membre** | profil (nom affiché), changement de mot de passe, activation de l'OTP |
| **Administration** | tableau de bord, CRUD des articles, CRUD des comptes, recherche, suppression en POST + CSRF |
| **Ajax** | publier / dépublier un article depuis la liste, réponse au format `OsdAurox\Api` |
| **Comment ça marche** | une page `/guide.php` qui explique le trajet d'une requête, l'anatomie d'une page, les modèles, les formulaires, la sécurité |
| **Scripts CLI** | `scripts/create_admin.php`, `scripts/seed_demo.php` |

Thème : le *Brutalism Dev Design* de [aurox.fr](https://aurox.fr) (noir, blanc, rouge, sans ombre ni arrondi), sur Bootstrap 5 pour la grille.

---

## Installation

Prérequis : **PHP ≥ 8.1** (`pdo_mysql` ou `pdo_pgsql`, `mbstring`, `gd`, `curl`), **MySQL / MariaDB** ou **PostgreSQL**, Apache2 en production.

```bash
git clone https://github.com/osd84/aurox-start.git mon-projet
cd mon-projet

# vendor/ est versionné (déploiement par simple copie). Pour le régénérer :
# composer install   (ajouter --ignore-platform-req=ext-imagick si imagick n'est pas installé)

cp conf_example.php conf.php      # puis l'éditer : base, appUrl, salt...
```

### Base de données

MySQL / MariaDB :

```bash
mysql -u root -p -e "CREATE DATABASE aurox_start CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p aurox_start < migrations/2026-10-12-schema-mysql.sql
mysql -u root -p aurox_start < migrations/2026-10-13-otp-password-reset-mysql.sql
```

PostgreSQL (dans `conf.php` : `'driver' => 'pgsql'`, `'port' => '5432'`) :

```bash
createdb aurox_start
psql -d aurox_start -f migrations/2026-10-12-schema-pgsql.sql
psql -d aurox_start -f migrations/2026-10-13-otp-password-reset-pgsql.sql
```

Les migrations se jouent dans l'ordre de leur date.

SSL vers la base : `'dbSsl' => true` est obligatoire pour une base distante (en MySQL, déposer le certificat CA dans `conf_mysql_ssl_cert/mysql-ca.pem`). En local, `'dbSsl' => false`.

### Premier compte et articles de démo

```bash
php scripts/create_admin.php admin@exemple.fr "Mon nom"   # le mot de passe est demandé au clavier
                                                           # sur un compte existant : le repasse admin, actif, sans OTP (porte de secours)
php scripts/seed_demo.php --dry-run                        # voir ce qui sera créé
php scripts/seed_demo.php
```

### Lancer

En local, le serveur intégré de PHP suffit :

```bash
php -S 127.0.0.1:8000 -t public
```

En production, Apache avec `public/` comme racine web (tout le reste est hors de la racine) :

```apache
<VirtualHost *:80>
    ServerName mon-site.example
    DocumentRoot /var/www/mon-projet/public/
    <Directory /var/www/mon-projet/public/>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Puis dans `conf.php` : `'debug' => false`, `'disableHttpsRedirect' => false`, `appUrl` en `https://` (elle sert aussi aux liens des mails et du flux RSS), un `salt` long et aléatoire, et les clés `mail*` pour que « mot de passe oublié » envoie vraiment ses mails.
PHP doit pouvoir écrire dans `logs/`, à la racine du projet (fichiers du WAF, dossiers de cache et de rate-limit) et dans `public/.htaccess` (bans IP).

---

## Visite du code

```
aurox.php              le bootstrap, inclus en tête de chaque page
conf_example.php       modèle de conf.php (jamais versionné)
app/
  AppUrls.php          toutes les URLs : une URL absente = 404
  Auth.php             la fin d'une connexion réussie (partagée par login et login_otp)
  Models/              UserModel, PostModel, PasswordResetModel : méthodes statiques, SQL écrit en entier
public/                la seule racine web, une URL = un fichier
  index.php, blog.php, post.php, rss.php, guide.php, 404.php
  auth/                login.php, login_otp.php, register.php, password_forgot.php, password_reset.php
  member/              profile.php, otp.php
  admin/               index, posts, post_edit, post_delete, users, user_edit, user_delete, user_otp_reset
  ajax/                post_toggle_status.php
templates/layout/      header.php, footer.php, flash.php
templates/admin/       sidebar.php
migrations/            schéma SQL daté, MySQL et PostgreSQL
scripts/               create_admin.php, seed_demo.php
translations/          fr.php, en.php
vendor/osd84/aurox/    le framework
```

À lire dans cet ordre : `aurox.php`, `app/AppUrls.php`, `public/blog.php`, `public/post.php`, `app/Models/PostModel.php`,
`public/auth/login.php`, `public/auth/login_otp.php`, `public/auth/password_forgot.php`, `public/auth/register.php`, `public/admin/post_edit.php`, `public/admin/posts.php`, `public/ajax/post_toggle_status.php`.

Une page a toujours la même forme :

```php
<?php
require_once __DIR__ . '/../../aurox.php';   // bootstrap

Sec::isAdminOrDie();                         // qui a le droit d'être ici
$csrf = Csrf::protect();                     // un POST sans jeton valide s'arrête là

// Controller : paramètres, requêtes, POST, redirections
$pdo = Dbo::getPdo();
$post = PostModel::get($pdo, (int) Sec::getParam('id', 'int'));

// View
require_once APP_ROOT . '/templates/layout/header.php';
?>
<h1><?= Sec::hNoHtml($post['title']) ?></h1>
<?php
require_once APP_ROOT . '/templates/layout/footer.php';
```

Les conventions (nommage, SQL à plat, formulaires, sécurité) sont dans [`CLAUDE.md`](CLAUDE.md).

---

## Recettes

**Ajouter une page** : créer le fichier dans `public/`, déclarer sa constante dans `app/AppUrls.php`, ajouter le lien au menu (`templates/layout/header.php`).

**Ajouter une colonne** : une migration datée dans `migrations/`, la colonne dans `insert()` et `update()` du modèle, une ligne dans `$postData` de la page d'édition (+ sa validation), un champ `$form->input(...)` dans la vue. Rien n'est deviné.

**Ajouter une table** : la migration, un modèle dans `app/Models/` (copier `PostModel`), une liste + une fiche + une suppression dans `public/admin/` (copier `posts.php`, `post_edit.php`, `post_delete.php`), les URLs dans `AppUrls`, le lien dans `templates/admin/sidebar.php`.

---

## Sécurité

- Entrées filtrées par `Sec::getParam('clé', 'type')`, sorties échappées par `Sec::hNoHtml()`.
- SQL uniquement en requêtes préparées, paramètres nommés.
- Tout formulaire : `Csrf::protect()` + `Csrf::inputHtml()`. Toute modification : POST puis redirect.
- CSP avec nonce activée (`'nonce' => true`) : les scripts inline portent `nonce="<?= Sec::noneCsp() ?>"`, aucun `onclick="..."`.
- Mots de passe : `password_hash()` / `password_verify()`, session régénérée à la connexion.
- Connexion : 20 échecs par heure et par IP, compte désactivé après 5 échecs, mots de passe et codes OTP confondus (un admin le réactive depuis sa fiche).
- OTP : un code ne sert qu'une fois (`otp_last_timeslice`), 10 codes faux par IP toutes les 10 minutes.
- Mot de passe oublié : même réponse que le compte existe ou non, jeton haché en base, `Referrer-Policy: no-referrer` sur la page du lien. **Ne jamais laisser `debug => true` en production** : le lien y serait affiché à l'écran.
- Les limites connues d'aurox sont listées dans le [README d'aurox-dist](https://github.com/osd84/aurox-dist#sécurité--limitations).

---

## Pour aller plus loin

Idées de suite, dans l'ordre où elles apportent le plus :

1. **Codes de secours OTP** : une dizaine de codes à usage unique, hachés, pour se connecter sans téléphone.
2. **Catégories ou tags** sur les articles : une table de référence, un many-to-many.
3. **Image d'article** : upload + `OsdAurox\Image` pour redimensionner.
4. **Commentaires** modérés, avec `Captcha` et rate-limit.
5. **Formulaire de contact** : `Mailer` + `Captcha`.
6. **`sitemap.xml`**, sur le modèle de `rss.php`.
7. **Sélecteur de langue** : `I18n` est déjà branché (`translations/`).

---

## Liens

- Documentation : https://aurox.fr
- Framework : https://github.com/osd84/aurox-dist (Packagist : `osd84/aurox`)
