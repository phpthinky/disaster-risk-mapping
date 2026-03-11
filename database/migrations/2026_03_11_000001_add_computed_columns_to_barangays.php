<?php

// ─────────────────────────────────────────
//  Add computed population columns to barangays
//  These are auto-filled by sync_barangay()
// ─────────────────────────────────────────

return new class {
    public function up(PDO $pdo): void {
        $pdo->exec("
            ALTER TABLE `barangays`
                ADD COLUMN `household_count` INT(11) DEFAULT 0 AFTER `population`,
                ADD COLUMN `pwd_count` INT(11) DEFAULT 0 AFTER `household_count`,
                ADD COLUMN `senior_count` INT(11) DEFAULT 0 AFTER `pwd_count`,
                ADD COLUMN `children_count` INT(11) DEFAULT 0 AFTER `senior_count`,
                ADD COLUMN `infant_count` INT(11) DEFAULT 0 AFTER `children_count`,
                ADD COLUMN `pregnant_count` INT(11) DEFAULT 0 AFTER `infant_count`,
                ADD COLUMN `ip_count` INT(11) DEFAULT 0 AFTER `pregnant_count`
        ");

        // Add ip_count to affected_areas if missing
        $pdo->exec("
            ALTER TABLE `affected_areas`
                ADD COLUMN `ip_count` INT(11) DEFAULT 0 AFTER `affected_pregnant`
        ");
    }

    public function down(PDO $pdo): void {
        $pdo->exec("
            ALTER TABLE `barangays`
                DROP COLUMN IF EXISTS `household_count`,
                DROP COLUMN IF EXISTS `pwd_count`,
                DROP COLUMN IF EXISTS `senior_count`,
                DROP COLUMN IF EXISTS `children_count`,
                DROP COLUMN IF EXISTS `infant_count`,
                DROP COLUMN IF EXISTS `pregnant_count`,
                DROP COLUMN IF EXISTS `ip_count`
        ");
        $pdo->exec("ALTER TABLE `affected_areas` DROP COLUMN IF EXISTS `ip_count`");
    }
};
