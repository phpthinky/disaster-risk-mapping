<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\HazardType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultSeeder extends Seeder
{
    public function run(): void
    {
        // ── Barangays ──────────────────────────────────────────
        $barangays = [
            ['id' => 1,  'name' => 'Buenavista',            'area_km2' => 15.20, 'coordinates' => '12.8472,120.7803'],
            ['id' => 2,  'name' => 'Burgos',                'area_km2' => 12.80, 'coordinates' => '12.6947,120.8862'],
            ['id' => 3,  'name' => 'Claudio Salgado',       'area_km2' => 10.55, 'coordinates' => '12.730611,120.833989'],
            ['id' => 4,  'name' => 'Poblacion',             'area_km2' => 18.60, 'coordinates' => '12.8347,120.7684'],
            ['id' => 6,  'name' => 'Batong Buhay',          'area_km2' => 13.40, 'coordinates' => '12.8166,120.8652'],
            ['id' => 7,  'name' => 'Gen. Emilio Aguinaldo', 'area_km2' => 11.90, 'coordinates' => '12.7300,120.8354'],
            ['id' => 8,  'name' => 'Ibud',                  'area_km2' => 14.50, 'coordinates' => '12.8849,120.8060'],
            ['id' => 9,  'name' => 'Ligaya',                'area_km2' => 16.10, 'coordinates' => '12.7232,120.8554'],
            ['id' => 10, 'name' => 'San Agustin',           'area_km2' => 12.00, 'coordinates' => '12.9230,120.9057'],
            ['id' => 11, 'name' => 'San Francisco',         'area_km2' => 13.80, 'coordinates' => '12.8935,120.8677'],
            ['id' => 13, 'name' => 'San Nicolas',           'area_km2' => 11.70, 'coordinates' => '12.7262,120.8158'],
            ['id' => 14, 'name' => 'San Vicente',           'area_km2' => 14.10, 'coordinates' => '12.8836,120.8309'],
            ['id' => 15, 'name' => 'Santa Lucia',           'area_km2' => 13.20, 'coordinates' => '12.7568,120.7945'],
            ['id' => 17, 'name' => 'Victoria',              'area_km2' => 13.60, 'coordinates' => '12.9285,120.8395'],
            ['id' => 18, 'name' => 'Paetan',                'area_km2' => 11.20, 'coordinates' => '12.9126,120.8541'],
            ['id' => 19, 'name' => 'Lagnas',                'area_km2' => 12.00, 'coordinates' => '12.9327,120.8633'],
            ['id' => 21, 'name' => 'Pag-asa',               'area_km2' => 10.80, 'coordinates' => '13.0587,121.0514'],
            ['id' => 22, 'name' => 'Tuban',                 'area_km2' => 11.60, 'coordinates' => '12.8063,120.8384'],
            ['id' => 23, 'name' => 'Ilvita',                'area_km2' => 14.50, 'coordinates' => '12.9602,120.8176'],
            ['id' => 24, 'name' => 'Malisbong',             'area_km2' => 16.10, 'coordinates' => '12.7701,120.8432'],
            ['id' => 25, 'name' => 'Santo Niño',            'area_km2' => 11.70, 'coordinates' => '12.8630,120.7905'],
            ['id' => 26, 'name' => 'Tagumpay',              'area_km2' => 11.60, 'coordinates' => '12.9112,120.8096'],
        ];

        foreach ($barangays as $data) {
            Barangay::updateOrCreate(['id' => $data['id']], [
                'name'        => $data['name'],
                'population'  => 0,
                'area_km2'    => $data['area_km2'],
                'coordinates' => $data['coordinates'],
            ]);
        }

        // ── Hazard Types ───────────────────────────────────────
        $hazardTypes = [
            ['id' => 1, 'name' => 'Flooding',       'color' => '#3498db', 'icon' => 'fa-water'],
            ['id' => 2, 'name' => 'Landslide',      'color' => '#e67e22', 'icon' => 'fa-mountain'],
            ['id' => 3, 'name' => 'Storm Surge',    'color' => '#9b59b6', 'icon' => 'fa-wind'],
            ['id' => 4, 'name' => 'Liquefaction',   'color' => '#e74c3c', 'icon' => 'fa-house-crack'],
            ['id' => 5, 'name' => 'Ground Shaking', 'color' => '#c0392b', 'icon' => 'fa-road'],
            ['id' => 6, 'name' => 'Tsunami',        'color' => '#1ad1ff', 'icon' => 'fa-wave-square'],
        ];

        foreach ($hazardTypes as $data) {
            HazardType::updateOrCreate(['id' => $data['id']], [
                'name'  => $data['name'],
                'color' => $data['color'],
                'icon'  => $data['icon'],
            ]);
        }

        // ── Default Users ──────────────────────────────────────
        // Default password: 'Admin@1234' — CHANGE IN PRODUCTION!
        $defaultPassword = Hash::make('Admin@1234');

        $users = [
            ['id' => 8, 'username' => 'admin',           'name' => 'Administrator',    'email' => 'admin@sablayan.gov.ph',          'barangay_id' => null, 'role' => 'admin'],
            ['id' => 9, 'username' => 'division_chief',  'name' => 'Division Chief',   'email' => 'division-chief@sablayan.gov.ph', 'barangay_id' => null, 'role' => 'division_chief'],
            ['id' => 4, 'username' => 'staff_buenavista','name' => 'Staff Buenavista', 'email' => 'buenavista@sablayan.gov.ph',     'barangay_id' => 1,    'role' => 'barangay_staff'],
            ['id' => 5, 'username' => 'staff_burgos',    'name' => 'Staff Burgos',     'email' => 'burgos@sablayan.gov.ph',         'barangay_id' => 2,    'role' => 'barangay_staff'],
            ['id' => 6, 'username' => 'staff_claudio',   'name' => 'Staff Claudio',    'email' => 'claudio@sablayan.gov.ph',        'barangay_id' => 3,    'role' => 'barangay_staff'],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(['id' => $data['id']], [
                'username'    => $data['username'],
                'name'        => $data['name'],
                'email'       => $data['email'],
                'password'    => $defaultPassword,
                'barangay_id' => $data['barangay_id'],
                'role'        => $data['role'],
                'is_active'   => true,
            ]);
        }
    }
}
