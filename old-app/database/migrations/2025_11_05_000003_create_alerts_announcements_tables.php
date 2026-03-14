<?php

return new class {
    public function up(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE `alerts` (
                `id`         INT(11) NOT NULL AUTO_INCREMENT,
                `title`      VARCHAR(255) NOT NULL,
                `message`    TEXT DEFAULT NULL,
                `alert_type` ENUM('warning','info','danger') DEFAULT NULL,
                `barangay_id` INT(11) DEFAULT NULL,
                `is_active`  TINYINT(1) DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `barangay_id` (`barangay_id`),
                CONSTRAINT `alerts_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $pdo->exec("
            CREATE TABLE `announcements` (
                `id`                INT(11) NOT NULL AUTO_INCREMENT,
                `title`             VARCHAR(255) NOT NULL,
                `message`           TEXT NOT NULL,
                `announcement_type` ENUM('emergency','info','maintenance','general') DEFAULT 'general',
                `target_audience`   ENUM('all','barangay_staff','division_chief','admin') DEFAULT 'all',
                `is_active`         TINYINT(1) DEFAULT 1,
                `created_by`        INT(11) DEFAULT NULL,
                `created_at`        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `created_by` (`created_by`),
                CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `announcements`");
        $pdo->exec("DROP TABLE IF EXISTS `alerts`");
    }
};
