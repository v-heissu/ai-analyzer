<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Gemini API</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .result { background: #f0f0f0; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { background: #ffcccc; }
        .success { background: #ccffcc; }
        input { width: 400px; padding: 10px; margin: 10px 0; }
        button { padding: 10px 20px; cursor: pointer; }
        pre { white-space: pre-wrap; word-wrap: break-word; }
    </style>
</head>
<body>
    <h1>Test Gemini API - Fan-out Generator</h1>

    <form method="POST">
        <div>
            <label>API Key Gemini:</label><br>
            <input type="text" name="api_key" value="<?php echo htmlspecialchars($_POST['api_key'] ?? ''); ?>" required>
        </div>

        <div>
            <label>Keyword:</label><br>
            <input type="text" name="keyword" value="<?php echo htmlspecialchars($_POST['keyword'] ?? 'scarpe running'); ?>" required>
        </div>

        <div>
            <label>Brand (opzionale):</label><br>
            <input type="text" name="brand" value="<?php echo htmlspecialchars($_POST['brand'] ?? ''); ?>">
        </div>

        <div>
            <label>Modello:</label><br>
            <select name="model">
                <option value="gemini-2.5-flash" selected>gemini-2.5-flash (Latest)</option>
                <option value="gemini-1.5-flash">gemini-1.5-flash</option>
                <option value="gemini-1.5-pro">gemini-1.5-pro</option>
                <option value="gemini-2.0-flash-exp">gemini-2.0-flash-exp</option>
            </select>
        </div>

        <button type="submit">Test Fanout</button>
    </form>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_once __DIR__ . '/apis/gemini.php';

        $api_key = $_POST['api_key'] ?? '';
        $keyword = $_POST['keyword'] ?? '';
        $brand = $_POST['brand'] ?? '';
        $model = $_POST['model'] ?? 'gemini-2.5-flash';

        echo "<h2>Testing...</h2>";
        echo "<div class='result'>";
        echo "<strong>Configurazione:</strong><br>";
        echo "Model: {$model}<br>";
        echo "Keyword: {$keyword}<br>";
        echo "Brand: " . ($brand ?: '(nessuno)') . "<br>";
        echo "API Key: " . substr($api_key, 0, 10) . "..." . substr($api_key, -5) . "<br>";
        echo "Endpoint: v1beta<br>";
        echo "</div>";

        try {
            $gemini = new GeminiAPI($api_key, $model);
            $queries = $gemini->generateFanOutQueries($keyword, $brand);

            if (count($queries) > 0) {
                echo "<div class='result success'>";
                echo "<h3>✅ SUCCESSO! Generate " . count($queries) . " query:</h3>";
                echo "<ol>";
                foreach ($queries as $query) {
                    echo "<li>" . htmlspecialchars($query) . "</li>";
                }
                echo "</ol>";
                echo "</div>";
            } else {
                echo "<div class='result error'>";
                echo "<h3>❌ ERRORE: Nessuna query generata</h3>";
                echo "<p>La chiamata API è riuscita ma non ha restituito query valide.</p>";
                echo "</div>";
            }
        } catch (Exception $e) {
            echo "<div class='result error'>";
            echo "<h3>❌ ECCEZIONE PHP:</h3>";
            echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
            echo "</div>";
        }

        // Mostra debug log se esiste
        if (file_exists('debug.log')) {
            echo "<div class='result'>";
            echo "<h3>Debug Log (ultime 20 righe):</h3>";
            $lines = file('debug.log');
            $last_lines = array_slice($lines, -20);
            echo "<pre>" . htmlspecialchars(implode('', $last_lines)) . "</pre>";
            echo "</div>";
        }
    }
    ?>
</body>
</html>
