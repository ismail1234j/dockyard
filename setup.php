<?php
// $db = new PDO('sqlite:db.sqlite');
/*
$createAppsTableQuery = "
    CREATE TABLE IF NOT EXISTS apps (
        ID INTEGER PRIMARY KEY AUTOINCREMENT, 
        ContainerName TEXT NOT NULL, 
        Image TEXT NOT NULL,
        Comment TEXT NOT NULL,
        Url TEXT NOT NULL
    )";

$createUsersTableQuery = "
    CREATE TABLE IF NOT EXISTS users (
        ID INTEGER PRIMARY KEY AUTOINCREMENT, 
        username TEXT NOT NULL, 
        password TEXT NOT NULL,
        email TEXT,
        IsAdmin BOOLEAN NOT NULL DEFAULT 0
    )";

$insertQuery = "INSERT INTO users (username, password, email, IsAdmin) VALUES ('admin', '" . password_hash('pass', PASSWORD_DEFAULT) . "', '', TRUE)";

// Execute the query's
$db->exec($createUsersTableQuery);
$db->exec($insertQuery);
$db->exec($createAppsTableQuery);
*/
// remove test app
/*
$deleteQuery = "DELETE FROM apps WHERE ContainerName = 'TestMinecraft'";
$db->exec($deleteQuery);

// delete user mahdjew
$deleteUserQuery = "DELETE FROM users WHERE username = 'mahdjew'";
$db->exec($deleteUserQuery);

// change admin password
$updateQuery = "UPDATE users SET password = '" . password_hash('$0IsmailServer2010', PASSWORD_DEFAULT) . "' WHERE username = 'admin'";
$db->exec($updateQuery);

// Create User Mahjew
$insertQuery = "INSERT INTO users (username, password, email, IsAdmin) VALUES ('mahdjew', '" . password_hash('£1MahdiStinks25', PASSWORD_DEFAULT) . "', '', TRUE)";
$db->exec($insertQuery);
*/

//$insertQuery = "INSERT INTO apps (ContainerName, Image, Comment, Url) VALUES ('TestMinecraft', 'bedrock', 'Test comment', 'server:19132')";
// $db->exec($insertQuery);
