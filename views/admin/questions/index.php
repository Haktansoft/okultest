<?php use function App\{e, csrfField}; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Sorular</h1>
    <div class="page-sub">Tüm soru havuzun. Kategoriye göre filtreleyebilir veya soru metninde arama yapabilirsin.</div>
  </div>
  <div class="d-flex gap-2">
    <a href="/admin/questions/import" class="btn btn-outline-primary"><i class="bi bi-file-earmark-spreadsheet"></i> XLSX İçe Aktar</a>
    <a href="/admin/questions/new" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Yeni Soru</a>
  </div>
</div>

<form class="card mb-3" method="get"><div class="card-body py-2">
  <div class="d-flex flex-wrap gap-2 align-items-center">
    <label class="form-label m-0 muted tiny">Kategori</label>
    <select name="category_id" class="form-select form-select-sm" style="max-width:240px">
      <option value="0">Tümü</option>
      <?php foreach ($cats as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $selectedCat == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <label class="form-label m-0 muted tiny ms-2">Ara</label>
    <input name="q" value="<?= e($q ?? '') ?>" class="form-control form-control-sm" style="max-width:280px" placeholder="Soru metninde ara…">
    <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i> Filtrele</button>
    <?php if ((!empty($q) || !empty($selectedCat))): ?>
      <a class="btn btn-sm btn-outline-secondary" href="/admin/questions">Temizle</a>
    <?php endif; ?>
    <span class="ms-auto muted tiny"><?= (int)($total ?? count($items)) ?> sonuç</span>
  </div>
</div></form>

<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Soru</th><th>Kategori</th><th class="text-end">Şık</th><th class="text-end">Top. Puan</th><th>Tip</th><th></th></tr></thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="6">
          <div class="empty-state"><div class="icon"><i class="bi bi-question-square"></i></div><?= !empty($q) ? 'Aramanla eşleşen soru yok.' : 'Henüz soru yok.' ?></div>
        </td></tr>
      <?php else: foreach ($items as $i): ?>
        <tr>
          <td style="max-width:520px"><?= e(mb_strimwidth(strip_tags($i['prompt']),0,140,'…','UTF-8')) ?></td>
          <td><span class="badge text-bg-light"><?= e($i['category_name']) ?></span></td>
          <td class="text-end"><?= (int)$i['option_count'] ?></td>
          <td class="text-end"><?= e($i['total_score']) ?></td>
          <td><?= $i['is_physical'] ? '<span class="badge text-bg-warning">Fiziksel</span>' : '<span class="badge text-bg-secondary">Standart</span>' ?></td>
          <td class="text-end">
            <a href="/admin/questions/<?= (int)$i['id'] ?>/edit" class="btn btn-sm btn-outline-secondary">Düzenle</a>
            <form method="post" action="/admin/questions/<?= (int)$i['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Silinsin mi?')">
              <?= csrfField() ?>
              <button class="btn btn-sm btn-outline-danger">Sil</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php if (($totalPages ?? 1) > 1): ?>
  <nav class="mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="muted tiny">Toplam <?= (int)$total ?> soru — sayfa <?= (int)$page ?> / <?= (int)$totalPages ?></div>
    <ul class="pagination pagination-sm m-0">
      <?php
        $win = 2; $start = max(1, $page - $win); $end = min($totalPages, $page + $win);
        $url = function ($p) use ($selectedCat, $q) {
            $qs = ['category_id' => (int)$selectedCat, 'q' => (string)$q, 'page' => (int)$p];
            return '?' . http_build_query($qs);
        };
      ?>
      <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= $url(max(1, $page - 1)) ?>">&laquo;</a></li>
      <?php if ($start > 1): ?>
        <li class="page-item"><a class="page-link" href="<?= $url(1) ?>">1</a></li>
        <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
      <?php endif; ?>
      <?php for ($p = $start; $p <= $end; $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= $url($p) ?>"><?= $p ?></a></li>
      <?php endfor; ?>
      <?php if ($end < $totalPages): ?>
        <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
        <li class="page-item"><a class="page-link" href="<?= $url($totalPages) ?>"><?= (int)$totalPages ?></a></li>
      <?php endif; ?>
      <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>"><a class="page-link" href="<?= $url(min($totalPages, $page + 1)) ?>">&raquo;</a></li>
    </ul>
  </nav>
<?php endif; ?>
