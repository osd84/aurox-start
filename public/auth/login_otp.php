<?php
// -----------------------------------------------------------------------------
// Connexion, étape 2 : le code de l'application d'authentification (TOTP).
// On n'arrive ici que depuis login.php, mot de passe correct, avec $_SESSION['otp_pending'].
//
// Montre : OsdAurox\Otp::verify() avec anti-rejeu (un code ne sert qu'une fois),
// un rate-limit dédié, et le même compteur d'échecs que le mot de passe.
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../../aurox.php';

use App\AppUrls;
use App\Auth;
use App\Models\UserModel;
use OsdAurox\AppConfig;
use OsdAurox\Base;
use OsdAurox\Csrf;
use OsdAurox\Dbo;
use OsdAurox\Flash;
use OsdAurox\Otp;
use OsdAurox\Sec;

Csrf::protect(forcePerRequest: true);

$title = AppConfig::get('appName') . ' - Vérification en deux étapes';
$navActive = AppUrls::LOGIN;

// -----------------------------------------------------------------------------
// Controller
// -----------------------------------------------------------------------------
$pdo = Dbo::getPdo();

// Pas de connexion en attente, ou plus de 5 minutes : retour à l'étape 1
$pendingId = (int) ($_SESSION['otp_pending']['id'] ?? 0);
$pendingExpires = (int) ($_SESSION['otp_pending']['expires'] ?? 0);
if (!$pendingId || $pendingExpires < time()) {
    unset($_SESSION['otp_pending']);
    Flash::error('Session expirée, reconnectez-vous');
    Base::redirect(AppUrls::LOGIN);
}

$user = UserModel::get($pdo, $pendingId);
if (!$user || !$user['is_active'] || !$user['is_otp_enabled'] || !$user['otp_secret']) {
    unset($_SESSION['otp_pending']);
    Flash::error('Connexion impossible, reconnectez-vous');
    Base::redirect(AppUrls::LOGIN);
}

// 10 codes faux par IP toutes les 10 minutes
$maxFailedOtp = 10;

if (Sec::isPost()) {

    if (!Sec::checkCurrentRateLimit(600, $maxFailedOtp, 'otp')) {
        http_response_code(429);
        die('Trop de tentatives. Réessayez dans 10 minutes.');
    }

    // '09' : ne garde que les chiffres (un code tapé "123 456" reste valable)
    $code = (string) Sec::getParam('code', '09', source: 1);

    // verify() rend la tranche de 30 s du code accepté, ou false.
    // Avec la dernière tranche utilisée, il refuse un code déjà servi (rejeu).
    $lastTimeslice = $user['otp_last_timeslice'] !== null ? (int) $user['otp_last_timeslice'] : null;
    $matchedTimeslice = Otp::verify($user['otp_secret'], $code, 1, $lastTimeslice);

    if ($matchedTimeslice !== false) {
        UserModel::updateOtpTimeslice($pdo, (int) $user['id'], $matchedTimeslice);
        Auth::finalizeLogin($pdo, $user);
    }

    // ---------- Échec : comptés comme les mauvais mots de passe ----------
    UserModel::registerFailedLogin($pdo, (int) $user['id']);
    Sec::setRateLimit(600, $maxFailedOtp, 'otp');

    Flash::error('Code invalide');
    Base::redirect(AppUrls::LOGIN_OTP);
}

// -----------------------------------------------------------------------------
// View
// -----------------------------------------------------------------------------
require_once APP_ROOT . '/templates/layout/header.php';
?>
<main class="ax-narrow">
    <h1>Vérification</h1>
    <p>Saisissez le code à 6 chiffres affiché par votre application d'authentification (Aegis, Google Authenticator, FreeOTP…).</p>

    <form action="<?= AppUrls::LOGIN_OTP ?>" method="post" class="ax-form" autocomplete="off">
        <?= Csrf::inputHtml() ?>

        <div class="mb-3">
            <label for="code" class="form-label">Code</label>
            <input type="text" id="code" name="code" class="form-control ax-otp-input" required autofocus
                   inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" placeholder="123456"
                   autocomplete="one-time-code">
        </div>

        <button type="submit" class="ax-btn ax-btn-primary">Valider</button>
    </form>

    <p class="mt-4"><a href="<?= AppUrls::LOGOUT ?>">Annuler et revenir à la connexion</a></p>
</main>
<?php
require_once APP_ROOT . '/templates/layout/footer.php';
