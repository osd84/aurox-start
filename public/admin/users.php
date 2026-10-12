<?php
// -----------------------------------------------------------------------------
// Administration : liste des comptes.
// Même construction que posts.php : Paginator + recherche LIKE, suppression en POST.
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../../aurox.php';

use App\AppUrls;
use App\Models\UserModel;
use OsdAurox\AppConfig;
use OsdAurox\Csrf;
use OsdAurox\Dbo;
use OsdAurox\I18n;
use OsdAurox\Paginator;
use OsdAurox\Sec;

Sec::isAdminOrDie();
$csrf = Csrf::protect();

$title = AppConfig::get('appName') . ' - Utilisateurs';
$navActive = AppUrls::ADMIN_HOME;
$sidebarActive = AppUrls::ADMIN_USERS;

// -----------------------------------------------------------------------------
// Controller
// -----------------------------------------------------------------------------
$pdo = Dbo::getPdo();

$searchText = Sec::safeForLikeStrong($_GET['search_text'] ?? null);
$searchLike = null;
$paginatorUrl = AppUrls::ADMIN_USERS;
if ($searchText) {
    $searchLike = mb_strtoupper('%' . $searchText . '%', 'UTF-8');
    $paginatorUrl = AppUrls::ADMIN_USERS . '?search_text=' . urlencode($searchText);
}

$queries = UserModel::listQueries($searchLike);

$paginator = new Paginator(
    $pdo,
    $queries['query'],
    $queries['count_query'],
    $paginatorUrl,
    bind_params: $queries['bind_params'],
    per_page: 20,
);

$users = $paginator->getItems();

$currentUserId = Sec::getUserIdOrDie();

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
            <h1>Utilisateurs</h1>

            <div class="ax-toolbar">
                <a class="ax-btn ax-btn-primary" href="<?= AppUrls::ADMIN_USER_EDIT ?>">+ Créer un compte</a>

                <form method="get" action="<?= AppUrls::ADMIN_USERS ?>" class="ax-search">
                    <input type="text" name="search_text" class="form-control" placeholder="Email ou nom"
                           value="<?= Sec::hNoHtml($searchText) ?>">
                    <button type="submit" class="ax-btn">Chercher</button>
                </form>
            </div>

            <table class="table ax-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Email</th>
                    <th>Nom</th>
                    <th>Rôle</th>
                    <th>Actif</th>
                    <th>OTP</th>
                    <th>Dernière connexion</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <?php $editUrl = AppUrls::ADMIN_USER_EDIT . '?id=' . (int) $user['id']; ?>
                    <tr>
                        <td><?= (int) $user['id'] ?></td>
                        <td><a href="<?= $editUrl ?>"><?= Sec::hNoHtml($user['email']) ?></a></td>
                        <td><?= Sec::hNoHtml($user['username']) ?></td>
                        <td><?= $user['role'] === UserModel::ROLE_ADMIN ? '<strong>admin</strong>' : 'membre' ?></td>
                        <td>
                            <?php if ($user['is_active']): ?>
                                Oui
                            <?php else: ?>
                                <span class="text-danger">Non</span>
                                <?php if ($user['failed_login_attempts'] >= UserModel::MAX_FAILED_LOGIN_ATTEMPTS): ?>
                                    <small>(bloqué : <?= (int) $user['failed_login_attempts'] ?> échecs)</small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td><?= $user['is_otp_enabled'] ? 'Oui' : '—' ?></td>
                        <td><?= $user['last_login_at'] ? I18n::dateTime($user['last_login_at']) : '—' ?></td>
                        <td class="text-end text-nowrap">
                            <a class="ax-btn ax-btn-sm" href="<?= $editUrl ?>">Modifier</a>
                            <?php if ((int) $user['id'] !== $currentUserId): ?>
                                <form action="<?= AppUrls::ADMIN_USER_DELETE ?>" method="post" class="d-inline"
                                      data-confirm="Supprimer le compte <?= Sec::hNoHtml($user['email']) ?> ?">
                                    <?= Csrf::inputHtml() ?>
                                    <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                    <button type="submit" class="ax-btn ax-btn-sm ax-btn-danger">Supprimer</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$users): ?>
                    <tr><td colspan="8" class="text-muted">Aucun compte</td></tr>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if ($paginator->getTotalPages() > 1): ?>
                <?= $paginator->renderPagination() ?>
            <?php endif; ?>
            <p class="ax-meta"><?= (int) $paginator->getTotalItems() ?> compte(s)</p>
        </main>
    </div>
</div>
<?php
require_once APP_ROOT . '/templates/layout/footer.php';
