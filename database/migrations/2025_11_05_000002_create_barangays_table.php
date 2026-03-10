<?php

return new class {
    public function up(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE `barangays` (
                `id`          INT(11) NOT NULL AUTO_INCREMENT,
                `name`        VARCHAR(100) NOT NULL,
                `population`  INT(11) DEFAULT 0,
                `area_km2`    DECIMAL(10,2) DEFAULT NULL,
                `coordinates` VARCHAR(255) DEFAULT NULL,
                `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        // Add FK for users.barangay_id now that barangays exists
        $pdo->exec("
            ALTER TABLE `users`
                ADD CONSTRAINT `users_ibfk_1`
                FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`)
        ");
    }

    public function down(PDO $pdo): void {
        $pdo->exec("ALTER TABLE `users` DROP FOREIGN KEY IF EXISTS `users_ibfk_1`");
        $pdo->exec("DROP TABLE IF EXISTS `barangays`");
    }
};
