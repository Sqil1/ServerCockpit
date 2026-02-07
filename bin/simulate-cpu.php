<?php
// bin/simulate-cpu.php

$statFile = __DIR__ . '/../var/proc/stat';

if (!file_exists($statFile)) {
    die("Fichier stat non trouvé. Crée d'abord var/proc/stat\n");
}

// Lire le fichier actuel
$content = file_get_contents($statFile);
$lines = explode("\n", $content);

$newLines = [];

foreach ($lines as $line) {
    if (str_starts_with($line, 'cpu')) {
        // Extraire les valeurs
        $parts = preg_split('/\s+/', trim($line));
        $name = $parts[0];

        // Incrémenter toutes les valeurs de manière aléatoire
        $values = [];
        for ($i = 1; $i < count($parts); $i++) {
            if (is_numeric($parts[$i])) {
                // Ajouter entre 10000 et 50000 (simule activité CPU)
                $increment = rand(10000, 50000);
                $values[] = (int)$parts[$i] + $increment;
            } else {
                $values[] = $parts[$i];
            }
        }

        $newLines[] = $name . ' ' . implode(' ', $values);
    } else {
        $newLines[] = $line;
    }
}

// Écrire le nouveau fichier
file_put_contents($statFile, implode("\n", $newLines));

echo "✅ Fichier stat mis à jour avec de nouvelles valeurs\n";
echo "🔄 Rafraîchis ton dashboard maintenant !\n";