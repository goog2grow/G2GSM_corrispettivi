<?php
/** @var string $title */
/** @var string $content */
use App\Core\View;
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= View::e($title ?? 'Corrispettivi') ?> - Corrispettivi Shopify</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/assets/css/app.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container">
    <a class="navbar-brand" href="/lavorazioni">Corrispettivi Shopify</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMain">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="/lavorazioni/nuova">Nuova lavorazione</a></li>
        <li class="nav-item"><a class="nav-link" href="/lavorazioni">Elenco lavorazioni</a></li>
        <li class="nav-item"><a class="nav-link" href="/brand">Gestione brand</a></li>
      </ul>
    </div>
  </div>
</nav>
<main class="container pb-5">
<?= $content ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
