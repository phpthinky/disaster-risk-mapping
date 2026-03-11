<?php

// ─────────────────────────────────────────
//  DefaultSeeder.php
//  Seeds: barangays, hazard_types, users
// ─────────────────────────────────────────

return new class {
    public function run(PDO $pdo): void {

        // ── Barangays ──────────────────────────
        $barangays = [
            [1,  'Buenavista',             0, 15.20, '12.8472,120.7803'],
            [2,  'Burgos',                 0, 12.80, '12.6947,120.8862'],
            [3,  'Claudio Salgado',        0, 10.55, '12.730611,120.833989'],
            [4,  'Poblacion',              0, 18.60, '12.8347,120.7684'],
            [6,  'Batong Buhay',           0, 13.40, '12.8166,120.8652'],
            [7,  'Gen. Emilio Aguinaldo',  0, 11.90, '12.7300,120.8354'],
            [8,  'Ibud',                   0, 14.50, '12.8849,120.8060'],
            [9,  'Ligaya',                 0, 16.10, '12.7232,120.8554'],
            [10, 'San Agustin',            0, 12.00, '12.9230,120.9057'],
            [11, 'San Francisco',          0, 13.80, '12.8935,120.8677'],
            [13, 'San Nicolas',            0, 11.70, '12.7262,120.8158'],
            [14, 'San Vicente',            0, 14.10, '12.8836,120.8309'],
            [15, 'Santa Lucia',            0, 13.20, '12.7568,120.7945'],
            [17, 'Victoria',               0, 13.60, '12.9285,120.8395'],
            [18, 'Paetan',                 0, 11.20, '12.9126,120.8541'],
            [19, 'Lagnas',                 0, 12.00, '12.9327,120.8633'],
            [21, 'Pag-asa',                0, 10.80, '13.0587,121.0514'],
            [22, 'Tuban',                  0, 11.60, '12.8063,120.8384'],
            [23, 'Ilvita',                 0, 14.50, '12.9602,120.8176'],
            [24, 'Malisbong',              0, 16.10, '12.7701,120.8432'],
            [25, 'Santo Niño',             0, 11.70, '12.8630,120.7905'],
            [26, 'Tagumpay',               0, 11.60, '12.9112,120.8096'],
        ];

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO `barangays` (id, name, population, area_km2, coordinates)
            VALUES (?, ?, ?, ?, ?)
        ");
        foreach ($barangays as $b) $stmt->execute($b);

        // ── Hazard Types ───────────────────────
        $hazardTypes = [
            [1, 'Flooding',       '#3498db', 'fa-water'],
            [2, 'Landslide',      '#e67e22', 'fa-mountain'],
            [3, 'Storm Surge',    '#9b59b6', 'fa-wind'],
            [4, 'Liquefaction',   '#e74c3c', 'fa-house-crack'],
            [5, 'Ground Shaking', '#c0392b', 'fa-road'],
            [6, 'Tsunami',        '#1ad1ff', 'fa-wave-square'],
        ];

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO `hazard_types` (id, name, color, icon)
            VALUES (?, ?, ?, ?)
        ");
        foreach ($hazardTypes as $h) $stmt->execute($h);

        // ── Default Users ──────────────────────
        // Password for all: 'Admin@1234' — CHANGE THESE IN PRODUCTION!
        $defaultPassword = password_hash('Admin@1234', PASSWORD_DEFAULT);

        $users = [
            [8, 'admin',          $defaultPassword, 'admin@sablayan.gov.ph',          null, 'admin'],
            [9, 'division_chief', $defaultPassword, 'division-chief@sablayan.gov.ph', null, 'division_chief'],
            [4, 'staff_buenavista', $defaultPassword, 'buenavista@sablayan.gov.ph',   1,    'barangay_staff'],
            [5, 'staff_burgos',     $defaultPassword, 'burgos@sablayan.gov.ph',        2,    'barangay_staff'],
            [6, 'staff_claudio',    $defaultPassword, 'claudio@sablayan.gov.ph',       3,    'barangay_staff'],
        ];

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO `users` (id, username, password, email, barangay_id, role)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($users as $u) $stmt->execute($u);
    }
};
