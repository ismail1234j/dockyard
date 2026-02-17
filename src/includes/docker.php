<?php
// needs to be tested and debugged, not currently used in the app

class Docker
{
    private string $scriptPath;

    public function __construct()
    {
        $path = realpath(__DIR__ . '/../private/manage_containers.sh');
        if ($path === false || !file_exists($path)) {
            throw new RuntimeException('Script not found');
        }
        $this->scriptPath = $path;
    }

    private function validateName(string $name): string
    {
        if (!preg_match('/^[a-zA-Z0-9_.-]{1,64}$/', $name)) {
            throw new InvalidArgumentException('Invalid container name');
        }
        return escapeshellarg($name);
    }

    /** @return array{output: string, success: bool} */
    public function start(string $name): array
    {
        $escapedName = $this->validateName($name);
        $output = shell_exec("bash {$this->scriptPath} start $escapedName 2>&1") ? '';
        $success = strpos($output, $name) !== false || empty(trim($output));
        return ['output' => $output, 'success' => $success];
    }
    
    /** @return array{output: string, success: bool} */
    public function stop(string $name): array
    {
        $escapedName = $this->validateName($name);
        $output = shell_exec("bash {$this->scriptPath} stop $escapedName 2>&1") ? '';
        $success = strpos($output, $name) !== false || empty(trim($output));
        return ['output' => $output, 'success' => $success];
    }

    /** @return array{output: string, success: bool} */
    public function logs(string $name, int $lines = 30): array
    {
        $escapedName = $this->validateName($name);
        $lines = max(1, min($lines, 500));
        $output = shell_exec("bash {$this->scriptPath} logs $escapedName $lines 2>&1") ? '';
        return ['output' => $output, 'success' => true];
    }

    /** @return array{output: string, success: bool} */
    public function status(string $name): array
    {
        $escapedName = $this->validateName($name);
        $output = shell_exec("bash {$this->scriptPath} status $escapedName 2>&1") ? '';
        return ['output' => $output, 'success' => true];
    }
}