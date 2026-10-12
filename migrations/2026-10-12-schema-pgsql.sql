-- -----------------------------------------------------------------------------
-- Aurox Start : schéma PostgreSQL
-- psql -U aurox_start -d aurox_start -f migrations/2026-10-12-schema-pgsql.sql
--
-- Même schéma que la version MySQL, colonne pour colonne.
-- La table des comptes s'appelle `users` (et non `user`) : `user` est un mot
-- réservé en PostgreSQL, et aurox (Sec::needChangePassword) attend `users`.
-- -----------------------------------------------------------------------------

CREATE TABLE users (
    id                    SERIAL       PRIMARY KEY,
    email                 VARCHAR(255) NOT NULL,
    username              VARCHAR(100) NOT NULL,
    password              VARCHAR(255) NOT NULL,
    role                  VARCHAR(20)  NOT NULL DEFAULT 'user',
    is_active             SMALLINT     NOT NULL DEFAULT 1,
    failed_login_attempts INTEGER      NOT NULL DEFAULT 0,
    last_login_at         TIMESTAMP    NULL,
    created_at            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP    NULL,
    created_by            INTEGER      NULL,
    updated_by            INTEGER      NULL,
    CONSTRAINT uq_users_email UNIQUE (email)
);

CREATE TABLE post (
    id           SERIAL       PRIMARY KEY,
    title        VARCHAR(255) NOT NULL,
    slug         VARCHAR(255) NOT NULL,
    excerpt      VARCHAR(500) NULL,
    content      TEXT         NOT NULL,
    status       VARCHAR(20)  NOT NULL DEFAULT 'draft',
    published_at TIMESTAMP    NULL,
    user_id      INTEGER      NOT NULL REFERENCES users (id),
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    NULL,
    created_by   INTEGER      NULL,
    updated_by   INTEGER      NULL,
    CONSTRAINT uq_post_slug UNIQUE (slug)
);

CREATE INDEX idx_post_status_published_at ON post (status, published_at);
