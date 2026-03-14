<?php

return new class {
    public function up(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE `hazard_types` (
                `id`    INT(11) NOT NULL AUTO_INCREMENT,
                `name`  VARCHAR(50) NOT NULL,
                `color` VARCHAR(7) DEFAULT NULL,
                `icon`  VARCHAR(50) DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $pdo->exec("
            CREATE TABLE `hazard_zones` (
                `id`                  INT(11) NOT NULL AUTO_INCREMENT,
                `hazard_type_id`      INT(11) DEFAULT NULL,
                `barangay_id`         INT(11) DEFAULT NULL,
                `risk_level`          ENUM(
                                        'High Susceptible',
                                        'Moderate Susceptible',
                                        'Low Susceptible',
                                        'Not Susceptible',
                                        'Prone',
                                        'Generally Susceptible',
                                        'PEIS VIII - Very destructive to devastating ground shaking',
                                        'PEIS VII - Destructive ground shaking',
                                        'General Inundation'
                                      ) DEFAULT NULL,
                `area_km2`            DECIMAL(10,2) DEFAULT NULL,
                `affected_population` INT(11) DEFAULT NULL,
                `coordinates`         TEXT DEFAULT NULL,
                `description`         TEXT DEFAULT NULL,
                `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `hazard_type_id` (`hazard_type_id`),
                KEY `barangay_id` (`barangay_id`),
                CONSTRAINT `hazard_zones_ibfk_1` FOREIGN KEY (`hazard_type_id`) REFERENCES `hazard_types` (`id`),
                CONSTRAINT `hazard_zones_ibfk_2` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }

    public function down(PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `hazard_zones`");
        $pdo->exec("DROP TABLE IF EXISTS `hazard_types`");
    }
};
