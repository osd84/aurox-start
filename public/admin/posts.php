<?php
// -----------------------------------------------------------------------------
// Administration : liste des articles.
//
// Montre : Paginator en pagination SQL avec recherche LIKE, Sec::safeForLikeStrong(),
// suppression par formulaire POST + CSRF, et un appel ajax (publier / dépublier)
// écrit en jQuery dans un <script nonce="...">.
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../../aurox.php';

use App\AppUrls;
use App\Models\PostModel;
use OsdAurox\AppConfig;
use OsdAurox\Csrf;
use OsdAurox\Dbo;
use OsdAurox\I18n;
use OsdAurox\Paginator;
use OsdAurox\Sec;

Sec::isAdminOrDie();
$csrf = Csrf::protect();

$title = AppConfig::get('appName') . ' - Articles';
$navActive = AppUrls::ADMIN_HOME;
$sidebarActive = AppUrls::ADMIN_POSTS;

// -----------------------------------------------------------------------------
// Controller
// -----------------------------------------------------------------------------
$pdo = Dbo::getPdo();

// safeForLikeStrong() : garde lettres, chiffres et espaces, échappe % et _, rend '' sous 2 caractères
$searchText = Sec::safeForLikeStrong($_GET['search_text'] ?? null);
$searchLike = null;
$paginatorUrl = AppUrls::ADMIN_POSTS;
if ($searchText) {
    $searchLike = mb_strtoupper('%' . $searchText . '%', 'UTF-8');
    // la recherche doit survivre au changement de page : elle part dans l'URL des liens
    $paginatorUrl = AppUrls::ADMIN_POSTS . '?search_text=' . urlencode($searchText);
}

$queries = PostModel::listQueries($searchLike);

$paginator = new Paginator(
    $pdo,
    $queries['query'],
    $queries['count_query'],
    $paginatorUrl,
    bind_params: $queries['bind_params'],
    per_page: 20,
);

$posts = $paginator->getItems();

// -----------------------------------------------------------------------------
// View
// -----------------------------------------------------------------------------
require_once APP_ROOT . '/templates/layout/header.php';
?>
<div class="row">
    <div class="col-md-3">
        <?php require APP_ROOT . '/templates/admin/sidebar.php'; ?>
    </div>
    <div class="col-md-9">
        <main class="ax-wide">
            <h1>Articles</h1>

            <div class="ax-toolbar">
                <a class="ax-btn ax-btn-primary" href="<?= AppUrls::ADMIN_POST_EDIT ?>">+ Écrire un article</a>

                <form method="get" action="<?= AppUrls::ADMIN_POSTS ?>" class="ax-search">
                    <input type="text" name="search_text" class="form-control" placeholder="Titre ou slug"
                           value="<?= Sec::hNoHtml($searchText) ?>">
                    <button type="submit" class="ax-btn">Chercher</button>
                </form>
            </div>

            <?php // le jeton CSRF que le JS enverra avec chaque appel ajax ?>
            <table class="table ax-table" id="posts" data-csrf="<?= Csrf::getToken() ?>">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Titre</th>
                    <th>Auteur</th>
                    <th>Statut</th>
                    <th>Publié le</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($posts as $post): ?>
                    <?php $editUrl = AppUrls::ADMIN_POST_EDIT . '?id=' . (int) $post['id']; ?>
                    <tr>
                        <td><?= (int) $post['id'] ?></td>
                        <td>
                            <a href="<?= $editUrl ?>"><?= Sec::hNoHtml($post['title']) ?></a><br>
                            <small class="text-muted"><?= Sec::hNoHtml($post['slug']) ?></small>
                        </td>
                        <td><?= Sec::hNoHtml($post['author_username']) ?></td>
                        <td>
                            <button type="button" class="ax-badge ax-badge-<?= Sec::hNoHtml($post['status']) ?> js-toggle-status"
                                    data-id="<?= (int) $post['id'] ?>" title="Cliquer pour changer le statut">
                                <?= $post['status'] === PostModel::STATUS_PUBLISHED ? 'Publié' : 'Brouillon' ?>
                            </button>
                        </td>
                        <td class="js-published-at"><?= $post['published_at'] ? I18n::date($post['published_at']) : '—' ?></td>
                        <td class="text-end text-nowrap">
                            <?php if ($post['status'] === PostModel::STATUS_PUBLISHED): ?>
                                <a class="ax-btn ax-btn-sm" href="<?= AppUrls::POST ?>?slug=<?= Sec::hNoHtml($post['slug']) ?>">Voir</a>
                            <?php endif; ?>
                            <a class="ax-btn ax-btn-sm" href="<?= $editUrl ?>">Modifier</a>
                            <?php // suppression : POST + jeton CSRF, confirmation par data-confirm (voir public/js/script.js) ?>
                            <form action="<?= AppUrls::ADMIN_POST_DELETE ?>" method="post" class="d-inline"
                                  data-confirm="Supprimer l'article « <?= Sec::hNoHtml($post['title']) ?> » ?">
                                <?= Csrf::inputHtml() ?>
                                <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
                                <button type="submit" class="ax-btn ax-btn-sm ax-btn-danger">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$posts): ?>
                    <tr><td colspan="6" class="text-muted">Aucun article</td></tr>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if ($paginator->getTotalPages() > 1): ?>
                <?= $paginator->renderPagination() ?>
            <?php endif; ?>
            <p class="ax-meta"><?= (int) $paginator->getTotalItems() ?> article(s)</p>
        </main>
    </div>
</div>

<?php
// Script inline : il porte le nonce de la page, sinon la CSP (conf 'nonce' => true) le bloque.
?>
<script nonce="<?= Sec::noneCsp() ?>">
    $(function () {
        var csrfToken = $('#posts').data('csrf');

        // Publier / dépublier sans recharger la page.
        // Le serveur répond au format OsdAurox\Api : {status, success[], errors[], datas{}}
        $('#posts').on('click', '.js-toggle-status', function () {
            var $button = $(this);
            var $row = $button.closest('tr');

            $.post('<?= AppUrls::AJAX_POST_TOGGLE_STATUS ?>', {_csrf: csrfToken, id: $button.data('id')}, null, 'json')
                .done(function (response) {
                    if (!response.status) {
                        alert(response.errors.join("\n"));
                        return;
                    }
                    $button
                        .text(response.datas.status_label)
                        .removeClass('ax-badge-draft ax-badge-published')
                        .addClass('ax-badge-' + response.datas.status);
                    $row.find('.js-published-at').text(response.datas.published_at);
                })
                .fail(function () {
                    alert('Erreur réseau, réessayez.');
                });
        });
    });
</script>
<?php
require_once APP_ROOT . '/templates/layout/footer.php';
