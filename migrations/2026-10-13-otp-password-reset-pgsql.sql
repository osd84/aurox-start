-- -----------------------------------------------------------------------------
-- Aurox Start : double authentification (OTP) et mot de passe oublié, PostgreSQL
-- À jouer APRÈS 2026-10-12-schema-pgsql.sql :
-- psql -d aurox_start -f migrations/2026-10-13-otp-password-reset-pgsql.sql
-- -----------------------------------------------------------------------------

-- OTP (TOTP, OsdAurox\Otp) :
--   otp_secret          clé partagée avec l'application d'authentification (base32)
--   is_otp_enabled      1 une fois l'enrôlement confirmé par un premier code
--   otp_last_timeslice  dernière tranche de 30 s acceptée : un code ne sert qu'une fois (anti-rejeu)
ALTER TABLE users
    ADD COLUMN otp_secret         VARCHAR(64) NULL,
    ADD COLUMN is_otp_enabled     SMALLINT    NOT NULL DEFAULT 0,
    ADD COLUMN otp_last_timeslice INTEGER     NULL;

-- Mot de passe oublié : un jeton à usage unique par demande.
-- On ne stocke que l'empreinte SHA-256 du jeton : une fuite de la base ne donne aucun lien valide.
CREATE TABLE password_reset (
    id          SERIAL      PRIMARY KEY,
    user_id     INTEGER     NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    token_hash  CHAR(64)    NOT NULL,
    expires_at  TIMESTAMP   NOT NULL,
    used_at     TIMESTAMP   NULL,
    ip          VARCHAR(45) NULL,
    created_at  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_password_reset_token_hash UNIQUE (token_hash)
);

CREATE INDEX idx_password_reset_user_id ON password_reset (user_id);
