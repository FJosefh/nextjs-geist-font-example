<?php
// Simple debug page to check session and headers
session_start();

header('Content-Type: text/plain');

echo "Session Data:\n";
print_r($_SESSION);

echo "\nHeaders:\n";
print_r(headers_list());
?>
