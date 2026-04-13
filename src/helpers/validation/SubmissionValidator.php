<?php

declare(strict_types=1);

class SubmissionValidator
{
	private const MAX_ATTACHMENT_SIZE_BYTES = 20 * 1024 * 1024;

	private array $allowedBudgets = ['5_99', '100_249', '250_499'];
	private array $allowedExtensions = ['pdf', 'doc', 'docx', 'txt', 'rtf', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'webp'];
	private array $allowedMimeTypes = [
		'application/pdf',
		'application/msword',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'application/vnd.ms-powerpoint',
		'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		'text/plain',
		'application/rtf',
		'text/rtf',
		'image/jpeg',
		'image/png',
		'image/webp',
	];

	private array $regions;

	public function __construct(string $regionsPath)
	{
		$json = file_get_contents($regionsPath);
		if ($json === false) {
			error_log('SubmissionValidator: could not read regions file: ' . $regionsPath);
			$this->regions = [];
			return;
		}
		$decoded = json_decode($json, true);
		if (!is_array($decoded)) {
			error_log('SubmissionValidator: invalid JSON in regions file: ' . $regionsPath);
			$this->regions = [];
			return;
		}
		if (isset($decoded['countries']) && is_array($decoded['countries'])) {
			$this->regions = $decoded['countries'];
		} else {
			$this->regions = $decoded;
		}
	}

	public function getRegions(): array
	{
		return $this->regions;
	}

	public function validate(array $input, array $files): array
	{
		$errors = [];

		$values = [
			'job_title' => trim((string) ($input['job_title'] ?? '')),
			'job_small_script' => trim((string) ($input['job_small_script'] ?? '')),
			'country' => trim((string) ($input['country'] ?? '')),
			'state_province' => trim((string) ($input['state_province'] ?? '')),
			'budget' => trim((string) ($input['budget'] ?? '')),
		];

		if ($values['job_title'] === '') {
			$errors['job_title'] = 'Job title is required.';
		} elseif (mb_strlen($values['job_title']) > 120) {
			$errors['job_title'] = 'Job title must be 120 characters or fewer.';
		}

		if (mb_strlen($values['job_small_script']) > 1000) {
			$errors['job_small_script'] = 'Job small script must be 1000 characters or fewer.';
		}

		if ($values['country'] === '') {
			$errors['country'] = 'Country is required.';
		} elseif (!isset($this->regions[$values['country']])) {
			$errors['country'] = 'Country must be Canada or USA.';
		}

		if ($values['state_province'] === '') {
			$errors['state_province'] = 'State or province is required.';
		} elseif (!isset($errors['country'])) {
			$validRegions = $this->regions[$values['country']] ?? [];
			if (!in_array($values['state_province'], $validRegions, true)) {
				$errors['state_province'] = 'State or province does not match the selected country.';
			}
		}

		if ($values['budget'] === '') {
			$errors['budget'] = 'Budget is required.';
		} elseif (!in_array($values['budget'], $this->allowedBudgets, true)) {
			$errors['budget'] = 'Budget selection is invalid.';
		}

		$attachment = $this->validateAttachment($files['attachment'] ?? null, $errors);

		return [
			'errors' => $errors,
			'values' => $values,
			'attachment' => $attachment,
		];
	}

	private function validateAttachment($file, array &$errors): array
	{
		$result = [
			'attachment_original_name' => null,
			'attachment_stored_name' => null,
			'attachment_extension' => null,
			'attachment_mime' => null,
			'attachment_size' => null,
		];

		if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
			return $result;
		}

		if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
			$errors['attachment'] = 'Failed to upload file.';
			return $result;
		}

		$size = (int) ($file['size'] ?? 0);
		if ($size > self::MAX_ATTACHMENT_SIZE_BYTES) {
			$errors['attachment'] = 'Attachment must be 20MB or smaller.';
			return $result;
		}

		$allowedTypeHint = 'Allowed types: PDF, DOC, DOCX, PPT, PPTX, TXT, RTF, JPG, JPEG, PNG, WEBP.';

		$originalName = (string) ($file['name'] ?? '');
		$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
		if ($extension === '' || !in_array($extension, $this->allowedExtensions, true)) {
			$errors['attachment'] = 'Attachment file type is not allowed. ' . $allowedTypeHint;
			return $result;
		}

		$finfo = new finfo(FILEINFO_MIME_TYPE);
		$mime = (string) $finfo->file((string) ($file['tmp_name'] ?? ''));
		if ($mime !== '' && !in_array($mime, $this->allowedMimeTypes, true)) {
			$errors['attachment'] = 'Attachment mime type is not allowed. ' . $allowedTypeHint;
			return $result;
		}

		$safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '-', pathinfo($originalName, PATHINFO_FILENAME));
		$safeBase = trim((string) $safeBase, '-');
		if ($safeBase === '') {
			$safeBase = 'attachment';
		}

		$storedName = $safeBase . '-' . bin2hex(random_bytes(8)) . '.' . $extension;

		$result['attachment_original_name'] = $originalName;
		$result['attachment_stored_name'] = $storedName;
		$result['attachment_extension'] = $extension;
		$result['attachment_mime'] = $mime;
		$result['attachment_size'] = $size;

		return $result;
	}
}
