<?php

$file = __DIR__ . '/../var/proc/net/dev';

if (!file_exists($file)) {
    die("❌ Fichier $file introuvable\n");
}

$lines = file($file);

// Incrémenter les valeurs RX/TX aléatoirement
$newLines = [];
foreach ($lines as $index => $line) {
    if ($index < 2) {
        $newLines[] = $line;  // Headers
        continue;
    }

    if (trim($line) === '') {
        $newLines[] = $line;
        continue;
    }

    $parts = explode(':', $line);
    if (count($parts) < 2) {
        $newLines[] = $line;
        continue;
    }

    $name = $parts[0];
    $stats = preg_split('/\s+/', trim($parts[1]));

    // Incrémenter RX/TX (simule du trafic)
    $stats[0] = (int)$stats[0] + rand(1000, 100000);  // RX bytes
    $stats[8] = (int)$stats[8] + rand(500, 50000);    // TX bytes

    $newLines[] = $name . ': ' . implode(' ', $stats) . "\n";
}

file_put_contents($file, implode('', $newLines));

echo "✅ Fichier network mis à jour\n";