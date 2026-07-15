<?php
/** @var array $brands */
/** @var array $mesi */
/** @var array $anni */
/** @var array $errors */
/** @var array $old */
use App\Core\View;
?>
<h1 class="h3 mb-4">Nuova lavorazione</h1>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
  <ul class="mb-0">
    <?php foreach ($errors as $error): ?>
      <li style="white-space: pre-line;"><?= View::e($error) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-body">
    <form action="/lavorazioni/anteprima" method="post" enctype="multipart/form-data">
      <div class="mb-3">
        <label class="form-label" for="csv">File CSV corrispettivi</label>
        <input class="form-control" type="file" id="csv" name="csv" accept=".csv,text/csv" required>
      </div>

      <div class="row">
        <div class="col-md-3 mb-3">
          <label class="form-label" for="mese">Mese</label>
          <select class="form-select" id="mese" name="mese" required>
            <option value="">-- seleziona --</option>
            <?php foreach ($mesi as $numero => $nome): ?>
              <option value="<?= $numero ?>" <?= (int) ($old['mese'] ?? 0) === $numero ? 'selected' : '' ?>><?= View::e($nome) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label" for="anno">Anno</label>
          <select class="form-select" id="anno" name="anno" required>
            <option value="">-- seleziona --</option>
            <?php foreach ($anni as $anno): ?>
              <option value="<?= $anno ?>" <?= (int) ($old['anno'] ?? 0) === $anno ? 'selected' : '' ?>><?= $anno ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label" for="negozio">Negozio</label>
          <select class="form-select" id="negozio" name="negozio">
            <option value="Shopify" selected>Shopify</option>
          </select>
        </div>
        <div class="col-md-3 mb-3">
          <label class="form-label" for="brand_id">Brand</label>
          <select class="form-select" id="brand_id" name="brand_id" required>
            <option value="">-- seleziona --</option>
            <?php foreach ($brands as $brand): ?>
              <option value="<?= (int) $brand['id'] ?>" <?= (int) ($old['brandId'] ?? 0) === (int) $brand['id'] ? 'selected' : '' ?>><?= View::e($brand['nome']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">
            Brand non presente? <a href="/brand">+ aggiungi nuovo brand</a>
          </div>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label" for="nota">Nota (opzionale)</label>
        <textarea class="form-control" id="nota" name="nota" rows="2"><?= View::e($old['nota'] ?? '') ?></textarea>
      </div>

      <button type="submit" class="btn btn-primary">Carica e verifica</button>
    </form>
  </div>
</div>
