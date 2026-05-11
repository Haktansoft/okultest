(() => {
  const D = window.TEST_DATA;
  if (!D) return;
  const MODE = D.mode || 'student';
  const isTeacher = MODE === 'teacher_bulk';
  // attempt_token: started_at zaman damgası. Sıfırlandığında değişir, eski yerel veriyi otomatik geçersiz kılar.
  const ATTEMPT_TOKEN = D.attempt_token || 0;
  const KEY_PREFIX = isTeacher
    ? `teacher:${(D.endpoints && D.endpoints.submit) || D.assignment_id}`
    : `attempt:${D.assignment_id}`;
  const STORE_KEY = `${KEY_PREFIX}:${ATTEMPT_TOKEN}`;
  // Aynı atamanın eski (farklı token'lı) localStorage anahtarlarını temizle
  try {
    for (let i = localStorage.length - 1; i >= 0; i--) {
      const k = localStorage.key(i);
      if (k && k.startsWith(KEY_PREFIX + ':') && k !== STORE_KEY) localStorage.removeItem(k);
      // Eski format (token'sız) anahtarları da temizle
      if (k === KEY_PREFIX) localStorage.removeItem(k);
    }
  } catch {}
  const BLANK = '__blank__';
  const LETTERS = ['A','B','C','D','E','F','G','H','I','J'];
  const AUTO_ADVANCE = !!D.autoAdvance;
  const TRANSITION_MS = 260;

  const container = document.getElementById('question-container');
  const tpl = document.getElementById('opt-template');
  const prevBtn = document.getElementById('prev-btn');
  const nextBtn = document.getElementById('next-btn');
  const finishBtn = document.getElementById('finish-btn');
  const progress = document.getElementById('progress-counter');
  const remainingCounter = document.getElementById('remaining-counter');
  const saveStatus = document.getElementById('save-status');
  const timer = document.getElementById('timer');
  const timerText = timer ? timer.querySelector('.timer-text') : null;
  const qnavGroups = document.getElementById('qnav-groups');
  const progressFill = document.querySelector('.topbar-progress-fill');

  const local = readLocal();
  const answers = {};
  const timings = {};
  const marked = {};
  for (const q of D.questions) {
    if (D.serverAnswers[q.id] != null) {
      answers[q.id] = D.serverAnswers[q.id];
      marked[q.id] = true;
    } else if (local.answers && local.answers[q.id] !== undefined && local.answers[q.id] !== null) {
      answers[q.id] = local.answers[q.id];
      marked[q.id] = true;
    } else if (local.marked && local.marked[q.id]) {
      answers[q.id] = BLANK;
      marked[q.id] = true;
    } else {
      answers[q.id] = null;
      marked[q.id] = false;
    }
    timings[q.id] = (D.serverTimings && D.serverTimings[q.id]) ?? local.timings?.[q.id] ?? 0;
  }
  let currentIndex = Math.min(local.currentIndex || 0, D.questions.length - 1);
  if (currentIndex < 0) currentIndex = 0;

  // Bir kategorinin intro'sunu göstermek için: o kategoride henüz hiçbir soru
  // işaretlenmemişse VE bu sefer o kategorinin ilk sorusuna geliyorsak.
  // İntro acknowledgment'ı tek seferlik gösterimi sağlar.
  const introSeen = new Set();
  // Sayfa açılışında: zaten yanıtlı kategorilerin intro'sunu görmüş say.
  for (const q of D.questions) {
    if (marked[q.id]) introSeen.add(q.category_id);
  }
  // Şu an gösterilen "sahne" — soru mu yoksa kategori-intro mu
  let currentScene = null; // {type:'question'|'intro', categoryId?, qIndex?}

  // Bir kategorinin "ilk soru indeksi" tablosu — soruları sıralı taradığımızda
  // kategori değiştiği yer.
  const firstIndexByCategory = {};
  D.questions.forEach((q, i) => {
    if (firstIndexByCategory[q.category_id] === undefined) {
      firstIndexByCategory[q.category_id] = i;
    }
  });

  let questionEnterTime = Date.now();
  let saveTimer = null;
  let dirty = false;
  let submitted = false;
  let remaining = D.remainingSeconds;
  let advanceTimer = null;

  function readLocal() {
    try { return JSON.parse(localStorage.getItem(STORE_KEY) || '{}'); }
    catch { return {}; }
  }
  function persistLocal() {
    try {
      localStorage.setItem(STORE_KEY, JSON.stringify({
        answers, timings, marked, currentIndex, savedAt: Date.now(),
      }));
    } catch {}
  }

  // ---- Sidebar (öğretmen modunda var, öğrenci modunda DOM yok) ----
  function renderSidebar() {
    if (!qnavGroups) return;
    qnavGroups.innerHTML = '';
    const groups = new Map();
    D.questions.forEach((q, i) => {
      const cat = q.category || 'Diğer';
      if (!groups.has(cat)) groups.set(cat, []);
      groups.get(cat).push({ q, i });
    });
    for (const [cat, items] of groups) {
      const wrap = document.createElement('div');
      wrap.className = 'qnav-group';
      const title = document.createElement('div');
      title.className = 'qnav-cat';
      const catName = document.createElement('span');
      catName.textContent = cat;
      const catCount = document.createElement('span');
      catCount.className = 'qnav-cat-count';
      const markedCount = items.filter(({ q }) => marked[q.id]).length;
      catCount.textContent = `${markedCount}/${items.length}`;
      title.appendChild(catName);
      title.appendChild(catCount);
      wrap.appendChild(title);
      const grid = document.createElement('div');
      grid.className = 'qnav-grid';
      items.forEach(({ i, q }) => {
        const cell = document.createElement('span');
        cell.className = 'qnav-cell';
        cell.textContent = String(i + 1);
        if (i === currentIndex) cell.classList.add('current');
        if (marked[q.id]) cell.classList.add('marked');
        if (q.is_physical) {
          cell.classList.add('physical');
          cell.title = 'Fiziksel soru';
        }
        if (isTeacher) {
          cell.classList.add('clickable');
          cell.addEventListener('click', () => jumpTo(i));
        }
        grid.appendChild(cell);
      });
      wrap.appendChild(grid);
      qnavGroups.appendChild(wrap);
    }
  }

  function updateProgress() {
    const total = D.questions.length;
    const done = D.questions.filter(q => marked[q.id]).length;
    const left = total - done;
    if (remainingCounter) remainingCounter.textContent = `Kalan ${left}`;
    if (progressFill) {
      progressFill.style.width = ((done / total) * 100).toFixed(1) + '%';
    }
  }

  // ---- Sahne kararı ve render zinciri ----

  // currentIndex için: önce o kategorinin intro'sunu gösterelim mi?
  function shouldShowIntroForCurrent() {
    if (isTeacher) return false; // öğretmen modunda intro yok
    const q = D.questions[currentIndex];
    if (!q) return false;
    if (introSeen.has(q.category_id)) return false;
    if (firstIndexByCategory[q.category_id] !== currentIndex) return false;
    const introHtml = (q.category_description_html || '').trim();
    const introText = (q.category_description || '').trim();
    const hasIntroContent = introHtml.length > 0 || introText.length > 0 || q.category_audio;
    if (!hasIntroContent) {
      introSeen.add(q.category_id);
      return false;
    }
    return true;
  }

  function renderIntro() {
    const q = D.questions[currentIndex];
    clearTopbarPills();
    container.innerHTML = '';
    const card = document.createElement('div');
    card.className = 'category-intro';

    const eyebrow = document.createElement('div');
    eyebrow.className = 'ci-eyebrow';
    eyebrow.innerHTML = '<i class="bi bi-bookmark-fill"></i> Yeni Bölüm';
    card.appendChild(eyebrow);

    const title = document.createElement('h2');
    title.className = 'ci-title';
    title.textContent = q.category || 'Bölüm';
    card.appendChild(title);

    const descHtml = (q.category_description_html || '').trim();
    const descText = (q.category_description || '').trim();
    if (descHtml.length > 0) {
      const desc = document.createElement('div');
      desc.className = 'ci-desc ci-desc-rich';
      desc.innerHTML = descHtml;
      card.appendChild(desc);
    } else if (descText.length > 0) {
      const desc = document.createElement('div');
      desc.className = 'ci-desc';
      desc.textContent = descText;
      card.appendChild(desc);
    }

    if (q.category_audio) {
      const audio = document.createElement('div');
      audio.className = 'ci-audio';
      audio.innerHTML = '<i class="bi bi-volume-up-fill"></i>';
      const a = document.createElement('audio');
      a.controls = true;
      a.src = q.category_audio.url;
      a.preload = 'auto';
      audio.appendChild(a);
      card.appendChild(audio);
    }

    const actions = document.createElement('div');
    actions.className = 'ci-actions';
    const startBtn = document.createElement('button');
    startBtn.type = 'button';
    startBtn.className = 'ci-start';
    startBtn.innerHTML = '<i class="bi bi-play-fill"></i> Teste Başla';
    startBtn.addEventListener('click', () => {
      introSeen.add(q.category_id);
      currentScene = null;
      transitionTo(renderCurrent);
    });
    actions.appendChild(startBtn);
    card.appendChild(actions);

    container.appendChild(card);
    currentScene = { type: 'intro', categoryId: q.category_id };

    if (progress) progress.textContent = `${currentIndex + 1} / ${D.questions.length}`;
    if (prevBtn) prevBtn.disabled = currentIndex === 0;
    if (nextBtn) {
      nextBtn.disabled = true;
      nextBtn.style.display = 'none';
    }
    if (finishBtn) finishBtn.style.display = 'none';
    updateProgress();
    renderSidebar();
  }

  function renderQuestion() {
    const q = D.questions[currentIndex];
    progress.textContent = `${currentIndex + 1} / ${D.questions.length}`;
    setTopbarPills(q);
    container.innerHTML = '';
    const card = document.createElement('div');
    card.className = 'question-card';
    if (q.is_physical) card.classList.add('question-card-physical');

    const prompt = document.createElement('div');
    prompt.className = 'question-prompt';
    prompt.textContent = q.prompt;
    card.appendChild(prompt);

    if (q.prompt_media) {
      card.appendChild(renderMedia(q.prompt_media, 'prompt-media mt-3'));
    }
    if (q.prompt_audio) {
      card.appendChild(renderMedia(q.prompt_audio, 'prompt-media prompt-audio mt-3'));
    }

    const opts = document.createElement('div');
    const hasMediaOpt = q.options.some(o => o && o.media);
    const longestLabel = q.options.reduce((m, o) => Math.max(m, ((o && o.label) || '').length), 0);
    const textStacked = !hasMediaOpt && longestLabel > 22;
    opts.className = 'options-grid mt-4'
      + (hasMediaOpt ? ' has-media' : '')
      + (textStacked ? ' text-stacked' : '');
    let blankBtnRef = null;
    q.options.forEach((o, idx) => {
      const node = tpl.content.cloneNode(true);
      const root = node.querySelector('.option');
      const input = node.querySelector('input');
      const label = node.querySelector('.opt-label');
      const slot  = node.querySelector('.opt-media-slot');
      const letter = node.querySelector('.opt-letter');
      input.value = o.id;
      input.checked = answers[q.id] === o.id;
      if (input.checked) root.classList.add('selected');
      letter.textContent = LETTERS[idx] || String(idx + 1);
      label.textContent = o.label;
      if (o.media) slot.appendChild(renderMedia(o.media, ''));
      input.addEventListener('change', () => {
        opts.querySelectorAll('.option').forEach(x => x.classList.remove('selected'));
        if (input.checked) {
          root.classList.add('selected');
          answers[q.id] = o.id;
          marked[q.id] = true;
          dirty = true;
          persistLocal();
          updateProgress();
          renderSidebar();
          updateNavButtons();
          if (blankBtnRef) {
            blankBtnRef.innerHTML = '<i class="bi bi-skip-forward"></i> Boş bırak';
            blankBtnRef.classList.remove('active');
          }
          scheduleSave(400);
          if (AUTO_ADVANCE) scheduleAutoAdvance();
        }
      });
      opts.appendChild(node);
    });
    card.appendChild(opts);

    const actions = document.createElement('div');
    actions.className = 'question-actions';
    const hint = document.createElement('div');
    hint.className = 'qa-hint';
    hint.innerHTML = isTeacher
      ? '<i class="bi bi-info-circle"></i> Öğrenci cevap vermediyse boş bırakabilirsin. Tüm soruları işaretledikten sonra kaydet.'
      : '<i class="bi bi-info-circle"></i> Bir şıkkı seçtiğinde otomatik olarak sonraki soruya geçilir. Emin değilsen boş bırakabilirsin.';
    actions.appendChild(hint);

    const blankBtn = document.createElement('button');
    blankBtn.type = 'button';
    blankBtn.className = 'btn btn-blank';
    blankBtn.id = 'blank-btn';
    if (answers[q.id] === BLANK) {
      blankBtn.innerHTML = '<i class="bi bi-check2"></i> Boş bırakıldı';
      blankBtn.classList.add('active');
    } else {
      blankBtn.innerHTML = '<i class="bi bi-skip-forward"></i> Boş bırak';
    }
    blankBtn.addEventListener('click', () => {
      opts.querySelectorAll('.option').forEach(x => x.classList.remove('selected'));
      opts.querySelectorAll('input').forEach(i => { i.checked = false; });
      answers[q.id] = BLANK;
      marked[q.id] = true;
      dirty = true;
      persistLocal();
      updateProgress();
      renderSidebar();
      updateNavButtons();
      blankBtn.innerHTML = '<i class="bi bi-check2"></i> Boş bırakıldı';
      blankBtn.classList.add('active');
      scheduleSave(400);
      if (AUTO_ADVANCE) scheduleAutoAdvance();
    });
    actions.appendChild(blankBtn);
    blankBtnRef = blankBtn;
    card.appendChild(actions);

    container.appendChild(card);
    currentScene = { type: 'question', qIndex: currentIndex };

    updateNavButtons();
    updateProgress();
    renderSidebar();

    questionEnterTime = Date.now();
    if (!isTeacher) {
      fetch(`/student/tests/${D.assignment_id}/event`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': D.csrf },
        body: JSON.stringify({ type: 'focus_question', question_id: q.id }),
        keepalive: true,
      }).catch(()=>{});
    }
  }

  function renderCurrent() {
    if (shouldShowIntroForCurrent()) renderIntro();
    else renderQuestion();
  }

  function setTopbarPills(q) {
    const catEl = document.getElementById('current-category');
    const physEl = document.getElementById('current-physical');
    if (catEl) {
      if (q && q.category) {
        catEl.querySelector('.cat-text').textContent = q.category;
        catEl.style.display = '';
      } else {
        catEl.style.display = 'none';
      }
    }
    if (physEl) {
      physEl.style.display = (q && q.is_physical) ? '' : 'none';
    }
  }
  function clearTopbarPills() { setTopbarPills(null); }

  function transitionTo(renderFn) {
    if (advanceTimer) { clearTimeout(advanceTimer); advanceTimer = null; }
    container.classList.remove('is-entering');
    container.classList.add('is-leaving');
    setTimeout(() => {
      renderFn();
      container.classList.remove('is-leaving');
      container.classList.add('is-entering');
      // Bir frame sonra is-entering'i kaldır → enter animasyonu çalışsın
      requestAnimationFrame(() => {
        requestAnimationFrame(() => container.classList.remove('is-entering'));
      });
    }, TRANSITION_MS);
  }

  function scheduleAutoAdvance() {
    if (advanceTimer) clearTimeout(advanceTimer);
    const isLast = currentIndex === D.questions.length - 1;
    advanceTimer = setTimeout(() => {
      if (isLast) {
        const allMarked = D.questions.every(qq => marked[qq.id]);
        if (allMarked) finishTest(true);
      } else {
        doMove(+1);
      }
    }, 480);
  }

  function updateNavButtons() {
    if (!nextBtn || !prevBtn || !finishBtn) return;
    const q = D.questions[currentIndex];
    const isLast = currentIndex === D.questions.length - 1;
    const canAdvance = isTeacher || marked[q.id] === true;
    prevBtn.disabled = currentIndex === 0;
    nextBtn.disabled = !canAdvance || isLast;
    nextBtn.style.display = isLast ? 'none' : '';
    if (isLast) {
      const allMarked = D.questions.every(qq => marked[qq.id]);
      finishBtn.style.display = '';
      finishBtn.disabled = !allMarked;
    } else {
      finishBtn.style.display = 'none';
    }
  }

  function renderMedia(m, cls) {
    const wrap = document.createElement('div');
    wrap.className = cls;
    if (m.kind === 'image') {
      const img = document.createElement('img');
      img.src = m.url; img.alt = m.name || '';
      img.style.maxWidth = '100%'; img.style.maxHeight = '320px';
      img.className = 'rounded';
      wrap.appendChild(img);
    } else if (m.kind === 'audio') {
      const a = document.createElement('audio');
      a.controls = true; a.src = m.url; a.className = 'audio-tile';
      wrap.appendChild(a);
    } else if (m.kind === 'video') {
      const v = document.createElement('video');
      v.controls = true; v.src = m.url; v.className = 'video-tile';
      v.style.maxWidth = '100%'; v.style.maxHeight = '320px';
      wrap.appendChild(v);
    }
    return wrap;
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
  }

  function trackTimeOnLeave() {
    if (currentScene && currentScene.type !== 'question') return;
    const q = D.questions[currentIndex];
    if (!q) return;
    const dt = Math.floor((Date.now() - questionEnterTime) / 1000);
    timings[q.id] = (timings[q.id] || 0) + dt;
    persistLocal();
    if (!isTeacher) {
      fetch(`/student/tests/${D.assignment_id}/event`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': D.csrf },
        body: JSON.stringify({ type: 'blur_question', question_id: q.id, payload: { dt } }),
        keepalive: true,
      }).catch(()=>{});
    }
  }

  function doMove(delta) {
    const q = D.questions[currentIndex];
    if (delta > 0 && !isTeacher && !marked[q.id]) return;
    trackTimeOnLeave();
    if (!isTeacher) scheduleSave(0);
    const next = currentIndex + delta;
    if (next < 0 || next >= D.questions.length) return;
    currentIndex = next;
    persistLocal();
    transitionTo(renderCurrent);
  }

  function jumpTo(idx) {
    if (idx === currentIndex || idx < 0 || idx >= D.questions.length) return;
    trackTimeOnLeave();
    if (!isTeacher) scheduleSave(0);
    currentIndex = idx;
    persistLocal();
    transitionTo(renderCurrent);
  }

  async function finishTest(skipConfirm = false) {
    const allMarked = D.questions.every(qq => marked[qq.id]);
    if (!allMarked && !skipConfirm) return;
    const confirmMsg = isTeacher
      ? 'Yanıtları kaydetmek istediğine emin misin?'
      : 'Testi bitirmek istediğinden emin misin?';
    if (!skipConfirm && !confirm(confirmMsg)) return;
    trackTimeOnLeave();

    if (isTeacher) {
      submitTeacherForm();
      return;
    }

    await saveNow(true);
    submitted = true;
    try { localStorage.removeItem(STORE_KEY); } catch {}
    location.href = `/student/tests/${D.assignment_id}/finished`;
  }

  function submitTeacherForm() {
    submitted = true;
    saveStatus.innerHTML = '<i class="bi bi-arrow-repeat"></i> Kaydediliyor';
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = D.endpoints.submit;
    form.style.display = 'none';
    const csrf = document.createElement('input');
    csrf.type = 'hidden'; csrf.name = '_csrf'; csrf.value = D.csrf;
    form.appendChild(csrf);
    for (const qid in answers) {
      const v = answers[qid];
      if (v != null && v !== BLANK) {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = `option[${qid}]`;
        inp.value = String(v);
        form.appendChild(inp);
      }
    }
    document.body.appendChild(form);
    try { localStorage.removeItem(STORE_KEY); } catch {}
    form.submit();
  }

  if (prevBtn) prevBtn.addEventListener('click', () => doMove(-1));
  if (nextBtn) nextBtn.addEventListener('click', () => doMove(+1));
  if (finishBtn) finishBtn.addEventListener('click', () => finishTest(false));

  function scheduleSave(delay) {
    if (isTeacher) return;
    clearTimeout(saveTimer);
    saveTimer = setTimeout(saveNow, delay ?? 800);
  }

  function answersForServer() {
    const out = {};
    for (const qid in answers) {
      const v = answers[qid];
      out[qid] = (v === BLANK || v == null) ? null : v;
    }
    return out;
  }

  // Hata sonrası otomatik yeniden deneme (exponential backoff)
  let retryTimer = null;
  let countdownTimer = null;
  let retryDelayMs = 3000;
  const RETRY_MAX_MS = 30000;
  const FETCH_TIMEOUT_MS = 12000;
  let sessionExpired = false;
  let saveInFlight = false;

  function clearCountdown() {
    if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
  }

  function scheduleRetry(reasonHtml) {
    if (sessionExpired) return;
    if (retryTimer) return;
    let secondsLeft = Math.max(1, Math.round(retryDelayMs / 1000));
    const tick = () => {
      if (saveInFlight || sessionExpired) return; // başka bir kaydetme çalışıyor
      saveStatus.innerHTML = `${reasonHtml} — ${secondsLeft}s sonra yeniden deneniyor`;
      secondsLeft--;
    };
    tick();
    clearCountdown();
    countdownTimer = setInterval(tick, 1000);
    retryTimer = setTimeout(() => {
      retryTimer = null;
      clearCountdown();
      if (dirty) saveNow();
    }, retryDelayMs);
    retryDelayMs = Math.min(Math.round(retryDelayMs * 1.5), RETRY_MAX_MS);
  }
  function resetRetry() {
    retryDelayMs = 3000;
    if (retryTimer) { clearTimeout(retryTimer); retryTimer = null; }
    clearCountdown();
  }

  async function saveNow(isFinal = false) {
    if (isTeacher) return;
    if (!dirty && !isFinal) return;
    if (sessionExpired && !isFinal) return;
    if (saveInFlight) return; // eşzamanlı çağrıları engelle — bağlantı havuzunu tıkamasın
    saveInFlight = true;
    // Bekleyen retry varsa iptal et — şimdi yeni bir deneme yapıyoruz
    if (retryTimer) { clearTimeout(retryTimer); retryTimer = null; }
    clearCountdown();
    saveStatus.innerHTML = '<i class="bi bi-arrow-repeat"></i> Kaydediliyor';

    const ac = new AbortController();
    const timeoutId = setTimeout(() => ac.abort(), FETCH_TIMEOUT_MS);

    try {
      const url = isFinal
        ? `/student/tests/${D.assignment_id}/submit`
        : `/student/tests/${D.assignment_id}/autosave`;
      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-Token': D.csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ answers: answersForServer(), timings }),
        signal: ac.signal,
        cache: 'no-store',
      });
      clearTimeout(timeoutId);

      if (res.ok) {
        dirty = false;
        resetRetry();
        saveStatus.innerHTML = '<i class="bi bi-check2"></i> Kaydedildi';
        setTimeout(() => { if (!dirty) saveStatus.textContent = ''; }, 1500);
      } else if (res.status === 419 || res.status === 401) {
        sessionExpired = true;
        clearCountdown();
        saveStatus.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Oturum süresi doldu — sayfayı yenileyin';
      } else {
        const reason = `<i class="bi bi-exclamation-triangle"></i> Kaydedilemedi (${res.status})`;
        saveStatus.innerHTML = reason;
        if (!isFinal) scheduleRetry(reason);
      }
    } catch (e) {
      clearTimeout(timeoutId);
      const why = (e && e.name === 'AbortError') ? 'zaman aşımı' : 'bağlantı hatası';
      const reason = `<i class="bi bi-wifi-off"></i> ${why}`;
      saveStatus.innerHTML = reason;
      if (!isFinal) scheduleRetry(reason);
    } finally {
      saveInFlight = false;
    }
  }

  if (!isTeacher) {
    setInterval(() => { if (dirty) saveNow(); }, 10000);
    window.addEventListener('beforeunload', () => {
      if (submitted) return;
      trackTimeOnLeave();
      if (dirty) {
        navigator.sendBeacon?.(
          `/student/tests/${D.assignment_id}/autosave`,
          new Blob([JSON.stringify({ answers: answersForServer(), timings, _csrf: D.csrf })], { type: 'application/json' })
        );
      }
    });
  }

  if (remaining !== null && remaining !== undefined) {
    const tick = () => {
      const m = Math.floor(remaining / 60), s = remaining % 60;
      const text = String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
      if (timerText) timerText.textContent = text; else timer.textContent = text;
      timer.classList.toggle('warning', remaining <= 60 && remaining > 10);
      timer.classList.toggle('danger', remaining <= 10);
      if (remaining <= 0) {
        clearInterval(tInt);
        D.questions.forEach(qq => { if (!marked[qq.id]) { answers[qq.id] = BLANK; marked[qq.id] = true; } });
        dirty = true;
        finishTest(true);
        return;
      }
      remaining--;
    };
    tick();
    const tInt = setInterval(tick, 1000);
  }

  renderCurrent();
})();
