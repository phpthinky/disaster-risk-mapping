#!/usr/bin/env php
<?php

// ─────────────────────────────────────────
//  migrate.php — Laravel-like Migration CLI
//  Usage: php migrate.php <command>
// ─────────────────────────────────────────

define('RESET',   "\033[0m");
define('RED',     "\033[31m");
define('GREEN',   "\033[32m");
define('YELLOW',  "\033[33m");
define('CYAN',    "\033[36m");
define('BLUE',    "\033[34m");
define('BOLD',    "\033[1m");
define('DIM',     "\033[2m");

define('MIGRATIONS_PATH', __DIR__ . '/database/migrations');
define('SEEDERS_PATH',    __DIR__ . '/database/seeders');

// ─────────────────────────────────────────
//  HELPERS
// ─────────────────────────────────────────

function line(string $color, string $tag, string $msg): void {
    echo $color . BOLD . "  {$tag}" . RESET . "  {$msg}" . PHP_EOL;
}

function info(string $msg):    void { line(CYAN,   '[INFO]  ', $msg); }
function success(string $msg): void { line(GREEN,  '[OK]    ', $msg); }
function warn(string $msg):    void { line(YELLOW, '[WARN]  ', $msg); }
function error(string $msg):   void { line(RED,    '[ERROR] ', $msg); }
function dim(string $msg):     void { echo DIM . "          {$msg}" . RESET . PHP_EOL; }

function divider(): void {
    echo DIM . "  " . str_repeat('─', 55) . RESET . PHP_EOL;
}

function banner(string $title): void {
    echo PHP_EOL;
    echo BOLD . CYAN . "  ┌" . str_repeat('─', 53) . "┐" . RESET . PHP_EOL;
    echo BOLD . CYAN . "  │  " . str_pad("Migration Manager — {$title}", 51) . "│" . RESET . PHP_EOL;
    echo BOLD . CYAN . "  └" . str_repeat('─', 53) . "┘" . RESET . PHP_EOL;
    echo PHP_EOL;
}

// ─────────────────────────────────────────
//  DATABASE CONNECTION
// ─────────────────────────────────────────

function getConnection(): PDO {
    require_once __DIR__ . '/config.php';
    if (!isset($pdo)) {
        error("No \$pdo connection found in config.php");
        exit(1);
    }
    return $pdo;
}

// ─────────────────────────────────────────
//  MIGRATIONS TABLE
// ─────────────────────────────────────────

function ensureMigrationsTable(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `migrations` (
            `id`         INT(11) NOT NULL AUTO_INCREMENT,
            `migration`  VARCHAR(255) NOT NULL,
            `batch`      INT(11) NOT NULL,
            `ran_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `migration` (`migration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function getRanMigrations(PDO $pdo): array {
    return $pdo->query("SELECT migration, batch FROM migrations ORDER BY id ASC")
               ->fetchAll(PDO::FETCH_KEY_PAIR);
}

function getLastBatch(PDO $pdo): int {
    $result = $pdo->query("SELECT MAX(batch) FROM migrations")->fetchColumn();
    return (int)($result ?? 0);
}

function getMigrationFiles(): array {
    if (!is_dir(MIGRATIONS_PATH)) return [];
    $files = glob(MIGRATIONS_PATH . '/*.php');
    sort($files);
    return $files;
}

function loadMigration(string $file): object {
    return require $file;
}

// ─────────────────────────────────────────
//  COMMANDS
// ─────────────────────────────────────────

function cmdMigrate(): void {
    banner('migrate');
    $pdo = getConnection();
    ensureMigrationsTable($pdo);

    $ran   = getRanMigrations($pdo);
    $files = getMigrationFiles();
    $batch = getLastBatch($pdo) + 1;
    $count = 0;

    foreach ($files as $file) {
        $name = basename($file, '.php');
        if (isset($ran[$name])) continue;

        info("Migrating: {$name}");
        try {
            $migration = loadMigration($file);
            $migration->up($pdo);
            $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)")
                ->execute([$name, $batch]);
            success("Migrated:  {$name}");
            $count++;
        } catch (Throwable $e) {
            error("Failed:    {$name}");
            dim($e->getMessage());
            exit(1);
        }
    }

    divider();
    if ($count === 0) {
        warn("Nothing to migrate. All migrations already ran.");
    } else {
        success("{$count} migration(s) ran successfully. Batch: {$batch}");
    }
    echo PHP_EOL;
}

function cmdRollback(): void {
    banner('migrate:rollback');
    $pdo = getConnection();
    ensureMigrationsTable($pdo);

    $lastBatch = getLastBatch($pdo);
    if ($lastBatch === 0) {
        warn("Nothing to rollback.");
        echo PHP_EOL;
        return;
    }

    $stmt = $pdo->prepare("SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC");
    $stmt->execute([$lastBatch]);
    $toRollback = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($toRollback as $name) {
        $file = MIGRATIONS_PATH . "/{$name}.php";
        info("Rolling back: {$name}");
        try {
            if (file_exists($file)) {
                $migration = loadMigration($file);
                $migration->down($pdo);
            }
            $pdo->prepare("DELETE FROM migrations WHERE migration = ?")->execute([$name]);
            success("Rolled back:  {$name}");
        } catch (Throwable $e) {
            error("Failed:       {$name}");
            dim($e->getMessage());
            exit(1);
        }
    }

    divider();
    success("Batch {$lastBatch} rolled back successfully.");
    echo PHP_EOL;
}

function cmdFresh(): void {
    banner('migrate:fresh');
    warn("This will DROP all tables and re-run all migrations!");
    echo PHP_EOL;
    echo "  Are you sure? Type " . BOLD . "yes" . RESET . " to continue: ";
    $confirm = trim(fgets(STDIN));
    if ($confirm !== 'yes') {
        warn("Cancelled.");
        echo PHP_EOL;
        return;
    }

    $pdo = getConnection();
    info("Dropping all tables...");

    // Disable FK checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        dim("Dropped: {$table}");
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    success("All tables dropped.");
    divider();

    // Re-run migrations
    ensureMigrationsTable($pdo);
    $files = getMigrationFiles();
    $batch = 1;
    $count = 0;

    foreach ($files as $file) {
        $name = basename($file, '.php');
        info("Migrating: {$name}");
        try {
            $migration = loadMigration($file);
            $migration->up($pdo);
            $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)")
                ->execute([$name, $batch]);
            success("Migrated:  {$name}");
            $count++;
        } catch (Throwable $e) {
            error("Failed:    {$name}");
            dim($e->getMessage());
            exit(1);
        }
    }

    divider();
    success("Fresh migration complete. {$count} migration(s) ran.");
    echo PHP_EOL;
}

function cmdStatus(): void {
    banner('migrate:status');
    $pdo = getConnection();
    ensureMigrationsTable($pdo);

    $ran   = getRanMigrations($pdo);
    $files = getMigrationFiles();

    if (empty($files)) {
        warn("No migration files found in database/migrations/");
        echo PHP_EOL;
        return;
    }

    echo BOLD . "  " . str_pad("Migration", 50) . str_pad("Batch", 8) . "Status" . RESET . PHP_EOL;
    divider();

    foreach ($files as $file) {
        $name = basename($file, '.php');
        if (isset($ran[$name])) {
            echo GREEN . "  ✔  " . RESET . str_pad($name, 50) . DIM . str_pad($ran[$name], 8) . RESET . GREEN . "Ran" . RESET . PHP_EOL;
        } else {
            echo YELLOW . "  ○  " . RESET . str_pad($name, 50) . str_pad("—", 8) . YELLOW . "Pending" . RESET . PHP_EOL;
        }
    }
    echo PHP_EOL;
}

function cmdMake(string $name): void {
    banner('make:migration');

    if (empty($name)) {
        error("Please provide a migration name. e.g. php migrate.php make:migration create_users_table");
        exit(1);
    }

    $timestamp = date('Y_m_d_His');
    $filename  = "{$timestamp}_{$name}.php";
    $filepath  = MIGRATIONS_PATH . "/{$filename}";

    $stub = <<<PHP
<?php

return new class {
    public function up(PDO \$pdo): void {
        \$pdo->exec("
            CREATE TABLE `{$name}` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    public function down(PDO \$pdo): void {
        \$pdo->exec("DROP TABLE IF EXISTS `{$name}`");
    }
};
PHP;

    if (!is_dir(MIGRATIONS_PATH)) mkdir(MIGRATIONS_PATH, 0755, true);
    file_put_contents($filepath, $stub);

    success("Migration created: database/migrations/{$filename}");
    echo PHP_EOL;
}

function cmdSeed(): void {
    banner('db:seed');
    $pdo   = getConnection();
    $files = glob(SEEDERS_PATH . '/*.php');
    sort($files);

    if (empty($files)) {
        warn("No seeder files found in database/seeders/");
        echo PHP_EOL;
        return;
    }

    foreach ($files as $file) {
        $name = basename($file, '.php');
        info("Seeding: {$name}");
        try {
            $seeder = require $file;
            $seeder->run($pdo);
            success("Seeded:  {$name}");
        } catch (Throwable $e) {
            error("Failed:  {$name}");
            dim($e->getMessage());
        }
    }
    echo PHP_EOL;
}

function cmdHelp(): void {
    echo PHP_EOL;
    echo BOLD . CYAN . "  PHP Migration Manager" . RESET . PHP_EOL;
    echo DIM   . "  Plain PHP — Laravel-style migrations" . RESET . PHP_EOL;
    echo PHP_EOL;
    echo BOLD  . "  Usage:" . RESET . PHP_EOL;
    echo "    php migrate.php <command>" . PHP_EOL . PHP_EOL;
    echo BOLD  . "  Commands:" . RESET . PHP_EOL;
    echo "    " . GREEN . "migrate" . RESET          . "                   Run all pending migrations\n";
    echo "    " . YELLOW . "migrate:rollback" . RESET . "           Rollback the last batch of migrations\n";
    echo "    " . RED . "migrate:fresh" . RESET       . "              Drop all tables and re-run everything\n";
    echo "    " . CYAN . "migrate:status" . RESET     . "             Show the status of each migration\n";
    echo "    " . BLUE . "make:migration <name>" . RESET . "      Create a new migration file\n";
    echo "    " . BLUE . "db:seed" . RESET            . "                   Run all seeder files\n";
    echo PHP_EOL;
    echo BOLD  . "  Examples:" . RESET . PHP_EOL;
    echo "    php migrate.php migrate\n";
    echo "    php migrate.php migrate:rollback\n";
    echo "    php migrate.php migrate:fresh\n";
    echo "    php migrate.php migrate:status\n";
    echo "    php migrate.php make:migration create_incidents_table\n";
    echo "    php migrate.php db:seed\n";
    echo PHP_EOL;
}

// ─────────────────────────────────────────
//  MAIN ROUTER
// ─────────────────────────────────────────

$command = $argv[1] ?? 'help';
$arg2    = $argv[2] ?? '';

match ($command) {
    'migrate'          => cmdMigrate(),
    'migrate:rollback' => cmdRollback(),
    'migrate:fresh'    => cmdFresh(),
    'migrate:status'   => cmdStatus(),
    'make:migration'   => cmdMake($arg2),
    'db:seed'          => cmdSeed(),
    default            => cmdHelp(),
};
