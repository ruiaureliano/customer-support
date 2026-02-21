<?php

# STRICT TYPES
declare(strict_types=1);

# PHP REPORTING
error_reporting(E_ERROR | E_PARSE);

# RESPONSE
header('Status: 200');
header('Content-Type: application/json');

$result = [
	'status' => 200,
	'success' => true
];
ksort($result);

# OUTPUT
echo json_encode($result);
