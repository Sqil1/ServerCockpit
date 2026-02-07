# SystemMonitoringService - Documentation

## Vue d'ensemble

Service Symfony qui lit les métriques système depuis `/proc` (Linux) et fournit des données formatées pour le dashboard.

## Architecture

### Métriques disponibles

| Méthode | Source | Cache | Description |
|---------|--------|-------|-------------|
| `getCpuUsage()` | `/proc/stat` | Oui (2s) | Utilisation CPU globale + par cœur |
| `getMemoryUsage()` | `/proc/meminfo` | Non | Utilisation RAM instantanée |
| `getDiskUsage($path)` | `disk_*_space()` | Non | Utilisation disque pour un chemin |

### Système de cache CPU

Le CPU utilise **Symfony Cache** avec un mécanisme de delta :
```
1. Lecture snapshot actuel
2. Si cache existe ET < 2s → Retourne dernier résultat
3. Si cache existe ET ≥ 2s → Compare avec ancien snapshot → Calcule %
4. Sauvegarde nouveau snapshot + résultat
```

**Avantage :** Rafraîchissement stable toutes les 2 secondes, pas de `sleep()`.

## Utilisation

### Dans un contrôleur
```php
public function dashboard(SystemMonitoringService $monitoring): Response
{
    return $this->render('dashboard/index.html.twig', [
        'cpu' => $monitoring->getCpuUsage(),
        'memory' => $monitoring->getMemoryUsage(),
        'disk' => $monitoring->getDiskUsage('/'),
    ]);
}
```

### Format de retour

**CPU :**
```php
[
    'cpu' => ['percentage' => 45.2],    // Global
    'cpu0' => ['percentage' => 67.0],   // Cœur 0
    'cpu1' => ['percentage' => 34.5],   // Cœur 1
    // ...
]
```

**Mémoire :**
```php
[
    'total' => 16777216,           // en kB
    'available' => 8388608,
    'used' => 8388608,
    'percentage' => 50.0,
    'formatted' => [
        'total' => '16.00 GB',
        'available' => '8.00 GB',
        'used' => '8.00 GB',
    ]
]
```

**Disque :**
```php
[
    'total' => 500107862016,       // en bytes
    'free' => 250053931008,
    'used' => 250053931008,
    'percentage' => 50.0,
    'formatted' => [
        'total' => '465.76 GB',
        'free' => '232.88 GB',
        'used' => '232.88 GB',
    ]
]
```

## Configuration

### Service (config/services.yaml)
```yaml
App\Service\SystemMonitoringService:
    arguments:
        $procPath: '/proc'
        $cache: '@cache.app'
```

### Tests (procPath personnalisé)
```yaml
# config/services_test.yaml
App\Service\SystemMonitoringService:
    arguments:
        $procPath: '%kernel.project_dir%/tests/fixtures/proc'
```

## Gestion d'erreurs

| Erreur | Exception | Raison |
|--------|-----------|--------|
| Fichier `/proc/*` illisible | `RuntimeException` | Permissions, système non-Linux |
| Chemin disque invalide | `RuntimeException` | Dossier inexistant |
| `disk_*_space()` retourne false | `RuntimeException` | Permissions, filesystem spécial |

## Points techniques

### Pourquoi un cache pour le CPU ?

`/proc/stat` contient des **valeurs cumulatives** depuis le boot. Pour calculer l'utilisation actuelle, on doit comparer deux mesures espacées dans le temps.

### Pourquoi pas de cache pour mémoire/disque ?

Ces métriques sont **instantanées**, pas besoin de delta.

### Performance

- **CPU** : 1 lecture `/proc/stat` toutes les 2s minimum
- **Mémoire** : 1 lecture `/proc/meminfo` par appel (~1ms)
- **Disque** : 2 appels système `disk_*_space()` (~5ms)

## Extensions futures

- `getNetworkStats()` : Trafic réseau (même logique que CPU avec delta)
- `getLoadAverage()` : Charge système 1/5/15min
- `getSystemInfo()` : Hostname, uptime, nombre de cœurs