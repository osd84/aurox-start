<?php
// -----------------------------------------------------------------------------
// Comment ça marche : la visite guidée du code de cette application.
// Les extraits de code sont des chaînes PHP (nowdoc) affichées avec Sec::h() :
// les balises s'affichent telles quelles, sans jamais être interprétées.
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../aurox.php';

use App\AppUrls;
use OsdAurox\AppConfig;
use OsdAurox\Sec;

$title = AppConfig::get('appName') . ' - Comment ça marche';
$navActive = AppUrls::GUIDE;

// -----------------------------------------------------------------------------
// Controller : rien à calculer, seulement les extraits de code à afficher
// -----------------------------------------------------------------------------

$codeTree = <<<'TXT'
aurox.php              le bootstrap, inclus en tête de chaque page
conf.php               la configuration (copie de conf_example.php, jamais versionnée)
app/
  AppUrls.php          toutes les URLs du site : c'est aussi le routeur
  Auth.php             la fin d'une connexion réussie (session, redirection)
  Models/
    UserModel.php      les comptes
    PostModel.php      les articles
    PasswordResetModel.php  les liens "mot de passe oublié"
public/                la SEULE racine web
  index.php            accueil
  blog.php, post.php   le blog
  rss.php              le flux RSS
  guide.php            cette page
  auth/                connexion (+ étape OTP), inscription, mot de passe oublié
  member/              espace membre (profil, double authentification)
  admin/               administration (articles, comptes)
  ajax/                endpoints JSON
  css/ js/ img/        fichiers statiques
templates/
  layout/              header.php, footer.php, flash.php
  admin/sidebar.php    menu de l'administration
migrations/            schéma SQL daté (MySQL et PostgreSQL)
scripts/               scripts CLI : create_admin.php, seed_demo.php
translations/          fr.php, en.php
vendor/osd84/aurox/    le framework (ne jamais le modifier à la main)
TXT;

$codePage = <<<'PHP'
<?php
require_once __DIR__ . '/../../aurox.php';   // 1. le bootstrap

use App\AppUrls;
use App\Models\PostModel;
use OsdAurox\Csrf;
use OsdAurox\Dbo;
use OsdAurox\Sec;

Sec::isAdminOrDie();                         // 2. qui a le droit d'être ici ?
$csrf = Csrf::protect();                     //    un POST sans jeton valide s'arrête là

// -----------------------------------------------------------------------------
// Controller
// -----------------------------------------------------------------------------
$pdo = Dbo::getPdo();                        // 3. lire, calculer, rediriger
$id = (int) Sec::getParam('id', 'int');
$post = PostModel::get($pdo, $id);

// -----------------------------------------------------------------------------
// View
// -----------------------------------------------------------------------------
require_once APP_ROOT . '/templates/layout/header.php';   // 4. afficher
?>
<h1><?= Sec::hNoHtml($post['title']) ?></h1>
<?php
require_once APP_ROOT . '/templates/layout/footer.php';
PHP;

$codeModel = <<<'PHP'
class PostModel extends BaseModel
{
    public const TABLE = 'post';

    public static function update(PDO $pdo, int $id, array $post, int $byUserId): void
    {
        $stmt = $pdo->prepare('UPDATE post SET
                    title = :title,
                    slug = :slug,
                    ...
                WHERE id = :id');
        $stmt->execute([
            'title' => $post['title'],
            'slug' => $post['slug'],
            ...
            'id' => $id,
        ]);
    }
}
PHP;

$codeForm = <<<'PHP'
$errors = [];

if (Sec::isPost()) {
    // 1. lire : une ligne par colonne
    $postData = [
        'title' => (string) Sec::getParam('title', 'nohtml', source: 1),
        'status' => (string) Sec::getParam('status', 'aZ09', source: 1),
    ];

    // 2. valider : une règle = un if
    if ($postData['title'] === '') {
        $errors['title'][] = 'Le titre est obligatoire';
    }

    // 3. enregistrer, puis rediriger (un F5 ne renvoie rien)
    if (empty($errors)) {
        PostModel::update($pdo, $id, $postData, Sec::getUserIdOrDie());
        Flash::success('Article mis à jour');
        Base::redirect(AppUrls::ADMIN_POST_EDIT . '?id=' . $id);
    }

    // 4. sinon : on réaffiche la saisie
    $post = array_merge($post, $postData);
}

$validator = new FormValidator();
$validator->addErrors($errors);
$form = new Forms(AppUrls::ADMIN_POST_EDIT, validator: $validator, entity: $post);

// dans la vue
<?= $form->formStart() ?>                       // <form>, _csrf, id caché, résumé des erreurs
<?= $form->input('title', label: 'Titre', required: true) ?>
<?= $form->select($optionsForSelectStatus, 'status', label: 'Statut') ?>
<?= $form->formEnd() ?>
PHP;

$codeAddPage = <<<'TXT'
1. créer public/contact.php           (copier une page existante : bootstrap, Controller, View)
2. déclarer l'URL dans app/AppUrls.php   public const CONTACT = '/contact.php';
3. ajouter le lien dans le menu          templates/layout/header.php, tableau $navLinks
TXT;

$codeAddColumn = <<<'TXT'
1. migrations/AAAA-MM-JJ-post-cover.sql   ALTER TABLE post ADD cover_url VARCHAR(255) NULL;
2. app/Models/PostModel.php               la colonne dans insert() et dans update()
3. public/admin/post_edit.php             une ligne dans $postData, sa validation
4. public/admin/post_edit.php (vue)       $form->input('cover_url', label: 'Image')
5. public/post.php                        l'afficher, avec Sec::hNoHtml()
TXT;

// -----------------------------------------------------------------------------
// View
// -----------------------------------------------------------------------------
require_once APP_ROOT . '/templates/layout/header.php';
?>
<div class="row">
    <div class="col-md-3 d-none d-md-block">
        <aside class="ax-sidebar">
            <p class="ax-sidebar-title">Sommaire</p>
            <ul>
                <li><a href="#arborescence">Arborescence</a></li>
                <li><a href="#requete">Le trajet d'une requête</a></li>
                <li><a href="#page">Anatomie d'une page</a></li>
                <li><a href="#modele">Les modèles</a></li>
                <li><a href="#formulaire">Un formulaire</a></li>
                <li><a href="#auth">L'authentification</a></li>
                <li><a href="#securite">La sécurité</a></li>
                <li><a href="#lire">Les fichiers à lire</a></li>
                <li><a href="#recettes">Recettes</a></li>
            </ul>
        </aside>
    </div>
    <div class="col-md-9">
        <main>
            <h1>Comment ça marche</h1>
            <p>
                Aurox n'est pas un framework qui décide à votre place : c'est une collection de classes PHP
                (<code>OsdAurox\...</code>) que vos pages appellent. Pas de routeur, pas de conteneur, pas d'ORM, pas de moteur de template.
                <strong>Une URL = un fichier PHP</strong>, qui se lit de haut en bas.
            </p>

            <h2 id="arborescence">Arborescence</h2>
            <pre><code><?= Sec::h($codeTree) ?></code></pre>
            <p>
                Apache ne sert que <code>public/</code>. Tout le reste (configuration, modèles, templates, migrations) est hors de la racine web,
                et chaque dossier porte en plus un <code>.htaccess</code> <code>Deny from all</code>.
            </p>

            <h2 id="requete">Le trajet d'une requête</h2>
            <ol>
                <li>Apache reçoit <code>/blog.php</code> et exécute <code>public/blog.php</code>.</li>
                <li>La page inclut <code>aurox.php</code>, qui charge <code>conf.php</code> (<code>AppConfig</code>), règle les erreurs et les logs,
                    démarre la session, pose les en-têtes de sécurité (CSP avec nonce, HSTS en production), ouvre la base (<code>Dbo</code>),
                    passe le WAF (<code>Ban</code>) et prépare les traductions (<code>I18n</code>).</li>
                <li><code>AppUrls::existOr404()</code> vérifie que l'URL est déclarée dans <code>app/AppUrls.php</code>. Sinon : 404.</li>
                <li>La partie <em>Controller</em> de la page lit les paramètres, interroge les modèles, traite le POST et redirige.</li>
                <li>La partie <em>View</em> inclut le header, écrit le HTML, inclut le footer.</li>
            </ol>

            <h2 id="page">Anatomie d'une page</h2>
            <pre><code><?= Sec::h($codePage) ?></code></pre>
            <p>
                Toujours dans cet ordre : bootstrap, garde d'accès, CSRF, Controller, View.
                La page pose aussi quelques variables lues par le header : <code>$title</code>, <code>$description</code>, <code>$navActive</code>.
            </p>

            <h2 id="modele">Les modèles</h2>
            <p>
                Un modèle étend <code>OsdAurox\BaseModel</code> et ne contient que des <strong>méthodes statiques</strong> qui reçoivent le PDO.
                Les données circulent en <strong>tableaux</strong>, du SQL jusqu'à la vue. <code>BaseModel</code> fournit
                <code>get()</code>, <code>getBy()</code>, <code>getAll()</code>, <code>getAllBy()</code>, <code>count()</code>, <code>exist()</code>,
                <code>delete()</code>, <code>check_uniq()</code>… mais <strong>ni <code>insert()</code> ni <code>update()</code></strong> : on les écrit, colonne par colonne.
            </p>
            <pre><code><?= Sec::h($codeModel) ?></code></pre>
            <p>
                Le schéma SQL fait foi (<code>migrations/</code>). Conventions : clé primaire <code>id</code>, clé étrangère <code>&lt;table&gt;_id</code>,
                dates <code>xxx_at</code>, booléens <code>is_xxx</code> en <code>smallint</code> 0/1, et le socle
                <code>created_at</code> / <code>updated_at</code> / <code>created_by</code> / <code>updated_by</code>.
            </p>

            <h2 id="formulaire">Un formulaire</h2>
            <pre><code><?= Sec::h($codeForm) ?></code></pre>
            <p>
                <code>Forms</code> génère des champs déjà échappés et affiche sous chacun les erreurs du <code>FormValidator</code>.
                Exemples complets : <code>public/admin/post_edit.php</code>, <code>public/admin/user_edit.php</code>, <code>public/auth/register.php</code>.
            </p>

            <h2 id="auth">L'authentification</h2>
            <p>
                Il n'y a pas de classe « Auth » : être connecté, c'est avoir <code>$_SESSION['user']</code>, un tableau
                <code>['id', 'username', 'email', 'role']</code> rempli par <code>public/auth/login.php</code>. Tout aurox s'appuie dessus :
            </p>
            <table class="table ax-table">
                <tbody>
                <tr><td>Sec::isLogged()</td><td>pas connecté : Flash « Accès refusé » et redirection vers <code>AppUrls::LOGIN</code></td></tr>
                <tr><td>Sec::isAdminOrDie()</td><td>pareil, mais il faut <code>role = 'admin'</code></td></tr>
                <tr><td>Sec::isRoleOrDie('editor')</td><td>pour un rôle à vous</td></tr>
                <tr><td>Sec::isLoggedBool(), isAdminBool()</td><td>les mêmes tests, sans redirection (pour un menu, un bouton)</td></tr>
                <tr><td>Sec::getUserIdOrDie()</td><td>l'id du compte connecté (<code>created_by</code>, <code>updated_by</code>…)</td></tr>
                </tbody>
            </table>
            <p>
                Le mot de passe est haché par <code>password_hash()</code>. Le login compare avec <code>password_verify()</code>, renouvelle
                l'identifiant de session, limite les échecs par IP (<code>Sec::setRateLimit()</code>) et bloque un compte après 5 échecs.
                L'inscription ajoute un <code>Captcha</code> sans service externe.
            </p>
            <h3>Double authentification (OTP)</h3>
            <p>
                <code>OsdAurox\Otp</code> implémente TOTP (RFC 6238) sans dépendance : les codes de Aegis, FreeOTP, Google Authenticator…
                Le membre l'active dans <code>public/member/otp.php</code> : une clé est générée (<code>Otp::generateSecret()</code>),
                affichée en QR code dessiné <em>dans le navigateur</em> (<code>Otp::provisioningUri()</code>), puis confirmée par un premier code.
                À la connexion, <code>login.php</code> vérifie le mot de passe, note en session le compte en attente
                (<code>$_SESSION['otp_pending']</code>, 5 minutes) et passe la main à <code>login_otp.php</code>.
                <code>Otp::verify()</code> rend la tranche de 30 s du code accepté : on la stocke (<code>otp_last_timeslice</code>)
                et un code déjà servi est refusé. Téléphone perdu : un admin désactive l'OTP depuis la fiche du compte,
                ou <code>php scripts/create_admin.php</code> pour le dernier admin.
            </p>
            <h3>Mot de passe oublié</h3>
            <p>
                <code>password_forgot.php</code> crée un jeton aléatoire (<code>random_bytes(32)</code>) et l'envoie par mail avec <code>Mailer</code>.
                La base ne garde que son empreinte SHA-256 : qui lit la base ne peut pas fabriquer un lien.
                Le lien vaut une heure, sert une fois, et chaque nouvelle demande annule la précédente.
                La réponse est la même que l'email existe ou non. En mode debug, le lien est aussi affiché à l'écran pour tester sans serveur mail.
            </p>

            <h2 id="securite">La sécurité</h2>
            <ul>
                <li><strong>Filtrer en entrée</strong> : <code>Sec::getParam('id', 'int')</code>, <code>'email'</code>, <code>'slug'</code>, <code>'nohtml'</code>… jamais <code>$_GET</code> brut dans du SQL.</li>
                <li><strong>Échapper en sortie</strong> : <code>Sec::hNoHtml($valeur)</code> pour toute donnée affichée (<code>Sec::h()</code> si l'on veut montrer des balises telles quelles).</li>
                <li><strong>SQL</strong> : des paramètres PDO (<code>:nom</code>), toujours. Jamais une saisie dans un nom de colonne, un <code>ORDER BY</code> ou le <code>$select</code> de <code>BaseModel</code>.</li>
                <li><strong>CSRF</strong> : <code>Csrf::protect()</code> en tête de toute page qui reçoit un POST, <code>Csrf::inputHtml()</code> dans le formulaire.</li>
                <li><strong>Modifier = POST</strong>, puis redirect. Une suppression n'est jamais un lien.</li>
                <li><strong>CSP</strong> : avec <code>'nonce' => true</code>, un <code>&lt;script&gt;</code> inline doit porter <code>nonce="&lt;?= Sec::noneCsp() ?&gt;"</code>, et les <code>onclick="..."</code> sont bloqués.</li>
                <li><strong>Production</strong> : <code>'debug' => false</code>, HTTPS forcé, <code>salt</code> renseigné.</li>
            </ul>

            <h2 id="lire">Les fichiers à lire, dans l'ordre</h2>
            <table class="table ax-table">
                <tbody>
                <tr><td>aurox.php</td><td>ce qui se passe avant chaque page</td></tr>
                <tr><td>app/AppUrls.php</td><td>les URLs, et le 404 automatique</td></tr>
                <tr><td>public/blog.php</td><td>une page simple : modèle + Paginator + vue</td></tr>
                <tr><td>public/post.php</td><td>un paramètre GET, un 404, l'échappement</td></tr>
                <tr><td>app/Models/PostModel.php</td><td>un modèle complet</td></tr>
                <tr><td>public/auth/login.php</td><td>la connexion, la session, le rate-limit</td></tr>
                <tr><td>public/auth/login_otp.php</td><td>l'étape 2 : le code TOTP, l'anti-rejeu</td></tr>
                <tr><td>public/auth/password_forgot.php</td><td>un jeton à usage unique, un mail avec <code>Mailer</code></td></tr>
                <tr><td>public/auth/register.php</td><td>Forms, FormValidator, Captcha</td></tr>
                <tr><td>public/admin/post_edit.php</td><td>la recette d'une fiche (création + modification)</td></tr>
                <tr><td>public/admin/posts.php</td><td>une liste admin, la recherche, la suppression, l'ajax</td></tr>
                <tr><td>public/ajax/post_toggle_status.php</td><td>un endpoint JSON au format <code>Api</code></td></tr>
                <tr><td>public/rss.php</td><td>du XML écrit à la main, échappé en <code>ENT_XML1</code></td></tr>
                </tbody>
            </table>

            <h2 id="recettes">Recettes</h2>
            <h3>Ajouter une page</h3>
            <pre><code><?= Sec::h($codeAddPage) ?></code></pre>
            <h3>Ajouter une colonne à une table</h3>
            <pre><code><?= Sec::h($codeAddColumn) ?></code></pre>
            <p>Rien n'est deviné : si une étape manque, ça se voit en lisant le fichier.</p>

            <h2>Pour aller plus loin</h2>
            <ul>
                <li>La documentation : <a href="<?= AppUrls::AUROX_DOC ?>" target="_blank" rel="noopener">aurox.fr</a></li>
                <li>Le code du framework, court et commenté : <code>vendor/osd84/aurox/src/OsdAurox/</code>, ou
                    <a href="<?= AppUrls::AUROX_GITHUB ?>" target="_blank" rel="noopener">aurox-dist sur GitHub</a></li>
                <li>Les conventions de ce projet : <code>CLAUDE.md</code> à la racine</li>
            </ul>
        </main>
    </div>
</div>
<?php
require_once APP_ROOT . '/templates/layout/footer.php';
