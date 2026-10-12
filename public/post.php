<?php
// -----------------------------------------------------------------------------
// Un article, trouvé par son slug : /post.php?slug=mon-article
// Montre : Sec::getParam() (filtre d'entrée typé), Base::redirect404(),
// l'affichage d'un texte saisi sans jamais faire confiance à son HTML.
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../aurox.php';

use App\AppUrls;
use App\Models\PostModel;
use OsdAurox\AppConfig;
use OsdAurox\Base;
use OsdAurox\Dbo;
use OsdAurox\I18n;
use OsdAurox\Sec;

// -----------------------------------------------------------------------------
// Controller
// -----------------------------------------------------------------------------
$pdo = Dbo::getPdo();

// 'slug' : ne garde que a-z, 0-9 et "-". source: 0 = GET uniquement
$slug = Sec::getParam('slug', 'slug', source: 0);
if (!$slug) {
    Base::redirect404('post');
}

// Un brouillon n'est jamais visible ici : la requête filtre sur status = 'published'
$post = PostModel::getPublishedBySlug($pdo, $slug);
if (!$post) {
    Base::redirect404('post');
}

$title = $post['title'] . ' - ' . AppConfig::get('appName');
$description = $post['excerpt'];
$navActive = AppUrls::BLOG;

// -----------------------------------------------------------------------------
// View
// -----------------------------------------------------------------------------
require_once APP_ROOT . '/templates/layout/header.php';
?>
<main>
    <p><a href="<?= AppUrls::BLOG ?>">← Retour au blog</a></p>

    <article>
        <h1><?= Sec::hNoHtml($post['title']) ?></h1>
        <p class="ax-meta">
            Publié le <?= I18n::date($post['published_at']) ?> par <?= Sec::hNoHtml($post['author_username']) ?>
            <?php if ($post['updated_at']): ?>
                · mis à jour le <?= I18n::date($post['updated_at']) ?>
            <?php endif; ?>
        </p>

        <?php if ($post['excerpt']): ?>
            <p class="ax-lead"><strong><?= Sec::hNoHtml($post['excerpt']) ?></strong></p>
        <?php endif; ?>

        <?php
        // Le contenu est du texte brut : on échappe PUIS on transforme les retours ligne en <br>.
        // Dans l'autre ordre, un <script> saisi passerait.
        // Sec::h() et non Sec::hNoHtml() : on veut AFFICHER un "<?php" ou un "a < b" tapé dans l'article
        // (rendu inoffensif par htmlspecialchars), pas le faire disparaître avec strip_tags.
        ?>
        <div class="ax-post-content">
            <?= nl2br(Sec::h($post['content'])) ?>
        </div>
    </article>

    <?php if (Sec::isAdminBool()): ?>
        <p class="mt-4">
            <a class="ax-btn" href="<?= AppUrls::ADMIN_POST_EDIT ?>?id=<?= (int) $post['id'] ?>">Modifier cet article</a>
        </p>
    <?php endif; ?>
</main>
<?php
require_once APP_ROOT . '/templates/layout/footer.php';
