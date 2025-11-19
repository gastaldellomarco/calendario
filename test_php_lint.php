<?php
// Debug: Use PHP_BINARY to run lint over a file
echo "PHP_BINARY: " . (defined('PHP_BINARY') ? PHP_BINARY : 'not_defined') . PHP_EOL;
$cmd = (defined('PHP_BINARY') ? PHP_BINARY : 'php') . ' -l ' . escapeshellarg(__DIR__ . '/algorithm/CalendarioGenerator.php') . ' 2>&1';
echo "CMD: $cmd" . PHP_EOL;
$out = shell_exec($cmd);
echo $out . PHP_EOL;
?>
