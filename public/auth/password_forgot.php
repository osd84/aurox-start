<?php
// -----------------------------------------------------------------------------
// Mot de passe oublié, étape 1 : demander un lien par mail.
//
// Montre : OsdAurox\Mailer (PHPMailer configuré par conf.php), un jeton à usage unique
// (PasswordResetModel), Captcha + rate-limit contre l'envoi de mails en rafale,
// et une réponse identique que le compte existe ou non (on ne révèle pas les inscrits).
//
// En mode debug, le lien est aussi affiché sur la page : on peut tester sans serveur mail.
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../../aurox.php';

use App\AppUrls;
use App\Models\PasswordResetModel;
use App\Models\UserModel;
use OsdAurox\AppConfig;
use OsdAurox\Base;
use OsdAurox\Captcha;
use OsdAurox\Csrf;
use OsdAurox\Dbo;
use OsdAurox\Flash;
use OsdAurox\Log;
use OsdAurox\Mailer;
use OsdAurox\Sec;

$csrf = Csrf::protect();

$title = AppConfig::get('appName') . ' - Mot de passe oublié';
$navActive = AppUrls::LOGIN;

// -----------------------------------------------------------------------------
// Controller
// -----------------------------------------------------------------------------
$pdo = Dbo::getPdo();

if (Sec::isLoggedBool()) {
    Base::redirect(AppUrls::MEMBER_PROFILE);
}

$errorCaptcha = false;

if (Sec::isPost()) {

    // 5 demandes par heure et par IP, au-delà : 429 et arrêt
    Sec::setRateLimitOrDie(3600, 5, 'password_forgot');

    $email = (string) Sec::getParam('email', 'email', source: 1, lowerCase: True);
    $postCaptcha = is_string($_POST['captcha'] ?? null) ? $_POST['captcha'] : '';

    if (!Captcha::verify($postCaptcha)) {
        Captcha::clear();
        Flash::error('Captcha incorrect');
        Base::redirect(AppUrls::PASSWORD_FORGOT);
    }

    $user = null;
    if ($email) {
        $user = UserModel::getBy($pdo, 'email', $email);
    }

    // Un lien n'est envoyé qu'à un compte actif. Pour les autres : rien, mais le même message.
    if ($user && $user['is_active']) {

        $token = PasswordResetModel::createForUser($pdo, (int) $user['id'], Sec::getRealIpAddr());
        $link = rtrim(AppConfig::get('appUrl', safe: true), '/') . AppUrls::PASSWORD_RESET . '?token=' . $token;

        // Le mail, écrit en clair. Chaque valeur insérée dans le HTML est échappée.
        $subject = AppConfig::get('appName', safe: true) . ' : réinitialiser votre mot de passe';
        $bodyHtml = '<p>Bonjour ' . Sec::hNoHtml($user['username']) . ',</p>'
            . '<p>Une demande de réinitialisation de mot de passe a été faite pour votre compte.</p>'
            . '<p><a href="' . Sec::hNoHtml($link) . '">Choisir un nouveau mot de passe</a></p>'
            . '<p>Ce lien est valable ' . PasswordResetModel::LIFETIME_MINUTES . ' minutes et ne sert qu\'une fois.<br>'
            . 'Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message : votre mot de passe ne change pas.</p>';
        $bodyText = "Bonjour " . $user['username'] . ",\n\n"
            . "Pour choisir un nouveau mot de passe, ouvrez ce lien (valable " . PasswordResetModel::LIFETIME_MINUTES . " minutes) :\n"
            . $link . "\n\n"
            . "Si vous n'êtes pas à l'origine de cette demande, ignorez ce message.";

        // Pas de serveur mail configuré (valeurs "<changeMe>" de conf_example.php) : on n'essaie pas
        $mailHost = (string) AppConfig::get('mailHost', '', safe: true);
        $mailConfigured = $mailHost !== '' && !str_starts_with($mailHost, '<');

        if ($mailConfigured) {
            try {
                $mailer = new Mailer();
                $mailer->mail->Timeout = 15;   // un serveur SMTP muet ne bloque pas la page 5 minutes
                $mailer->mail->addAddress($user['email']);
                $mailer->mail->Subject = $subject;
                $mailer->mail->Body = $bodyHtml;
                $mailer->mail->AltBody = $bodyText;
                if (!$mailer->mail->send()) {
                    Log::error('password_forgot : envoi impossible vers ' . $user['email'] . ' : ' . $mailer->mail->ErrorInfo);
                }
            } catch (\Throwable $e) {
                Log::error('password_forgot : envoi impossible vers ' . $user['email'] . ' : ' . $e->getMessage());
            }
        } else {
            Log::warning('password_forgot : mailHost non configuré, aucun mail envoyé');
        }

        // Mode debug : on montre le lien sur la page suivante, pour tester sans serveur mail.
        // JAMAIS en production : n'importe qui pourrait réinitialiser le compte de n'importe qui.
        if (AppConfig::isDebug()) {
            $_SESSION['debug_password_reset_link'] = $link;
        }
    }

    Flash::success('Si un compte actif existe pour cette adresse, un lien vient de lui être envoyé.');
    Base::redirect(AppUrls::PASSWORD_FORGOT);
}

// Lien de debug posé par la requête précédente, affiché une seule fois
$debugLink = null;
if (AppConfig::isDebug() && !empty($_SESSION['debug_password_reset_link'])) {
    $debugLink = $_SESSION['debug_password_reset_link'];
}
unset($_SESSION['debug_password_reset_link']);

// -----------------------------------------------------------------------------
// View
// -----------------------------------------------------------------------------
require_once APP_ROOT . '/templates/layout/header.php';
?>
<main class="ax-narrow">
    <h1>Mot de passe oublié</h1>

    <?php if ($debugLink): ?>
        <div class="ax-flash ax-flash-warning">
            <strong>Debug</strong>
            Le lien envoyé par mail : <a href="<?= Sec::hNoHtml($debugLink) ?>">réinitialiser le mot de passe</a>.
            Affiché ici parce que <code>debug</code> vaut <code>true</code>.
        </div>
    <?php endif; ?>

    <p>Indiquez l'email de votre compte : vous recevrez un lien pour choisir un nouveau mot de passe.</p>

    <form action="<?= AppUrls::PASSWORD_FORGOT ?>" method="post" class="ax-form">
        <?= Csrf::inputHtml() ?>

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" class="form-control" required autofocus autocomplete="username">
        </div>

        <div class="mb-3">
            <?= Captcha::captchaHtml() ?>
        </div>

        <button type="submit" class="ax-btn ax-btn-primary">Recevoir le lien</button>
    </form>

    <p class="mt-4"><a href="<?= AppUrls::LOGIN ?>">← Retour à la connexion</a></p>
</main>
<?php
require_once APP_ROOT . '/templates/layout/footer.php';
