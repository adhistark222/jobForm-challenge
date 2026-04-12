<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Submission Successful</title>
  <link rel="stylesheet" href="/assets/css/form.css">
</head>
<body>
  <main class="page">
    <section class="hero">
      <div class="hero__content">
        <h1 class="hero__title">Form submitted successfully</h1>
      </div>
    </section>

    <section class="panel">
      <div class="panel__inner">
        <p>Form submitted successfully.</p>
        <p>Click anywhere to go back to the form.</p>
        <p>
          <a class="button button--primary" href="/">Back to form</a>
        </p>
      </div>
    </section>
  </main>
  <script>
    document.addEventListener('click', function (event) {
      const target = event.target;
      if (target instanceof HTMLAnchorElement) {
        return;
      }

      window.location.href = '/';
    });
  </script>
</body>
</html>
