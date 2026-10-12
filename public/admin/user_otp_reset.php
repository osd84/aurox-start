<?php
// -----------------------------------------------------------------------------
// Administration : désactiver la double authentification d'un compte
// (le membre a perdu son téléphone). POST + CSRF.
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

if (!UserModel::exist($pdo, $id)) {
    Flash::error('Compte introuvable');
    Base::redirect(AppUrls::ADMIN_USERS);
}

UserModel::disableOtp($pdo, $id, Sec::getUserIdOrDie());
Flash::success('Double authentification désactivée pour ce compte');

Base::redirect(AppUrls::ADMIN_USER_EDIT . '?id=' . $id);
