<?php
// Wrapper that forces verify_system.php to output JSON for debugging
try {
	if (!isset($_GET['format'])) $_GET['format'] = 'json';
	ob_start();
	require __DIR__ . '/verify_system.php';
	$s = ob_get_clean();
	echo 'Captured length: ' . strlen($s) . PHP_EOL;
	echo $s;
} catch (Throwable $t) {
	echo 'Exception: ' . $t->getMessage() . PHP_EOL;
}
