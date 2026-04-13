<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Job Form Challenge V2</title>
  <link rel="stylesheet" href="/assets/css/form.css">
</head>
<body>
  <main class="page">
    <section class="hero">
      <div class="hero__content">
        <h1 class="hero__title">Let us know your project requirements</h1>
      </div>
    </section>

    <section class="panel">
      <div class="panel__inner">
        <form
          class="job-form"
          method="post"
          action=""
          enctype="multipart/form-data"
          novalidate
        >
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
          <h2 class="sr-only">Job submission form</h2>

          <div class="form-grid">
            <!-- Job title -->
            <div class="form-group span-all">
              <label class="form-label" for="job_title">
                What's your project's name?
              </label>
              <p id="job_title_hint" class="form-hint">Provide a short, descriptive title to attract talent.</p>
              <input
                class="form-control"
                id="job_title"
                name="job_title"
                type="text"
                required
                placeholder="For example, '30 Second Radio Spot' or 'Corporate Training Video'"
                maxlength="120"
                aria-describedby="job_title_hint job_title_error"
                aria-invalid="<?php echo isset($errors['job_title']) ? 'true' : 'false'; ?>"
                value="<?php echo htmlspecialchars($jobTitleValue, ENT_QUOTES, 'UTF-8'); ?>"
              />
              <p id="job_title_error" class="form-error" aria-live="polite"<?php echo isset($errors['job_title']) ? '' : ' hidden'; ?>><?php echo htmlspecialchars((string) ($errors['job_title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <!-- Script -->
            <div class="form-group span-all">
              <label class="form-label" for="job_small_script">
                Small Script
                <span class="optional-text">(Optional)</span>
              </label>
              <p class="form-hint">Include a small piece of a script you would like talent to read.</p>
              <textarea
                class="form-control form-control--textarea"
                id="job_small_script"
                name="job_small_script"
                placeholder="Type or paste a sample script here."
                rows="6"
                maxlength="1000"
                aria-describedby="job_small_script_count job_small_script_error"
                aria-invalid="<?php echo isset($errors['job_small_script']) ? 'true' : 'false'; ?>"
              ><?php echo htmlspecialchars($scriptValue, ENT_QUOTES, 'UTF-8'); ?></textarea>
              <div class="form-meta-row">
                <p id="job_small_script_error" class="form-error" aria-live="polite"<?php echo isset($errors['job_small_script']) ? '' : ' hidden'; ?>><?php echo htmlspecialchars((string) ($errors['job_small_script'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                <p id="job_small_script_count" class="form-counter" aria-live="polite">0 words</p>
              </div>
            </div>

            <!-- Country -->
            <div class="form-group">
              <label class="form-label" for="country">
                Country
              </label>
              <select
                class="form-control form-select"
                id="country"
                name="country"
                required
                aria-describedby="country_error"
                aria-invalid="<?php echo isset($errors['country']) ? 'true' : 'false'; ?>"
              >
                <option value="">Select country</option>
                <?php foreach ($countryNames as $countryName): ?>
                  <option value="<?php echo htmlspecialchars($countryName, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $selectedCountry === $countryName ? ' selected' : ''; ?>>
                    <?php echo htmlspecialchars($countryName, ENT_QUOTES, 'UTF-8'); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <p id="country_error" class="form-error" aria-live="polite"<?php echo isset($errors['country']) ? '' : ' hidden'; ?>><?php echo htmlspecialchars((string) ($errors['country'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <!-- Province / State -->
            <div class="form-group">
              <label class="form-label" for="state_province">
                State / Province
              </label>
              <select
                class="form-control form-select"
                id="state_province"
                name="state_province"
                required
                aria-describedby="state_province_error"
                aria-invalid="<?php echo isset($errors['state_province']) ? 'true' : 'false'; ?>"
              >
                <option value="">Select state/province</option>
                <?php foreach ($stateOptions as $groupLabel => $groupItems): ?>
                  <optgroup label="<?php echo htmlspecialchars((string) $groupLabel, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php foreach ($groupItems as $stateOption): ?>
                      <option value="<?php echo htmlspecialchars((string) $stateOption, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $selectedStateProvince === (string) $stateOption ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars((string) $stateOption, ENT_QUOTES, 'UTF-8'); ?>
                      </option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
              <p id="state_province_error" class="form-error" aria-live="polite"<?php echo isset($errors['state_province']) ? '' : ' hidden'; ?>><?php echo htmlspecialchars((string) ($errors['state_province'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <!-- Attachment -->
            <div class="form-group span-all">
              <label class="form-label" for="attachment">
                Please upload your reference file here
                <span class="optional-text">(Optional)</span>
              </label>
              <p class="form-hint">
                (Max. 20 mb)
              </p>
              <input
                class="form-control form-control--file"
                id="attachment"
                name="attachment"
                type="file"
                accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.rtf,.jpg,.jpeg,.png,.webp"
                aria-describedby="attachment_hint attachment_error"
                aria-invalid="<?php echo isset($errors['attachment']) ? 'true' : 'false'; ?>"
              />
              <p id="attachment_error" class="form-error" aria-live="polite"<?php echo isset($errors['attachment']) ? '' : ' hidden'; ?>><?php echo htmlspecialchars((string) ($errors['attachment'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <!-- Budget -->
            <fieldset class="form-group form-group--fieldset span-all" aria-describedby="budget_error">
              <legend class="form-label">
                What's your budget?
              </legend>
              <p class="form-hint">Minimum project cost is $5 USD</p>

              <div class="budget-options">
                <label class="choice-card" for="budget_1">
                  <input
                    id="budget_1"
                    name="budget"
                    type="radio"
                    value="5_99"
                    required
                    aria-invalid="<?php echo isset($errors['budget']) ? 'true' : 'false'; ?>"
                    <?php echo $selectedBudget === '5_99' ? 'checked' : ''; ?>
                  />
                  <span>$5 - $99</span>
                </label>

                <label class="choice-card" for="budget_2">
                  <input
                    id="budget_2"
                    name="budget"
                    type="radio"
                    value="100_249"
                    required
                    aria-invalid="<?php echo isset($errors['budget']) ? 'true' : 'false'; ?>"
                    <?php echo $selectedBudget === '100_249' ? 'checked' : ''; ?>
                  />
                  <span>$100 - $249</span>
                </label>

                <label class="choice-card" for="budget_3">
                  <input
                    id="budget_3"
                    name="budget"
                    type="radio"
                    value="250_499"
                    required
                    aria-invalid="<?php echo isset($errors['budget']) ? 'true' : 'false'; ?>"
                    <?php echo $selectedBudget === '250_499' ? 'checked' : ''; ?>
                  />
                  <span>$250 - $499</span>
                </label>
              </div>

              <p id="budget_error" class="form-error" aria-live="polite"<?php echo isset($errors['budget']) ? '' : ' hidden'; ?>><?php echo htmlspecialchars((string) ($errors['budget'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
            </fieldset>

            <!-- Actions -->
            <div class="form-actions span-all">
              <a class="button button--secondary" href="/">Reset</a>
              <button class="button button--primary" type="submit">Submit</button>
            </div>
          </div>
        </form>
      </div>
    </section>
  </main>
  <script id="regions-data" type="application/json"><?php echo json_encode($regions ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
  <script src="/assets/js/form.js" defer></script>
</body>
</html>