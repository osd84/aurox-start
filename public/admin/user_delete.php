<?php
// -----------------------------------------------------------------------------
// Administration : supprimer un compte. POST + CSRF.
// Un compte qui a écrit des articles est retenu par la clé étrangère post.user_id :
// UserModel::deleteSafe() rend alors false au lieu de planter.
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../../aurox.php';

use App\AppUrls;
use App\Models\UserModel;
use OsdAurox\Base;
use OsdAurox\Csrf;
use OsdAurox\Dbo;
use OsdAurox\Flash;
use OsdAurox\Sec;

Sec::isAdminOrDie();
Csrf::protect();

// -----------------------------------------------------------------------------
// Controller (pas de vue : on traite puis on redirige)
// -----------------------------------------------------------------------------
if (!Sec::isPost()) {
    Base::redirect(AppUrls::ADMIN_USERS);
}

$pdo = Dbo::getPdo();

$id = (int) Sec::getParam('id', 'int', source: 1);

if ($id === Sec::getUserIdOrDie()) {
    Flash::error('Vous ne pouvez pas supprimer votre propre compte');
    Base::redirect(AppUrls::ADMIN_USERS);
}

if (!UserModel::exist($pdo, $id)) {
    Flash::error('Compte introuvable');
    Base::redirect(AppUrls::ADMIN_USERS);
}

if (UserModel::deleteSafe($pdo, $id)) {
    Flash::success('Compte supprimé');
} else {
    Flash::error('Ce compte a écrit des articles : supprimez-les d\'abord, ou désactivez le compte.');
}

Base::redirect(AppUrls::ADMIN_USERS);
