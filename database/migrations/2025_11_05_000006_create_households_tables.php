<?php

return new class {
    public function up(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE `households` (
                `id`                    INT(11) NOT NULL AUTO_INCREMENT,
                `household_head`        VARCHAR(255) NOT NULL,
                `barangay_id`           INT(11) NOT NULL,
                `zone`                  VARCHAR(50) DEFAULT NULL,
                `sex`                   ENUM('Male','Female') NOT NULL,
                `age`                   INT(11) NOT NULL,
                `gender`                ENUM('Male','Female','Other') NOT NULL,
                `house_type`            VARCHAR(100) DEFAULT NULL,
                `family_members`        INT(11) DEFAULT 1,
                `pwd_count`             INT(11) DEFAULT 0,
                `pregnant_count`        INT(11) DEFAULT 0,
                `senior_count`          INT(11) DEFAULT 0,
                `infant_count`          INT(11) DEFAULT 0,
                `minor_count`           INT(11) DEFAULT 0,
                `latitude`              DECIMAL(10,8) DEFAULT NULL,
                `longitude`             DECIMAL(11,8) DEFAULT NULL,
                `sitio_purok_zone`      VARCHAR(255) DEFAULT NULL,
                `ip_non_ip`             ENUM('IP','Non-IP') DEFAULT NULL,
                `hh_id`                 VARCHAR(100) DEFAULT NULL,
                `child_count`           INT(11) DEFAULT 0,
                `adolescent_count`      INT(11) DEFAULT 0,
                `young_adult_count`     INT(11) DEFAULT 0,
                `adult_count`           INT(11) DEFAULT 0,
                `middle_aged_count`     INT(11) DEFAULT 0,
                `preparedness_kit`      ENUM('Yes','No') DEFAULT NULL,
                `educational_attainment` VARCHAR(255) DEFAULT NULL,
                `created_at`            TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`            TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `barangay_id` (`barangay_id`),
                CONSTRAINT `households_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE `household_members` (
                `id`            INT(11) NOT NULL AUTO_INCREMENT,
                `household_id`  INT(11) NOT NULL,
                `full_name`     VARCHAR(255) NOT NULL,
                `age`           INT(11) NOT NULL,
                `gender`        ENUM('Male','Female','Other') NOT NULL,
                `relationship`  VARCHAR(100) NOT NULL,
                `is_pwd`        TINYINT(1) DEFAULT 0,
                `is_pregnant`   TINYINT(1) DEFAULT 0,
                `is_senior`     TINYINT(1) DEFAULT 0,
                `is_infant`     TINYINT(1) DEFAULT 0,
                `is_minor`      TINYINT(1) DEFAULT 0,
                `created_at`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `household_id` (`household_id`),
                CONSTRAINT `household_members_ibfk_1`
                    FOREIGN KEY (`household_id`) REFERENCES `households` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `household_members`");
        $pdo->exec("DROP TABLE IF EXISTS `households`");
    }
};
