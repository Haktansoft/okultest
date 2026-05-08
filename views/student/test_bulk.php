<?php use function App\{e, csrfField, csrfToken, mediaUrl}; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <div><strong><?= e($test['title']) ?></strong> <span class="text-muted small">— Toplu yanıt</span></div>
  <div class="d-flex align-items-center gap-2">
    <span id="timer" class="timer-pill" <?= $remainingSeconds === null ? 'style="display:none"' : '' ?>>--:--</span>
    <span id="save-status" class="small text-muted"></span>
  </div>
</div>

<div class="alert alert-light small">
  Sorular numaralı listede; her satırda yalnızca yanıt şıkkını seçmen yeterli.
  Yanıtların kayboluştan korunması için her seçim hem tarayıcıya hem sunucuya kaydedilir.
</div>

<form id="bulk-form" class="bulk-list">
  <?php foreach ($questions as $idx => $q): ?>
    <div class="q-block" data-qid="<?= (int)$q['id'] ?>">
      <div class="d-flex justify-content-between">
        <strong>#<?= $idx+1 ?>.</strong>
      </div>
      <div class="mt-1"><?= e($q['prompt']) ?></div>
      <?php if (!empty($q['prompt_media'])): ?>
        <div class="mt-2 small text-muted"><i class="bi bi-paperclip"></i> Sorunun medyası mevcuttu (yazılı çözümde görmüş olmalısın).</div>
      <?php endif; ?>
      <div class="mt-2 d-flex flex-wrap gap-2">
        <?php foreach ($q['options'] as $i => $o):
          $checked = (int)($serverAnswers[$q['id']] ?? 0) === (int)$o['id'];
          $letter = chr(65 + $i);
        ?>
          <label class="btn btn-sm btn-outline-secondary <?= $checked ? 'active' : '' ?>">
            <input class="d-none" type="radio" name="q<?= (int)$q['id'] ?>" value="<?= (int)$o['id'] ?>" <?= $checked ? 'checked' : '' ?>>
            <strong><?= $letter ?>)</strong> <?= e($o['label']) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</form>

<div class="d-flex justify-content-end my-3">
  <button class="btn btn-success" id="finish-btn">Yanıtları gönder</button>
</div>

<script>
window.BULK_DATA = <?php echo json_encode([
    'assignment_id' => (int)$assignment['id'],
    'remainingSeconds' => $remainingSeconds,
    'csrf' => csrfToken(),
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script>
(() => {
  const D = window.BULK_DATA;
  const STORE_KEY = `attempt:${D.assignment_id}`;
  const form = document.getElementById('bulk-form');
  const finishBtn = document.getElementById('finish-btn');
  const saveStatus = document.getElementById('save-status');
  const timer = document.getElementById('timer');

  // localStorage'tan tamamla
  try {
    const saved = JSON.parse(localStorage.getItem(STORE_KEY) || '{}');
    if (saved.answers) {
      for (const [qid, oid] of Object.entries(saved.answers)) {
        const inp = form.querySelector(`input[name="q${qid}"][value="${oid}"]`);
        if (inp && !form.querySelector(`input[name="q${qid}"]:checked`)) {
          inp.checked = true;
          inp.closest('label')?.classList.add('active');
        }
      }
    }
  } catch {}

  let dirty = false, submitted = false, saveTimer = null;
  let remaining = D.remainingSeconds;

  function collect() {
    const answers = {};
    form.querySelectorAll('.q-block').forEach(b => {
      const qid = b.dataset.qid;
      const sel = b.querySelector('input[type=radio]:checked');
      if (sel) answers[qid] = parseInt(sel.value, 10);
    });
    return answers;
  }

  function persistLocal() {
    try {
      localStorage.setItem(STORE_KEY, JSON.stringify({ answers: collect(), savedAt: Date.now() }));
    } catch {}
  }

  form.addEventListener('change', e => {
    if (e.target.tagName !== 'INPUT') return;
    // active sınıfını yönet
    const name = e.target.name;
    form.querySelectorAll(`input[name="${name}"]`).forEach(i => {
      i.closest('label')?.classList.toggle('active', i.checked);
    });
    dirty = true;
    persistLocal();
    clearTimeout(saveTimer);
    saveTimer = setTimeout(saveNow, 600);
  });

  async function saveNow(final = false) {
    if (!dirty && !final) return;
    saveStatus.textContent = 'Kaydediliyor…';
    const url = final
      ? `/student/tests/${D.assignment_id}/submit`
      : `/student/tests/${D.assignment_id}/autosave`;
    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type':'application/json', 'X-CSRF-Token': D.csrf, 'Accept': final ? 'application/json' : '' },
        body: JSON.stringify({ answers: collect(), timings: {} }),
        keepalive: true,
      });
      if (res.ok) {
        dirty = false;
        saveStatus.textContent = 'Kaydedildi ✓';
        setTimeout(() => { if (!dirty) saveStatus.textContent = ''; }, 1200);
      } else {
        saveStatus.textContent = 'Kaydedilemedi';
      }
    } catch {
      saveStatus.textContent = 'Bağlantı hatası';
    }
  }

  finishBtn.addEventListener('click', async () => {
    if (!confirm('Yanıtlarını göndermek üzeresin. Devam edilsin mi?')) return;
    await saveNow(true);
    submitted = true;
    try { localStorage.removeItem(STORE_KEY); } catch {}
    location.href = `/student/tests/${D.assignment_id}/finished`;
  });

  setInterval(() => { if (dirty) saveNow(); }, 10000);
  window.addEventListener('beforeunload', () => {
    if (submitted || !dirty) return;
    navigator.sendBeacon?.(
      `/student/tests/${D.assignment_id}/autosave`,
      new Blob([JSON.stringify({ answers: collect(), timings: {}, _csrf: D.csrf })], { type:'application/json' })
    );
  });

  if (remaining !== null && remaining !== undefined) {
    const tick = () => {
      const m = Math.floor(remaining/60), s = remaining%60;
      timer.textContent = String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
      timer.classList.toggle('warning', remaining <= 60 && remaining > 10);
      timer.classList.toggle('danger', remaining <= 10);
      if (remaining <= 0) { clearInterval(tInt); finishBtn.click(); return; }
      remaining--;
    };
    tick();
    const tInt = setInterval(tick, 1000);
  }
})();
</script>
