<?php

declare(strict_types=1);

/**
 * SubmissionValidator
 *
 * The single source of truth for all validation rules in this app.
 * Client-side JS mirrors some of these rules for UX, but the server
 * always re-validates — JS can be disabled or bypassed.
 *
 * Keeping validation in its own class (rather than inside the controller)
 * means the rules can be tested independently without any HTTP context.
 */
class SubmissionValidator
{
	// Hard limit matches php.ini upload_max_filesize and the hint shown in the form.
	private const MAX_ATTACHMENT_SIZE_BYTES = 20 * 1024 * 1024;

	// Budget values are the internal identifiers stored in the DB, not display labels.
	// Validated against this allowlist to prevent arbitrary values being inserted.
	private array $allowedBudgets = ['5_99', '100_249', '250_499'];

	// Two-layer file validation: extension check first (fast, no disk I/O),
	// then MIME type check via finfo (reads the file header — harder to spoof).
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

	/**
	 * Loads regions from disk at construction time.
	 * Failures are logged explicitly — silent fallback to an empty array would allow
	 * any country/state combination to pass validation, which would be a data integrity bug.
	 */
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
		// Support both wrapped {"countries": {...}} and flat {"USA": [...]} shapes.
		if (isset($decoded['countries']) && is_array($decoded['countries'])) {
			$this->regions = $decoded['countries'];
		} else {
			$this->regions = $decoded;
		}
	}

	// Exposed so the controller can pass the same data to the view for rendering
	// the country/state selects without loading the file a second time.
	public function getRegions(): array
	{
		return $this->regions;
	}

	/**
	 * Validates all submitted fields and the optional file upload.
	 *
	 * Returns a map of:
	 *   'errors'     => field-keyed error messages (empty means valid)
	 *   'values'     => sanitised scalar field values for repopulation and DB insert
	 *   'attachment' => attachment metadata (nulls if no file uploaded)
	 */
	public function validate(array $input, array $files): array
	{
		$errors = [];

		// Trim all scalar fields upfront so individual checks don't repeat it.
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

		// Script is optional — only validate length if something was entered.
		if (mb_strlen($values['job_small_script']) > 1000) {
			$errors['job_small_script'] = 'Job small script must be 1000 characters or fewer.';
		}

		if ($values['country'] === '') {
			$errors['country'] = 'Country is required.';
		} elseif (!isset($this->regions[$values['country']])) {
			$errors['country'] = 'Country must be Canada or USA.';
		}

		// State is validated against the server-side regions data, not the client-submitted
		// list — this prevents a user from submitting a state that belongs to a different country.
		if ($values['state_province'] === '') {
			$errors['state_province'] = 'State or province is required.';
		} elseif (!isset($errors['country'])) {
			// Only cross-validate state if country was itself valid — avoids misleading errors.
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

		// File validation is extracted to its own method — it has several distinct failure
		// modes (missing, wrong type, too large) and its own return shape.
		$attachment = $this->validateAttachment($files['attachment'] ?? null, $errors);

		return [
			'errors' => $errors,
			'values' => $values,
			'attachment' => $attachment,
		];
	}

	/**
	 * Validates the uploaded file and returns attachment metadata for the DB insert.
	 * Returns null values for all metadata fields if no file was uploaded (optional field).
	 * Errors are written into the $errors array by reference so they appear alongside
	 * other field errors in the same response.
	 */
	private function validateAttachment($file, array &$errors): array
	{
		$result = [
			'attachment_original_name' => null,
			'attachment_stored_name' => null,
			'attachment_extension' => null,
			'attachment_mime' => null,
			'attachment_size' => null,
		];

		// UPLOAD_ERR_NO_FILE means the field was left empty — valid for an optional field.
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

		// Extension check: quick filter before spending I/O on MIME detection.
		$originalName = (string) ($file['name'] ?? '');
		$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
		if ($extension === '' || !in_array($extension, $this->allowedExtensions, true)) {
			$errors['attachment'] = 'Attachment file type is not allowed. ' . $allowedTypeHint;
			return $result;
		}

		// MIME check via finfo reads the actual file header — a renamed .exe with a .pdf
		// extension would still fail here. This is deeper than relying on the browser-provided
		// MIME type, which can be trivially spoofed.
		$finfo = new finfo(FILEINFO_MIME_TYPE);
		$mime = (string) $finfo->file((string) ($file['tmp_name'] ?? ''));
		if ($mime !== '' && !in_array($mime, $this->allowedMimeTypes, true)) {
			$errors['attachment'] = 'Attachment mime type is not allowed. ' . $allowedTypeHint;
			return $result;
		}

		// Sanitise the original filename for storage — strip everything except alphanumeric,
		// hyphens, and underscores. Append a random hex suffix to prevent filename collisions
		// and make stored filenames unpredictable (prevents direct enumeration of uploads).
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
