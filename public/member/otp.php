<?php
// -----------------------------------------------------------------------------
// Espace membre : double authentification (OTP / TOTP).
//
// Trois états, lus dans la base :
//   - désactivée      : otp_secret NULL
//   - en attente      : otp_secret posé, is_otp_enabled = 0 (QR code affiché, on attend un premier code)
//   - activée         : is_otp_enabled = 1
//
// Une seule page, un champ caché "otp_action" dit quel bouton a été pressé.
// Montre : Otp::generateSecret(), Otp::provisioningUri(), Otp::verify(),
// un QR code dessiné dans le navigateur (la clé ne part chez aucun service externe).
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../../aurox.php';

use App\AppUrls;
use App\Models\UserModel;
use OsdAurox\AppConfig;
use OsdAurox\Base;
use OsdAurox\Csrf;
use OsdAurox\Dbo;
use OsdAurox\Flash;
use OsdAurox\Otp;
use OsdAurox\Sec;

Sec::isLogged();
$csrf = Csrf::protect();

$title = AppConfig::get('appName') . ' - Double authentification';
$navActive = AppUrls::MEMBER_PROFILE;

// -----------------------------------------------------------------------------
// Controller
// -----------------------------------------------------------------------------
$pdo = Dbo::getPdo();

$userId = Sec::getUserIdOrDie();
$user = UserModel::get($pdo, $userId);
if (!$user) {
    unset($_SESSION['user']);
    Base::redirect(AppUrls::LOGIN);
}

if (Sec::isPost()) {

    $otpAction = Sec::getParam('otp_action', 'aZ09', source: 1);

    // ---------- Commencer (ou recommencer) l'enrôlement ----------
    if ($otpAction === 'generate' && !$user['is_otp_enabled']) {
        UserModel::setOtpSecret($pdo, $userId, Otp::generateSecret());
        Flash::info('Scannez le QR code avec votre application, puis saisissez le code affiché.');
        Base::redirect(AppUrls::MEMBER_OTP);
    }

    // ---------- Confirmer avec un premier code : l'OTP devient actif ----------
    if ($otpAction === 'confirm' && $user['otp_secret'] && !$user['is_otp_enabled']) {
        $code = (string) Sec::getParam('code', '09', source: 1);
        $matchedTimeslice = Otp::verify($user['otp_secret'], $code);
        if ($matchedTimeslice !== false) {
            UserModel::enableOtp($pdo, $userId, $matchedTimeslice);
            Flash::success('Double authentification activée : le code vous sera demandé à chaque connexion.');
        } else {
            Flash::error('Code invalide. Vérifiez l\'heure de votre téléphone et réessayez.');
        }
        Base::redirect(AppUrls::MEMBER_OTP);
    }

    // ---------- Abandonner un enrôlement en cours ----------
    if ($otpAction === 'cancel' && !$user['is_otp_enabled']) {
        UserModel::disableOtp($pdo, $userId, $userId);
        Flash::info('Activation annulée');
        Base::redirect(AppUrls::MEMBER_OTP);
    }

    // ---------- Désactiver : on redemande le mot de passe ----------
    if ($otpAction === 'disable' && $user['is_otp_enabled']) {
        $postPassword = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
        if (password_verify($postPassword, $user['password'])) {
            UserModel::disableOtp($pdo, $userId, $userId);
            Flash::success('Double authentification désactivée');
        } else {
            Flash::error('Mot de passe incorrect, la double authentification reste active.');
        }
        Base::redirect(AppUrls::MEMBER_OTP);
    }

    Base::redirect(AppUrls::MEMBER_OTP);
}

// En attente de confirmation : l'URI otpauth:// que le QR code encode
$provisioningUri = null;
$secretForDisplay = null;
if ($user['otp_secret'] && !$user['is_otp_enabled']) {
    $provisioningUri = Otp::provisioningUri($user['otp_secret'], $user['email'], AppConfig::get('appName', safe: true));
    // la clé par groupes de 4, pour la saisir à la main si le QR code ne passe pas
    $secretForDisplay = implode(' ', str_split($user['otp_secret'], 4));
}

// -----------------------------------------------------------------------------
// View
// -----------------------------------------------------------------------------
require_once APP_ROOT . '/templates/layout/header.php';
?>
<main>
    <p><a href="<?= AppUrls::MEMBER_PROFILE ?>">← Mon profil</a></p>
    <h1>Double authentification</h1>
    <p>
        En plus du mot de passe, un code à 6 chiffres, qui change toutes les 30 secondes, vous sera demandé à la connexion.
        Il est donné par une application sur votre téléphone : Aegis, FreeOTP, Google Authenticator, Microsoft Authenticator…
    </p>

    <?php if ($user['is_otp_enabled']): ?>

        <div class="ax-flash ax-flash-success"><strong>Activée</strong> Votre compte est protégé par un second facteur.</div>

        <h2>Désactiver</h2>
        <form action="<?= AppUrls::MEMBER_OTP ?>" method="post" class="ax-narrow" autocomplete="off"
              data-confirm="Désactiver la double authentification ?">
            <?= Csrf::inputHtml() ?>
            <input type="hidden" name="otp_action" value="disable">
            <div class="mb-3">
                <label for="current_password" class="form-label">Mot de passe actuel</label>
                <input type="password" id="current_password" name="current_password" class="form-control" required
                       autocomplete="current-password">
            </div>
            <button type="submit" class="ax-btn ax-btn-danger">Désactiver</button>
        </form>

    <?php elseif ($provisioningUri): ?>

        <div class="ax-flash ax-flash-warning"><strong>En attente</strong> Scannez, puis confirmez avec un premier code.</div>

        <div class="row">
            <div class="col-md-5">
                <h2>1. Scanner</h2>
                <?php // l'URI passe par un attribut data- (échappé), jamais écrite dans le JS ?>
                <div id="otp-qrcode" class="ax-qrcode" data-uri="<?= Sec::hNoHtml($provisioningUri) ?>"></div>
                <p class="ax-meta mt-2">
                    Ou saisissez la clé à la main :<br>
                    <code class="ax-otp-secret"><?= Sec::hNoHtml($secretForDisplay) ?></code>
                </p>
            </div>
            <div class="col-md-7">
                <h2>2. Confirmer</h2>
                <form action="<?= AppUrls::MEMBER_OTP ?>" method="post" autocomplete="off">
                    <?= Csrf::inputHtml() ?>
                    <input type="hidden" name="otp_action" value="confirm">
                    <div class="mb-3">
                        <label for="code" class="form-label">Code affiché par l'application</label>
                        <input type="text" id="code" name="code" class="form-control ax-otp-input" required autofocus
                               inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" placeholder="123456"
                               autocomplete="one-time-code">
                    </div>
                    <button type="submit" class="ax-btn ax-btn-primary">Activer</button>
                </form>

                <form action="<?= AppUrls::MEMBER_OTP ?>" method="post" class="mt-3">
                    <?= Csrf::inputHtml() ?>
                    <input type="hidden" name="otp_action" value="cancel">
                    <button type="submit" class="ax-btn ax-btn-sm">Annuler l'activation</button>
                </form>
            </div>
        </div>

        <!-- qrcode-generator (MIT), servi en local : la clé secrète ne quitte pas le navigateur -->
        <script src="/plugin/qrcode-generator/qrcode.js"></script>
        <script nonce="<?= Sec::noneCsp() ?>">
            $(function () {
                var $qrcode = $('#otp-qrcode');
                var qr = qrcode(0, 'M');           // version auto, correction d'erreur moyenne
                qr.addData($qrcode.attr('data-uri'));
                qr.make();
                $qrcode.html(qr.createSvgTag(5, 8));
            });
        </script>

    <?php else: ?>

        <div class="ax-flash ax-flash-info"><strong>Désactivée</strong> Seul votre mot de passe protège votre compte.</div>

        <form action="<?= AppUrls::MEMBER_OTP ?>" method="post">
            <?= Csrf::inputHtml() ?>
            <input type="hidden" name="otp_action" value="generate">
            <button type="submit" class="ax-btn ax-btn-primary">Activer la double authentification</button>
        </form>

    <?php endif; ?>

    <p class="ax-meta mt-4">
        Téléphone perdu ? Un administrateur peut désactiver la double authentification depuis votre fiche.
    </p>
</main>
<?php
require_once APP_ROOT . '/templates/layout/footer.php';
