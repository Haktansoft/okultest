<?php use function App\{e, csrfField}; $editing = !empty($item); $isAdmin = ($me['role'] ?? '') === 'admin'; ?>
<div class="page-header">
  <div>
    <h1 class="page-title"><?= $editing ? 'Öğrenciyi Düzenle' : 'Yeni Öğrenci' ?></h1>
    <div class="page-sub">
      Giriş şifresi <strong>T.C. Kimlik Numarasıdır</strong>.
      <?php if (!$editing): ?>Yeni öğrenciye mevcut tüm testler otomatik atanır.<?php endif; ?>
    </div>
  </div>
  <a href="/teacher/students" class="btn btn-link"><i class="bi bi-arrow-left"></i> Listeye dön</a>
</div>

<?php if (!$editing && $isAdmin && empty($teachers)): ?>
  <div class="alert alert-warning">
    Önce <a href="/admin/teachers/new">en az bir kampüs atanmış öğretmen</a> oluşturun.
  </div>
<?php elseif (!$editing && !$isAdmin && empty($classrooms)): ?>
  <div class="alert alert-warning">
    Önce <a href="/teacher/classrooms/new">en az bir sınıf</a> oluşturmalısın.
  </div>
<?php else: ?>
<form method="post"
      action="<?= $editing ? '/teacher/students/' . (int)$item['id'] . '/update' : '/teacher/students' ?>"
      class="card" style="max-width:560px">
  <div class="card-body">
    <?= csrfField() ?>

    <?php if (!$editing && $isAdmin): ?>
      <div class="mb-3">
        <label class="form-label">Öğretmen <span class="muted tiny">(öğrencinin bağlanacağı öğretmen — kampüsü buradan belirlenir)</span></label>
        <select class="form-select" name="teacher_id" id="teacher-select" required>
          <option value="">— Öğretmen seç —</option>
          <?php foreach ($teachers as $t): ?>
            <option value="<?= (int)$t['id'] ?>" data-campus="<?= (int)$t['campus_id'] ?>">
              <?= e($t['full_name']) ?> — <?= e($t['institution_name']) ?> / <?= e($t['campus_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>

    <div class="mb-3">
      <label class="form-label">Ad-Soyad</label>
      <input class="form-control" name="full_name" required maxlength="150" value="<?= e($item['full_name'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">T.C. Kimlik No <span class="muted tiny">(11 hane — şifre olarak kullanılacak)</span></label>
      <input class="form-control" name="tc" inputmode="numeric" pattern="\d{11}" maxlength="11"
             required value="<?= e($item['tc'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">Sınıf</label>
      <select class="form-select" name="classroom_id" id="classroom-select" required <?= (!$editing && $isAdmin) ? 'disabled' : '' ?>>
        <option value="">— Önce öğretmen seç —</option>
        <?php foreach (($classrooms ?? []) as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ($item && $item['classroom_id'] == $c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="card-footer text-end">
    <a href="/teacher/students" class="btn btn-link">İptal</a>
    <button class="btn btn-primary"><?= $editing ? 'Kaydet' : 'Ekle' ?></button>
  </div>
</form>

<?php if (!$editing && $isAdmin): ?>
<script>
(() => {
  const classroomsByCampus = <?= json_encode($classroomsByCampus ?? [], JSON_UNESCAPED_UNICODE) ?>;
  const teacherSel = document.getElementById('teacher-select');
  const classSel   = document.getElementById('classroom-select');
  teacherSel.addEventListener('change', () => {
    const opt = teacherSel.options[teacherSel.selectedIndex];
    const campusId = opt ? (opt.dataset.campus || 0) : 0;
    classSel.innerHTML = '';
    const list = classroomsByCampus[campusId] || [];
    if (!list.length) {
      classSel.innerHTML = '<option value="">— Bu kampüste sınıf yok —</option>';
      classSel.disabled = true;
      return;
    }
    classSel.disabled = false;
    classSel.appendChild(new Option('— Sınıf seç —', ''));
    list.forEach(c => classSel.appendChild(new Option(c.name, c.id)));
  });
})();
</script>
<?php endif; ?>

<?php endif; ?>
