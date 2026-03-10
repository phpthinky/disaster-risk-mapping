<?php
// config.php
$host = 'localhost';
$dbname = 'disaster_db';
$username = 'root'; //'u215934853_gis_root001';
$password = '';//'+jZiDiM$5';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>