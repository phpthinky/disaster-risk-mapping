<?php

return new class {
    public function up(PDO $pdo): void {
        $pdo->exec("
            ALTER TABLE `barangays`
                ADD COLUMN `calculated_area_km2` DECIMAL(10,4) DEFAULT NULL
                    COMMENT 'Area auto-calculated from boundary polygon (km²)'
                    AFTER `area_km2`
        ");
    }

    public function down(PDO $pdo): void {
        $pdo->exec("ALTER TABLE `barangays` DROP COLUMN IF EXISTS `calculated_area_km2`");
    }
};
