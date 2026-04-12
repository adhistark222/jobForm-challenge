<?php

declare(strict_types=1);

class Submission
{
	private PDO $pdo;

	public function __construct(PDO $pdo)
	{
		$this->pdo = $pdo;
	}

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
			':job_small_script' => $payload['job_small_script'] ?? null,
			':country' => $payload['country'],
			':state_province' => $payload['state_province'],
			':budget' => $payload['budget'],
			':attachment_original_name' => $payload['attachment_original_name'] ?? null,
			':attachment_stored_name' => $payload['attachment_stored_name'] ?? null,
			':attachment_extension' => $payload['attachment_extension'] ?? null,
			':attachment_mime' => $payload['attachment_mime'] ?? null,
			':attachment_size' => $payload['attachment_size'] ?? null,
			':created_at' => date('Y-m-d H:i:s'),
		]);

		return (int) $this->pdo->lastInsertId();
	}
}
