<?php

# STRICT TYPES
declare(strict_types=1);

# PHP REPORTING
error_reporting(E_ERROR | E_PARSE);

final class Result {

	// +-----------------------------------------------------------------------------------+
	// | PRIVATE MEMBERS                                                                   |
	// +-----------------------------------------------------------------------------------+

	private function encodeJsonResponse(array $result): string {
		$this->sortRecursive($result);
		$json = json_encode($result);
		switch (true) {
			case $json === false:
				http_response_code(500);
				$fallback = [
					'error' => [
						'code' => 500,
						'name' => 'INTERNAL ERROR',
						'reason' => 'Failed To Encode JSON Response',
					],
					'status' => 500,
					'success' => false,
				];
				$this->sortRecursive($fallback);
				return json_encode($fallback) ?: '';
			default:
				return $json;
		}
	}

	private function sortRecursive(array &$value): void {
		$stack = [&$value];
		$visit = [false];
		while (count($stack) > 0) {
			$index = count($stack) - 1;
			$current = &$stack[$index];
			$currentVisit = $visit[$index];
			array_pop($stack);
			array_pop($visit);

			if (!is_array($current)) {
				unset($current);
				continue;
			}

			$isList = array_values($current) === $current;
			if ($currentVisit || $isList) {
				if (!$isList) {
					ksort($current);
				}
				unset($current);
				continue;
			}

			$stack[] = &$current;
			$visit[] = true;
			foreach ($current as $key => &$nested) {
				if (is_array($nested)) {
					$stack[] = &$nested;
					$visit[] = false;
				}
			}
			unset($nested);
			unset($current);
		}
	}

	// +-----------------------------------------------------------------------------------+
	// | PUBLIC MEMBERS                                                                    |
	// +-----------------------------------------------------------------------------------+

	public function showError($status, $name, $code, $reason): string {
		http_response_code((int) $status);
		header('Content-Type: application/json');
		$result = [
			'error' => [
				'code' => (int) $code,
				'name' => $name,
				'reason' => $reason,
			],
			'status' => (int) $status,
			'success' => false,
		];
		return $this->encodeJsonResponse($result);
	}

	public function showSuccess(string $key, $value): string {
		http_response_code(200);
		header('Content-Type: application/json');
		$result = [
			'status' => 200,
			'success' => true,
		];
		if (isset($value)) {
			$result[$key] = $value;
		}
		return $this->encodeJsonResponse($result);
	}

	public function showSuccessWithMetadata(string $key, $value, $metadata): string {
		http_response_code(200);
		header('Content-Type: application/json');
		$result = [
			'metadata' => $metadata,
			'status' => 200,
			'success' => true,
		];
		if (isset($value)) {
			$result[$key] = $value;
		}
		return $this->encodeJsonResponse($result);
	}

	public function sortItems(array $items): array {
		foreach ($items as $index => $item) {
			if (is_array($item)) {
				$this->sortRecursive($item);
				$items[$index] = $item;
			}
		}
		return $items;
	}
}
