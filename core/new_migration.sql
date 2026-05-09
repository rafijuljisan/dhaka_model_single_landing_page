-- ============================================================
-- DMA Admin Panel — INCREMENTAL Migration
-- Adds ONLY what is new. Safe to run on the existing live DB.
-- All existing tables (registrations, admin_users, status_logs,
-- campaign_settings) are left completely untouched.
-- ============================================================

-- ── 1. New columns on `registrations` ────────────────────────
--   These are the only additions. Everything else already exists.

ALTER TABLE `registrations`
  ADD COLUMN IF NOT EXISTS `assigned_to` INT(11) UNSIGNED NULL DEFAULT NULL
      COMMENT 'FK → admin_users.id — which staff member owns this lead'
      AFTER `status`,

  ADD COLUMN IF NOT EXISTS `priority` ENUM('normal','hot','warm','cold')
      NOT NULL DEFAULT 'normal'
      COMMENT 'Lead priority for sorting and urgency display'
      AFTER `assigned_to`;

-- ── 2. Foreign key for assigned_to (skip if already exists) ──

SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA  = DATABASE()
    AND TABLE_NAME    = 'registrations'
    AND CONSTRAINT_NAME = 'fk_reg_assigned_to'
);
SET @fk_sql = IF(
  @fk_exists = 0,
  'ALTER TABLE `registrations` ADD CONSTRAINT `fk_reg_assigned_to`
   FOREIGN KEY (`assigned_to`) REFERENCES `admin_users`(`id`)
   ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT "assigned_to FK already exists" AS info'
);
PREPARE _stmt FROM @fk_sql;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- ── 3. Index on new columns ───────────────────────────────────

ALTER TABLE `registrations`
  ADD INDEX IF NOT EXISTS `idx_assigned`  (`assigned_to`),
  ADD INDEX IF NOT EXISTS `idx_priority`  (`priority`);

-- ── 4. `lead_activities` table (brand new) ───────────────────
--   Tracks every staff interaction: calls, notes, WhatsApp,
--   emails, meetings, and follow-up scheduling.

CREATE TABLE IF NOT EXISTS `lead_activities` (
  `id`           INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `reg_id`       INT(11) UNSIGNED NOT NULL,
  `admin_id`     INT(11) UNSIGNED NOT NULL,
  `type`         ENUM('note','call','whatsapp','email','meeting','follow_up','status')
                 NOT NULL DEFAULT 'note',
  `content`      TEXT         NOT NULL,
  `scheduled_at` DATETIME     NULL DEFAULT NULL
                 COMMENT 'For follow_up entries — when to follow up',
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_la_reg`      (`reg_id`),
  KEY `idx_la_admin`    (`admin_id`),
  KEY `idx_la_type`     (`type`),
  KEY `idx_la_schedule` (`scheduled_at`),
  CONSTRAINT `fk_la_reg`
    FOREIGN KEY (`reg_id`)   REFERENCES `registrations`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_la_admin`
    FOREIGN KEY (`admin_id`) REFERENCES `admin_users`(`id`)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. Add `user_agent` column if missing ────────────────────
--   (Some installs may have skipped it in the original schema)

ALTER TABLE `registrations`
  ADD COLUMN IF NOT EXISTS `user_agent` TEXT NULL DEFAULT NULL
      COMMENT 'Browser user-agent captured at submission';

-- ── Done ─────────────────────────────────────────────────────
SELECT CONCAT(
  'Migration complete. ',
  'New columns: assigned_to, priority. ',
  'New table: lead_activities.'
) AS result;