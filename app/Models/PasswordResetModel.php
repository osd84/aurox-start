<?php

namespace App\Models;

use OsdAurox\BaseModel;
use PDO;

/**
 * Les demandes de réinitialisation de mot de passe ("mot de passe oublié").
 *
 * Le jeton envoyé par mail n'est JAMAIS stocké : seule son empreinte SHA-256 l'est.
 * Quelqu'un qui lit la base ne peut donc pas fabriquer un lien valide.
 * Un jeton sert une fois (used_at) et expire (expires_at).
 */
class PasswordResetModel extends BaseModel
{
    public const TABLE = 'password_reset';

    // Durée de validité d'un lien, en minutes
    public const LIFETIME_MINUTES = 60;

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Crée une demande et rend le jeton EN CLAIR (à mettre dans le lien du mail, nulle part ailleurs).
     * Les demandes précédentes encore ouvertes de ce compte sont annulées : seul le dernier lien marche.
     */
    public static function createForUser(PDO $pdo, int $userId, string $ip): string
    {
        self::invalidateForUser($pdo, $userId);

        // 32 octets aléatoires = 64 caractères hexadécimaux
        $token = bin2hex(random_bytes(32));

        $stmt = $pdo->prepare('INSERT INTO password_reset (user_id, token_hash, expires_at, ip, created_at)
                               VALUES (:user_id, :token_hash, :expires_at, :ip, :created_at)');
        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => self::hashToken($token),
            'expires_at' => date('Y-m-d H:i:s', time() + self::LIFETIME_MINUTES * 60),
            'ip' => $ip,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $token;
    }

    /**
     * La demande correspondant à un jeton, si elle est encore utilisable
     * (pas utilisée, pas expirée, compte actif). null sinon.
     */
    public static function getValidByToken(PDO $pdo, string $token): ?array
    {
        $stmt = $pdo->prepare('SELECT pr.id, pr.user_id, pr.expires_at, u.email, u.username
                               FROM password_reset pr
                               JOIN users u ON u.id = pr.user_id
                               WHERE pr.token_hash = :token_hash
                                 AND pr.used_at IS NULL
                                 AND pr.expires_at > :now
                                 AND u.is_active = 1');
        $stmt->execute([
            'token_hash' => self::hashToken($token),
            'now' => date('Y-m-d H:i:s'),
        ]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);
        return $reset ?: null;
    }

    /**
     * Annule toutes les demandes encore ouvertes d'un compte (nouvelle demande, ou mot de passe changé).
     */
    public static function invalidateForUser(PDO $pdo, int $userId): void
    {
        $stmt = $pdo->prepare('UPDATE password_reset SET used_at = :used_at WHERE user_id = :user_id AND used_at IS NULL');
        $stmt->execute([
            'used_at' => date('Y-m-d H:i:s'),
            'user_id' => $userId,
        ]);
    }
}
