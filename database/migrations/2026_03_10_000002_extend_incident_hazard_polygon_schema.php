<?php

// ─────────────────────────────────────────
//  POLYGON & INCIDENT SCHEMA EXTENSIONS
//  Phase 3–4 of the GIS upgrade
//
//  Extends the schema created by migration 000001:
//  - Renames affected_polygon → polygon_geojson in incident_reports
//  - Adds aggregate totals columns to incident_reports
//  - Adds ip_count to affected_areas
//  - Adds polygon_geojson to hazard_zones (Phase 3 hazard polygon drawing)
//  - Adds population_data_archive table if not exists
// ─────────────────────────────────────────

return new class {
    public function up(PDO $pdo): void {

        // 1. Rename affected_polygon → polygon_geojson in incident_reports
        //    (if the old column name exists from migration 000001)
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM `incident_reports` LIKE 'affected_polygon'")->fetchAll();
            if (!empty($cols)) {
                $pdo->exec("ALTER TABLE `incident_reports` CHANGE `affected_polygon` `polygon_geojson` LONGTEXT DEFAULT NULL COMMENT 'GeoJSON polygon of the affected area'");
            }
        } catch (Throwable $e) { /* column may already have correct name */ }

        // 2. Add polygon_geojson if it doesn't exist yet
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM `incident_reports` LIKE 'polygon_geojson'")->fetchAll();
            if (empty($cols)) {
                $pdo->exec("ALTER TABLE `incident_reports` ADD COLUMN `polygon_geojson` LONGTEXT DEFAULT NULL COMMENT 'GeoJSON polygon of the affected area' AFTER `description`");
            }
        } catch (Throwable $e) { /* ignore */ }

        // 3. Add aggregate total columns to incident_reports
        $incidentExtras = [
            "ADD COLUMN IF NOT EXISTS `total_affected_households` INT(11) DEFAULT 0 AFTER `polygon_geojson`",
            "ADD COLUMN IF NOT EXISTS `total_affected_population` INT(11) DEFAULT 0 AFTER `total_affected_households`",
            "ADD COLUMN IF NOT EXISTS `total_affected_pwd`        INT(11) DEFAULT 0 AFTER `total_affected_population`",
            "ADD COLUMN IF NOT EXISTS `total_affected_seniors`    INT(11) DEFAULT 0 AFTER `total_affected_pwd`",
            "ADD COLUMN IF NOT EXISTS `total_affected_infants`    INT(11) DEFAULT 0 AFTER `total_affected_seniors`",
            "ADD COLUMN IF NOT EXISTS `total_affected_minors`     INT(11) DEFAULT 0 AFTER `total_affected_infants`",
            "ADD COLUMN IF NOT EXISTS `total_affected_pregnant`   INT(11) DEFAULT 0 AFTER `total_affected_minors`",
            "ADD COLUMN IF NOT EXISTS `total_ip_count`            INT(11) DEFAULT 0 AFTER `total_affected_pregnant`",
            "ADD COLUMN IF NOT EXISTS `created_by`                INT(11) DEFAULT NULL AFTER `total_ip_count`",
        ];
        foreach ($incidentExtras as $clause) {
            try {
                $pdo->exec("ALTER TABLE `incident_reports` {$clause}");
            } catch (Throwable $e) { /* column may already exist */ }
        }

        // 4. Add ip_count to affected_areas
        try {
            $pdo->exec("ALTER TABLE `affected_areas` ADD COLUMN IF NOT EXISTS `ip_count` INT(11) DEFAULT 0 AFTER `affected_pregnant`");
        } catch (Throwable $e) { /* ignore */ }

        // 5. Add polygon_geojson to hazard_zones (for Phase 3 polygon drawing)
        try {
            $pdo->exec("ALTER TABLE `hazard_zones` ADD COLUMN IF NOT EXISTS `polygon_geojson` LONGTEXT DEFAULT NULL COMMENT 'GeoJSON polygon drawn for this hazard zone' AFTER `coordinates`");
        } catch (Throwable $e) { /* ignore */ }

        // 6. Ensure population_data_archive table exists (Phase 6)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `population_data_archive` (
                `id`              INT(11) NOT NULL AUTO_INCREMENT,
                `barangay_id`     INT(11) NOT NULL,
                `total_population` INT(11) DEFAULT 0,
                `male_count`      INT(11) DEFAULT 0,
                `female_count`    INT(11) DEFAULT 0,
                `elderly_count`   INT(11) DEFAULT 0,
                `children_count`  INT(11) DEFAULT 0,
                `pwd_count`       INT(11) DEFAULT 0,
                `archived_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `archived_by`     VARCHAR(100) DEFAULT NULL,
                `change_type`     ENUM('UPDATE','DELETE','INITIAL') DEFAULT 'UPDATE',
                PRIMARY KEY (`id`),
                KEY `barangay_id` (`barangay_id`),
                CONSTRAINT `pop_archive_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void {
        // Remove added columns from incident_reports
        $dropCols = ['total_affected_households','total_affected_population','total_affected_pwd',
                     'total_affected_seniors','total_affected_infants','total_affected_minors',
                     'total_affected_pregnant','total_ip_count','created_by'];
        foreach ($dropCols as $col) {
            try { $pdo->exec("ALTER TABLE `incident_reports` DROP COLUMN IF EXISTS `{$col}`"); } catch (Throwable $e) {}
        }
        try { $pdo->exec("ALTER TABLE `affected_areas` DROP COLUMN IF EXISTS `ip_count`"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE `hazard_zones` DROP COLUMN IF EXISTS `polygon_geojson`"); } catch (Throwable $e) {}
        $pdo->exec("DROP TABLE IF EXISTS `population_data_archive`");
    }
};
