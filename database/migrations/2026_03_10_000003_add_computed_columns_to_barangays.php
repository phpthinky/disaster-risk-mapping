<?php
// Migration: Add computed demographic columns to barangays table
// Run via migrate.php

return [
    'up' => "
        ALTER TABLE barangays
            ADD COLUMN IF NOT EXISTS pwd_count       INT DEFAULT 0 AFTER population,
            ADD COLUMN IF NOT EXISTS senior_count    INT DEFAULT 0 AFTER pwd_count,
            ADD COLUMN IF NOT EXISTS children_count  INT DEFAULT 0 AFTER senior_count,
            ADD COLUMN IF NOT EXISTS infant_count    INT DEFAULT 0 AFTER children_count,
            ADD COLUMN IF NOT EXISTS pregnant_count  INT DEFAULT 0 AFTER infant_count,
            ADD COLUMN IF NOT EXISTS ip_count        INT DEFAULT 0 AFTER pregnant_count,
            ADD COLUMN IF NOT EXISTS household_count INT DEFAULT 0 AFTER ip_count;
    ",
    'down' => "
        ALTER TABLE barangays
            DROP COLUMN IF EXISTS pwd_count,
            DROP COLUMN IF EXISTS senior_count,
            DROP COLUMN IF EXISTS children_count,
            DROP COLUMN IF EXISTS infant_count,
            DROP COLUMN IF EXISTS pregnant_count,
            DROP COLUMN IF EXISTS ip_count,
            DROP COLUMN IF EXISTS household_count;
    ",
];
