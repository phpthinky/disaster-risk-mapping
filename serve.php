#!/usr/bin/env php
<?php

// ─────────────────────────────────────────
//  serve.php — PHP Dev Server (artisan-like)
//  Usage: php serve.php [--host=] [--port=] [--path=]
// ─────────────────────────────────────────

define('RESET',   "\033[0m");
define('RED',     "\033[31m");
define('GREEN',   "\033[32m");
define('YELLOW',  "\033[33m");
define('CYAN',    "\033[36m");
define('BOLD',    "\033[1m");

function writeln(string $color, string $prefix, string $message): void {
    echo $color . BOLD . "  [{$prefix}]" . RESET . "  {$message}" . PHP_EOL;
}

function parseArgs(array $argv): array {
    $args = [
        'host' => '127.0.0.2',
        'port' => '8000',
        'path' => getcwd(),
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with($arg, '--host=')) {
            $args['host'] = substr($arg, 7);
        } elseif (str_starts_with($arg, '--port=')) {
            $args['port'] = substr($arg, 7);
        } elseif (str_starts_with($arg, '--path=')) {
            $args['path'] = substr($arg, 7);
        } elseif ($arg === '--help' || $arg === '-h') {
            showHelp();
            exit(0);
        }
    }

    return $args;
}

function showHelp(): void {
    echo PHP_EOL;
    echo BOLD . CYAN . "  PHP Dev Server" . RESET . PHP_EOL;
    echo "  ─────────────────────────────────────────" . PHP_EOL;
    echo BOLD . "  Usage:" . RESET . PHP_EOL;
    echo "    php serve.php [options]" . PHP_EOL . PHP_EOL;
    echo BOLD . "  Options:" . RESET . PHP_EOL;
    echo "    --host=<host>   " . YELLOW . "Host address   (default: 127.0.0.1)" . RESET . PHP_EOL;
    echo "    --port=<port>   " . YELLOW . "Port number    (default: 8000)" . RESET . PHP_EOL;
    echo "    --path=<path>   " . YELLOW . "Document root  (default: current directory)" . RESET . PHP_EOL;
    echo "    --help, -h      " . YELLOW . "Show this help message" . RESET . PHP_EOL;
    echo PHP_EOL;
    echo BOLD . "  Examples:" . RESET . PHP_EOL;
    echo "    php serve.php" . PHP_EOL;
    echo "    php serve.php --port=8080" . PHP_EOL;
    echo "    php serve.php --port=9000 --path=C:/xampp/htdocs/my-project" . PHP_EOL;
    echo "    php serve.php --host=0.0.0.0 --port=8000" . PHP_EOL;
    echo PHP_EOL;
}

function validatePath(string $path): string {
    $realPath = realpath($path);
    if ($realPath === false) {
        writeln(RED, 'ERROR', "Path does not exist: {$path}");
        exit(1);
    }
    if (!is_dir($realPath)) {
        writeln(RED, 'ERROR', "Path is not a directory: {$realPath}");
        exit(1);
    }
    return $realPath;
}

function validatePort(string $port): void {
    if (!is_numeric($port) || (int)$port < 1 || (int)$port > 65535) {
        writeln(RED, 'ERROR', "Invalid port number: {$port}");
        exit(1);
    }
}

function checkPhpVersion(): void {
    if (version_compare(PHP_VERSION, '7.4.0', '<')) {
        writeln(YELLOW, 'WARN', 'PHP version ' . PHP_VERSION . ' detected. PHP 7.4+ recommended.');
    }
}

function detectIndexFile(string $path): string {
    $indexes = ['index.php', 'index.html', 'index.htm'];
    foreach ($indexes as $file) {
        if (file_exists($path . DIRECTORY_SEPARATOR . $file)) {
            return $file;
        }
    }
    return 'No index file found';
}

// ─────────────────────────────────────────
//  MAIN
// ─────────────────────────────────────────

$args = parseArgs($argv);

$host    = $args['host'];
$port    = $args['port'];
$docRoot = validatePath($args['path']);

validatePort($port);
checkPhpVersion();

$indexFile = detectIndexFile($docRoot);
$url       = "http://{$host}:{$port}";
$phpBin    = PHP_BINARY;

// Banner
echo PHP_EOL;
echo BOLD . CYAN . "  ┌─────────────────────────────────────┐" . RESET . PHP_EOL;
echo BOLD . CYAN . "  │       PHP Development Server         │" . RESET . PHP_EOL;
echo BOLD . CYAN . "  └─────────────────────────────────────┘" . RESET . PHP_EOL;
echo PHP_EOL;

writeln(GREEN,  'INFO',  "Server started successfully!");
writeln(CYAN,   'URL',   $url);
writeln(YELLOW, 'ROOT',  $docRoot);
writeln(YELLOW, 'INDEX', $indexFile);
writeln(YELLOW, 'PHP',   PHP_VERSION);
echo PHP_EOL;
writeln(RESET,  'READY', "Press Ctrl+C to stop the server.");
echo PHP_EOL;
echo "  ─────────────────────────────────────────" . PHP_EOL . PHP_EOL;

// Start server
$command = sprintf('%s -S %s:%s -t %s', escapeshellcmd($phpBin), $host, $port, escapeshellarg($docRoot));
passthru($command);