<?php use App\Web\Core\Html; ?>

<div class="panel-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h4 mb-1"><?= Html::escape($tables[$table] ?? $table) ?></h2>
            <div class="text-secondary">Primary Key: <?= Html::escape($primaryKey) ?></div>
        </div>
        <a class="btn btn-outline-secondary" href="/stage-browser?table=<?= Html::escape($table) ?>">Zurueck zur Tabelle</a>
    </div>
    <?php if ($row === null): ?>
        <div class="text-secondary">Datensatz nicht gefunden.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped mb-0">
                <tbody>
                <?php foreach ($row as $column => $value): ?>
                    <tr>
                        <th class="w-25"><?= Html::escape($column) ?></th>
                        <td><pre class="mb-0 text-wrap"><?= Html::escape($value) ?></pre></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
