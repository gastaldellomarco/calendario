<?php
ob_start();
include __DIR__ . '/verify_system.php';
$s = ob_get_clean();
echo 'len ' . strlen($s) . PHP_EOL;
?>
