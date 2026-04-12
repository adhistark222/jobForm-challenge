(() => {
	"use strict";

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

			return typeof parsed.countries === "object" && parsed.countries !== null
				? parsed.countries
				: parsed;
		} catch (_error) {
			return {};
		}
	}

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

	function clearAllValidationUI() {
		const errorNodes = form.querySelectorAll(".form-error");
		errorNodes.forEach((node) => {
			node.textContent = "";
			node.hidden = true;
		});

		const invalidNodes = form.querySelectorAll("[aria-invalid]");
		invalidNodes.forEach((node) => {
			node.setAttribute("aria-invalid", "false");
		});
	}

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

	clearAllValidationUI();

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
		if (!validateAll()) {
			event.preventDefault();
			focusFirstInvalidRequiredField();
		}
	});

	form.addEventListener("reset", () => {
		window.setTimeout(() => {
			clearAllValidationUI();
			updateWordCount();
			if (country) {
				repopulateRegionsForCountry(country.value);
			}
		}, 0);
	});
})();
