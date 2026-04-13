<?php

declare(strict_types=1);

/**
 * Submission
 *
 * The model layer — responsible only for persisting a validated submission to the DB.
 * It receives a flat payload array from the controller and executes a single INSERT.
 *
 * Keeping this separate from the controller means the DB logic is independently testable
 * and swappable — tests inject a FakeSubmissionModel that counts calls without touching a DB.
 */
class Submission
{
	private PDO $pdo;

	// PDO is injected rather than instantiated here so the model doesn't know how the
	// connection is created — that concern belongs to DatabaseConnection.
	public function __construct(PDO $pdo)
	{
		$this->pdo = $pdo;
	}

	/**
	 * Inserts one submission row and returns the new auto-increment ID.
	 * All values reach here already validated and sanitised by SubmissionValidator.
	 * PDO prepared statements handle escaping — no manual sanitisation is done here.
	 * Attachment columns are nullable so submissions without a file insert cleanly.
	 */
	public function insert(array $payload): int
	{
		$sql = 'INSERT INTO submissions (
			job_title,
			job_small_script,
			country,
			state_province,
			budget,
			attachment_original_name,
			attachment_stored_name,
			attachment_extension,
			attachment_mime,
			attachment_size,
			created_at
		) VALUES (
			:job_title,
			:job_small_script,
			:country,
			:state_province,
			:budget,
			:attachment_original_name,
			:attachment_stored_name,
			:attachment_extension,
			:attachment_mime,
			:attachment_size,
			:created_at
		)';

		$statement = $this->pdo->prepare($sql);
		$statement->execute([
			':job_title' => $payload['job_title'],
			':job_small_script' => $payload['job_small_script'] ?? null,  // optional
			':country' => $payload['country'],
			':state_province' => $payload['state_province'],
			':budget' => $payload['budget'],
			':attachment_original_name' => $payload['attachment_original_name'] ?? null,  // null if no upload
			':attachment_stored_name' => $payload['attachment_stored_name'] ?? null,      // randomised filename
			':attachment_extension' => $payload['attachment_extension'] ?? null,
			':attachment_mime' => $payload['attachment_mime'] ?? null,
			':attachment_size' => $payload['attachment_size'] ?? null,                    // bytes
			':created_at' => date('Y-m-d H:i:s'),
		]);

		return (int) $this->pdo->lastInsertId();
	}
}
