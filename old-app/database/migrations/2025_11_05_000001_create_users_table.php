<?php

return new class {
    public function up(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE `users` (
                `id`         INT(11) NOT NULL AUTO_INCREMENT,
                `username`   VARCHAR(50) NOT NULL,
                `password`   VARCHAR(255) NOT NULL,
                `email`      VARCHAR(100) DEFAULT NULL,
                `barangay_id` INT(11) DEFAULT NULL,
                `role`       ENUM('admin','barangay_staff','division_chief') DEFAULT 'barangay_staff',
                `is_active`  TINYINT(1) DEFAULT 1,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }

    public function down(PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `users`");
    }
};
