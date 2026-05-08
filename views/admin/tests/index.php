<?php use function App\{e, csrfField}; $isAdmin = ($me['role'] ?? '') === 'admin'; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Testler</h1>
    <div class="page-sub"><?= $isAdmin ? 'Soruları bir araya getirip testler oluştur, öğretmenler atasın.' : 'Tüm testler — içeriği görüntüleyebilir, PDF olarak yazdırabilirsin.' ?></div>
  </div>
  <?php if ($isAdmin): ?>
    <a href="/admin/tests/new" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Yeni Test</a>
  <?php endif; ?>
</div>

<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Başlık</th><th class="text-end">Süre</th><th class="text-end">Soru</th><th class="text-end">Atama</th><th></th></tr></thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="5"><div class="empty-state"><div class="icon"><i class="bi bi-card-list"></i></div>Henüz test yok.</div></td></tr>
      <?php else: foreach ($items as $i): ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= e($i['title']) ?></div>
            <div class="muted tiny"><?= e(mb_strimwidth((string)($i['description'] ?? ''), 0, 100, '…', 'UTF-8')) ?></div>
          </td>
          <td class="text-end"><?= $i['time_limit_minutes'] ? e($i['time_limit_minutes']) . ' dk' : '<span class="muted">limitsiz</span>' ?></td>
          <td class="text-end"><?= (int)$i['qcount'] ?></td>
          <td class="text-end"><?= (int)$i['acount'] ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-primary" href="/admin/tests/<?= (int)$i['id'] ?>/questions" title="<?= $isAdmin ? 'Soruları yönet' : 'İçeriği görüntüle' ?>"><i class="bi bi-list-check"></i></a>
            <a class="btn btn-sm btn-outline-secondary" href="/admin/tests/<?= (int)$i['id'] ?>/pdf" target="_blank" title="PDF"><i class="bi bi-file-earmark-pdf"></i></a>
            <?php if ($isAdmin): ?>
              <a class="btn btn-sm btn-outline-secondary" href="/admin/tests/<?= (int)$i['id'] ?>/edit" title="Düzenle"><i class="bi bi-pencil"></i></a>
              <form method="post" action="/admin/tests/<?= (int)$i['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Silinsin mi?')">
                <?= csrfField() ?>
                <button class="btn btn-sm btn-outline-danger" title="Sil"><i class="bi bi-trash"></i></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
