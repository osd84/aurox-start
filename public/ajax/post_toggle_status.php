<?php
// -----------------------------------------------------------------------------
// Ajax : bascule un article entre "brouillon" et "publié".
// Appelé en POST par la liste admin (public/admin/posts.php).
//
// Montre : un endpoint JSON au format OsdAurox\Api, protégé comme une page
// (admin + CSRF), qui ne répond qu'aux appels ajax.
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../../aurox.php';

use App\Models\PostModel;
use OsdAurox\Api;
use OsdAurox\Base;
use OsdAurox\Csrf;
use OsdAurox\Dbo;
use OsdAurox\I18n;
use OsdAurox\Sec;

Base::noIndex();
Base::blockNoAjax();                // 403 si l'en-tête X-Requested-With manque (ignoré en debug)
Sec::isAdminOrDie(redirect: false); // pas de redirection HTML pour un appel ajax : on s'arrête
Csrf::protect();                    // le POST doit porter _csrf

$pdo = Dbo::getPdo();
$response = new Api();

if (!Sec::isPost()) {
    $response->errors[] = 'POST attendu';
    $response->returnJsonResponse();
}

$id = (int) Sec::getParam('id', 'int', source: 1);
$post = PostModel::get($pdo, $id);
if (!$post) {
    $response->errors[] = 'Article introuvable';
    $response->returnJsonResponse();
}

$newStatus = $post['status'] === PostModel::STATUS_PUBLISHED ? PostModel::STATUS_DRAFT : PostModel::STATUS_PUBLISHED;
PostModel::updateStatus($pdo, $id, $newStatus, Sec::getUserIdOrDie());

// relu en base : published_at a pu être posé par la requête
$post = PostModel::get($pdo, $id);

$response->status = true;
$response->success[] = 'Statut mis à jour';
$response->datas['status'] = $post['status'];
$response->datas['status_label'] = $post['status'] === PostModel::STATUS_PUBLISHED ? 'Publié' : 'Brouillon';
$response->datas['published_at'] = $post['published_at'] ? I18n::date($post['published_at']) : '—';
// returnJsonResponse() envoie le JSON puis arrête le script
$response->returnJsonResponse();
