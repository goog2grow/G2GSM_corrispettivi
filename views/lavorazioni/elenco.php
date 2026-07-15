<?php
/** @var array $perAnno */
/** @var array $conteggioVersioni */
/** @var array $mesiItaliani */
/** @var string|null $flashSuccess */
use App\Core\View;

$tutteLeRigheperModali = [];
foreach ($perAnno as $dati) {
    foreach ($dati['righe'] as $r) {
        $tutteLeRigheperModali[] = $r;
    }
}
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">Elenco lavorazioni</h1>
  <a href="/lavorazioni/nuova" class="btn btn-primary">+ Nuova lavorazione</a>
</div>

<?php if ($flashSuccess !== null): ?>
<div class="alert alert-success"><?= View::e($flashSuccess) ?></div>
<?php endif; ?>

<?php if (empty($perAnno)): ?>
<div class="alert alert-info">Nessuna lavorazione caricata.</div>
<?php else: ?>

<div class="accordion" id="accordionAnni">
<?php $primo = true; foreach ($perAnno as $anno => $dati): ?>
  <div class="accordion-item">
    <h2 class="accordion-header">
      <button class="accordion-button <?= $primo ? '' : 'collapsed' ?>" type="button"
              data-bs-toggle="collapse" data-bs-target="#anno<?= $anno ?>">
        <?= $anno ?>
        <span class="ms-3 text-muted small">
          Totale (lavorazioni attive): <?= number_format($dati['totaleIncassato'], 2, ',', '.') ?> &euro; incassato
          &nbsp;/&nbsp; <?= number_format($dati['totaleIva'], 2, ',', '.') ?> &euro; IVA
        </span>
      </button>
    </h2>
    <div id="anno<?= $anno ?>" class="accordion-collapse collapse <?= $primo ? 'show' : '' ?>" data-bs-parent="#accordionAnni">
      <div class="accordion-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Brand</th>
                <th>Mese</th>
                <th>Nome lavorazione</th>
                <th class="text-end">N. ordini</th>
                <th class="text-end">N. resi</th>
                <th class="text-end">Totale incassato</th>
                <th class="text-end">Totale IVA</th>
                <th>Stato</th>
                <th>Caricata il</th>
                <th class="text-end">Azioni</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($dati['righe'] as $riga): ?>
              <?php
                $chiave = $riga['brand_id'] . '_' . $riga['mese'] . '_' . $riga['anno'];
                $piuVersioni = ($conteggioVersioni[$chiave] ?? 1) > 1;
              ?>
              <tr class="<?= $piuVersioni ? 'table-warning' : '' ?>">
                <td><?= View::e($riga['brand_nome']) ?></td>
                <td><?= View::e($mesiItaliani[(int) $riga['mese']]) ?></td>
                <td>
                  <a href="/lavorazioni/<?= (int) $riga['id'] ?>"><?= View::e($riga['nome_lavorazione']) ?></a>
                  <?php if ($piuVersioni): ?>
                    <span class="badge text-bg-warning ms-1" title="Per questo periodo esistono piu' versioni">v<?= (int) $riga['numero_versione'] ?></span>
                  <?php endif; ?>
                </td>
                <td class="text-end"><?= (int) $riga['numero_ordini'] ?></td>
                <td class="text-end"><?= (int) $riga['numero_resi'] ?></td>
                <td class="text-end"><?= number_format((float) $riga['totale_incassato'], 2, ',', '.') ?></td>
                <td class="text-end"><?= number_format((float) $riga['totale_iva'], 2, ',', '.') ?></td>
                <td>
                  <?php if ((int) $riga['is_attiva'] === 1): ?>
                    <span class="badge text-bg-success">Attiva</span>
                  <?php else: ?>
                    <span class="badge text-bg-secondary">Storica</span>
                  <?php endif; ?>
                </td>
                <td><?= View::e((new DateTime($riga['created_at']))->format('d/m/Y H:i')) ?></td>
                <td class="text-end text-nowrap">
                  <a href="/lavorazioni/<?= (int) $riga['id'] ?>/excel" class="btn btn-sm btn-outline-success">Excel</a>
                  <button type="button" class="btn btn-sm btn-outline-danger"
                          data-bs-toggle="modal" data-bs-target="#modalElimina<?= (int) $riga['id'] ?>">
                    Elimina
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
<?php $primo = false; endforeach; ?>
</div>
<?php endif; ?>

<?php foreach ($tutteLeRigheperModali as $riga): ?>
<div class="modal fade" id="modalElimina<?= (int) $riga['id'] ?>" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Conferma eliminazione</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        Sei sicuro di voler eliminare la lavorazione
        &laquo;<?= View::e($riga['nome_lavorazione']) ?>&raquo;?
        <br><strong>L'operazione non e' reversibile da interfaccia.</strong>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
        <form action="/lavorazioni/<?= (int) $riga['id'] ?>/elimina" method="post" class="d-inline">
          <button type="submit" class="btn btn-danger">Elimina definitivamente</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
