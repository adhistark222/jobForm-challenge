<?php

declare(strict_types=1);

/**
 * DatabaseConnection
 *
 * Thin factory that builds a configured PDO instance from environment variables.
 * Centralising this here means every part of the app (controller, tests) gets
 * a connection the same way and the credentials never appear in source code.
 */
class DatabaseConnection
{
	/**
	 * Reads DB credentials from environment variables set in docker-compose.yml / .env.
	 * Falls back to safe defaults so the app degrades gracefully in misconfigured envs
	 * rather than throwing a cryptic undefined-variable error.
	 *
	 * PDO options set here:
	 *   ERRMODE_EXCEPTION   — throw on DB errors instead of silent failure
	 *   FETCH_ASSOC         — return associative arrays, not indexed arrays
	 *   EMULATE_PREPARES    — off: use native prepared statements for real parameterisation
	 */
	public static function createPdoFromEnvironment(): PDO
	{
		$host = getenv('DB_HOST') ?: 'db';
		$port = getenv('DB_PORT') ?: '3306';  // DB_PORT lets us use a non-default host port (e.g. 3307) without conflict
		$name = getenv('DB_NAME') ?: 'jobform';
		$user = getenv('DB_USER') ?: 'root';
		$password = getenv('DB_PASSWORD') ?: '';

		$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

		return new PDO($dsn, $user, $password, [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => false,
		]);
	}
}
