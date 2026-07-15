<?php
/** @var \App\Services\CsvParseResult $result */
/** @var \App\Services\PivotResult $pivot */
/** @var array $pending */
/** @var array $extraWarnings */
use App\Core\View;
use App\Services\MesiItaliani;

$warnings = array_merge($extraWarnings, $result->warnings);
$anteprimaRighe = array_slice($result->rows, 0, 15);
?>
<h1 class="h3 mb-4">Verifica caricamento</h1>

<div class="alert alert-secondary">
  <strong>Periodo:</strong> <?= View::e(MesiItaliani::nome($pending['mese'])) ?> <?= (int) $pending['anno'] ?>
  &nbsp;|&nbsp; <strong>Brand:</strong> <?= View::e($pending['brand_nome']) ?>
  &nbsp;|&nbsp; <strong>Negozio:</strong> <?= View::e($pending['negozio']) ?>
  &nbsp;|&nbsp; <strong>File:</strong> <?= View::e($pending['original_filename']) ?>
  <?php if ($pending['nota'] !== ''): ?>
    <br><strong>Nota:</strong> <?= View::e($pending['nota']) ?>
  <?php endif; ?>
</div>

<?php if (!empty($warnings)): ?>
<div class="alert alert-warning">
  <strong>Avvisi (non bloccanti):</strong>
  <ul class="mb-0">
    <?php foreach ($warnings as $w): ?>
      <li><?= View::e($w) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php if (!empty($result->rowErrors)): ?>
<div class="alert alert-danger">
  <strong><?= count($result->rowErrors) ?> riga/e scartate per errori di formato:</strong>
  <ul class="mb-0" style="max-height: 200px; overflow-y: auto;">
    <?php foreach ($result->rowErrors as $err): ?>
      <li><?= View::e($err) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="row mb-4">
  <div class="col-md-3">
    <div class="card text-center">
      <div class="card-body">
        <div class="text-muted small">Righe valide</div>
        <div class="h4 mb-0"><?= $result->totaleRighe ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center">
      <div class="card-body">
        <div class="text-muted small">Resi rilevati</div>
        <div class="h4 mb-0"><?= $result->totaleResi ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center">
      <div class="card-body">
        <div class="text-muted small">Totale incassato (senza split)</div>
        <div class="h4 mb-0"><?= number_format($pivot->totaleSenzaSplitIncassato, 2, ',', '.') ?> &euro;</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card text-center">
      <div class="card-body">
        <div class="text-muted small">Totale IVA (senza split)</div>
        <div class="h4 mb-0"><?= number_format($pivot->totaleSenzaSplitIva, 2, ',', '.') ?> &euro;</div>
      </div>
    </div>
  </div>
</div>
<p class="text-muted small mb-4">
  "Senza split": somma di tutti i totPaid/totTax del file, indipendentemente da giorno e aliquota
  (riepilogo sintetico, vedi anche i totali della pivot sotto).
</p>

<div class="card mb-4">
  <div class="card-body">
    <h2 class="h6">Pivot giorno &times; aliquota - <?= View::e(MesiItaliani::nome($pivot->mese)) ?> <?= $pivot->anno ?></h2>
    <?php View::render('lavorazioni/_pivot_table', ['pivot' => $pivot]); ?>
  </div>
</div>

<div class="card mb-4">
  <div class="card-body">
    <h2 class="h6">Aliquote IVA rilevate</h2>
    <p>
      <?php foreach ($result->aliquote as $aliquota): ?>
        <span class="badge text-bg-info me-1"><?= number_format($aliquota * 100, 2, ',', '.') ?>%</span>
      <?php endforeach; ?>
    </p>
    <h2 class="h6">Range date pagamento (paidDate)</h2>
    <p class="mb-0"><?= View::e($result->paidDateMin) ?> &rarr; <?= View::e($result->paidDateMax) ?></p>
  </div>
</div>

<div class="card mb-4">
  <div class="card-body">
    <h2 class="h6">Anteprima dati grezzi (prime <?= count($anteprimaRighe) ?> righe di <?= $result->totaleRighe ?>)</h2>
    <div class="table-responsive">
      <table class="table table-sm table-striped">
        <thead>
          <tr>
            <th>paidDate</th>
            <th>orderName</th>
            <th>tax</th>
            <th>totPaid</th>
            <th>totTax</th>
            <th>brand</th>
            <th>channel</th>
            <th>reso</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($anteprimaRighe as $row): ?>
          <tr>
            <td><?= View::e($row['paidDate']) ?></td>
            <td><?= View::e($row['orderName']) ?></td>
            <td><?= number_format($row['tax'] * 100, 2, ',', '.') ?>%</td>
            <td><?= number_format($row['totPaid'], 2, ',', '.') ?></td>
            <td><?= number_format($row['totTax'], 2, ',', '.') ?></td>
            <td><?= View::e($row['brand']) ?></td>
            <td><?= View::e($row['channel']) ?></td>
            <td><?= $row['is_reso'] ? '<span class="badge text-bg-danger">si</span>' : '' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="alert alert-info">
  Il salvataggio definitivo della lavorazione (con la logica di naming/versioning) verra'
  collegato nel prossimo step di sviluppo.
</div>

<a href="/lavorazioni/nuova" class="btn btn-outline-secondary">Torna indietro</a>
