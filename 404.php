<?php

# STRICT TYPES
declare(strict_types=1);

error_reporting(E_ERROR | E_PARSE);

header('Status: 404');
header('Content-Type: application/json');

$error = [
	'code' => 404,
	'name' => 'UNAUTHORIZED',
	'reason' => 'Not Found',
];
ksort($error);
$result = [
	'error' => $error,
	'status' => 404,
	'success' => false,
];
ksort($result);
echo json_encode($result);
