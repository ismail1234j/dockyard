<?php
/**
 * DEPRECATED: This file is not actively used in the application.
 * 
 * The application uses manage_containers.sh bash script for Docker operations instead.
 * This file remains for reference or future migration purposes.
 * 
 * Current implementation:
 * - Container management: manage_containers.sh (bash script)
 * - Container sync: cron/cron.php uses manage_containers.sh
 * - Container actions: apps/action.php uses manage_containers.sh
 */

function docker_request(string $method, string $endpoint, ?array $body = null): array
{
    $ch = curl_init();

    $url = "http://docker" . $endpoint; // dummy host for UNIX socket

    $options = [
        CURLOPT_UNIX_SOCKET_PATH => '/var/run/docker.sock',
        CURLOPT_URL => $url,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Host:' // important: empty Host header!
        ],
    ];

    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($body);
    }

    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("Docker cURL error: $error");
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 400) {
        throw new Exception("Docker HTTP error: $http_code, response: " . $response);
    }

    return json_decode($response, true) ?? [];
}


function docker_get_container(string $name): ?array
{
    $containers = docker_request('GET', '/containers/json');

    foreach ($containers as $container) {
        if (isset($container['Names']) && in_array('/' . $name, $container['Names'])) {
            return docker_request('GET', '/containers/' . $container['Id'] . '/json');
        }
    }

    return null;
}

function docker_start_container(string $name): void
{
    $container = docker_get_container($name);
    if ($container) {
        docker_request('POST', '/containers/' . $container['Id'] . '/start');
    } else {
        throw new Exception("Container '$name' not found");
    }
}

function docker_stop_container(string $name): void
{
    $container = docker_get_container($name);
    if ($container) {
        docker_request('POST', '/containers/' . $container['Id'] . '/stop');
    } else {
        throw new Exception("Container '$name' not found");
    }
}
