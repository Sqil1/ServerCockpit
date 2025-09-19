<?php

namespace App\Service;

class SystemMonitoringService
{
    private string $procPath;

    public function __construct(string $procPath = '/proc')
    {
        // Validation pour la production seulement
        if ($_ENV['APP_ENV'] === 'prod') {
            if ($procPath !== '/proc') {
                throw new \InvalidArgumentException('Seul /proc est autorisé en production');
            }
        }

        $this->procPath = $procPath;
    }

    /**
     * Lit les informations CPU depuis /proc/stat
     * Récupère TOUS les CPU individuels + global
     */
    public function getCpuUsage(): array
    {
        $content = file_get_contents($this->procPath . '/stat');
        $lines = explode("\n", $content);

        $cpus = [];
        $globalStats = null;

        foreach ($lines as $line) {
            if (empty($line)) continue;

            // Ligne CPU globale ou individuelle
            if (preg_match('/^cpu(\d*)\s+(.+)/', $line, $matches)) {
                $cpuId = $matches[1] === '' ? 'global' : (int)$matches[1];
                $statsStr = $matches[2];

                // Parser les valeurs
                $values = array_map('intval', preg_split('/\s+/', trim($statsStr)));

                $cpuData = [
                    'user' => $values[0] ?? 0,
                    'nice' => $values[1] ?? 0,
                    'system' => $values[2] ?? 0,
                    'idle' => $values[3] ?? 0,
                    'iowait' => $values[4] ?? 0,
                    'irq' => $values[5] ?? 0,
                    'softirq' => $values[6] ?? 0,
                    'steal' => $values[7] ?? 0
                ];

                $cpuData['total'] = array_sum($cpuData);
                $cpuData['active'] = $cpuData['total'] - $cpuData['idle'];

                if ($cpuId === 'global') {
                    $globalStats = $cpuData;
                } else {
                    $cpus[$cpuId] = $cpuData;
                }
            }
        }

        return [
            'global' => $globalStats,
            'individual_cpus' => $cpus,
            'cores_count' => count($cpus),
            'total_cores' => $this->getCpuCores()
        ];
    }

    /**
     * Lit les informations mémoire depuis /proc/meminfo
     */
    public function getMemoryUsage(): array
    {
        $content = file_get_contents($this->procPath . '/meminfo');

        // Normaliser les fins de ligne
        $content = str_replace("\r\n", "\n", $content);
        $lines = explode("\n", $content);

        $memory = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Parser chaque ligne individuellement
            if (preg_match('/^(\w+):\s+(\d+)\s*kB?$/i', $line, $matches)) {
                $memory[$matches[1]] = (int)$matches[2];
            }
        }

        // Vérifier que les clés essentielles existent
        if (!isset($memory['MemTotal'])) {
            throw new \Exception("MemTotal non trouvé dans meminfo. Clés disponibles: " . implode(', ', array_keys($memory)));
        }

        // Conversion kB → bytes
        $total = $memory['MemTotal'] * 1024;
        $available = ($memory['MemAvailable'] ?? $memory['MemFree']) * 1024;
        $free = $memory['MemFree'] * 1024;
        $buffers = ($memory['Buffers'] ?? 0) * 1024;
        $cached = ($memory['Cached'] ?? 0) * 1024;

        // Calcul de la mémoire réellement utilisée
        $used = $total - $available;
        $percentage = round(($used / $total) * 100, 2);

        return [
            'total' => $total,
            'used' => $used,
            'free' => $free,
            'available' => $available,
            'buffers' => $buffers,
            'cached' => $cached,
            'percentage' => $percentage,
            'formatted' => [
                'total' => $this->formatBytes($total),
                'used' => $this->formatBytes($used),
                'available' => $this->formatBytes($available)
            ]
        ];
    }

    /**
     * Lit les informations disque pour un chemin donné
     */
    public function getDiskUsage(string $path = '/'): array
    {
        // Fonctions PHP natives qui lisent les infos du système de fichiers
        $total = disk_total_space($path);
        $free = disk_free_space($path);
        $used = $total - $free;
        $percentage = round(($used / $total) * 100, 2);

        return [
            'path' => $path,
            'total' => $total,
            'used' => $used,
            'free' => $free,
            'percentage' => $percentage,
            'formatted' => [
                'total' => $this->formatBytes($total),
                'used' => $this->formatBytes($used),
                'free' => $this->formatBytes($free)
            ]
        ];
    }

    /**
     * Lit les statistiques réseau depuis /proc/net/dev
     */
    public function getNetworkStats(): array
    {
        $content = file_get_contents($this->procPath . '/net/dev');
        $lines = explode("\n", $content);

        $interfaces = [];

        foreach ($lines as $line) {
            // Ignorer les lignes d'en-tête et les interfaces virtuelles
            if (strpos($line, ':') === false) continue;
            if (preg_match('/\b(lo|docker|veth|br-)\b/', $line)) continue;

            // Parse la ligne: "interface: rx_bytes rx_packets ... tx_bytes tx_packets ..."
            $parts = preg_split('/\s+/', trim($line));
            $interfaceName = str_replace(':', '', $parts[0]);

            if (count($parts) >= 10) {
                $interfaces[$interfaceName] = [
                    'rx_bytes' => (int)$parts[1],      // Bytes reçus
                    'rx_packets' => (int)$parts[2],    // Paquets reçus
                    'rx_errors' => (int)$parts[3],     // Erreurs réception
                    'tx_bytes' => (int)$parts[9],      // Bytes envoyés
                    'tx_packets' => (int)$parts[10],   // Paquets envoyés
                    'tx_errors' => (int)$parts[11],    // Erreurs envoi
                    'formatted' => [
                        'rx' => $this->formatBytes((int)$parts[1]),
                        'tx' => $this->formatBytes((int)$parts[9])
                    ]
                ];
            }
        }

        return $interfaces;
    }

    /**
     * Lit la charge système depuis /proc/loadavg
     */
    public function getLoadAverage(): array
    {
        $content = trim(file_get_contents($this->procPath . '/loadavg'));
        $parts = explode(' ', $content);

        // Format: "1min 5min 15min running/total last_pid"
        return [
            '1min' => (float)$parts[0],   // Charge sur 1 minute
            '5min' => (float)$parts[1],   // Charge sur 5 minutes
            '15min' => (float)$parts[2],  // Charge sur 15 minutes
            'running_processes' => explode('/', $parts[3])[0], // Processus actifs
            'total_processes' => explode('/', $parts[3])[1]    // Total processus
        ];
    }

    /**
     * Informations générales du système
     */
    public function getSystemInfo(): array
    {
        return [
            'hostname' => gethostname(),
            'os' => PHP_OS_FAMILY,
            'kernel' => php_uname('r'),        // Version du noyau
            'architecture' => php_uname('m'),   // Architecture (x86_64)
            'php_version' => PHP_VERSION,
            'uptime' => $this->getUptime(),
            'server_time' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get()
        ];
    }

    /**
     * Compte le nombre de cœurs CPU
     */
    private function getCpuCores(): int
    {
        static $cores = null;

        if ($cores === null) {
            $content = file_get_contents($this->procPath . '/cpuinfo');
            // Compte le nombre d'occurrences de "processor"
            $cores = substr_count($content, 'processor');
        }

        return $cores ?: 1;
    }

    /**
     * Lit l'uptime depuis /proc/uptime
     */
    private function getUptime(): string
    {
        $content = trim(file_get_contents($this->procPath . '/uptime'));
        $uptimeSeconds = (float)explode(' ', $content)[0];

        // Conversion en format lisible
        $days = floor($uptimeSeconds / 86400);
        $hours = floor(($uptimeSeconds % 86400) / 3600);
        $minutes = floor(($uptimeSeconds % 3600) / 60);

        return sprintf('%dd %dh %dm', $days, $hours, $minutes);
    }

    /**
     * Formate les bytes en unités lisiblesfet
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor(log($bytes, 1024));

        return sprintf('%.2f %s', $bytes / pow(1024, $factor), $units[$factor]);
    }
}