<?php
$db = new PDO('sqlite:db.sqlite');

$createAppsTableQuery = "
    CREATE TABLE IF NOT EXISTS apps (
        ID INTEGER PRIMARY KEY AUTOINCREMENT, 
        Name TEXT NOT NULL, 
        Image TEXT NOT NULL, 
        Version TEXT NOT NULL,
        Status TEXT NOT NULL,
        Comment TEXT NOT NULL,
        Url TEXT NOT NULL
    )";

$createUsersTableQuery = "
    CREATE TABLE IF NOT EXISTS users (
        ID INTEGER PRIMARY KEY AUTOINCREMENT, 
        username TEXT NOT NULL, 
        password TEXT NOT NULL
    )";

$insertQuery = "INSERT INTO users (username, password) VALUES ('admin', '" . password_hash('pass', PASSWORD_DEFAULT) . "')";

// Execute the query's
$db->exec($createUsersTableQuery);
$db->exec($insertQuery);
$db->exec($createAppsTableQuery);