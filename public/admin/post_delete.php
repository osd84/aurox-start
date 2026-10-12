<?php
// -----------------------------------------------------------------------------
// Administration : supprimer un article.
// POST uniquement, avec jeton CSRF. Un lien GET de suppression serait déclenchable
// par n'importe quelle image piégée sur un autre site.
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../../aurox.php';

use App\AppUrls;
use App\Models\PostModel;
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
    Base::redirect(AppUrls::ADMIN_POSTS);
}

$pdo = Dbo::getPdo();

$id = (int) Sec::getParam('id', 'int', source: 1);

// delete() de BaseModel : DELETE ... WHERE id = :id, rend le nombre de lignes supprimées
if (PostModel::delete($pdo, $id)) {
    Flash::success('Article supprimé');
} else {
    Flash::error('Article introuvable');
}

Base::redirect(AppUrls::ADMIN_POSTS);
