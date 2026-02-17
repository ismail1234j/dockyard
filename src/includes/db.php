<?php
function get_db(): PDO {
    static $db = null;
    if ($db === null) {
        $db_path = __DIR__ . '/../data/db.sqlite';
        $db = new PDO('sqlite:' . $db_path);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA foreign_keys = ON');
    }
    return $db;
}