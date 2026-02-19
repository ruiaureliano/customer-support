<?php

# STRICT TYPES
declare(strict_types=1);

# PHP REPORTING
error_reporting(E_ERROR | E_PARSE);

# API
define('api', [
	'key' => 'A3F9B2C1-7D84-4E21-9A6F-12B3C4D5E6F7',
	'content-type' => 'application/json',
	'server' => 'https://amicoapps.com/customer-support',
	'version' => '1.0'
]);

# APPS
define('app', [
	'quitall' => [
		'key' => 'C8D1E2F3-4A56-4B78-8C9D-1234ABCD5678',
		'bundle' => 'com.amicoapps.quitall',
		'name' => 'QuitAll',
		'instructions' => 'quitall-instructions.md'
	]
]);

# MYSQL
define('mysql', [
	'database' => '****',
	'hostname' =>  '****',
	'password' =>  '****',
	'username' =>  '****'
]);

#OPENAI
define('openai', [
	'key' => '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',
	'model' => 'gpt-4o-mini',
	'temperature' => 0.3,
	'url' => 'https://api.openai.com/v1',
	'updated' => '2026-02-19',
	'pricing' => [
		'gpt-4o-mini' => [
			'currency' => 'USD',
			'input' => 0.15,
			'output' => 0.60,
			'unit' => '1M_tokens'
		],
		'gpt-4o-mini-2024-07-18' => [
			'currency' => 'USD',
			'input' => 0.15,
			'output' => 0.60,
			'unit' => '1M_tokens'
		],
		'gpt-5.2' => [
			'currency' => 'USD',
			'input' => 1.75,
			'output' => 14.0,
			'unit' => '1M_tokens'
		],
		'gpt-5.2-pro' => [
			'currency' => 'USD',
			'input' => 21.0,
			'output' => 168.0,
			'unit' => '1M_tokens'
		],
		'gpt-5-mini' => [
			'currency' => 'USD',
			'input' => 0.25,
			'output' => 2.0,
			'unit' => '1M_tokens'
		]
	]
]);
