<?php
/** @var array $esito */
use App\Core\View;
?>
<h1 class="h3 mb-4">Lavorazione salvata</h1>

<div class="alert alert-success">
  <h2 class="h5">&laquo;<?= View::e($esito['nome_lavorazione']) ?>&raquo;</h2>
  <p class="mb-1">Versione: <strong>v<?= (int) $esito['numero_versione'] ?></strong> (attiva)</p>
  <?php if ($esito['precedente_nome'] !== null): ?>
    <p class="mb-0">
      Questa lavorazione sostituisce come attiva la precedente
      &laquo;<?= View::e($esito['precedente_nome']) ?>&raquo;, che resta salvata a DB come storica
      (non cancellata, non piu' attiva).
    </p>
  <?php else: ?>
    <p class="mb-0">Prima lavorazione caricata per questo periodo/brand (versione 1).</p>
  <?php endif; ?>
</div>

<a href="/lavorazioni/<?= (int) $esito['lavorazione_id'] ?>" class="btn btn-outline-primary">Vai al dettaglio</a>
<a href="/lavorazioni/<?= (int) $esito['lavorazione_id'] ?>/excel" class="btn btn-success">Scarica Excel</a>
<a href="/lavorazioni/nuova" class="btn btn-primary">Carica un'altra lavorazione</a>
<a href="/lavorazioni" class="btn btn-outline-secondary">Torna all'elenco</a>
