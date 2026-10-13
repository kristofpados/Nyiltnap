-- =====================================================================
-- Nyílt nap kezelő — adatbázis-séma
--
-- Célplatform: MariaDB 10.3+ vagy MySQL 5.7+/8.x, InnoDB motor.
-- Karakterkészlet: utf8mb4 / utf8mb4_unicode_ci (teljes Unicode, ékezetek).
--
-- Idempotens: többször is lefuttatható, meglévő táblát és adatot NEM töröl.
-- Importálás: `php scripts/install.php` (ajánlott), vagy
--             `mysql -u USER -p DBNAME < database/schema.sql`
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- Szakmák / képzések. Az űrlap és a statisztikák is innen dolgoznak,
-- így a választható szakmák egyetlen helyen vannak definiálva.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS programs (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    slug        VARCHAR(80)   NOT NULL,
    name        VARCHAR(160)  NOT NULL,
    description VARCHAR(255)  NULL,
    icon        VARCHAR(40)   NULL,
    is_active   TINYINT(1)    NOT NULL DEFAULT 1,
    sort_order  INT           NOT NULL DEFAULT 0,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_programs_slug (slug),
    KEY idx_programs_active_sort (is_active, sort_order),
    CONSTRAINT chk_programs_active CHECK (is_active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Jelentkezések. A név szándékosan NEM egyedi: azonos nevű diákok
-- (sőt teljesen azonos adatokkal beküldött jelentkezések) is rögzíthetők.
-- reference_code: a jelentkezőnek mutatott, nem sorszám jellegű azonosító.
-- created_at: az alkalmazás időzónájában (APP_TIMEZONE) tárolt időpont.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS registrations (
    id                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    reference_code       CHAR(8)       NOT NULL,
    full_name            VARCHAR(120)  NOT NULL,
    current_school       VARCHAR(180)  NOT NULL,
    residency_preference ENUM('boarding', 'commuter') NOT NULL,
    is_graduating        TINYINT(1)    NOT NULL,
    created_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_registrations_reference (reference_code),
    KEY idx_registrations_created_at (created_at),
    KEY idx_registrations_graduating (is_graduating),
    KEY idx_registrations_residency (residency_preference, is_graduating),
    KEY idx_registrations_full_name (full_name),
    CONSTRAINT chk_registrations_graduating CHECK (is_graduating IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Kapcsolótábla: egy jelentkezés több szakmát is megjelölhet.
-- A kompozit elsődleges kulcs garantálja, hogy egy jelentkezés egy
-- szakmánál legfeljebb egyszer szerepel (a statisztikák erre építenek).
-- Jelentkezés törlésekor a kapcsolósorok automatikusan törlődnek
-- (CASCADE); szakmát viszont nem lehet törölni, amíg jelentkezés
-- hivatkozik rá (RESTRICT) — helyette inaktiválni kell.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS registration_programs (
    registration_id INT UNSIGNED NOT NULL,
    program_id      INT UNSIGNED NOT NULL,
    PRIMARY KEY (registration_id, program_id),
    KEY idx_registration_programs_program (program_id, registration_id),
    CONSTRAINT fk_registration_programs_registration
        FOREIGN KEY (registration_id) REFERENCES registrations (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_registration_programs_program
        FOREIGN KEY (program_id) REFERENCES programs (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Adminisztrátorok. Jelszó csak password_hash() kimenetként tárolódik.
-- Fiókot a `php scripts/create_admin.php` paranccsal lehet létrehozni.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username      VARCHAR(60)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME     NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Sikertelen bejelentkezések (brute-force védelem). Az IP-cím csak
-- SHA-256 hash formában kerül tárolásra, és a régi sorok automatikusan
-- törlődnek.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip_hash      CHAR(64)        NOT NULL,
    attempted_at DATETIME        NOT NULL,
    PRIMARY KEY (id),
    KEY idx_login_attempts_ip_time (ip_hash, attempted_at),
    KEY idx_login_attempts_time (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
