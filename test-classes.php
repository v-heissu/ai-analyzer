<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting test...\n";

$file = __DIR__ . '/apis/gemini.php';
echo "File path: $file\n";
echo "File exists: " . (file_exists($file) ? 'YES' : 'NO') . "\n";
echo "File readable: " . (is_readable($file) ? 'YES' : 'NO') . "\n";

if (file_exists($file)) {
    echo "File size: " . filesize($file) . " bytes\n";
    echo "First 100 chars: " . substr(file_get_contents($file), 0, 100) . "\n";
}

try {
    require_once $file;
    echo "Require successful\n";
    echo "GeminiAPI exists: " . (class_exists('GeminiAPI') ? 'YES' : 'NO') . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>