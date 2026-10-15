-- VylarisAdvertise – Datenbankschema (MySQL 5.7+/8.x, MariaDB 10.4+)
-- Alle Geldbeträge werden als Ganzzahl in Cent gespeichert.
-- Provisionen werden in Basispunkten gespeichert (1500 = 15,00 %).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email           VARCHAR(190) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  name            VARCHAR(120) NOT NULL,
  company         VARCHAR(160) NULL,
  -- 'both' = Werbetreibender und Webseitenbetreiber in einem Konto
  role            ENUM('advertiser','publisher','both','admin') NOT NULL,
  status          ENUM('active','blocked','deleted') NOT NULL DEFAULT 'active',
  payout_details  VARCHAR(500) NULL,
  -- Datenschutz: Nachweis der Zustimmung zu den Nutzungsbedingungen, Löschung/Anonymisierung (Art. 7, 17 DSGVO)
  consent_at      DATETIME NULL,
  consent_version VARCHAR(20) NULL,
  deleted_at      DATETIME NULL,
  anonymize_after DATE NULL,
  -- Für spätere Anbindung an das VylarisGroup-Ökosystem (SSO / Vylaris-Licence)
  external_source VARCHAR(40) NULL,
  external_id     VARCHAR(120) NULL,
  last_login_at   DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_external (external_source, external_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip           VARCHAR(45) NOT NULL,
  email        VARCHAR(190) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_login_ip (ip, attempted_at),
  KEY idx_login_email (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  name  VARCHAR(64) PRIMARY KEY,
  value TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sites (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          INT UNSIGNED NOT NULL,
  name             VARCHAR(160) NOT NULL,
  url              VARCHAR(255) NOT NULL,
  category         VARCHAR(80) NOT NULL,
  description      TEXT NOT NULL,
  monthly_visitors INT UNSIGNED NOT NULL DEFAULT 0,
  status           ENUM('pending','approved','rejected','paused') NOT NULL DEFAULT 'pending',
  admin_note       VARCHAR(500) NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_sites_user (user_id),
  KEY idx_sites_status (status),
  CONSTRAINT fk_sites_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_slots (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  site_id         INT UNSIGNED NOT NULL,
  name            VARCHAR(160) NOT NULL,
  format          VARCHAR(20) NOT NULL,
  width           SMALLINT UNSIGNED NOT NULL,
  height          SMALLINT UNSIGNED NOT NULL,
  placement       VARCHAR(160) NOT NULL DEFAULT '',
  description     TEXT NOT NULL,
  -- Erweiterungspunkt: später 'cpc' / 'cpm'. Im MVP nur 'fixed'.
  pricing_model   ENUM('fixed') NOT NULL DEFAULT 'fixed',
  price_per_day   INT UNSIGNED NOT NULL,
  min_days        SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  max_days        SMALLINT UNSIGNED NOT NULL DEFAULT 365,
  available_from  DATE NOT NULL,
  available_until DATE NULL,
  status          ENUM('pending','approved','rejected','paused') NOT NULL DEFAULT 'pending',
  admin_note      VARCHAR(500) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_slots_site (site_id),
  KEY idx_slots_status (status),
  CONSTRAINT fk_slots_site FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campaigns (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  name          VARCHAR(160) NOT NULL,
  target_url    VARCHAR(500) NOT NULL,
  alt_text      VARCHAR(200) NOT NULL DEFAULT '',
  banner_file   VARCHAR(80) NOT NULL,
  banner_mime   VARCHAR(40) NOT NULL,
  banner_width  SMALLINT UNSIGNED NOT NULL,
  banner_height SMALLINT UNSIGNED NOT NULL,
  banner_size   INT UNSIGNED NOT NULL,
  status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_note    VARCHAR(500) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_campaigns_user (user_id),
  KEY idx_campaigns_status (status),
  CONSTRAINT fk_campaigns_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bookings (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference         VARCHAR(32) NULL,
  campaign_id       INT UNSIGNED NOT NULL,
  slot_id           INT UNSIGNED NOT NULL,
  advertiser_id     INT UNSIGNED NOT NULL,
  publisher_id      INT UNSIGNED NOT NULL,
  start_date        DATE NOT NULL,
  end_date          DATE NOT NULL,
  days              SMALLINT UNSIGNED NOT NULL,
  -- Preis-Snapshot: wird serverseitig zum Anfragezeitpunkt festgeschrieben
  pricing_model     ENUM('fixed') NOT NULL DEFAULT 'fixed',
  price_per_day     INT UNSIGNED NOT NULL,
  total_amount      INT UNSIGNED NOT NULL,
  commission_bp     SMALLINT UNSIGNED NOT NULL,
  commission_amount INT UNSIGNED NOT NULL,
  publisher_amount  INT UNSIGNED NOT NULL,
  currency          CHAR(3) NOT NULL,
  status            ENUM('requested','accepted','declined','cancelled','expired','paid','completed','refunded') NOT NULL DEFAULT 'requested',
  payment_status    ENUM('unpaid','reported','paid','refunded') NOT NULL DEFAULT 'unpaid',
  invoice_no        VARCHAR(32) NULL,
  serve_token       CHAR(32) NULL,
  message           VARCHAR(1000) NOT NULL DEFAULT '',
  decision_note     VARCHAR(500) NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  decided_at        DATETIME NULL,
  paid_at           DATETIME NULL,
  UNIQUE KEY uq_bookings_reference (reference),
  UNIQUE KEY uq_bookings_invoice (invoice_no),
  UNIQUE KEY uq_bookings_token (serve_token),
  KEY idx_bookings_slot_dates (slot_id, status, start_date, end_date),
  KEY idx_bookings_adv (advertiser_id),
  KEY idx_bookings_pub (publisher_id),
  CONSTRAINT fk_bookings_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id),
  CONSTRAINT fk_bookings_slot FOREIGN KEY (slot_id) REFERENCES ad_slots(id),
  CONSTRAINT fk_bookings_adv FOREIGN KEY (advertiser_id) REFERENCES users(id),
  CONSTRAINT fk_bookings_pub FOREIGN KEY (publisher_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payments (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id  INT UNSIGNED NOT NULL,
  -- Erweiterungspunkt für Zahlungsanbieter: method + provider_ref
  method      VARCHAR(30) NOT NULL,
  provider_ref VARCHAR(120) NULL,
  amount      INT NOT NULL,
  currency    CHAR(3) NOT NULL,
  note        VARCHAR(500) NOT NULL DEFAULT '',
  received_on DATE NOT NULL,
  recorded_by INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_payments_booking (booking_id),
  CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payouts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  publisher_id INT UNSIGNED NOT NULL,
  amount       INT UNSIGNED NOT NULL,
  currency     CHAR(3) NOT NULL,
  method       VARCHAR(30) NOT NULL,
  note         VARCHAR(500) NOT NULL DEFAULT '',
  paid_on      DATE NOT NULL,
  recorded_by  INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_payouts_pub (publisher_id),
  CONSTRAINT fk_payouts_pub FOREIGN KEY (publisher_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Unveränderliches Buchungsjournal für nachvollziehbare Abrechnungen
CREATE TABLE IF NOT EXISTS ledger (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id  INT UNSIGNED NULL,
  payout_id   INT UNSIGNED NULL,
  user_id     INT UNSIGNED NULL,
  type        ENUM('advertiser_payment','platform_commission','publisher_earning','publisher_payout','refund','commission_reversal','earning_reversal') NOT NULL,
  amount      INT NOT NULL,
  currency    CHAR(3) NOT NULL,
  description VARCHAR(255) NOT NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ledger_user (user_id),
  KEY idx_ledger_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS booking_stats (
  booking_id  INT UNSIGNED NOT NULL,
  day         DATE NOT NULL,
  impressions INT UNSIGNED NOT NULL DEFAULT 0,
  clicks      INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (booking_id, day),
  CONSTRAINT fk_stats_booking FOREIGN KEY (booking_id) REFERENCES bookings(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NULL,
  action     VARCHAR(60) NOT NULL,
  entity     VARCHAR(30) NOT NULL,
  entity_id  INT UNSIGNED NULL,
  details    TEXT NULL,
  ip         VARCHAR(45) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_entity (entity, entity_id),
  KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (name, value) VALUES
  ('platform_name', 'VylarisAdvertise'),
  ('currency', 'CHF'),
  ('commission_bp', '1500'),
  ('bank_details', 'Kontoinhaber: Ihre Firma\nIBAN: CH00 0000 0000 0000 0000 0\nBIC: XXXXXXXX'),
  ('invoice_prefix', 'VA'),
  ('invoice_counter', '0'),
  ('min_payout', '2000'),
  ('legal_version', '2026-10-14'),
  ('legal_name', ''), ('legal_address', ''), ('legal_email', ''), ('legal_phone', ''),
  ('legal_register', ''), ('legal_representative', ''), ('legal_hoster', ''),
  ('legal_privacy_custom', ''), ('legal_imprint_custom', ''), ('legal_terms_custom', '');
