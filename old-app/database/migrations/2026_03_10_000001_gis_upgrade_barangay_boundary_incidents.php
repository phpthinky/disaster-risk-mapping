<?php

// ─────────────────────────────────────────
//  GIS UPGRADE MIGRATION
//  - Adds boundary_geojson to barangays
//  - Creates barangay_boundary_logs
//  - Creates incident_reports
//  - Creates affected_areas
// ─────────────────────────────────────────

return new class {
    public function up(PDO $pdo): void {

        // 1. Add boundary column to barangays
        $pdo->exec("
            ALTER TABLE `barangays`
                ADD COLUMN `boundary_geojson` LONGTEXT DEFAULT NULL
                    COMMENT 'GeoJSON polygon drawn by admin for barangay boundary'
                    AFTER `coordinates`
        ");

        // 2. Track who drew/edited each boundary
        $pdo->exec("
            CREATE TABLE `barangay_boundary_logs` (
                `id`          INT(11) NOT NULL AUTO_INCREMENT,
                `barangay_id` INT(11) NOT NULL,
                `action`      ENUM('created','updated') NOT NULL,
                `drawn_by`    INT(11) DEFAULT NULL,
                `drawn_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `barangay_id` (`barangay_id`),
                CONSTRAINT `boundary_logs_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`),
                CONSTRAINT `boundary_logs_ibfk_2` FOREIGN KEY (`drawn_by`) REFERENCES `users` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 3. Disaster incident reports
        $pdo->exec("
            CREATE TABLE `incident_reports` (
                `id`                  INT(11) NOT NULL AUTO_INCREMENT,
                `title`               VARCHAR(255) NOT NULL,
                `hazard_type_id`      INT(11) DEFAULT NULL,
                `incident_date`       DATE NOT NULL,
                `status`              ENUM('ongoing','resolved','monitoring') DEFAULT 'ongoing',
                `affected_polygon`    LONGTEXT DEFAULT NULL COMMENT 'GeoJSON polygon of the affected area',
                `description`         TEXT DEFAULT NULL,
                `reported_by`         INT(11) DEFAULT NULL,
                `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `hazard_type_id` (`hazard_type_id`),
                KEY `reported_by` (`reported_by`),
                CONSTRAINT `incident_ibfk_1` FOREIGN KEY (`hazard_type_id`) REFERENCES `hazard_types` (`id`),
                CONSTRAINT `incident_ibfk_2` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 4. Computed affected areas per incident
        $pdo->exec("
            CREATE TABLE `affected_areas` (
                `id`                  INT(11) NOT NULL AUTO_INCREMENT,
                `incident_id`         INT(11) NOT NULL,
                `barangay_id`         INT(11) NOT NULL,
                `affected_households` INT(11) DEFAULT 0,
                `affected_population` INT(11) DEFAULT 0,
                `affected_pwd`        INT(11) DEFAULT 0,
                `affected_seniors`    INT(11) DEFAULT 0,
                `affected_infants`    INT(11) DEFAULT 0,
                `affected_minors`     INT(11) DEFAULT 0,
                `affected_pregnant`   INT(11) DEFAULT 0,
                `computed_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `incident_id` (`incident_id`),
                KEY `barangay_id` (`barangay_id`),
                CONSTRAINT `affected_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incident_reports` (`id`) ON DELETE CASCADE,
                CONSTRAINT `affected_ibfk_2` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `affected_areas`");
        $pdo->exec("DROP TABLE IF EXISTS `incident_reports`");
        $pdo->exec("DROP TABLE IF EXISTS `barangay_boundary_logs`");
        $pdo->exec("ALTER TABLE `barangays` DROP COLUMN IF EXISTS `boundary_geojson`");
    }
};
