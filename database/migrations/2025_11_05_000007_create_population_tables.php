<?php

return new class {
    public function up(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE `population_data` (
                `id`                INT(11) NOT NULL AUTO_INCREMENT,
                `barangay_id`       INT(11) DEFAULT NULL,
                `total_population`  INT(11) DEFAULT NULL,
                `households`        INT(11) DEFAULT NULL,
                `elderly_count`     INT(11) DEFAULT NULL,
                `children_count`    INT(11) DEFAULT NULL,
                `pwd_count`         INT(11) DEFAULT NULL,
                `ips_count`         INT(11) DEFAULT 0,
                `solo_parent_count` INT(11) DEFAULT 0,
                `widow_count`       INT(11) DEFAULT 0,
                `data_date`         DATE DEFAULT NULL,
                `entered_by`        VARCHAR(100) DEFAULT NULL,
                `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `barangay_id` (`barangay_id`),
                CONSTRAINT `population_data_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $pdo->exec("
            CREATE TABLE `population_data_archive` (
                `id`                INT(11) NOT NULL AUTO_INCREMENT,
                `original_id`       INT(11) NOT NULL,
                `barangay_id`       INT(11) NOT NULL,
                `total_population`  INT(11) NOT NULL,
                `households`        INT(11) NOT NULL,
                `elderly_count`     INT(11) NOT NULL,
                `children_count`    INT(11) NOT NULL,
                `pwd_count`         INT(11) NOT NULL,
                `ips_count`         INT(11) DEFAULT 0,
                `solo_parent_count` INT(11) DEFAULT 0,
                `widow_count`       INT(11) DEFAULT 0,
                `data_date`         DATE NOT NULL,
                `archived_at`       DATETIME DEFAULT CURRENT_TIMESTAMP,
                `archived_by`       VARCHAR(50) DEFAULT NULL,
                `change_type`       VARCHAR(20) DEFAULT NULL COMMENT 'UPDATE or DELETE',
                PRIMARY KEY (`id`),
                KEY `idx_archive_barangay` (`barangay_id`),
                KEY `idx_archive_date` (`archived_at`),
                CONSTRAINT `population_data_archive_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `population_data_archive`");
        $pdo->exec("DROP TABLE IF EXISTS `population_data`");
    }
};
