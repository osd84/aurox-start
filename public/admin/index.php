<?php
// -----------------------------------------------------------------------------
// Administration : tableau de bord.
// Toute page de public/admin/ commence par Sec::isAdminOrDie() :
// un visiteur qui n'est pas admin est renvoyé vers AppUrls::LOGIN.
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../../aurox.php';

use App\AppUrls;
use App\Models\PostModel;
use App\Models\UserModel;
use OsdAurox\AppConfig;
use OsdAurox\Dbo;
use OsdAurox\Sec;

Sec::isAdminOrDie();

$title = AppConfig::get('appName') . ' - Administration';
$navActive = AppUrls::ADMIN_HOME;
$sidebarActive = AppUrls::ADMIN_HOME;

// -----------------------------------------------------------------------------
// Controller
// -----------------------------------------------------------------------------
$pdo = Dbo::getPdo();

$countPublished = PostModel::countByStatus($pdo, PostModel::STATUS_PUBLISHED);
$countDraft = PostModel::countByStatus($pdo, PostModel::STATUS_DRAFT);
$countAdmins = UserModel::countByRole($pdo, UserModel::ROLE_ADMIN);
$countMembers = UserModel::countByRole($pdo, UserModel::ROLE_USER);

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
        <main>
            <h1>Tableau de bord</h1>

            <div class="ax-grid ax-grid-stats">
                <div>
                    <h3>Publiés</h3>
                    <p class="ax-stat"><?= (int) $countPublished ?></p>
                </div>
                <div>
                    <h3>Brouillons</h3>
                    <p class="ax-stat"><?= (int) $countDraft ?></p>
                </div>
                <div>
                    <h3>Admins</h3>
                    <p class="ax-stat"><?= (int) $countAdmins ?></p>
                </div>
                <div>
                    <h3>Membres</h3>
                    <p class="ax-stat"><?= (int) $countMembers ?></p>
                </div>
            </div>

            <div class="ax-btns">
                <a class="ax-btn ax-btn-primary" href="<?= AppUrls::ADMIN_POST_EDIT ?>">+ Écrire un article</a>
                <a class="ax-btn" href="<?= AppUrls::ADMIN_USER_EDIT ?>">+ Créer un compte</a>
            </div>

            <h2>Environnement</h2>
            <table class="table ax-table">
                <tbody>
                <tr><td>PHP</td><td><?= Sec::hNoHtml(PHP_VERSION) ?></td></tr>
                <tr><td>Base</td><td><?= Sec::hNoHtml(AppConfig::get('driver')) ?> · <?= Sec::hNoHtml(AppConfig::get('db')) ?></td></tr>
                <tr><td>SSL base</td><td><?= Sec::hNoHtml(Dbo::sslVersion()) ?></td></tr>
                <tr><td>Debug</td><td><?= AppConfig::isDebug() ? '<strong class="text-danger">true</strong> (jamais en production)' : 'false' ?></td></tr>
                <tr><td>CSP nonce</td><td><?= AppConfig::get('nonce') ? 'activée' : 'désactivée' ?></td></tr>
                </tbody>
            </table>
        </main>
    </div>
</div>
<?php
require_once APP_ROOT . '/templates/layout/footer.php';
