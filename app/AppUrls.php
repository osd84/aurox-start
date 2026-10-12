<?php

namespace App;

use OsdAurox\Sec;

/**
 * Toutes les URLs du site, en constantes.
 *
 * C'est aussi le routeur : aurox.php appelle AppUrls::existOr404() et toute
 * URL qui n'est pas ici part en 404. Ajouter une page = créer le fichier dans
 * public/ ET déclarer sa constante ici.
 *
 * Les noms LOGIN et LOGOUT sont lus par aurox (Sec::isAdminOrDie(), Sec::isLogged()...) :
 * ne pas les renommer.
 */
class AppUrls
{
    public const HOME = '/';
    public const GUIDE = '/guide.php';

    # 404
    public const NOT_FOUND = '/404.php';

    # Blog
    public const BLOG = '/blog.php';
    public const POST = '/post.php';
    public const RSS = '/rss.php';

    # Authentification
    public const LOGIN = '/auth/login.php';
    public const LOGOUT = '/auth/login.php?action=logout';
    public const REGISTER = '/auth/register.php';
    public const LOGIN_OTP = '/auth/login_otp.php';
    public const PASSWORD_FORGOT = '/auth/password_forgot.php';
    public const PASSWORD_RESET = '/auth/password_reset.php';

    # Espace membre
    public const MEMBER_PROFILE = '/member/profile.php';
    public const MEMBER_OTP = '/member/otp.php';

    # Administration
    public const ADMIN_HOME = '/admin/index.php';
    public const ADMIN_POSTS = '/admin/posts.php';
    public const ADMIN_POST_EDIT = '/admin/post_edit.php';
    public const ADMIN_POST_DELETE = '/admin/post_delete.php';
    public const ADMIN_USERS = '/admin/users.php';
    public const ADMIN_USER_EDIT = '/admin/user_edit.php';
    public const ADMIN_USER_DELETE = '/admin/user_delete.php';
    public const ADMIN_USER_OTP_RESET = '/admin/user_otp_reset.php';

    # Ajax
    public const AJAX_POST_TOGGLE_STATUS = '/ajax/post_toggle_status.php';

    # Liens externes
    public const AUROX_DOC = 'https://aurox.fr';
    public const AUROX_GITHUB = 'https://github.com/osd84/aurox-dist';


    public static function getList(): array
    {
        $reflect = new \ReflectionClass(__CLASS__);
        return $reflect->getConstants();
    }

    public static function existOr404()
    {
        if(!isset($_SERVER['REQUEST_URI'])) {
            return true;
        }
        $url = $_SERVER['REQUEST_URI'] ?? '/';
        $url = trim($url, '/');
        $url = explode('?', $url);
        $url = $url[0];
        $url = trim($url, '/');
        $url = strtolower($url);
        if(str_starts_with('/', $url)){
            $url = substr($url, 1);
        }
        $url = '/' . $url;
        if (in_array($url, ['/', '/index.php']))
        {
            return true;
        }
        $urls = self::getList();
        if(in_array($url, $urls)) {
            return true;
        }
        header('Location: ' . AppUrls::NOT_FOUND . '?url=' . Sec::hNoHtml($url));
        exit;
    }
}
