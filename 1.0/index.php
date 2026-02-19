<?php
#+-----------------------+
#|      ERROR CODES      |
#+-----+-----------------+
#| 400 | BAD REQUEST     |
#| 401 | UNAUTHORIZED    |
#| 404 | NOT FOUND       |
#| 405 | NOT ALLOWED     |
#| 409 | CONFLICT        |
#| 500 | INTERNAL SERVER |
#| 502 | BAD GATEWAY     |
#+-----------------------+

# STRICT TYPES
declare(strict_types=1);

# PHP REPORTING
error_reporting(E_ERROR | E_PARSE);

# EXTERNAL FILES
require_once __DIR__ . '/_php_/constants.php';
require_once __DIR__ . '/_php_/result.php';
require_once __DIR__ . '/_php_/conversation.php';

# REQUEST CONTEXT
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$payload = json_decode(file_get_contents('php://input'), true);

# INSTANCES
$result = new Result();
$conversation = new Conversation();

# ROUTE PARSE
$path = null;
switch (true) {
	case isset($_GET['_url']):
		$path = '/' . ltrim((string) $_GET['_url'], '/');
		break;
	case isset($_SERVER['PATH_INFO']):
		$path = (string) $_SERVER['PATH_INFO'];
		break;
	case isset($_SERVER['REDIRECT_URL']):
		$path = (string) $_SERVER['REDIRECT_URL'];
		break;
	default:
		$path = parse_url($requestUri, PHP_URL_PATH);
		break;
}

$path = trim((string) $path, '/');
$segments = $path === '' ? [] : explode('/', $path);

$version = trim((string) api['version'], '/');
$versionIndex = array_search($version, $segments, true);
switch (true) {
	case is_int($versionIndex):
		$segments = array_slice($segments, $versionIndex + 1);
		break;
	default:
		break;
}

$segmentCount = count($segments);
$seg0 = $segments[0] ?? null;
$seg1 = $segments[1] ?? null;

# ROUTES
switch (true) {
	case $apiKey !== api['key']:
		echo $result->showError(401, 'UNAUTHORIZED', 401, 'Invalid API Key');
		break;
	case $seg0 === 'apps' && $requestMethod !== 'GET':
		echo $result->showError(405, 'NOT ALLOWED', 405, 'HTTP Method Not Allowed');
		break;
	case $seg0 === 'apps' && $requestMethod === 'GET' && $segmentCount > 2:
		echo $result->showError(404, 'NOT FOUND', 404, 'Invalid Endpoint');
		break;
	case $seg0 === 'apps' && $requestMethod === 'GET':
		switch (true) {
			case $segmentCount === 2 && (!is_string($seg1) || trim($seg1) === ''):
				echo $result->showError(404, 'NOT FOUND', 404, 'Invalid Endpoint');
				break 2;
			case $segmentCount === 2 && !array_key_exists(trim((string) $seg1), app):
				echo $result->showError(404, 'NOT FOUND', 404, 'App Not Found');
				break 2;
			case $segmentCount === 2:
				$appId = trim((string) $seg1);
				$appData = app[$appId];
				$appItem = [
					'id' => $appId,
				];
				if (is_array($appData)) {
					$appItem['bundle'] = $appData['bundle'] ?? null;
					$appItem['instructions'] = $appData['instructions'] ?? null;
					$appItem['name'] = $appData['name'] ?? null;
				}
				echo $result->showSuccess('app', $appItem);
				break 2;
			default:
				break;
		}

		$apps = [];
		foreach (app as $appKey => $appData) {
			$appItem = [
				'id' => $appKey,
			];
			if (is_array($appData)) {
				$appItem['bundle'] = $appData['bundle'] ?? null;
				$appItem['instructions'] = $appData['instructions'] ?? null;
				$appItem['name'] = $appData['name'] ?? null;
			}
			$apps[] = $appItem;
		}
		$apps = $result->sortItems($apps);
		echo $result->showSuccess('apps', $apps);
		break;
	case is_string($seg0) && $seg1 === 'conversation' && $requestMethod !== 'POST':
		echo $result->showError(405, 'NOT ALLOWED', 405, 'HTTP Method Not Allowed');
		break;
	case is_string($seg0) && $seg1 === 'conversation' && $requestMethod === 'POST' && $payload === null && json_last_error() !== JSON_ERROR_NONE:
		echo $result->showError(400, 'BAD REQUEST', 400, 'Invalid JSON Payload');
		break;
	case is_string($seg0) && $seg1 === 'conversation' && $requestMethod === 'POST' && $segmentCount !== 2:
		echo $result->showError(404, 'NOT FOUND', 404, 'Invalid Endpoint');
		break;
	case is_string($seg0) && $seg1 === 'conversation' && $requestMethod === 'POST' && !array_key_exists(trim((string) $seg0), app):
		echo $result->showError(404, 'NOT FOUND', 404, 'App Not Found');
		break;
	case is_string($seg0) && $seg1 === 'conversation' && $requestMethod === 'POST':
		echo $conversation->sendResponse(trim((string) $seg0), $payload);
		break;
	default:
		echo $result->showError(404, 'NOT FOUND', 404, 'Invalid Endpoint');
		break;
}
