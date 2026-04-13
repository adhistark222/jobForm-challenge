/**
 * form.js — progressive enhancement layer for the job submission form.
 *
 * The form is fully functional without this script (server renders all fields,
 * validates on POST, and returns errors inline). This file exists purely to improve
 * the experience when JS is available:
 *
 *   - Dependent country/state dropdown: the server renders all states grouped by
 *     country via <optgroup>. JS replaces that with a filtered list for the selected
 *     country, making the picker much shorter and easier to use.
 *
 *   - Live word counter on the script textarea.
 *
 *   - Client-side validation on submit: catches the most common errors before a round
 *     trip, and — critically — blocks oversized file uploads before they reach the
 *     server. PHP silently discards $_POST when post_max_size is exceeded, which would
 *     lose all other field values. JS is the only practical interception point.
 *
 * All validation rules here mirror SubmissionValidator.php. The server always
 * re-validates — JS is UX, not a security boundary.
 */
(() => {
	"use strict";

	// Guard: if the form isn't on this page, nothing below should run.
	const form = document.querySelector(".job-form");
	if (!form) {
		return;
	}

	const jobTitle = document.getElementById("job_title");
	const script = document.getElementById("job_small_script");
	const scriptCount = document.getElementById("job_small_script_count");
	const country = document.getElementById("country");
	const stateProvince = document.getElementById("state_province");
	const attachment = document.getElementById("attachment");

	// Regions data is embedded in the page by PHP as a JSON <script> tag.
	// This avoids an extra HTTP request and keeps JS consistent with what
	// the server used to render the initial <optgroup> list.
	const regionData = parseRegionsData();

	function parseRegionsData() {
		const node = document.getElementById("regions-data");
		if (!node) {
			return {};
		}

		try {
			const parsed = JSON.parse(node.textContent || "{}");
			if (typeof parsed !== "object" || parsed === null) {
				return {};
			}

			// Handle both {"countries": {...}} wrapper shape and flat {"USA": [...]} shape
			// so this stays compatible if the JSON structure ever changes.
			return typeof parsed.countries === "object" && parsed.countries !== null
				? parsed.countries
				: parsed;
		} catch (_error) {
			// Malformed JSON — degrade gracefully by returning an empty object.
			// The server-rendered <optgroup> list is still present in the DOM at this point.
			return {};
		}
	}

	/**
	 * Replaces the server-rendered <optgroup> country list with a flat JS-managed list.
	 * Preserves the previously selected value so error repopulation still works.
	 */
	function populateCountries() {
		if (!country) {
			return;
		}

		const selectedValue = country.value.trim();
		const countryNames = Object.keys(regionData)
			.filter((name) => Array.isArray(regionData[name]))
			.sort((a, b) => a.localeCompare(b));

		country.innerHTML = "";

		const placeholder = document.createElement("option");
		placeholder.value = "";
		placeholder.textContent = "Select country";
		country.appendChild(placeholder);

		countryNames.forEach((name) => {
			const option = document.createElement("option");
			option.value = name;
			option.textContent = name;
			if (name === selectedValue) {
				option.selected = true;
			}
			country.appendChild(option);
		});
	}

	/**
	 * Writes an error message for a field and marks it aria-invalid.
	 * Passing an empty string clears the error and resets aria state.
	 * Both the visual error paragraph and the aria attribute are updated together
	 * so screen readers and sighted users get the same information.
	 */
	function setError(fieldName, message) {
		const input = form.querySelector(`[name="${fieldName}"]`);
		const errorNode = document.getElementById(`${fieldName}_error`);

		if (input) {
			input.setAttribute("aria-invalid", message ? "true" : "false");
		}

		if (errorNode) {
			errorNode.textContent = message;
			errorNode.hidden = message === "";
		}
	}

	function countWords(value) {
		return value.trim() === "" ? 0 : value.trim().split(/\s+/).length;
	}

	function updateWordCount() {
		if (!script || !scriptCount) {
			return;
		}

		const words = countWords(script.value);
		scriptCount.textContent = `${words} word${words === 1 ? "" : "s"}`;
	}

	/**
	 * Rebuilds the state/province dropdown for the given country.
	 * When a country is selected, shows only that country's states.
	 * When no country is selected, disables the dropdown and prompts the user to pick first.
	 * This replaces the server's <optgroup> approach with a filtered flat list.
	 */
	function repopulateRegionsForCountry(selectedCountry) {
		if (!stateProvince) {
			return;
		}
		const normalizedCountry = (selectedCountry || "").trim();
		const options = Array.isArray(regionData[normalizedCountry]) ? regionData[normalizedCountry] : [];

		stateProvince.innerHTML = "";

		const placeholder = document.createElement("option");
		placeholder.value = "";
		placeholder.textContent = options.length > 0 ? "Select state/province" : "Select country first";
		stateProvince.appendChild(placeholder);

		options.forEach((regionCode) => {
			const option = document.createElement("option");
			option.value = regionCode;
			option.textContent = regionCode;
			stateProvince.appendChild(option);
		});

		// Disable the dropdown with no valid options so it's not submitted as an empty value.
		stateProvince.disabled = options.length === 0;
		stateProvince.value = "";
	}

	function validateJobTitle() {
		if (!jobTitle) {
			return true;
		}

		const value = jobTitle.value.trim();
		if (value === "") {
			setError("job_title", "Job title is required.");
			return false;
		}

		if (value.length > 120) {
			setError("job_title", "Job title must be 120 characters or fewer.");
			return false;
		}

		setError("job_title", "");
		return true;
	}

	function validateScript() {
		if (!script) {
			return true;
		}

		if (script.value.length >= 1000) {
			setError("job_small_script", "Character limit of 1000 reached.");
			return false;
		}

		setError("job_small_script", "");
		return true;
	}

	function validateCountry() {
		if (!country) {
			return true;
		}

		if (country.value === "") {
			setError("country", "Country is required.");
			return false;
		}

		setError("country", "");
		return true;
	}

	function validateStateProvince() {
		if (!stateProvince) {
			return true;
		}

		// A disabled dropdown means no country was selected — treat as missing.
		if (stateProvince.disabled || stateProvince.value === "") {
			setError("state_province", "State or province is required.");
			return false;
		}

		setError("state_province", "");
		return true;
	}

	function validateAttachment() {
		if (!attachment) {
			return true;
		}

		const file = attachment.files && attachment.files[0] ? attachment.files[0] : null;
		if (!file) {
			setError("attachment", "");
			return true;
		}

		// This is the critical check: block the request before it reaches the server.
		// If a file over post_max_size is submitted, PHP discards the entire request body
		// (including all other field values). There is no way to recover them server-side.
		const maxBytes = 20 * 1024 * 1024;
		if (file.size > maxBytes) {
			setError("attachment", "Attachment must be 20MB or smaller.");
			return false;
		}

		setError("attachment", "");
		return true;
	}

	function validateBudget() {
		const selected = form.querySelector('input[name="budget"]:checked');
		if (!selected) {
			setError("budget", "Budget is required.");
			return false;
		}

		setError("budget", "");
		return true;
	}

	function validateAll() {
		const results = [
			validateJobTitle(),
			validateScript(),
			validateCountry(),
			validateStateProvince(),
			validateAttachment(),
			validateBudget(),
		];

		return results.every(Boolean);
	}

	/**
	 * Moves focus to the first invalid required field after a failed submit attempt.
	 * Improves keyboard and screen reader accessibility — the user lands directly
	 * on the first problem rather than having to tab through the form to find it.
	 */
	function focusFirstInvalidRequiredField() {
		const requiredFieldOrder = ["job_title", "country", "state_province", "budget"];

		for (const fieldName of requiredFieldOrder) {
			const field = form.querySelector(`[name="${fieldName}"]`);
			if (!field || field.getAttribute("aria-invalid") !== "true") {
				continue;
			}

			if (field instanceof HTMLElement && !field.hasAttribute("disabled")) {
				field.focus();
			}
			break;
		}
	}

	// --- Event wiring ---

	// populateCountries / repopulateRegionsForCountry run immediately on load so
	// the JS-managed dropdowns replace the server-rendered <optgroup> lists before
	// the user sees them. Preserves any pre-selected value from error repopulation.
	if (script) {
		script.addEventListener("input", () => {
			updateWordCount();
			validateScript();
		});
		updateWordCount();
	}

	if (jobTitle) {
		jobTitle.addEventListener("input", validateJobTitle);
		jobTitle.addEventListener("blur", validateJobTitle);
	}

	if (country) {
		populateCountries();
		country.addEventListener("change", () => {
			repopulateRegionsForCountry(country.value);
			validateCountry();
			validateStateProvince();
		});
		repopulateRegionsForCountry(country.value);
	}

	if (stateProvince) {
		stateProvince.addEventListener("change", validateStateProvince);
	}

	if (attachment) {
		attachment.addEventListener("change", validateAttachment);
	}

	form.querySelectorAll('input[name="budget"]').forEach((radio) => {
		radio.addEventListener("change", validateBudget);
	});

	form.addEventListener("submit", (event) => {
		// Prevent submission if any field is invalid. focusFirstInvalidRequiredField
		// guides the user to the first problem after all errors are displayed.
		if (!validateAll()) {
			event.preventDefault();
			focusFirstInvalidRequiredField();
		}
	});

})();
