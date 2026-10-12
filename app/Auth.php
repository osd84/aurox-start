<?php

namespace App;

use App\Models\UserModel;
use OsdAurox\AppConfig;
use OsdAurox\Base;
use OsdAurox\Discord;
use OsdAurox\Flash;
use PDO;

/**
 * La fin d'une connexion réussie, partagée par les deux portes d'entrée :
 * public/auth/login.php (compte sans OTP) et public/auth/login_otp.php (après le code).
 *
 * Une seule fonction, volontairement : c'est du code de sécurité, on ne veut pas
 * deux copies qui divergent.
 */
class Auth
{
    /**
     * Ouvre la session du compte puis redirige (admin -> administration, membre -> profil).
     * Ne rend jamais la main.
     */
    public static function finalizeLogin(PDO $pdo, array $user): never
    {
        UserModel::registerSuccessfulLogin($pdo, (int) $user['id']);

        // Nouvel identifiant de session à chaque changement de privilège (fixation de session)
        session_regenerate_id(true);
        unset($_SESSION['otp_pending']);

        // C'est tout ce que aurox lit pour savoir qui est connecté :
        // Sec::isLoggedBool(), Sec::isAdminBool(), Sec::isRoleBool(), Sec::getUserIdOrDie()
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];

        Flash::success('Connexion réussie');

        if ($user['role'] === UserModel::ROLE_ADMIN) {
            // alerte facultative, si 'discordWebhook' est renseigné dans conf.php
            if (AppConfig::get('discordWebhook')) {
                Discord::send('Connexion admin de ' . $user['email'] . ' sur ' . AppConfig::get('appName'));
            }
            Base::redirect(AppUrls::ADMIN_HOME);
        }
        Base::redirect(AppUrls::MEMBER_PROFILE);
    }
}
