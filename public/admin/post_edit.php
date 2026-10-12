<?php
// -----------------------------------------------------------------------------
// Administration : créer ou modifier un article.
//   /admin/post_edit.php          création
//   /admin/post_edit.php?id=12    modification
//
// C'est LA recette d'une fiche aurox, à recopier pour toute nouvelle table :
//   1. charger l'enregistrement (ou rien en création)
//   2. si POST : lire chaque champ (une ligne par colonne), valider (un if par règle),
//      enregistrer, Flash, redirect
//   3. sinon / si erreurs : afficher le formulaire avec Forms, alimenté par l'entité
//      et les erreurs du FormValidator
// Ajouter une colonne = la migration, l'INSERT et l'UPDATE du modèle, une ligne
// dans $postData (+ sa validation), un champ dans la vue. Rien n'est deviné.
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../../aurox.php';

use App\AppUrls;
use App\Models\PostModel;
use OsdAurox\AppConfig;
use OsdAurox\Base;
use OsdAurox\Csrf;
use OsdAurox\Dbo;
use OsdAurox\Flash;
use OsdAurox\Forms;
use OsdAurox\FormValidator;
use OsdAurox\I18n;
use OsdAurox\Sec;

Sec::isAdminOrDie();
$csrf = Csrf::protect();

$navActive = AppUrls::ADMIN_HOME;
$sidebarActive = AppUrls::ADMIN_POSTS;

// -----------------------------------------------------------------------------
// Controller
// -----------------------------------------------------------------------------
$pdo = Dbo::getPdo();

// En création il n'y a pas d'id. formStart() renvoie l'id dans un champ caché : on le lit en POST ou en GET.
$id = (int) Sec::getParam('id', 'int');
$post = null;
if ($id) {
    $post = PostModel::get($pdo, $id);
    if (!$post) {
        Flash::error('Article introuvable');
        Base::redirect(AppUrls::ADMIN_POSTS);
    }
}

$title = AppConfig::get('appName') . ' - ' . ($id ? 'Modifier un article' : 'Écrire un article');

// Une variable par select, alimentée par une méthode nommée du modèle
$optionsForSelectStatus = PostModel::getSelectStatus();

$errors = [];

if (Sec::isPost()) {

    // Sec::getParam() rend null quand le champ est absent : on ramène à une chaîne avant de valider.
    // 'nohtml' retire les balises, 'raw' garde le texte tel quel (il sera échappé à l'affichage).
    $postData = [
        'title' => (string) Sec::getParam('title', 'nohtml', source: 1),
        'slug' => (string) Sec::getParam('slug', 'slug', source: 1),
        'excerpt' => (string) Sec::getParam('excerpt', 'nohtml', source: 1),
        'content' => (string) Sec::getParam('content', 'raw', source: 1),
        'status' => (string) Sec::getParam('status', 'aZ09', source: 1),
    ];

    // slug vide : on le fabrique depuis le titre
    if ($postData['slug'] === '') {
        $postData['slug'] = PostModel::slugify($postData['title']);
    }

    // ---------- Validation, une règle = un if ----------
    if ($postData['title'] === '') {
        $errors['title'][] = 'Le titre est obligatoire';
    }
    if (mb_strlen($postData['title']) > 255) {
        $errors['title'][] = '255 caractères maximum';
    }
    if ($postData['slug'] === '') {
        $errors['slug'][] = 'Slug invalide';
    }
    if (mb_strlen($postData['slug']) > 255) {
        $errors['slug'][] = '255 caractères maximum';
    }
    // check_uniq() de BaseModel : true si personne d'autre (on s'exclut en édition) n'a ce slug
    if ($postData['slug'] !== '' && !PostModel::check_uniq($pdo, 'slug', $postData['slug'], $id ?: null)) {
        $errors['slug'][] = 'Ce slug est déjà utilisé par un autre article';
    }
    if (mb_strlen($postData['excerpt']) > 500) {
        $errors['excerpt'][] = '500 caractères maximum';
    }
    if (trim($postData['content']) === '') {
        $errors['content'][] = 'Le contenu est obligatoire';
    }
    if (!in_array($postData['status'], [PostModel::STATUS_DRAFT, PostModel::STATUS_PUBLISHED], true)) {
        $errors['status'][] = 'Statut invalide';
    }

    // La date de publication : posée au premier passage en "publié", conservée ensuite
    $postData['published_at'] = $post['published_at'] ?? null;
    if ($postData['status'] === PostModel::STATUS_PUBLISHED && !$postData['published_at']) {
        $postData['published_at'] = date('Y-m-d H:i:s');
    }

    // Les chaînes vides partent en NULL en base (colonne nullable)
    if ($postData['excerpt'] === '') {
        $postData['excerpt'] = null;
    }

    if (empty($errors)) {
        $userId = Sec::getUserIdOrDie();
        if ($id) {
            PostModel::update($pdo, $id, $postData, $userId);
            Flash::success('Article mis à jour');
        } else {
            $id = PostModel::insert($pdo, $postData, $userId);
            Flash::success('Article créé');
        }
        // POST puis redirect : un F5 ne renvoie pas le formulaire
        Base::redirect(AppUrls::ADMIN_POST_EDIT . '?id=' . (int) $id);
    }

    Flash::error('Le formulaire contient des erreurs');
    // On réaffiche la saisie, pas la valeur en base. 'id' doit exister : formStart() le renvoie en champ caché.
    $post = array_merge(['id' => $id ?: null], $post ?? [], $postData);
}

// Créé APRÈS le POST : il porte les erreurs vers Forms
$validator = new FormValidator();
$validator->addErrors($errors);

$form = new Forms(AppUrls::ADMIN_POST_EDIT, validator: $validator, entity: $post);

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
            <h1><?= $id ? 'Modifier un article' : 'Écrire un article' ?></h1>

            <?php if ($id && $post['created_at']): ?>
                <p class="ax-meta">
                    Créé le <?= I18n::dateTime($post['created_at']) ?>
                    <?php if ($post['updated_at']): ?>
                        · modifié le <?= I18n::dateTime($post['updated_at']) ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <?= $form->formStart(autocomplete: false, error_summary: false) ?>

                <div class="row">
                    <div class="col-md-8">
                        <?= $form->input('title', label: 'Titre', required: true, layout: 'break') ?>
                    </div>
                    <div class="col-md-4">
                        <?= $form->select($optionsForSelectStatus, 'status', label: 'Statut', class_select: 'form-select', required: true) ?>
                    </div>
                </div>

                <?= $form->input('slug', label: 'Slug (adresse de l\'article, vide = calculé depuis le titre)', layout: 'break',
                    placeholder: 'mon-premier-article') ?>

                <?= $form->textarea('excerpt', label: 'Chapeau (résumé affiché dans la liste)', rows: 2) ?>

                <?= $form->textarea('content', label: 'Contenu (texte brut, les retours à la ligne sont conservés)', required: true, rows: 14) ?>

                <div class="ax-btns">
                    <button type="submit" class="ax-btn ax-btn-primary">Enregistrer</button>
                    <a class="ax-btn" href="<?= AppUrls::ADMIN_POSTS ?>">Annuler</a>
                    <?php if ($id && ($post['status'] ?? '') === PostModel::STATUS_PUBLISHED): ?>
                        <a class="ax-btn" href="<?= AppUrls::POST ?>?slug=<?= Sec::hNoHtml($post['slug']) ?>">Voir l'article</a>
                    <?php endif; ?>
                </div>

            <?= $form->formEnd(error_summary: false) ?>
        </main>
    </div>
</div>
<?php
require_once APP_ROOT . '/templates/layout/footer.php';
