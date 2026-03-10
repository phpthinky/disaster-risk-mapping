<?php

return new class {
    public function up(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE `evacuation_centers` (
                `id`                INT(11) NOT NULL AUTO_INCREMENT,
                `name`              VARCHAR(255) NOT NULL,
                `barangay_id`       INT(11) NOT NULL,
                `capacity`          INT(11) NOT NULL,
                `current_occupancy` INT(11) DEFAULT 0,
                `latitude`          DECIMAL(10,8) DEFAULT NULL,
                `longitude`         DECIMAL(11,8) DEFAULT NULL,
                `facilities`        TEXT DEFAULT NULL,
                `contact_person`    VARCHAR(255) DEFAULT NULL,
                `contact_number`    VARCHAR(20) DEFAULT NULL,
                `status`            ENUM('operational','maintenance','closed') DEFAULT 'operational',
                `created_at`        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`        TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `barangay_id` (`barangay_id`),
                CONSTRAINT `evacuation_centers_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE `data_entries` (
                `id`          INT(11) NOT NULL AUTO_INCREMENT,
                `user_id`     INT(11) DEFAULT NULL,
                `data_type`   ENUM('population','hazard','general') DEFAULT NULL,
                `description` TEXT DEFAULT NULL,
                `status`      ENUM('pending','approved','rejected') DEFAULT 'pending',
                `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`),
                CONSTRAINT `data_entries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }

    public function down(PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `data_entries`");
        $pdo->exec("DROP TABLE IF EXISTS `evacuation_centers`");
    }
};
