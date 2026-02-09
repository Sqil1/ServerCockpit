<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;

class SystemMonitoringService
{
    private string $procPath;
    private string $osReleasePath;
    private int $cacheLifetime = 2;

    public function __construct(
        string $procPath,
        string $osReleasePath
    ) {
        $this->procPath = $procPath;
        $this->osReleasePath = $osReleasePath;
    }

    // CPU - Avec cache et delta
    public function getCpuUsage(): array
    {
        $cacheFile = __DIR__ . '/../../var/cache/cpu_cache.json';
        $currentStats = $this->readCpuStats();

        if (file_exists($cacheFile)) {
            $cachedData = json_decode(file_get_contents($cacheFile), true);
            $age = time() - $cachedData['timestamp'];

            if ($age >= $this->cacheLifetime) {
                $oldStats = $cachedData['stats'];
                $result = $this->calculateDelta($oldStats, $currentStats);

                file_put_contents($cacheFile, json_encode([
                    'stats' => $currentStats,
                    'timestamp' => time(),
                    'lastResult' => $result,
                ]));

                return $result;
            }

            return $cachedData['lastResult'] ?? [];
        }

        file_put_contents($cacheFile, json_encode([
            'stats' => $currentStats,
            'timestamp' => time(),
            'lastResult' => [],
        ]));

        return [];
    }

    // Mémoire - Lecture directe
    public function getMemoryUsage(): array
    {
        $meminfoPath = rtrim($this->procPath, '/') . '/meminfo';
        $content = file_get_contents($meminfoPath);

        if ($content === false) {
            throw new \RuntimeException("Impossible de lire {$meminfoPath}");
        }

        $lines = explode("\n", $content);
        $memTotal = 0;
        $memAvailable = 0;

        foreach ($lines as $line) {
            if ($line === '') continue;

            if (str_starts_with($line, 'MemTotal:')) {
                $parts = preg_split('/\s+/', trim($line));
                if (isset($parts[1]) && is_numeric($parts[1])) {
                    $memTotal = (int)$parts[1];
                }
            }

            if (str_starts_with($line, 'MemAvailable:')) {
                $parts = preg_split('/\s+/', trim($line));
                if (isset($parts[1]) && is_numeric($parts[1])) {
                    $memAvailable = (int)$parts[1];
                }
            }
        }

        $memUsed = max(0, $memTotal - $memAvailable);
        $percentageUsed = ($memTotal > 0) ? ($memUsed / $memTotal) * 100 : 0;

        return [
            'total' => $memTotal,
            'available' => $memAvailable,
            'used' => $memUsed,
            'percentage' => round($percentageUsed, 1),
            'formatted' => [
                'total' => $this->formatBytes($memTotal * 1024),
                'available' => $this->formatBytes($memAvailable * 1024),
                'used' => $this->formatBytes($memUsed * 1024),
            ],
        ];
    }

    // Disque - Lecture directe
    public function getDiskUsage(string $path = '/'): array
    {
        if (!is_dir($path)) {
            throw new \RuntimeException("Le chemin {$path} n'est pas un répertoire valide");
        }

        $total = disk_total_space($path);
        $free = disk_free_space($path);

        if ($total === false || $free === false) {
            throw new \RuntimeException("Impossible de lire les informations du disque pour {$path}");
        }

        $used = $total - $free;
        $percentage = ($total > 0) ? ($used / $total) * 100 : 0;

        return [
            'total' => $total,
            'free' => $free,
            'used' => $used,
            'percentage' => round($percentage, 1),
            'formatted' => [
                'total' => $this->formatBytes($total),
                'free' => $this->formatBytes($free),
                'used' => $this->formatBytes($used),
            ],
        ];
    }

    // Réseau - Avec cache et delta (comme le CPU)
    public function getNetworkStats(): array
    {
        $cacheFile = __DIR__ . '/../../var/cache/network_cache.json';
        $currentStats = $this->readNetworkStats();

        if (file_exists($cacheFile)) {
            $cachedData = json_decode(file_get_contents($cacheFile), true);
            $age = time() - $cachedData['timestamp'];

            if ($age >= $this->cacheLifetime) {
                $oldStats = $cachedData['stats'];
                $result = $this->calculateNetworkDelta($oldStats, $currentStats, $age);

                file_put_contents($cacheFile, json_encode([
                    'stats' => $currentStats,
                    'timestamp' => time(),
                    'lastResult' => $result,
                ]));

                return $result;
            }

            return $cachedData['lastResult'] ?? [];
        }

        file_put_contents($cacheFile, json_encode([
            'stats' => $currentStats,
            'timestamp' => time(),
            'lastResult' => [],
        ]));

        return [];
    }

    // Load Average - Lecture directe
    public function getLoadAverage(): array
    {
        $loadavgPath = rtrim($this->procPath, '/') . '/loadavg';
        $content = file_get_contents($loadavgPath);

        if ($content === false) {
            throw new \RuntimeException("Impossible de lire {$loadavgPath}");
        }

        $parts = preg_split('/\s+/', trim($content));
        $load1 = (float)$parts[0];
        $load5 = (float)$parts[1];
        $load15 = (float)$parts[2];

        $processesParts = explode("/", $parts[3]);
        $runningProcesses = (int)$processesParts[0];
        $totalProcesses = (int)$processesParts[1];

        $cpuCores = $this->getCpuCores();

        return [
            'load1' => $load1,
            'load5' => $load5,
            'load15' => $load15,
            'load1_percent' => round(($load1 / $cpuCores) * 100, 1),
            'load5_percent' => round(($load5 / $cpuCores) * 100, 1),
            'load15_percent' => round(($load15 / $cpuCores) * 100, 1),
            'cpu_cores' => $cpuCores,
            'running_processes' => $runningProcesses,
            'total_processes' => $totalProcesses,
        ];
    }

    // Infos système
    public function getSystemInfo(): array
    {
        $os = 'Unknown';
        if (file_exists($this->osReleasePath)) {
            $osData = parse_ini_file($this->osReleasePath);
            $os = $osData['PRETTY_NAME'] ?? $osData['NAME'] ?? 'Linux';
        }

        return [
            'hostname' => gethostname(),
            'os' => $os,
            'architecture' => php_uname('m'),
            'kernel' => php_uname('r'),
            'uptime' => $this->getUptime(),
            'cpu_cores' => $this->getCpuCores(),
            'php_version' => PHP_VERSION,
        ];
    }

    // Lecture brute des stats CPU depuis /proc/stat
    private function readCpuStats(): array
    {
        $procInfoPath = rtrim($this->procPath, '/') . '/stat';
        $content = file_get_contents($procInfoPath);

        if ($content === false) {
            throw new \RuntimeException("Impossible de lire {$procInfoPath}");
        }

        $lines = explode("\n", $content);
        $cpus = [];

        foreach ($lines as $line) {
            if (empty(trim($line)) || !str_starts_with($line, 'cpu')) {
                continue;
            }

            $parts = preg_split('/\s+/', trim($line));

            if (count($parts) < 5) continue;

            $name = $parts[0];
            $values = array_slice($parts, 1);
            $values = array_map('intval', $values);
            $total = array_sum($values);

            $idle = isset($parts[4]) ? (int)$parts[4] : 0;
            $iowait = isset($parts[5]) ? (int)$parts[5] : 0;
            $idleAll = $idle + $iowait;

            $cpus[$name] = [
                'total' => $total,
                'idleAll' => $idleAll,
            ];
        }

        return $cpus;
    }

    // Calcul du delta CPU (différence entre 2 mesures)
    private function calculateDelta(array $oldStats, array $newStats): array
    {
        $result = [];

        foreach ($newStats as $cpuName => $newData) {
            if (!isset($oldStats[$cpuName])) continue;

            $oldData = $oldStats[$cpuName];
            $deltaTotal = $newData['total'] - $oldData['total'];
            $deltaIdle = $newData['idleAll'] - $oldData['idleAll'];

            if ($deltaTotal <= 0) {
                $percentage = 0;
            } else {
                $percentage = (($deltaTotal - $deltaIdle) / $deltaTotal) * 100;
            }

            $result[$cpuName] = [
                'percentage' => round($percentage, 2),
            ];
        }

        return $result;
    }

    // Lecture brute des stats réseau depuis /proc/net/dev
    private function readNetworkStats(): array
    {
        $netDevPath = rtrim($this->procPath, '/') . '/net/dev';
        $content = file_get_contents($netDevPath);

        if ($content === false) {
            throw new \RuntimeException("Impossible de lire {$netDevPath}");
        }

        $lines = explode("\n", $content);
        $interfaces = [];

        foreach ($lines as $index => $line) {
            // Skip headers (2 premières lignes)
            if ($index < 2 || trim($line) === '') continue;

            $parts = explode(':', $line);
            if (count($parts) < 2) continue;

            $interfaceName = trim($parts[0]);
            $stats = preg_split('/\s+/', trim($parts[1]));

            if (count($stats) < 9) continue;

            $interfaces[$interfaceName] = [
                'rx_bytes' => (int)$stats[0],
                'tx_bytes' => (int)$stats[8],
            ];
        }

        return $interfaces;
    }

    // Calcul du débit réseau (bytes/sec)
    private function calculateNetworkDelta(array $oldStats, array $newStats, int $timeElapsed): array
    {
        $result = [];

        foreach ($newStats as $interface => $newData) {
            if (!isset($oldStats[$interface])) continue;

            $oldData = $oldStats[$interface];

            $deltaRx = $newData['rx_bytes'] - $oldData['rx_bytes'];
            $deltaTx = $newData['tx_bytes'] - $oldData['tx_bytes'];

            $rxBytesPerSec = ($timeElapsed > 0) ? $deltaRx / $timeElapsed : 0;
            $txBytesPerSec = ($timeElapsed > 0) ? $deltaTx / $timeElapsed : 0;

            $result[$interface] = [
                'rx_bytes_per_sec' => (int)$rxBytesPerSec,
                'tx_bytes_per_sec' => (int)$txBytesPerSec,
                'formatted' => [
                    'rx' => $this->formatBytes((int)$rxBytesPerSec) . '/s',
                    'tx' => $this->formatBytes((int)$txBytesPerSec) . '/s',
                ],
            ];
        }

        return $result;
    }

    // Compte les threads logiques (processeurs)
    private function getCpuCores(): int
    {
        static $cores = null;

        if ($cores === null) {
            $content = file_get_contents($this->procPath . '/cpuinfo');
            preg_match_all('/^processor\s*:/m', $content, $matches);
            $cores = count($matches[0]);
        }

        return $cores ?: 1;
    }

    // Formatage de l'uptime
    private function getUptime(): string
    {
        $content = trim(file_get_contents($this->procPath . '/uptime'));
        $uptimeSeconds = (float)explode(' ', $content)[0];

        $days = floor($uptimeSeconds / 86400);
        $hours = floor(($uptimeSeconds % 86400) / 3600);
        $minutes = floor(($uptimeSeconds % 3600) / 60);

        return sprintf('%dd %dh %dm', $days, $hours, $minutes);
    }

    // Formatage bytes → KB/MB/GB
    public function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor(log($bytes, 1024));

        return sprintf('%.2f %s', $bytes / pow(1024, $factor), $units[$factor]);
    }

}