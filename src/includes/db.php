<?php
$root_dir = dirname(__DIR__);
$db_path = $root_dir . '/data/db.sqlite';

try {
    $db = new PDO('sqlite:' . $db_path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA foreign_keys = ON');
} catch (PDOException $e) {
    die("Database connection failed. Please try again later or contact support.");
}