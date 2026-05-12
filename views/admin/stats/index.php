<?php use function App\e; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">İstatistikler</h1>
    <div class="page-sub">Kuruma, kampüse ve tarih aralığına göre öğrenci test uygulama özetleri.</div>
  </div>
</div>

<form id="stats-form" method="get" action="/admin/stats" class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-3">
        <label class="form-label small mb-1">Kurum</label>
        <select name="institution_id" id="f_inst" class="form-select form-select-sm">
          <option value="">Tüm kurumlar</option>
          <?php foreach ($institutions as $i): ?>
            <option value="<?= (int)$i['id'] ?>" <?= (int)$filters['institution_id'] === (int)$i['id'] ? 'selected' : '' ?>>
              <?= e($i['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label small mb-1">Kampüs</label>
        <select name="campus_id" id="f_camp" class="form-select form-select-sm">
          <option value="">Tüm kampüsler</option>
          <?php foreach ($campuses as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (int)$filters['campus_id'] === (int)$c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?><?= isset($c['inst_name']) ? ' — ' . e($c['inst_name']) : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small mb-1">Durum</label>
        <select name="status" class="form-select form-select-sm">
          <option value=""        <?= $filters['status'] === ''       ? 'selected' : '' ?>>Tümü</option>
          <option value="done"    <?= $filters['status'] === 'done'   ? 'selected' : '' ?>>Uygulayan</option>
          <option value="undone"  <?= $filters['status'] === 'undone' ? 'selected' : '' ?>>Uygulamayan</option>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small mb-1">Başlangıç</label>
        <input type="date" name="from" value="<?= e($filters['from']) ?>" class="form-control form-control-sm">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small mb-1">Bitiş</label>
        <input type="date" name="to" value="<?= e($filters['to']) ?>" class="form-control form-control-sm">
      </div>
      <div class="col-6 col-md-12 d-flex gap-2 mt-2">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Filtrele</button>
        <a href="/admin/stats" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i> Temizle</a>
      </div>
    </div>
  </div>
</form>

<div class="row g-3 mb-3">
  <div class="col-12 col-md-4">
    <div class="stat-tile">
      <div class="ic"><i class="bi bi-people"></i></div>
      <div>
        <div class="num"><?= (int)$totals['students'] ?></div>
        <div class="lbl">Tanımlı Öğrenci</div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-4">
    <div class="stat-tile">
      <div class="ic"><i class="bi bi-check2-circle"></i></div>
      <div>
        <div class="num"><?= (int)$totals['done'] ?></div>
        <div class="lbl">Testi Uygulayan</div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-4">
    <div class="stat-tile">
      <div class="ic"><i class="bi bi-hourglass-split"></i></div>
      <div>
        <div class="num"><?= (int)$totals['undone'] ?></div>
        <div class="lbl">Uygulamayan</div>
      </div>
    </div>
  </div>
</div>

<h2 class="page-title" style="font-size:16px;margin-bottom:8px;">Kurum / Kampüs Kırılımı</h2>
<div class="table-wrap mb-4">
  <table class="table align-middle mb-0">
    <thead>
      <tr>
        <th>Kurum</th>
        <th>Kampüs</th>
        <th class="text-end">Öğrenci</th>
        <th class="text-end">Uygulayan</th>
        <th class="text-end">Uygulamayan</th>
        <th class="text-end" style="width:140px;">Tamamlanma</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="6"><div class="empty-state"><div class="icon"><i class="bi bi-bar-chart"></i></div>Filtrelere uyan kayıt yok.</div></td></tr>
      <?php else: foreach ($rows as $r):
        $total = (int)$r['total_students'];
        $done  = (int)$r['done_students'];
        $undone= (int)$r['undone_students'];
        $pct   = $total > 0 ? (int)round(($done / $total) * 100) : 0;
      ?>
        <tr>
          <td class="fw-semibold"><?= e($r['inst_name']) ?></td>
          <td><?= e($r['camp_name']) ?></td>
          <td class="text-end"><?= $total ?></td>
          <td class="text-end text-success fw-semibold"><?= $done ?></td>
          <td class="text-end text-danger fw-semibold"><?= $undone ?></td>
          <td class="text-end">
            <div class="d-flex align-items-center justify-content-end gap-2">
              <div class="progress" style="width:80px;height:6px;">
                <div class="progress-bar bg-success" role="progressbar" style="width:<?= $pct ?>%"></div>
              </div>
              <span class="muted tiny"><?= $pct ?>%</span>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<h2 class="page-title" style="font-size:16px;margin-bottom:8px;">
  Öğrenci Listesi
  <?php if ($filters['status'] === 'done'): ?><span class="badge bg-success-subtle text-success">Uygulayan</span>
  <?php elseif ($filters['status'] === 'undone'): ?><span class="badge bg-danger-subtle text-danger">Uygulamayan</span>
  <?php endif; ?>
  <span class="muted tiny">(<?= count($students) ?>)</span>
</h2>
<div class="table-wrap">
  <table class="table align-middle mb-0">
    <thead>
      <tr>
        <th>Ad Soyad</th>
        <th>Sınıf / Şube</th>
        <th>Kurum</th>
        <th>Kampüs</th>
        <th>Son Uygulama</th>
        <th class="text-end" style="width:120px;">Durum</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$students): ?>
        <tr><td colspan="6"><div class="empty-state"><div class="icon"><i class="bi bi-person"></i></div>Eşleşen öğrenci yok.</div></td></tr>
      <?php else: foreach ($students as $s):
        $applied = !empty($s['last_started_at']);
        $when = $s['last_finished_at'] ?: $s['last_started_at'];
      ?>
        <tr>
          <td class="fw-semibold"><?= e($s['full_name']) ?></td>
          <td><?= e(trim((string)($s['grade_level'] ?? '') . ' ' . (string)($s['section'] ?? ''))) ?: '—' ?></td>
          <td><?= e($s['inst_name']) ?></td>
          <td><?= e($s['camp_name']) ?></td>
          <td><?= $when ? e(date('d.m.Y H:i', strtotime((string)$when))) : '<span class="muted">—</span>' ?></td>
          <td class="text-end">
            <?php if ($applied): ?>
              <span class="badge bg-success-subtle text-success">Uyguladı</span>
            <?php else: ?>
              <span class="badge bg-danger-subtle text-danger">Uygulamadı</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<script>
(() => {
  const inst = document.getElementById('f_inst');
  const camp = document.getElementById('f_camp');
  const form = document.getElementById('stats-form');
  // Kurum değişince kampüs listesi yeniden yüklensin (campus_id sıfırlanır).
  inst?.addEventListener('change', () => {
    if (camp) camp.value = '';
    form?.submit();
  });
})();
</script>
