<?php
/** @var array $lavorazione */
/** @var string $brandNome */
/** @var array $storico */
/** @var \App\Services\PivotResult $pivot */
/** @var array $anteprimaRighe */
/** @var int $totaleRigheRaw */
/** @var array $log */
use App\Core\View;
use App\Services\MesiItaliani;

$eliminata = $lavorazione['deleted_at'] !== null;
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
  <div>
    <h1 class="h3 mb-1"><?= View::e($lavorazione['nome_lavorazione']) ?></h1>
    <div class="text-muted">
      Brand: <?= View::e($brandNome) ?> &nbsp;|&nbsp;
      Periodo: <?= View::e(MesiItaliani::nome((int) $lavorazione['mese'])) ?> <?= (int) $lavorazione['anno'] ?> &nbsp;|&nbsp;
      Versione v<?= (int) $lavorazione['numero_versione'] ?>
      <?php if (!$eliminata && (int) $lavorazione['is_attiva'] === 1): ?>
        <span class="badge text-bg-success">Attiva</span>
      <?php elseif (!$eliminata): ?>
        <span class="badge text-bg-secondary">Storica</span>
      <?php else: ?>
        <span class="badge text-bg-danger">Eliminata</span>
      <?php endif; ?>
    </div>
  </div>
  <div class="text-nowrap">
    <a href="/lavorazioni/<?= (int) $lavorazione['id'] ?>/excel" class="btn btn-success">Scarica Excel</a>
    <a href="/lavorazioni/<?= (int) $lavorazione['id'] ?>/csv" class="btn btn-outline-secondary">Scarica CSV originale</a>
    <?php if (!$eliminata): ?>
      <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalElimina">Elimina</button>
    <?php endif; ?>
  </div>
</div>

<?php if ($eliminata): ?>
<div class="alert alert-danger">
  Questa lavorazione e' stata eliminata (soft-delete) il
  <?= View::e((new DateTime($lavorazione['deleted_at']))->format('d/m/Y H:i')) ?>.
  I dati restano salvati a DB per tracciabilita', ma la lavorazione non e' piu' considerata valida.
</div>
<?php endif; ?>

<?php if (!empty($lavorazione['nota'])): ?>
<div class="alert alert-secondary">
  <strong>Nota:</strong> <?= View::e($lavorazione['nota']) ?>
</div>
<?php endif; ?>

<div class="card mb-4">
  <div class="card-body">
    <h2 class="h6">Versioni per questo periodo (<?= View::e(MesiItaliani::nome((int) $lavorazione['mese'])) ?> <?= (int) $lavorazione['anno'] ?>, <?= View::e($brandNome) ?>)</h2>
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead>
          <tr>
            <th>Versione</th>
            <th>Nome</th>
            <th>Stato</th>
            <th>Nota</th>
            <th>Caricata il</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($storico as $v): ?>
          <tr class="<?= (int) $v['id'] === (int) $lavorazione['id'] ? 'table-primary' : '' ?>">
            <td>v<?= (int) $v['numero_versione'] ?></td>
            <td><a href="/lavorazioni/<?= (int) $v['id'] ?>"><?= View::e($v['nome_lavorazione']) ?></a></td>
            <td>
              <?php if ($v['deleted_at'] !== null): ?>
                <span class="badge text-bg-danger">Eliminata</span>
              <?php elseif ((int) $v['is_attiva'] === 1): ?>
                <span class="badge text-bg-success">Attiva</span>
              <?php else: ?>
                <span class="badge text-bg-secondary">Storica</span>
              <?php endif; ?>
            </td>
            <td><?= View::e($v['nota'] ?? '') ?></td>
            <td><?= View::e((new DateTime($v['created_at']))->format('d/m/Y H:i')) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <h2 class="h6 mb-0">Pivot giorno &times; aliquota</h2>
      <a href="/lavorazioni/<?= (int) $lavorazione['id'] ?>" class="btn btn-sm btn-outline-secondary">Ricalcola</a>
    </div>
    <p class="text-muted small">
      La pivot viene sempre ricalcolata dai dati grezzi ad ogni visualizzazione: non e' mai una
      copia salvata, quindi il pulsante "Ricalcola" e' qui soprattutto per un futuro cambio della
      logica di aggregazione.
    </p>
    <?php View::render('lavorazioni/_pivot_table', ['pivot' => $pivot]); ?>
  </div>
</div>

<div class="card mb-4">
  <div class="card-body">
    <h2 class="h6">Anteprima dati grezzi (prime <?= count($anteprimaRighe) ?> righe di <?= $totaleRigheRaw ?>)</h2>
    <div class="table-responsive">
      <table class="table table-sm table-striped">
        <thead>
          <tr>
            <th>paidDate</th>
            <th>orderName</th>
            <th>tax</th>
            <th>totPaid</th>
            <th>totTax</th>
            <th>channel</th>
            <th>reso</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($anteprimaRighe as $row): ?>
          <tr>
            <td><?= View::e($row['paid_date']) ?></td>
            <td><?= View::e($row['order_name']) ?></td>
            <td><?= number_format((float) $row['tax'] * 100, 2, ',', '.') ?>%</td>
            <td><?= number_format((float) $row['tot_paid'], 2, ',', '.') ?></td>
            <td><?= number_format((float) $row['tot_tax'], 2, ',', '.') ?></td>
            <td><?= View::e($row['channel'] ?? '') ?></td>
            <td><?= (int) $row['is_reso'] === 1 ? '<span class="badge text-bg-danger">si</span>' : '' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-body">
    <h2 class="h6">Log operazioni</h2>
    <?php if (empty($log)): ?>
      <p class="text-muted mb-0">Nessuna operazione registrata.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead>
            <tr>
              <th>Data</th>
              <th>Operazione</th>
              <th>Descrizione</th>
              <th class="text-end">N. ordini</th>
              <th class="text-end">N. resi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($log as $voce): ?>
            <tr>
              <td><?= View::e((new DateTime($voce['data_operazione']))->format('d/m/Y H:i')) ?></td>
              <td><span class="badge text-bg-light text-dark border"><?= View::e($voce['tipo_operazione']) ?></span></td>
              <td><?= View::e($voce['descrizione']) ?></td>
              <td class="text-end"><?= $voce['numero_ordini'] !== null ? (int) $voce['numero_ordini'] : '-' ?></td>
              <td class="text-end"><?= $voce['numero_resi'] !== null ? (int) $voce['numero_resi'] : '-' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<a href="/lavorazioni" class="btn btn-outline-secondary">Torna all'elenco</a>

<?php if (!$eliminata): ?>
<div class="modal fade" id="modalElimina" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Conferma eliminazione</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        Sei sicuro di voler eliminare la lavorazione
        &laquo;<?= View::e($lavorazione['nome_lavorazione']) ?>&raquo;?
        <br><strong>L'operazione non e' reversibile da interfaccia.</strong>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        <form action="/lavorazioni/<?= (int) $lavorazione['id'] ?>/elimina" method="post">
          <button type="submit" class="btn btn-danger">Elimina definitivamente</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
