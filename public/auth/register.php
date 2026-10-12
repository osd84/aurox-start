<?php
// -----------------------------------------------------------------------------
// Inscription publique (rôle 'user'). Activée par 'featureRegister' => true dans conf.php.
//
// Montre la recette d'un formulaire aurox, à plat :
//   1. lire le POST champ par champ avec Sec::getParam()
//   2. valider à la main, une condition lisible par règle, erreurs dans $errors['champ'][]
//   3. si tout va bien : INSERT, Flash, redirect (POST puis redirect)
//   4. sinon : on réaffiche le formulaire avec la saisie et les erreurs (FormValidator + Forms)
// Plus : Captcha (sans service externe) et Sec::setRateLimitOrDie().
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../../aurox.php';

use App\AppUrls;
use App\Models\UserModel;
use OsdAurox\AppConfig;
use OsdAurox\Base;
use OsdAurox\Captcha;
use OsdAurox\Csrf;
use OsdAurox\Dbo;
use OsdAurox\Flash;
use OsdAurox\Forms;
use OsdAurox\FormValidator;
use OsdAurox\Sec;

if (!AppConfig::get('featureRegister', false, safe: true)) {
    Base::redirect404('register');
}

$csrf = Csrf::protect();

$title = AppConfig::get('appName') . ' - Créer un compte';
$navActive = AppUrls::LOGIN;

// -----------------------------------------------------------------------------
// Controller
// -----------------------------------------------------------------------------
$pdo = Dbo::getPdo();

if (Sec::isLoggedBool()) {
    Base::redirect(AppUrls::MEMBER_PROFILE);
}

$errors = [];
$user = null;

$passwordMinLength = (int) AppConfig::get('passwordMinLength', 10, safe: true);

if (Sec::isPost()) {

    // 10 inscriptions tentées par heure et par IP, au-delà : 429 et arrêt
    Sec::setRateLimitOrDie(3600, 10, 'register');

    // Une ligne par colonne. Sec::getParam() rend null si le champ est absent : on ramène à une chaîne.
    $postData = [
        'email' => (string) Sec::getParam('email', 'email', source: 1, lowerCase: True),
        'username' => (string) Sec::getParam('username', 'nohtml', source: 1),
        'role' => UserModel::ROLE_USER,   // jamais lu dans le POST : on ne choisit pas son rôle
        'is_active' => 1,
    ];
    // Les mots de passe sont lus bruts : on ne filtre pas ce qu'on va hacher
    $postPassword = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $postPasswordConfirm = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';
    $postCaptcha = is_string($_POST['captcha'] ?? null) ? $_POST['captcha'] : '';

    // ---------- Validation, une règle = un if ----------
    if ($postData['email'] === '') {
        $errors['email'][] = 'Email invalide';
    }
    if ($postData['email'] !== '' && !UserModel::check_uniq($pdo, 'email', $postData['email'])) {
        $errors['email'][] = 'Cet email a déjà un compte';
    }
    if (mb_strlen($postData['username']) < 2 || mb_strlen($postData['username']) > 100) {
        $errors['username'][] = 'Entre 2 et 100 caractères';
    }
    if (mb_strlen($postPassword) < $passwordMinLength) {
        $errors['password'][] = $passwordMinLength . ' caractères minimum';
    }
    if ($postPassword !== $postPasswordConfirm) {
        $errors['password_confirm'][] = 'Les deux mots de passe sont différents';
    }
    if (!Captcha::verify($postCaptcha)) {
        $errors['captcha'][] = 'Captcha incorrect';
    }

    if (empty($errors)) {
        $postData['password'] = $postPassword;
        UserModel::insert($pdo, $postData, null);

        Flash::success('Compte créé, vous pouvez vous connecter');
        Base::redirect(AppUrls::LOGIN);
    }

    Flash::error('Le formulaire contient des erreurs');
    // nouveau captcha à chaque échec : pas d'essais en boucle sur le même
    Captcha::clear();
    // on réaffiche la saisie (jamais les mots de passe)
    $user = $postData;
}

// Le FormValidator est créé APRÈS le POST : il porte les erreurs jusqu'à Forms
$validator = new FormValidator();
$validator->addErrors($errors);

$form = new Forms(AppUrls::REGISTER, validator: $validator, entity: $user);

// -----------------------------------------------------------------------------
// View
// -----------------------------------------------------------------------------
require_once APP_ROOT . '/templates/layout/header.php';
?>
<main class="ax-narrow">
    <h1>Créer un compte</h1>

    <?php // formStart() ajoute le champ _csrf et le résumé des erreurs ?>
    <?= $form->formStart(autocomplete: false, error_summary: false) ?>

        <?= $form->input('email', label: 'Email', type: 'email', required: true, layout: 'break') ?>
        <?= $form->input('username', label: 'Nom affiché', required: true, layout: 'break') ?>
        <?= $form->input('password', label: 'Mot de passe', type: 'password', required: true, layout: 'break',
            placeholder: $passwordMinLength . ' caractères minimum') ?>
        <?= $form->input('password_confirm', label: 'Confirmer le mot de passe', type: 'password', required: true, layout: 'break') ?>

        <div class="mb-3">
            <?= Captcha::captchaHtml() ?>
            <?= $form->errorDiv('captcha') ?>
        </div>

        <button type="submit" class="ax-btn ax-btn-primary">Créer mon compte</button>

    <?= $form->formEnd(error_summary: false) ?>

    <p class="mt-4">Déjà un compte ? <a href="<?= AppUrls::LOGIN ?>">Se connecter</a></p>
</main>
<?php
require_once APP_ROOT . '/templates/layout/footer.php';
