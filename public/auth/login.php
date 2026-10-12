<?php
// -----------------------------------------------------------------------------
// Connexion / déconnexion.
//
//   GET  /auth/login.php                 affiche le formulaire
//   POST /auth/login.php                 vérifie email + mot de passe
//   GET  /auth/login.php?action=logout   déconnexion
//
// Si le compte a la double authentification, la connexion continue dans login_otp.php.
//
// Montre : Csrf::protect(), Sec::checkCurrentRateLimit() / setRateLimit(),
// password_verify(), session_regenerate_id(), $_SESSION['user'] (lu par Sec::isAdminOrDie() & co).
// -----------------------------------------------------------------------------
require_once __DIR__ . '/../../aurox.php';

use App\AppUrls;
use App\Auth;
use App\Models\UserModel;
use OsdAurox\AppConfig;
use OsdAurox\Base;
use OsdAurox\Csrf;
use OsdAurox\Dbo;
use OsdAurox\Discord;
use OsdAurox\Flash;
use OsdAurox\Sec;

// Jeton CSRF renouvelé à chaque requête sur cette page sensible.
// Un POST sans jeton valide s'arrête ici.
Csrf::protect(forcePerRequest: true);

$title = AppConfig::get('appName') . ' - Connexion';
$navActive = AppUrls::LOGIN;

// -----------------------------------------------------------------------------
// Controller
// -----------------------------------------------------------------------------
$pdo = Dbo::getPdo();

// Rate-limit par IP : 20 échecs par heure, puis plus rien n'est vérifié.
$maxFailedPerHour = 20;

$action = Sec::getAction('login', source: 0);

// ---------- Déconnexion ----------
if ($action === 'logout') {
    unset($_SESSION['user'], $_SESSION['otp_pending']);
    session_regenerate_id(true);
    Flash::info('Vous êtes déconnecté');
    Base::redirect(AppUrls::HOME);
}

// ---------- Déjà connecté : rien à faire ici ----------
if (Sec::isLoggedBool()) {
    Base::redirect(Sec::isAdminBool() ? AppUrls::ADMIN_HOME : AppUrls::MEMBER_PROFILE);
}

// ---------- Traitement du formulaire ----------
if (Sec::isPost()) {

    // checkCurrentRateLimit() lit le compteur SANS l'incrémenter : seuls les échecs comptent (plus bas)
    if (!Sec::checkCurrentRateLimit(3600, $maxFailedPerHour, 'login')) {
        http_response_code(429);
        die('Trop de tentatives. Réessayez dans une heure.');
    }

    // 'email' : filter_var(FILTER_VALIDATE_EMAIL), rend '' si invalide
    $email = (string) Sec::getParam('email', 'email', source: 1, lowerCase: True);
    // le mot de passe est lu brut : on ne filtre pas ce qu'on va hacher / comparer
    $password = $_POST['password'] ?? '';
    if (!is_string($password)) {
        $password = '';
    }

    // ralentit les attaques par mesure du temps de réponse
    usleep(random_int(100000, 300000));

    $user = null;
    if ($email && $password !== '') {
        $user = UserModel::getBy($pdo, 'email', $email);
    }

    if ($user && password_verify($password, $user['password'])) {

        if (!$user['is_active']) {
            Flash::error('Compte désactivé, contactez un administrateur.');
            Base::redirect(AppUrls::LOGIN);
        }

        // Double authentification activée : le mot de passe ne suffit pas.
        // On note en session QUI attend son code (5 minutes), sans le connecter, puis on passe à l'étape 2.
        if ($user['is_otp_enabled']) {
            session_regenerate_id(true);
            $_SESSION['otp_pending'] = [
                'id' => (int) $user['id'],
                'expires' => time() + 300,
            ];
            Base::redirect(AppUrls::LOGIN_OTP);
        }

        // Pas d'OTP : connexion directe (session, Flash, redirection selon le rôle)
        Auth::finalizeLogin($pdo, $user);
    }

    // ---------- Échec ----------
    // Le compte visé (s'il existe) prend un échec, et se bloque au 5e
    if ($user) {
        $attempts = UserModel::registerFailedLogin($pdo, (int) $user['id']);
        if ($attempts === UserModel::MAX_FAILED_LOGIN_ATTEMPTS && AppConfig::get('discordWebhook')) {
            Discord::send('Compte bloqué après ' . $attempts . ' échecs : ' . $user['email'] . ' sur ' . AppConfig::get('appName'));
        }
    }
    // L'IP prend un échec
    Sec::setRateLimit(3600, $maxFailedPerHour, 'login');

    // Même message que le compte existe ou non : on ne révèle pas les emails inscrits
    Flash::error('Email ou mot de passe incorrect');
    Base::redirect(AppUrls::LOGIN);
}

// -----------------------------------------------------------------------------
// View
// -----------------------------------------------------------------------------
require_once APP_ROOT . '/templates/layout/header.php';
?>
<main class="ax-narrow">
    <h1>Connexion</h1>

    <form action="<?= AppUrls::LOGIN ?>" method="post" class="ax-form">
        <?= Csrf::inputHtml() ?>

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" id="email" name="email" class="form-control" required autofocus
                   autocomplete="username">
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">Mot de passe</label>
            <input type="password" id="password" name="password" class="form-control" required
                   autocomplete="current-password">
        </div>

        <button type="submit" class="ax-btn ax-btn-primary">Se connecter</button>
    </form>

    <p class="mt-4"><a href="<?= AppUrls::PASSWORD_FORGOT ?>">Mot de passe oublié ?</a></p>

    <?php if (AppConfig::get('featureRegister', false, safe: true)): ?>
        <p>Pas encore de compte ? <a href="<?= AppUrls::REGISTER ?>">Créer un compte</a></p>
    <?php endif; ?>
</main>
<?php
require_once APP_ROOT . '/templates/layout/footer.php';
