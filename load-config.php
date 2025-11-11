<?php
/**
 * Load API Configuration
 * Carica le API keys dal file config.json
 */

header('Content-Type: application/json');

$config_file = __DIR__ . '/config.json';

// Check if config file exists
if (!file_exists($config_file)) {
    echo json_encode([
        'success' => false,
        'error' => 'File config.json non trovato. Crea il file partendo da config.example.json',
        'config' => null
    ]);
    exit;
}

// Read config file
$config_content = file_get_contents($config_file);
$config = json_decode($config_content, true);

if ($config === null) {
    echo json_encode([
        'success' => false,
        'error' => 'Errore nel parsing di config.json. Verifica che sia un JSON valido.',
        'config' => null
    ]);
    exit;
}

// Return config (but mask sensitive data in logs)
echo json_encode([
    'success' => true,
    'config' => $config,
    'message' => 'Configurazione caricata con successo'
]);
