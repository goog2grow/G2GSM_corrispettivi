<?php
/** @var array $brands */
/** @var array $errors */
/** @var array $old */
use App\Core\View;
?>
<h1 class="h3 mb-4">Gestione brand</h1>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
  <ul class="mb-0">
    <?php foreach ($errors as $error): ?>
      <li><?= View::e($error) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="card mb-4">
  <div class="card-body">
    <h2 class="h6">Aggiungi nuovo brand</h2>
    <form action="/brand" method="post" class="row g-2 align-items-end">
      <div class="col-auto">
        <label class="form-label" for="nome">Nome brand</label>
        <input class="form-control" type="text" id="nome" name="nome" value="<?= View::e($old['nome'] ?? '') ?>" required maxlength="100">
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary">Aggiungi</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <table class="table table-striped align-middle mb-0">
      <thead>
        <tr>
          <th>Nome</th>
          <th>Stato</th>
          <th>Creato il</th>
          <th class="text-end">Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($brands as $brand): ?>
        <tr>
          <td><?= View::e($brand['nome']) ?></td>
          <td>
            <?php if ((int) $brand['attivo'] === 1): ?>
              <span class="badge text-bg-success">Attivo</span>
            <?php else: ?>
              <span class="badge text-bg-secondary">Disattivato</span>
            <?php endif; ?>
          </td>
          <td><?= View::e((new DateTime($brand['created_at']))->format('d/m/Y H:i')) ?></td>
          <td class="text-end">
            <form action="/brand/<?= (int) $brand['id'] ?>/toggle" method="post" class="d-inline">
              <button type="submit" class="btn btn-sm btn-outline-secondary">
                <?= (int) $brand['attivo'] === 1 ? 'Disattiva' : 'Riattiva' ?>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($brands)): ?>
        <tr><td colspan="4" class="text-center text-muted">Nessun brand presente.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
