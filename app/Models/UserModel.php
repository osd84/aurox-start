<?php

namespace App\Models;

use OsdAurox\BaseModel;
use OsdAurox\Dbo;
use PDO;
use PDOException;

/**
 * Les comptes utilisateurs.
 *
 * Un modèle aurox = une classe de méthodes statiques qui reçoivent le PDO.
 * Pas d'objet, pas d'hydratation : des tableaux en entrée, des tableaux en sortie.
 * BaseModel fournit déjà get(), getBy(), getAll(), count(), delete(), check_uniq()...
 * mais pas d'insert() / update() : on les écrit ici, colonne par colonne.
 */
class UserModel extends BaseModel
{
    public const TABLE = 'users';

    public const ROLE_ADMIN = 'admin';
    public const ROLE_USER = 'user';

    // Nombre d'échecs de connexion avant blocage du compte
    public const MAX_FAILED_LOGIN_ATTEMPTS = 5;

    /**
     * Options du select "rôle" (format attendu par Forms::select()).
     */
    public static function getSelectRoles(): array
    {
        return [
            ['id' => self::ROLE_USER, 'name' => 'Membre'],
            ['id' => self::ROLE_ADMIN, 'name' => 'Administrateur'],
        ];
    }

    /**
     * Requêtes de la liste admin, pour le Paginator.
     *
     * @param string|null $searchLike motif LIKE déjà préparé ('%TEXTE%'), null = pas de filtre
     */
    public static function listQueries(?string $searchLike): array
    {
        $where = '';
        $bindParams = [];
        if ($searchLike) {
            $where = ' WHERE UPPER(email) LIKE :s1 OR UPPER(username) LIKE :s2';
            $bindParams = [':s1' => $searchLike, ':s2' => $searchLike];
        }

        $query = 'SELECT id, email, username, role, is_active, is_otp_enabled, failed_login_attempts, last_login_at, created_at
                  FROM users' . $where . '
                  ORDER BY id DESC';
        $countQuery = 'SELECT COUNT(*) FROM users' . $where;

        return [
            'query' => $query,
            'count_query' => $countQuery,
            'bind_params' => $bindParams,
        ];
    }

    public static function countByRole(PDO $pdo, string $role): int
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE role = :role');
        $stmt->execute(['role' => $role]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Crée un compte. Le mot de passe arrive en clair et repart haché.
     *
     * @return int id du compte créé
     */
    public static function insert(PDO $pdo, array $user, ?int $byUserId): int
    {
        $sql = 'INSERT INTO users (email, username, password, role, is_active, created_at, created_by)
                VALUES (:email, :username, :password, :role, :is_active, :created_at, :created_by)';
        // lastInsertId() demande un nom de séquence en PostgreSQL : on préfère RETURNING id
        if (Dbo::isPgsql()) {
            $sql .= ' RETURNING id';
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'email' => $user['email'],
            'username' => $user['username'],
            'password' => password_hash($user['password'], PASSWORD_DEFAULT),
            'role' => $user['role'],
            'is_active' => $user['is_active'],
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $byUserId,
        ]);

        if (Dbo::isPgsql()) {
            return (int) $stmt->fetchColumn();
        }
        return (int) $pdo->lastInsertId();
    }

    /**
     * Met à jour un compte (sans le mot de passe, voir updatePassword()).
     */
    public static function update(PDO $pdo, int $id, array $user, ?int $byUserId): void
    {
        $stmt = $pdo->prepare('UPDATE users SET
                    email = :email,
                    username = :username,
                    role = :role,
                    is_active = :is_active,
                    updated_at = :updated_at,
                    updated_by = :updated_by
                WHERE id = :id');
        $stmt->execute([
            'email' => $user['email'],
            'username' => $user['username'],
            'role' => $user['role'],
            'is_active' => $user['is_active'],
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $byUserId,
            'id' => $id,
        ]);
    }

    /**
     * Le membre modifie son propre profil : seulement son nom affiché.
     */
    public static function updateProfile(PDO $pdo, int $id, string $username): void
    {
        $stmt = $pdo->prepare('UPDATE users SET
                    username = :username,
                    updated_at = :updated_at,
                    updated_by = :updated_by
                WHERE id = :id');
        $stmt->execute([
            'username' => $username,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $id,
            'id' => $id,
        ]);
    }

    public static function updatePassword(PDO $pdo, int $id, string $plainPassword, ?int $byUserId): void
    {
        $stmt = $pdo->prepare('UPDATE users SET
                    password = :password,
                    updated_at = :updated_at,
                    updated_by = :updated_by
                WHERE id = :id');
        $stmt->execute([
            'password' => password_hash($plainPassword, PASSWORD_DEFAULT),
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $byUserId,
            'id' => $id,
        ]);
    }

    /**
     * Un échec de connexion de plus. Au-delà de MAX_FAILED_LOGIN_ATTEMPTS le compte est désactivé.
     *
     * @return int le nombre d'échecs après incrément
     */
    public static function registerFailedLogin(PDO $pdo, int $id): int
    {
        $stmt = $pdo->prepare('UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);

        $stmt = $pdo->prepare('SELECT failed_login_attempts FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $attempts = (int) $stmt->fetchColumn();

        if ($attempts >= self::MAX_FAILED_LOGIN_ATTEMPTS) {
            $stmt = $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = :id');
            $stmt->execute(['id' => $id]);
        }

        return $attempts;
    }

    /**
     * Connexion réussie : remise à zéro des échecs et date de dernière connexion.
     */
    public static function registerSuccessfulLogin(PDO $pdo, int $id): void
    {
        $stmt = $pdo->prepare('UPDATE users SET
                    failed_login_attempts = 0,
                    last_login_at = :last_login_at
                WHERE id = :id');
        $stmt->execute([
            'last_login_at' => date('Y-m-d H:i:s'),
            'id' => $id,
        ]);
    }

    /**
     * Réactiver un compte bloqué remet aussi le compteur d'échecs à zéro.
     */
    public static function resetFailedLogin(PDO $pdo, int $id): void
    {
        $stmt = $pdo->prepare('UPDATE users SET failed_login_attempts = 0 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    // -------------------------------------------------------------------------
    // Double authentification (OTP)
    // -------------------------------------------------------------------------

    /**
     * Étape 1 de l'enrôlement : on pose une nouvelle clé, pas encore active.
     * Elle ne le deviendra qu'avec un premier code correct (enableOtp).
     */
    public static function setOtpSecret(PDO $pdo, int $id, string $secret): void
    {
        $stmt = $pdo->prepare('UPDATE users SET
                    otp_secret = :otp_secret,
                    is_otp_enabled = 0,
                    otp_last_timeslice = NULL,
                    updated_at = :updated_at,
                    updated_by = :updated_by
                WHERE id = :id');
        $stmt->execute([
            'otp_secret' => $secret,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $id,
            'id' => $id,
        ]);
    }

    /**
     * Étape 2 : le premier code est bon, l'OTP est actif.
     *
     * @param int $timeslice tranche de 30 s du code utilisé (anti-rejeu)
     */
    public static function enableOtp(PDO $pdo, int $id, int $timeslice): void
    {
        $stmt = $pdo->prepare('UPDATE users SET
                    is_otp_enabled = 1,
                    otp_last_timeslice = :otp_last_timeslice,
                    updated_at = :updated_at,
                    updated_by = :updated_by
                WHERE id = :id');
        $stmt->execute([
            'otp_last_timeslice' => $timeslice,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $id,
            'id' => $id,
        ]);
    }

    /**
     * Désactive l'OTP et efface la clé (par le membre, ou par un admin si le téléphone est perdu).
     */
    public static function disableOtp(PDO $pdo, int $id, ?int $byUserId): void
    {
        $stmt = $pdo->prepare('UPDATE users SET
                    otp_secret = NULL,
                    is_otp_enabled = 0,
                    otp_last_timeslice = NULL,
                    updated_at = :updated_at,
                    updated_by = :updated_by
                WHERE id = :id');
        $stmt->execute([
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $byUserId,
            'id' => $id,
        ]);
    }

    /**
     * Mémorise la tranche de 30 s du dernier code accepté : Otp::verify() refusera
     * ensuite tout code de cette tranche ou d'une plus ancienne (un code volé ne resert pas).
     */
    public static function updateOtpTimeslice(PDO $pdo, int $id, int $timeslice): void
    {
        $stmt = $pdo->prepare('UPDATE users SET otp_last_timeslice = :otp_last_timeslice WHERE id = :id');
        $stmt->execute([
            'otp_last_timeslice' => $timeslice,
            'id' => $id,
        ]);
    }

    /**
     * Supprime un compte. Rend false si une clé étrangère le retient
     * (l'utilisateur a écrit des articles) au lieu de planter.
     */
    public static function deleteSafe(PDO $pdo, int $id): bool
    {
        try {
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $stmt->execute(['id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            // 23000 : contrainte MySQL, 23503 : clé étrangère PostgreSQL
            if (in_array($e->getCode(), ['23000', '23503'], true)) {
                return false;
            }
            throw $e;
        }
    }
}
