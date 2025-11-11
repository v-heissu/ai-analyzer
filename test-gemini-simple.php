<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><body>";
echo "<h1>Test Gemini - Diagnostico</h1>";

// Test 1: PHP funziona?
echo "<p>✓ PHP funziona!</p>";

// Test 2: Possiamo includere il file?
echo "<p>Tentativo di caricare GeminiAPI...</p>";

try {
    if (file_exists(__DIR__ . '/apis/gemini.php')) {
        echo "<p>✓ File gemini.php trovato</p>";
        require_once __DIR__ . '/apis/gemini.php';
        echo "<p>✓ File gemini.php caricato</p>";

        // Test 3: Possiamo creare l'oggetto?
        if (class_exists('GeminiAPI')) {
            echo "<p>✓ Classe GeminiAPI esiste</p>";

            // Test con una fake API key
            $testKey = 'test_key_123';
            $gemini = new GeminiAPI($testKey);
            echo "<p>✓ Oggetto GeminiAPI creato</p>";

            // Form per test reale
            echo '<hr><h2>Test Reale API</h2>';
            echo '<form method="POST">';
            echo 'API Key: <input type="text" name="api_key" value="' . htmlspecialchars($_POST['api_key'] ?? '') . '" size="50"><br><br>';
            echo 'Keyword: <input type="text" name="keyword" value="' . htmlspecialchars($_POST['keyword'] ?? 'scarpe running') . '"><br><br>';
            echo 'Brand: <input type="text" name="brand" value="' . htmlspecialchars($_POST['brand'] ?? '') . '"><br><br>';
            echo '<button type="submit">Test Fanout</button>';
            echo '</form>';

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['api_key'])) {
                echo '<hr><h2>Risultati Test</h2>';
                echo '<div style="background: #f0f0f0; padding: 15px; margin: 10px 0;">';

                $api_key = $_POST['api_key'];
                $keyword = $_POST['keyword'] ?? 'test';
                $brand = $_POST['brand'] ?? '';

                echo "<strong>Configurazione:</strong><br>";
                echo "API Key: " . substr($api_key, 0, 20) . "...<br>";
                echo "Keyword: {$keyword}<br>";
                echo "Brand: " . ($brand ?: '(nessuno)') . "<br><br>";

                $gemini = new GeminiAPI($api_key, 'gemini-1.5-flash');
                echo "Chiamando generateFanOutQueries...<br><br>";

                $start = microtime(true);
                $queries = $gemini->generateFanOutQueries($keyword, $brand);
                $elapsed = round((microtime(true) - $start) * 1000);

                echo "Tempo: {$elapsed}ms<br>";
                echo "Query generate: " . count($queries) . "<br><br>";

                if (count($queries) > 0) {
                    echo '<div style="background: #ccffcc; padding: 10px; margin: 10px 0;">';
                    echo '<strong>✓ SUCCESSO!</strong><br>';
                    echo '<ol>';
                    foreach ($queries as $q) {
                        echo '<li>' . htmlspecialchars($q) . '</li>';
                    }
                    echo '</ol>';
                    echo '</div>';
                } else {
                    echo '<div style="background: #ffcccc; padding: 10px; margin: 10px 0;">';
                    echo '<strong>✗ ERRORE: Nessuna query generata</strong><br>';
                    echo 'Controlla il debug.log per dettagli<br>';
                    echo '</div>';
                }

                // Mostra debug log
                if (file_exists('debug.log')) {
                    echo '<h3>Debug Log (ultime 30 righe):</h3>';
                    echo '<pre style="background: #f9f9f9; padding: 10px; overflow: auto; max-height: 400px;">';
                    $lines = file('debug.log');
                    $last = array_slice($lines, -30);
                    echo htmlspecialchars(implode('', $last));
                    echo '</pre>';
                }

                echo '</div>';
            }

        } else {
            echo "<p>✗ Classe GeminiAPI NON trovata!</p>";
        }
    } else {
        echo "<p>✗ File gemini.php NON trovato!</p>";
        echo "<p>Path cercato: " . __DIR__ . '/apis/gemini.php' . "</p>";
    }
} catch (Exception $e) {
    echo '<div style="background: #ffcccc; padding: 15px; margin: 10px 0;">';
    echo '<strong>ECCEZIONE:</strong><br>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    echo '</div>';
}

echo "</body></html>";
?>
