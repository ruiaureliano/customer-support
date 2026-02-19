<?php

# STRICT TYPES
declare(strict_types=1);

# PHP REPORTING
error_reporting(E_ERROR | E_PARSE);

require_once __DIR__ . '/result.php';

final class Conversation {

	// +-----------------------------------------------------------------------------------+
	// | PRIVATE MEMBERS                                                                   |
	// +-----------------------------------------------------------------------------------+

	private Result $result;

	private function getAppInstructions(string $appId): ?string {
		$appConfig = isset(app[$appId]) && is_array(app[$appId]) ? app[$appId] : null;
		if (!is_array($appConfig)) {
			return null;
		}
		$instructionsFile = $appConfig['instructions'] ?? null;
		if (!is_string($instructionsFile) || trim($instructionsFile) === '') {
			return null;
		}
		$path = __DIR__ . '/../_md_/' . trim($instructionsFile);
		if (!is_file($path) || !is_readable($path)) {
			return null;
		}
		$contents = file_get_contents($path);
		if (!is_string($contents) || trim($contents) === '') {
			return null;
		}
		return trim($contents);
	}

	private function formatDecimalPrice(?float $value): ?string {
		if (!is_float($value)) {
			return null;
		}
		$formatted = sprintf('%.8F', $value);
		$formatted = rtrim($formatted, '0');
		$formatted = rtrim($formatted, '.');
		return $formatted === '' ? '0' : $formatted;
	}

	private function calculateUsagePrice(string $model, ?int $inputTokens, ?int $outputTokens): array {
		$pricing = isset(openai['pricing']) && is_array(openai['pricing']) ? openai['pricing'] : [];
		$modelPricing = isset($pricing[$model]) && is_array($pricing[$model]) ? $pricing[$model] : null;
		if (!is_array($modelPricing)) {
			return [
				'currency' => null,
				'input' => null,
				'output' => null,
				'total' => null,
			];
		}

		$currency = isset($modelPricing['currency']) && is_string($modelPricing['currency']) ? trim($modelPricing['currency']) : null;
		$inputRate = isset($modelPricing['input']) && (is_float($modelPricing['input']) || is_int($modelPricing['input'])) ? (float) $modelPricing['input'] : null;
		$outputRate = isset($modelPricing['output']) && (is_float($modelPricing['output']) || is_int($modelPricing['output'])) ? (float) $modelPricing['output'] : null;
		$inputPrice = null;
		$outputPrice = null;
		if (is_int($inputTokens) && is_float($inputRate)) {
			$inputPrice = round(($inputTokens / 1000000) * $inputRate, 8);
		}
		if (is_int($outputTokens) && is_float($outputRate)) {
			$outputPrice = round(($outputTokens / 1000000) * $outputRate, 8);
		}

		$totalPrice = null;
		switch (true) {
			case is_float($inputPrice) && is_float($outputPrice):
				$totalPrice = round($inputPrice + $outputPrice, 8);
				break;
			case is_float($inputPrice):
				$totalPrice = $inputPrice;
				break;
			case is_float($outputPrice):
				$totalPrice = $outputPrice;
				break;
			default:
				break;
		}

		return [
			'currency' => $currency !== '' ? $currency : null,
			'input' => $this->formatDecimalPrice($inputPrice),
			'output' => $this->formatDecimalPrice($outputPrice),
			'total' => $this->formatDecimalPrice($totalPrice),
		];
	}

	private function extractOutputText(array $openAiData): string {
		$outputText = isset($openAiData['output_text']) && is_string($openAiData['output_text']) ? trim($openAiData['output_text']) : '';
		if ($outputText !== '') {
			return $outputText;
		}

		$chunks = [];
		$output = $openAiData['output'] ?? null;
		if (!is_array($output)) {
			return '';
		}

		foreach ($output as $item) {
			if (!is_array($item)) {
				continue;
			}
			$content = $item['content'] ?? null;
			if (!is_array($content)) {
				continue;
			}
			foreach ($content as $contentItem) {
				if (!is_array($contentItem)) {
					continue;
				}
				$type = $contentItem['type'] ?? null;
				$text = $contentItem['text'] ?? null;
				if ($type === 'output_text' && is_string($text) && trim($text) !== '') {
					$chunks[] = trim($text);
				}
			}
		}

		return trim(implode("\n", $chunks));
	}

	private function sendToOpenAi(string $role, string $content, ?string $previousResponseId, string $model, ?float $temperature, ?string $instructions): array {
		$url = rtrim((string) openai['url'], '/') . '/responses';
		$payload = [
			'model' => $model,
			'input' => [
				[
					'role' => $role,
					'content' => [
						[
							'type' => 'input_text',
							'text' => $content,
						],
					],
				],
			],
		];
		if (is_string($previousResponseId) && trim($previousResponseId) !== '') {
			$payload['previous_response_id'] = trim($previousResponseId);
		}
		if (is_float($temperature)) {
			$payload['temperature'] = $temperature;
		}
		if (is_string($instructions) && trim($instructions) !== '') {
			$payload['instructions'] = $instructions;
		}

		$headers = [
			'Authorization: Bearer ' . openai['key'],
			'Content-Type: application/json',
		];
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$raw = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		switch (true) {
			case $raw === false || trim($error) !== '':
				return [
					'ok' => false,
					'status' => 502,
					'name' => 'BAD GATEWAY',
					'reason' => 'OpenAI Request Failed',
				];
			default:
				$data = json_decode((string) $raw, true);
				if (!is_array($data)) {
					return [
						'ok' => false,
						'status' => 502,
						'name' => 'BAD GATEWAY',
						'reason' => 'Invalid OpenAI Response',
					];
				}
				if ($status < 200 || $status >= 300) {
					$upstreamMessage = null;
					if (isset($data['error']) && is_array($data['error']) && isset($data['error']['message']) && is_string($data['error']['message'])) {
						$upstreamMessage = trim($data['error']['message']);
					}
					$statusCode = $status > 0 ? $status : 502;
					$errorName = 'BAD GATEWAY';
					switch ($statusCode) {
						case 400:
							$errorName = 'BAD REQUEST';
							break;
						case 401:
							$errorName = 'UNAUTHORIZED';
							break;
						case 403:
							$errorName = 'FORBIDDEN';
							break;
						case 404:
							$errorName = 'NOT FOUND';
							break;
						case 405:
							$errorName = 'NOT ALLOWED';
							break;
						case 409:
							$errorName = 'CONFLICT';
							break;
						case 429:
							$errorName = 'TOO MANY REQUESTS';
							break;
						case 500:
							$errorName = 'INTERNAL SERVER';
							break;
						case 502:
							$errorName = 'BAD GATEWAY';
							break;
						case 503:
							$errorName = 'SERVICE UNAVAILABLE';
							break;
						case 504:
							$errorName = 'GATEWAY TIMEOUT';
							break;
						default:
							break;
					}
					return [
						'ok' => false,
						'status' => $statusCode,
						'name' => $errorName,
						'reason' => $upstreamMessage !== null && $upstreamMessage !== '' ? $upstreamMessage : 'OpenAI Error',
					];
				}
				return [
					'ok' => true,
					'data' => $data,
				];
		}
	}

	// +-----------------------------------------------------------------------------------+
	// | PUBLIC MEMBERS                                                                    |
	// +-----------------------------------------------------------------------------------+

	public function __construct() {
		$this->result = new Result();
	}

	public function sendResponse(string $appId, $payload): string {
		$role = is_array($payload) ? ($payload['role'] ?? null) : null;
		$content = is_array($payload) ? ($payload['content'] ?? null) : null;
		$model = is_array($payload) ? ($payload['model'] ?? null) : null;
		$responseValue = is_array($payload) ? ($payload['response'] ?? null) : null;
		$temperature = is_array($payload) ? ($payload['temperature'] ?? null) : null;
		$previousResponseId = is_array($responseValue) ? ($responseValue['id'] ?? null) : null;

		switch (true) {
			case trim($appId) === '':
				return $this->result->showError(400, 'BAD REQUEST', 400, 'Invalid \'app_id\'');
			case !is_array($payload):
				return $this->result->showError(400, 'BAD REQUEST', 400, 'Invalid Payload');
			case !is_string($role) || ($role !== 'system' && $role !== 'user'):
				return $this->result->showError(400, 'BAD REQUEST', 400, 'Invalid \'role\'');
			case !is_string($content) || trim($content) === '':
				return $this->result->showError(400, 'BAD REQUEST', 400, 'Invalid \'content\'');
			case isset($model) && (!is_string($model) || trim($model) === ''):
				return $this->result->showError(400, 'BAD REQUEST', 400, 'Invalid \'model\'');
			case isset($responseValue) && !is_array($responseValue):
				return $this->result->showError(400, 'BAD REQUEST', 400, 'Invalid \'response\'');
			case isset($responseValue) && (!isset($responseValue['id']) || !is_string($responseValue['id']) || trim((string) $responseValue['id']) === ''):
				return $this->result->showError(400, 'BAD REQUEST', 400, 'Invalid \'response.id\'');
			case isset($temperature) && !is_float($temperature) && !is_int($temperature):
				return $this->result->showError(400, 'BAD REQUEST', 400, 'Invalid \'temperature\'');
			case isset($temperature) && ((float) $temperature < 0 || (float) $temperature > 2):
				return $this->result->showError(400, 'BAD REQUEST', 400, '\'temperature\' Must Be Between 0 And 2');
			default:
				$resolvedModel = isset($model) && is_string($model) ? trim($model) : (string) openai['model'];
				$defaultTemperature = isset(openai['temperature']) && (is_float(openai['temperature']) || is_int(openai['temperature'])) ? (float) openai['temperature'] : 0.3;
				$resolvedTemperature = isset($temperature) ? (float) $temperature : $defaultTemperature;
				$instructions = $this->getAppInstructions(trim($appId));
				$openAi = $this->sendToOpenAi($role, trim($content), is_string($previousResponseId) ? $previousResponseId : null, $resolvedModel, $resolvedTemperature, $instructions);
				if (!isset($openAi['ok']) || $openAi['ok'] !== true || !isset($openAi['data']) || !is_array($openAi['data'])) {
					$status = isset($openAi['status']) && is_int($openAi['status']) ? $openAi['status'] : 502;
					$name = isset($openAi['name']) && is_string($openAi['name']) && trim($openAi['name']) !== '' ? trim($openAi['name']) : 'BAD GATEWAY';
					$reason = isset($openAi['reason']) && is_string($openAi['reason']) && trim($openAi['reason']) !== '' ? trim($openAi['reason']) : 'OpenAI Request Failed';
					return $this->result->showError($status, $name, $status, $reason);
				}
				$openAiData = $openAi['data'];
				$openAiResponseId = isset($openAiData['id']) && is_string($openAiData['id']) ? trim($openAiData['id']) : '';
				$openAiOutputText = $this->extractOutputText($openAiData);
				if ($openAiResponseId === '') {
					return $this->result->showError(502, 'BAD GATEWAY', 502, 'OpenAI Response Missing \'id\'');
				}
				if ($openAiOutputText === '') {
					return $this->result->showError(502, 'BAD GATEWAY', 502, 'OpenAI Response Missing Output');
				}

				$responsePayload = [
					'app' => [
						'id' => trim($appId),
					],
					'content' => $openAiOutputText,
					'id' => $openAiResponseId,
					'role' => 'assistant',
				];
				$metadata = [
					'model' => isset($openAiData['model']) && is_string($openAiData['model']) && trim($openAiData['model']) !== '' ? trim($openAiData['model']) : $resolvedModel,
					'temperature' => isset($openAiData['temperature']) && (is_float($openAiData['temperature']) || is_int($openAiData['temperature'])) ? (float) $openAiData['temperature'] : $resolvedTemperature,
					'usage' => [
						'tokens' => [
							'input' => isset($openAiData['usage']) && is_array($openAiData['usage']) && isset($openAiData['usage']['input_tokens']) && is_int($openAiData['usage']['input_tokens']) ? $openAiData['usage']['input_tokens'] : null,
							'output' => isset($openAiData['usage']) && is_array($openAiData['usage']) && isset($openAiData['usage']['output_tokens']) && is_int($openAiData['usage']['output_tokens']) ? $openAiData['usage']['output_tokens'] : null,
							'total' => isset($openAiData['usage']) && is_array($openAiData['usage']) && isset($openAiData['usage']['total_tokens']) && is_int($openAiData['usage']['total_tokens']) ? $openAiData['usage']['total_tokens'] : null,
						],
						'price' => $this->calculateUsagePrice(
							isset($openAiData['model']) && is_string($openAiData['model']) && trim($openAiData['model']) !== '' ? trim($openAiData['model']) : $resolvedModel,
							isset($openAiData['usage']) && is_array($openAiData['usage']) && isset($openAiData['usage']['input_tokens']) && is_int($openAiData['usage']['input_tokens']) ? $openAiData['usage']['input_tokens'] : null,
							isset($openAiData['usage']) && is_array($openAiData['usage']) && isset($openAiData['usage']['output_tokens']) && is_int($openAiData['usage']['output_tokens']) ? $openAiData['usage']['output_tokens'] : null
						),
					],
				];
				return $this->result->showSuccessWithMetadata('response', $responsePayload, $metadata);
		}
	}
}
