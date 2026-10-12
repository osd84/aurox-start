<?php
// -----------------------------------------------------------------------------
// Page d'accueil.
// Toutes les pages ont la même forme : le bootstrap, une partie Controller
// (lecture des paramètres, requêtes, redirections), puis une partie View (HTML).
// Un fichier se lit de haut en bas, sans en ouvrir un autre.
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../aurox.php';

use App\AppUrls;
use App\Models\PostModel;
use OsdAurox\AppConfig;
use OsdAurox\Dbo;
use OsdAurox\I18n;
use OsdAurox\Sec;

$title = AppConfig::get('appName') . ' - Accueil';
$navActive = AppUrls::HOME;

// -----------------------------------------------------------------------------
// Controller
// -----------------------------------------------------------------------------
$pdo = Dbo::getPdo();

$latestPosts = PostModel::getLatestPublished($pdo, 3);

// -----------------------------------------------------------------------------
// View
// -----------------------------------------------------------------------------
require_once APP_ROOT . '/templates/layout/header.php';
?>
<main class="ax-home">
    <section class="ax-hero">
        <h1>Aurox Start.</h1>
        <p class="ax-lead">
            Une petite application complète, écrite avec <strong>Aurox</strong>, pour apprendre le framework en lisant du vrai code :
            <strong>authentification</strong>, <strong>blog</strong>, <strong>espace membre</strong> et <strong>administration</strong>.
            Chaque fichier se lit de haut en bas, commentaires compris. Copiez, cassez, recommencez.
        </p>

        <div class="ax-install">
            <span>$</span>
            <code>composer install &amp;&amp; php -S 127.0.0.1:8000 -t public</code>
        </div>

        <div class="ax-btns">
            <a class="ax-btn ax-btn-primary" href="<?= AppUrls::GUIDE ?>">Comment ça marche</a>
            <a class="ax-btn" href="<?= AppUrls::BLOG ?>">Le blog</a>
            <?php if (Sec::isLoggedBool()): ?>
                <a class="ax-btn" href="<?= AppUrls::MEMBER_PROFILE ?>"><?= I18n::t('hello_user', ['name' => $_SESSION['user']['username']]) ?></a>
            <?php else: ?>
                <a class="ax-btn" href="<?= AppUrls::LOGIN ?>">Connexion</a>
            <?php endif; ?>
        </div>
    </section>

    <h2>Ce que contient l'application</h2>
    <div class="ax-grid">
        <div>
            <h3>Authentification</h3>
            <p>Connexion, déconnexion, inscription avec captcha, rôles <code>admin</code> / <code>user</code>, blocage après 5 échecs, rate-limit.</p>
        </div>
        <div>
            <h3>Blog</h3>
            <p>Liste paginée par <code>Paginator</code>, article par slug, brouillons invisibles du public.</p>
        </div>
        <div>
            <h3>Administration</h3>
            <p>CRUD des articles et des comptes : <code>Forms</code>, validation à plat, CSRF, suppression en POST.</p>
        </div>
        <div>
            <h3>Ajax</h3>
            <p>Publier / dépublier depuis la liste, en jQuery, réponse au format <code>OsdAurox\Api</code>.</p>
        </div>
    </div>

    <h2>Derniers articles</h2>
    <?php if (!$latestPosts): ?>
        <p class="text-muted">
            Aucun article publié pour l'instant.
            Lancez <code>php scripts/seed_demo.php</code> pour en créer quelques-uns, ou écrivez le premier depuis l'administration.
        </p>
    <?php endif; ?>
    <?php foreach ($latestPosts as $post): ?>
        <article class="ax-post-item">
            <h3><a href="<?= AppUrls::POST ?>?slug=<?= Sec::hNoHtml($post['slug']) ?>"><?= Sec::hNoHtml($post['title']) ?></a></h3>
            <p class="ax-meta"><?= I18n::date($post['published_at']) ?></p>
            <p><?= Sec::hNoHtml($post['excerpt']) ?></p>
        </article>
    <?php endforeach; ?>
    <p><a href="<?= AppUrls::BLOG ?>">Tous les articles →</a></p>
</main>
<?php
require_once APP_ROOT . '/templates/layout/footer.php';
