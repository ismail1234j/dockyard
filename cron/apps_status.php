<?php
$db = new PDO('sqlite:../db.sqlite');
// Put all the Url of the appps into a array
$apps = array();
$stmt = $db->prepare('SELECT * FROM apps');
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $apps[] = $row['Url'];
    // echo $row['Url'];
}
// Check if the apps are up
$apps_status = array();
foreach ($apps as $app) {
    // echo $app;
    $ch = curl_init($app);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // echo $http_code;
    if ($http_code == 200) {
        $apps_status[$app] = 'running';
        // echo 'running';
    } elseif (str_contains($http_code, '1') or str_contains($http_code, '2') or str_contains($http_code, '3') or str_contains($http_code, '4') or str_contains($http_code, '5')) {
        $apps_status[$app] = 'error: ' . $http_code;
        // echo 'down';
    } else {
        $apps_status[$app] = 'down';
    }
    curl_close($ch);
}
// Update the status of the apps
foreach ($apps_status as $app => $status) {
    $stmt = $db->prepare('UPDATE apps SET Status = :status WHERE Url = :url');
    $stmt->bindParam(':status', $status, PDO::PARAM_STR);
    $stmt->bindParam(':url', $app, PDO::PARAM_STR);
    $stmt->execute();
}