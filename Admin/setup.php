<?php
$db = new PDO('sqlite:db.sqlite');

/*// delete the apps table
$deleteTableQuery = "DROP TABLE IF EXISTS apps";

// SQL statement to create new table
$createTableQuery = "
    CREATE TABLE IF NOT EXISTS apps (
        ID INTEGER PRIMARY KEY AUTOINCREMENT, 
        Name TEXT NOT NULL, 
        Image TEXT NOT NULL, 
        Version TEXT NOT NULL,
        Status TEXT NOT NULL,
        Comment TEXT NOT NULL,
        Url TEXT NOT NULL
    )";

// Execute the query
$db->exec($deleteTableQuery);
$db->exec($createTableQuery);*/

// Insert into the users table the following values
$insertQuery = "INSERT INTO users (username, password) VALUES ('ismail', '" . password_hash('$0Ismail2010', PASSWORD_DEFAULT) . "')";

// Execute the query
$db->exec($insertQuery);