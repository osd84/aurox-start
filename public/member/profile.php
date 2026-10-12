<?php
// -----------------------------------------------------------------------------
// Espace membre : mon profil et mon mot de passe.
// Accessible à tout compte connecté (admin ou user).
//
// Montre : Sec::isLogged() (garde d'accès), Sec::getUserIdOrDie(),
// deux formulaires sur une même page, distingués par un champ caché "form".
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

// Pas connecté : Flash "Accès refusé" puis redirection vers AppUrls::LOGIN
Sec::isLogged();
$csrf = Csrf::protect();

$title = AppConfig::get('appName') . ' - Mon profil';
$navActive = AppUrls::MEMBER_PROFILE;

// -----------------------------------------------------------------------------
// Controller
// -----------------------------------------------------------------------------
$pdo = Dbo::getPdo();

$userId = Sec::getUserIdOrDie();
$user = UserModel::get($pdo, $userId);
if (!$user) {
    // compte supprimé pendant que la session était ouverte
    unset($_SESSION['user']);
    Base::redirect(AppUrls::LOGIN);
}

$passwordMinLength = (int) AppConfig::get('passwordMinLength', 10, safe: true);

$errorsProfile = [];
$errorsPassword = [];

if (Sec::isPost()) {

    $whichForm = Sec::getParam('form', 'aZ09', source: 1);

    // ---------- Formulaire 1 : le profil ----------
    if ($whichForm === 'profile') {
        $postUsername = (string) Sec::getParam('username', 'nohtml', source: 1);

        if (mb_strlen($postUsername) < 2 || mb_strlen($postUsername) > 100) {
            $errorsProfile['username'][] = 'Entre 2 et 100 caractères';
        }

        if (empty($errorsProfile)) {
            UserModel::updateProfile($pdo, $userId, $postUsername);
            // la session garde une copie du nom affiché (menu) : on la tient à jour
            $_SESSION['user']['username'] = $postUsername;
            Flash::success('Profil mis à jour');
            Base::redirect(AppUrls::MEMBER_PROFILE);
        }

        Flash::error('Le formulaire contient des erreurs');
        $user['username'] = $postUsername;
    }

    // ---------- Formulaire 2 : le mot de passe ----------
    if ($whichForm === 'password') {
        $postCurrent = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
        $postNew = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
        $postConfirm = is_string($_POST['new_password_confirm'] ?? null) ? $_POST['new_password_confirm'] : '';

        if (!password_verify($postCurrent, $user['password'])) {
            $errorsPassword['current_password'][] = 'Mot de passe actuel incorrect';
        }
        if (mb_strlen($postNew) < $passwordMinLength) {
            $errorsPassword['new_password'][] = $passwordMinLength . ' caractères minimum';
        }
        if ($postNew !== $postConfirm) {
            $errorsPassword['new_password_confirm'][] = 'Les deux mots de passe sont différents';
        }

        if (empty($errorsPassword)) {
            UserModel::updatePassword($pdo, $userId, $postNew, $userId);
            // un lien "mot de passe oublié" encore ouvert ne doit plus servir
            PasswordResetModel::invalidateForUser($pdo, $userId);
            session_regenerate_id(true);
            Flash::success('Mot de passe modifié');
            Base::redirect(AppUrls::MEMBER_PROFILE);
        }

        Flash::error('Le mot de passe n\'a pas été modifié');
    }
}

$validatorProfile = new FormValidator();
$validatorProfile->addErrors($errorsProfile);
$formProfile = new Forms(AppUrls::MEMBER_PROFILE, validator: $validatorProfile, entity: $user);

// Pas d'entity pour le formulaire du mot de passe : on ne réaffiche jamais un mot de passe
$validatorPassword = new FormValidator();
$validatorPassword->addErrors($errorsPassword);
$formPassword = new Forms(AppUrls::MEMBER_PROFILE, validator: $validatorPassword);

// -----------------------------------------------------------------------------
// View
// -----------------------------------------------------------------------------
require_once APP_ROOT . '/templates/layout/header.php';
?>
<main>
    <h1>Mon profil</h1>

    <table class="table ax-table">
        <tbody>
        <tr><td>Email</td><td><?= Sec::hNoHtml($user['email']) ?></td></tr>
        <tr><td>Rôle</td><td><?= $user['role'] === UserModel::ROLE_ADMIN ? 'Administrateur' : 'Membre' ?></td></tr>
        <tr><td>Inscrit le</td><td><?= I18n::date($user['created_at']) ?></td></tr>
        <tr><td>Dernière connexion</td><td><?= $user['last_login_at'] ? I18n::dateTime($user['last_login_at']) : '—' ?></td></tr>
        <tr>
            <td>Double authentification</td>
            <td>
                <?= $user['is_otp_enabled'] ? '<strong>activée</strong>' : 'désactivée' ?>
                · <a href="<?= AppUrls::MEMBER_OTP ?>">gérer</a>
            </td>
        </tr>
        </tbody>
    </table>

    <div class="row">
        <div class="col-md-6">
            <h2>Nom affiché</h2>
            <?= $formProfile->formStart(autocomplete: false, error_summary: false) ?>
                <?= $formProfile->hidden('form', 'profile') ?>
                <?= $formProfile->input('username', label: 'Nom affiché', required: true, layout: 'break') ?>
                <button type="submit" class="ax-btn ax-btn-primary">Enregistrer</button>
            <?= $formProfile->formEnd(error_summary: false) ?>
        </div>

        <div class="col-md-6">
            <h2>Mot de passe</h2>
            <?= $formPassword->formStart(autocomplete: false, error_summary: false) ?>
                <?= $formPassword->hidden('form', 'password') ?>
                <?= $formPassword->input('current_password', label: 'Mot de passe actuel', type: 'password', required: true, layout: 'break') ?>
                <?= $formPassword->input('new_password', label: 'Nouveau mot de passe', type: 'password', required: true, layout: 'break',
                    placeholder: $passwordMinLength . ' caractères minimum') ?>
                <?= $formPassword->input('new_password_confirm', label: 'Confirmer', type: 'password', required: true, layout: 'break') ?>
                <button type="submit" class="ax-btn ax-btn-primary">Changer le mot de passe</button>
            <?= $formPassword->formEnd(error_summary: false) ?>
        </div>
    </div>
</main>
<?php
require_once APP_ROOT . '/templates/layout/footer.php';
