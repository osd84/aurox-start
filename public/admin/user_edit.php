<?php
// -----------------------------------------------------------------------------
// Administration : créer ou modifier un compte.
// Même recette que post_edit.php, avec en plus :
//   - une case à cocher (is_active) lue avec le type 'bool_int' (case absente du POST = 0)
//   - un mot de passe obligatoire en création, facultatif en modification
//   - des règles métier : un admin ne peut ni se rétrograder ni se désactiver lui-même
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../../aurox.php';

use App\AppUrls;
use App\Models\PasswordResetModel;
use App\Models\UserModel;
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
$sidebarActive = AppUrls::ADMIN_USERS;

// -----------------------------------------------------------------------------
// Controller
// -----------------------------------------------------------------------------
$pdo = Dbo::getPdo();

$id = (int) Sec::getParam('id', 'int');
$user = null;
if ($id) {
    $user = UserModel::get($pdo, $id);
    if (!$user) {
        Flash::error('Compte introuvable');
        Base::redirect(AppUrls::ADMIN_USERS);
    }
    // on ne renvoie jamais le hash vers la vue
    unset($user['password']);
}

$title = AppConfig::get('appName') . ' - ' . ($id ? 'Modifier un compte' : 'Créer un compte');

$currentUserId = Sec::getUserIdOrDie();
$isMe = ($id === $currentUserId);

$passwordMinLength = (int) AppConfig::get('passwordMinLength', 10, safe: true);

$optionsForSelectRoles = UserModel::getSelectRoles();

$errors = [];

if (Sec::isPost()) {

    $postData = [
        'email' => (string) Sec::getParam('email', 'email', source: 1, lowerCase: True),
        'username' => (string) Sec::getParam('username', 'nohtml', source: 1),
        'role' => (string) Sec::getParam('role', 'aZ09', source: 1),
        'is_active' => Sec::getParam('is_active', 'bool_int', source: 1),   // case non cochée = absente du POST = 0
    ];
    $postPassword = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    // ---------- Validation ----------
    if ($postData['email'] === '') {
        $errors['email'][] = 'Email invalide';
    }
    if ($postData['email'] !== '' && !UserModel::check_uniq($pdo, 'email', $postData['email'], $id ?: null)) {
        $errors['email'][] = 'Cet email a déjà un compte';
    }
    if (mb_strlen($postData['username']) < 2 || mb_strlen($postData['username']) > 100) {
        $errors['username'][] = 'Entre 2 et 100 caractères';
    }
    if (!in_array($postData['role'], [UserModel::ROLE_USER, UserModel::ROLE_ADMIN], true)) {
        $errors['role'][] = 'Rôle invalide';
    }
    // mot de passe : obligatoire en création, facultatif en modification (vide = inchangé)
    if (!$id && $postPassword === '') {
        $errors['password'][] = 'Le mot de passe est obligatoire';
    }
    if ($postPassword !== '' && mb_strlen($postPassword) < $passwordMinLength) {
        $errors['password'][] = $passwordMinLength . ' caractères minimum';
    }
    // règles métier : on ne scie pas la branche sur laquelle on est assis
    if ($isMe && $postData['role'] !== UserModel::ROLE_ADMIN) {
        $errors['role'][] = 'Vous ne pouvez pas retirer votre propre rôle admin';
    }
    if ($isMe && !$postData['is_active']) {
        $errors['is_active'][] = 'Vous ne pouvez pas désactiver votre propre compte';
    }

    if (empty($errors)) {
        if ($id) {
            UserModel::update($pdo, $id, $postData, $currentUserId);
            if ($postPassword !== '') {
                UserModel::updatePassword($pdo, $id, $postPassword, $currentUserId);
                PasswordResetModel::invalidateForUser($pdo, $id);
            }
            // réactiver un compte bloqué remet son compteur d'échecs à zéro
            if ($postData['is_active'] && !$user['is_active']) {
                UserModel::resetFailedLogin($pdo, $id);
            }
            // on modifie son propre compte : la copie en session (menu) suit
            if ($isMe) {
                $_SESSION['user']['email'] = $postData['email'];
                $_SESSION['user']['username'] = $postData['username'];
            }
            Flash::success('Compte mis à jour');
        } else {
            $postData['password'] = $postPassword;
            $id = UserModel::insert($pdo, $postData, $currentUserId);
            Flash::success('Compte créé');
        }
        Base::redirect(AppUrls::ADMIN_USER_EDIT . '?id=' . (int) $id);
    }

    Flash::error('Le formulaire contient des erreurs');
    $user = array_merge(['id' => $id ?: null], $user ?? [], $postData);
}

$validator = new FormValidator();
$validator->addErrors($errors);

$form = new Forms(AppUrls::ADMIN_USER_EDIT, validator: $validator, entity: $user);

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
            <h1><?= $id ? 'Modifier un compte' : 'Créer un compte' ?></h1>

            <?php if ($id && !empty($user['created_at'])): ?>
                <p class="ax-meta">
                    Créé le <?= I18n::dateTime($user['created_at']) ?>
                    · dernière connexion : <?= $user['last_login_at'] ? I18n::dateTime($user['last_login_at']) : 'jamais' ?>
                    · échecs de connexion : <?= (int) $user['failed_login_attempts'] ?>
                </p>
            <?php endif; ?>

            <?= $form->formStart(autocomplete: false, error_summary: false) ?>

                <div class="row">
                    <div class="col-md-6">
                        <?= $form->input('email', label: 'Email', type: 'email', required: true, layout: 'break') ?>
                    </div>
                    <div class="col-md-6">
                        <?= $form->input('username', label: 'Nom affiché', required: true, layout: 'break') ?>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <?= $form->select($optionsForSelectRoles, 'role', label: 'Rôle', class_select: 'form-select', required: true) ?>
                    </div>
                    <div class="col-md-6">
                        <?= $form->input('password', label: $id ? 'Nouveau mot de passe (vide = inchangé)' : 'Mot de passe',
                            type: 'password', required: !$id, layout: 'break', placeholder: $passwordMinLength . ' caractères minimum') ?>
                    </div>
                </div>

                <?php // en création, un compte est actif par défaut ?>
                <?= $form->checkbox('is_active', label: 'Compte actif (décoché : connexion refusée)', checked: !$id) ?>

                <div class="ax-btns">
                    <button type="submit" class="ax-btn ax-btn-primary">Enregistrer</button>
                    <a class="ax-btn" href="<?= AppUrls::ADMIN_USERS ?>">Annuler</a>
                </div>

            <?= $form->formEnd(error_summary: false) ?>

            <?php if ($id && !empty($user['is_otp_enabled'])): ?>
                <h2>Double authentification</h2>
                <p>
                    Activée sur ce compte. Si son propriétaire a perdu son téléphone, désactivez-la :
                    il se connectera avec son seul mot de passe, puis pourra la réactiver depuis son profil.
                </p>
                <form action="<?= AppUrls::ADMIN_USER_OTP_RESET ?>" method="post"
                      data-confirm="Désactiver la double authentification de <?= Sec::hNoHtml($user['email']) ?> ?">
                    <?= Csrf::inputHtml() ?>
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <button type="submit" class="ax-btn ax-btn-danger">Désactiver l'OTP de ce compte</button>
                </form>
            <?php endif; ?>
        </main>
    </div>
</div>
<?php
require_once APP_ROOT . '/templates/layout/footer.php';
