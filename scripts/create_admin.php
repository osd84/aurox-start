<?php
// -----------------------------------------------------------------------------
// Crée (ou promeut) un compte administrateur, en ligne de commande.
//
//   php scripts/create_admin.php admin@exemple.fr "Mon nom"
//
// Le mot de passe est demandé au clavier, jamais passé en argument
// (il resterait dans l'historique du shell). Si l'email existe déjà,
// le compte passe admin, est réactivé, reçoit le nouveau mot de passe et
// perd sa double authentification : c'est la porte de secours si le seul
// admin a perdu son téléphone ou son mot de passe.
// -----------------------------------------------------------------------------

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../aurox.php';

use App\Models\PasswordResetModel;
use App\Models\UserModel;
use OsdAurox\AppConfig;
use OsdAurox\Dbo;
use OsdAurox\Sec;

$email = Sec::sanitize(mb_strtolower($argv[1] ?? ''), 'email');
$username = trim(strip_tags($argv[2] ?? ''));

if (!$email || $username === '') {
    echo "Usage : php scripts/create_admin.php email@exemple.fr \"Nom affiché\"\n";
    exit(1);
}

// Lecture du mot de passe sans l'afficher (stty, sous Linux / macOS)
function askPassword(string $question): string
{
    echo $question;
    $hasStty = DIRECTORY_SEPARATOR === '/' && function_exists('posix_isatty') && posix_isatty(STDIN);
    if ($hasStty) {
        shell_exec('stty -echo');
    }
    $value = rtrim((string) fgets(STDIN), "\r\n");
    if ($hasStty) {
        shell_exec('stty echo');
        echo "\n";
    }
    return $value;
}

$passwordMinLength = (int) AppConfig::get('passwordMinLength', 10, safe: true);

$password = askPassword('Mot de passe (' . $passwordMinLength . ' caractères minimum) : ');
$passwordConfirm = askPassword('Confirmer : ');

if (mb_strlen($password) < $passwordMinLength) {
    echo "Mot de passe trop court.\n";
    exit(1);
}
if ($password !== $passwordConfirm) {
    echo "Les deux mots de passe sont différents.\n";
    exit(1);
}

$pdo = Dbo::getPdo();

$existing = UserModel::getBy($pdo, 'email', $email);

if ($existing) {
    $id = (int) $existing['id'];
    UserModel::update($pdo, $id, [
        'email' => $email,
        'username' => $username,
        'role' => UserModel::ROLE_ADMIN,
        'is_active' => 1,
    ], null);
    UserModel::updatePassword($pdo, $id, $password, null);
    UserModel::resetFailedLogin($pdo, $id);
    UserModel::disableOtp($pdo, $id, null);
    PasswordResetModel::invalidateForUser($pdo, $id);
    echo "Compte #$id mis à jour : admin, actif, nouveau mot de passe, OTP désactivé.\n";
    exit(0);
}

$id = UserModel::insert($pdo, [
    'email' => $email,
    'username' => $username,
    'password' => $password,
    'role' => UserModel::ROLE_ADMIN,
    'is_active' => 1,
], null);

echo "Admin #$id créé : $email\n";
