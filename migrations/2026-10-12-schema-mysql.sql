-- -----------------------------------------------------------------------------
-- Aurox Start : schéma MySQL / MariaDB
-- mysql -u root -p aurox_start < migrations/2026-10-12-schema-mysql.sql
--
-- Conventions aurox : clé primaire `id`, clé étrangère `<table>_id`,
-- horodatage `xxx_at`, booléen `is_xxx` en smallint 0/1,
-- socle id / created_at / updated_at / created_by / updated_by.
-- La table des comptes s'appelle `users` (et non `user`) : `user` est un mot
-- réservé en PostgreSQL, et aurox (Sec::needChangePassword) attend `users`.
-- -----------------------------------------------------------------------------

CREATE TABLE users (
    id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email                 VARCHAR(255) NOT NULL,
    username              VARCHAR(100) NOT NULL,
    password              VARCHAR(255) NOT NULL,
    role                  VARCHAR(20)  NOT NULL DEFAULT 'user',
    is_active             SMALLINT     NOT NULL DEFAULT 1,
    failed_login_attempts INT          NOT NULL DEFAULT 0,
    last_login_at         DATETIME     NULL,
    created_at            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME     NULL,
    created_by            INT UNSIGNED NULL,
    updated_by            INT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE post (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title        VARCHAR(255) NOT NULL,
    slug         VARCHAR(255) NOT NULL,
    excerpt      VARCHAR(500) NULL,
    content      TEXT         NOT NULL,
    status       VARCHAR(20)  NOT NULL DEFAULT 'draft',
    published_at DATETIME     NULL,
    user_id      INT UNSIGNED NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NULL,
    created_by   INT UNSIGNED NULL,
    updated_by   INT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_post_slug (slug),
    KEY idx_post_status_published_at (status, published_at),
    CONSTRAINT fk_post_user FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Aurox Start : double authentification (OTP) et mot de passe oublié, MySQL / MariaDB
-- À jouer APRÈS 2026-10-12-schema-mysql.sql :
-- mysql -u root -p aurox_start < migrations/2026-10-13-otp-password-reset-mysql.sql
-- -----------------------------------------------------------------------------

-- OTP (TOTP, OsdAurox\Otp) :
--   otp_secret          clé partagée avec l'application d'authentification (base32)
--   is_otp_enabled      1 une fois l'enrôlement confirmé par un premier code
--   otp_last_timeslice  dernière tranche de 30 s acceptée : un code ne sert qu'une fois (anti-rejeu)
ALTER TABLE users
    ADD COLUMN otp_secret         VARCHAR(64) NULL,
    ADD COLUMN is_otp_enabled     SMALLINT    NOT NULL DEFAULT 0,
    ADD COLUMN otp_last_timeslice INT         NULL;

-- Mot de passe oublié : un jeton à usage unique par demande.
-- On ne stocke que l'empreinte SHA-256 du jeton : une fuite de la base ne donne aucun lien valide.
CREATE TABLE password_reset (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    token_hash  CHAR(64)     NOT NULL,
    expires_at  DATETIME     NOT NULL,
    used_at     DATETIME     NULL,
    ip          VARCHAR(45)  NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_password_reset_token_hash (token_hash),
    KEY idx_password_reset_user_id (user_id),
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
