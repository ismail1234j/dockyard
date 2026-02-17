<?php
// needs to be tested and debugged, not currently used in the app

class Docker
{
    private $scriptPath;

    public function __construct()
    {
        $this->scriptPath = realpath(__DIR__ . '/../private/manage_containers.sh');
        if (!$this->scriptPath || !file_exists($this->scriptPath)) {
            throw new RuntimeException('Script not found');
        }
    }

    private function validateName($name)
    {
        if (!preg_match('/^[a-zA-Z0-9_.-]{1,64}$/', $name)) {
            throw new InvalidArgumentException('Invalid container name');
        }
        return escapeshellarg($name);
    }

    public function start($name)
    {
        $escapedName = $this->validateName($name);
        $output = shell_exec("bash {$this->scriptPath} start $escapedName 2>&1");
        $success = strpos($output, $name) !== false || empty(trim($output));
        return ['output' => $output, 'success' => $success];
    }

    public function stop($name)
    {
        $escapedName = $this->validateName($name);
        $output = shell_exec("bash {$this->scriptPath} stop $escapedName 2>&1");
        $success = strpos($output, $name) !== false || empty(trim($output));
        return ['output' => $output, 'success' => $success];
    }

    public function logs($name, $lines = 30)
    {
        $escapedName = $this->validateName($name);
        $lines = max(1, min((int)$lines, 500));
        $output = shell_exec("bash {$this->scriptPath} logs $escapedName $lines 2>&1");
        return ['output' => $output, 'success' => true];
    }

    public function status($name)
    {
        $escapedName = $this->validateName($name);
        $output = shell_exec("bash {$this->scriptPath} status $escapedName 2>&1");
        return ['output' => $output, 'success' => true];
    }
}