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
