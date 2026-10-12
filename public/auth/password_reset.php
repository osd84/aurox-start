<?php
// -----------------------------------------------------------------------------
// Mot de passe oublié, étape 2 : le lien reçu par mail.
//   GET  /auth/password_reset.php?token=...   formulaire du nouveau mot de passe
//   POST /auth/password_reset.php             enregistre, consomme le jeton
//
// Le jeton n'est valable qu'une fois, pendant une heure, pour un compte actif.
// La double authentification reste exigée à la connexion qui suit.
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
use OsdAurox\Sec;

$csrf = Csrf::protect();

$title = AppConfig::get('appName') . ' - Nouveau mot de passe';
$navActive = AppUrls::LOGIN;

// La page ne doit pas être indexée, ni envoyer le jeton de l'URL à un site tiers via le Referer
Base::noIndex();
header('Referrer-Policy: no-referrer');

// -----------------------------------------------------------------------------
// Controller
// -----------------------------------------------------------------------------
$pdo = Dbo::getPdo();

// Le jeton arrive dans l'URL (GET), puis dans un champ caché du formulaire (POST).
// 'aZ09' : un jeton légitime n'est fait que de chiffres et de lettres a-f.
$token = (string) Sec::getParam('token', 'aZ09');

$reset = null;
if ($token !== '') {
    $reset = PasswordResetModel::getValidByToken($pdo, $token);
}
if (!$reset) {
    Flash::error('Lien invalide ou expiré. Faites une nouvelle demande.');
    Base::redirect(AppUrls::PASSWORD_FORGOT);
}

$passwordMinLength = (int) AppConfig::get('passwordMinLength', 10, safe: true);

$errors = [];

if (Sec::isPost()) {

    $postPassword = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $postPasswordConfirm = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';

    if (mb_strlen($postPassword) < $passwordMinLength) {
        $errors['password'][] = $passwordMinLength . ' caractères minimum';
    }
    if ($postPassword !== $postPasswordConfirm) {
        $errors['password_confirm'][] = 'Les deux mots de passe sont différents';
    }

    if (empty($errors)) {
        $userId = (int) $reset['user_id'];
        UserModel::updatePassword($pdo, $userId, $postPassword, $userId);
        // les échecs passés ne comptent plus, le jeton (et tout autre lien ouvert) est consommé
        UserModel::resetFailedLogin($pdo, $userId);
        PasswordResetModel::invalidateForUser($pdo, $userId);

        Flash::success('Mot de passe modifié, vous pouvez vous connecter.');
        Base::redirect(AppUrls::LOGIN);
    }

    Flash::error('Le mot de passe n\'a pas été modifié');
}

$validator = new FormValidator();
$validator->addErrors($errors);

$form = new Forms(AppUrls::PASSWORD_RESET, validator: $validator);

// -----------------------------------------------------------------------------
// View
// -----------------------------------------------------------------------------
require_once APP_ROOT . '/templates/layout/header.php';
?>
<main class="ax-narrow">
    <h1>Nouveau mot de passe</h1>
    <p>Pour le compte <strong><?= Sec::hNoHtml($reset['email']) ?></strong>.</p>

    <?= $form->formStart(autocomplete: false, error_summary: false) ?>
        <?= $form->hidden('token', $token) ?>
        <?= $form->input('password', label: 'Nouveau mot de passe', type: 'password', required: true, layout: 'break',
            placeholder: $passwordMinLength . ' caractères minimum') ?>
        <?= $form->input('password_confirm', label: 'Confirmer', type: 'password', required: true, layout: 'break') ?>
        <button type="submit" class="ax-btn ax-btn-primary">Enregistrer</button>
    <?= $form->formEnd(error_summary: false) ?>
</main>
<?php
require_once APP_ROOT . '/templates/layout/footer.php';
