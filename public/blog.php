<?php
// -----------------------------------------------------------------------------
// Blog : la liste des articles publiés, paginée côté SQL.
// Montre : OsdAurox\Paginator (LIMIT / OFFSET calculés depuis ?page=), une jointure,
// l'échappement de chaque sortie avec Sec::hNoHtml().
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../aurox.php';

use App\AppUrls;
use App\Models\PostModel;
use OsdAurox\AppConfig;
use OsdAurox\Dbo;
use OsdAurox\I18n;
use OsdAurox\Paginator;
use OsdAurox\Sec;

$title = AppConfig::get('appName') . ' - Blog';
$navActive = AppUrls::BLOG;

// -----------------------------------------------------------------------------
// Controller
// -----------------------------------------------------------------------------
$pdo = Dbo::getPdo();

// Le modèle rend les deux requêtes (lignes + total). Le Paginator ajoute LIMIT / OFFSET
// selon ?page= et ?per_page= dans l'URL, et compte les pages avec count_query.
$queries = PostModel::listPublishedQueries();

$paginator = new Paginator(
    $pdo,
    $queries['query'],
    $queries['count_query'],
    AppUrls::BLOG,
    bind_params: $queries['bind_params'],
    per_page: 5,
);

$posts = $paginator->getItems();

// -----------------------------------------------------------------------------
// View
// -----------------------------------------------------------------------------
require_once APP_ROOT . '/templates/layout/header.php';
?>
<main>
    <h1>Blog</h1>
    <p class="ax-meta">
        <?= (int) $paginator->getTotalItems() ?> article(s) publié(s) · <a href="<?= AppUrls::RSS ?>">flux RSS</a>
    </p>

    <?php if (!$posts): ?>
        <p class="text-muted">Aucun article publié pour l'instant.</p>
    <?php endif; ?>

    <?php foreach ($posts as $post): ?>
        <article class="ax-post-item">
            <h2><a href="<?= AppUrls::POST ?>?slug=<?= Sec::hNoHtml($post['slug']) ?>"><?= Sec::hNoHtml($post['title']) ?></a></h2>
            <p class="ax-meta">
                <?= I18n::date($post['published_at']) ?> · par <?= Sec::hNoHtml($post['author_username']) ?>
            </p>
            <p><?= Sec::hNoHtml($post['excerpt']) ?></p>
            <a href="<?= AppUrls::POST ?>?slug=<?= Sec::hNoHtml($post['slug']) ?>">Lire l'article →</a>
        </article>
    <?php endforeach; ?>

    <?php if ($paginator->getTotalPages() > 1): ?>
        <?= $paginator->renderPagination() ?>
    <?php endif; ?>
</main>
<?php
require_once APP_ROOT . '/templates/layout/footer.php';
