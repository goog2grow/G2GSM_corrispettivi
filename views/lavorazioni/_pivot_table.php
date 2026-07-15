<?php
/** @var \App\Services\PivotResult $pivot */
?>
<div class="table-responsive">
  <table class="table table-bordered table-sm table-pivot">
    <thead>
      <tr>
        <th rowspan="2" class="align-middle">Giorno</th>
        <?php foreach ($pivot->aliquote as $aliquota): ?>
          <th colspan="2" class="text-center"><?= number_format($aliquota * 100, 2, ',', '.') ?>%</th>
        <?php endforeach; ?>
        <th colspan="2" class="text-center table-secondary">Totale giorno</th>
      </tr>
      <tr>
        <?php foreach ($pivot->aliquote as $aliquota): ?>
          <th class="text-end">Incassato</th>
          <th class="text-end">IVA</th>
        <?php endforeach; ?>
        <th class="text-end table-secondary">Incassato</th>
        <th class="text-end table-secondary">IVA</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pivot->giorni as $giorno => $celle): ?>
      <tr>
        <td><?= $giorno ?></td>
        <?php foreach ($pivot->aliquote as $aliquota): ?>
          <?php $cella = $celle[(string) $aliquota]; ?>
          <td class="text-end"><?= number_format($cella['incassato'], 2, ',', '.') ?></td>
          <td class="text-end"><?= number_format($cella['iva'], 2, ',', '.') ?></td>
        <?php endforeach; ?>
        <?php $totRiga = $pivot->totaliPerGiorno[$giorno]; ?>
        <td class="text-end table-secondary"><?= number_format($totRiga['incassato'], 2, ',', '.') ?></td>
        <td class="text-end table-secondary"><?= number_format($totRiga['iva'], 2, ',', '.') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr class="table-dark">
        <th>Totale mese</th>
        <?php foreach ($pivot->aliquote as $aliquota): ?>
          <?php $tot = $pivot->totaliPerAliquota[(string) $aliquota]; ?>
          <th class="text-end"><?= number_format($tot['incassato'], 2, ',', '.') ?></th>
          <th class="text-end"><?= number_format($tot['iva'], 2, ',', '.') ?></th>
        <?php endforeach; ?>
        <th class="text-end"><?= number_format($pivot->totaleGeneraleIncassato, 2, ',', '.') ?></th>
        <th class="text-end"><?= number_format($pivot->totaleGeneraleIva, 2, ',', '.') ?></th>
      </tr>
    </tfoot>
  </table>
</div>

<?php if ($pivot->righeFuoriPeriodo > 0): ?>
<div class="alert alert-warning">
  <?= $pivot->righeFuoriPeriodo ?> riga/e con data di pagamento fuori dal periodo selezionato
  sono escluse dalla pivot sopra, ma restano incluse nel totale "senza split".
</div>
<?php endif; ?>
