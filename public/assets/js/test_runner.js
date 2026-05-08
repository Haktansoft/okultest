(() => {
  const D = window.TEST_DATA;
  if (!D) return;
  const MODE = D.mode || 'student';
  const isTeacher = MODE === 'teacher_bulk';
  const STORE_KEY = isTeacher
    ? `teacher:${(D.endpoints && D.endpoints.submit) || D.assignment_id}`
    : `attempt:${D.assignment_id}`;
  const BLANK = '__blank__';
  const LETTERS = ['A','B','C','D','E','F','G','H','I','J'];
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

  let questionEnterTime = Date.now();
  let saveTimer = null;
  let dirty = false;
  let submitted = false;
  let remaining = D.remainingSeconds;

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

  function renderSidebar() {
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
    remainingCounter.textContent = `Kalan ${left}`;
    if (progressFill) {
      progressFill.style.width = ((done / total) * 100).toFixed(1) + '%';
    }
  }

  function render() {
    const q = D.questions[currentIndex];
    progress.textContent = `${currentIndex + 1} / ${D.questions.length}`;
    container.innerHTML = '';
    const card = document.createElement('div');
    card.className = 'question-card';
    if (q.is_physical) card.classList.add('question-card-physical');

    const head = document.createElement('div');
    head.className = 'qc-head';
    const num = document.createElement('div');
    num.className = 'qc-num';
    num.innerHTML = `<span class="qc-num-label">Soru</span><span class="qc-num-val">${currentIndex + 1}</span>`;
    head.appendChild(num);
    const headRight = document.createElement('div');
    headRight.className = 'qc-head-right';
    if (q.is_physical) {
      const phys = document.createElement('span');
      phys.className = 'qc-physical';
      phys.innerHTML = '<i class="bi bi-pencil-square"></i> Fiziksel soru';
      headRight.appendChild(phys);
    }
    if (q.category) {
      const cat = document.createElement('span');
      cat.className = 'qc-cat';
      cat.innerHTML = `<i class="bi bi-bookmark"></i> ${q.category}`;
      headRight.appendChild(cat);
    }
    head.appendChild(headRight);
    card.appendChild(head);

    const prompt = document.createElement('div');
    prompt.className = 'question-prompt';
    prompt.textContent = q.prompt;
    card.appendChild(prompt);

    if (q.prompt_media) {
      card.appendChild(renderMedia(q.prompt_media, 'prompt-media mt-3'));
    }

    const opts = document.createElement('div');
    const hasMediaOpt = q.options.some(o => o && o.media);
    const longestLabel = q.options.reduce((m, o) => Math.max(m, ((o && o.label) || '').length), 0);
    // Görselli şıklar her zaman 3 sütun. Yazılı şıklar uzunsa tek sütuna iner.
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
      : '<i class="bi bi-info-circle"></i> Cevap vermeden sonraki soruya geçemezsin. Emin değilsen boş bırakabilirsin.';
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
    });
    actions.appendChild(blankBtn);
    blankBtnRef = blankBtn;
    card.appendChild(actions);

    container.appendChild(card);

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

  function updateNavButtons() {
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

  function trackTimeOnLeave() {
    const q = D.questions[currentIndex];
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

  function move(delta) {
    const q = D.questions[currentIndex];
    if (delta > 0 && !isTeacher && !marked[q.id]) return;
    trackTimeOnLeave();
    if (!isTeacher) scheduleSave(0);
    const next = currentIndex + delta;
    if (next < 0 || next >= D.questions.length) return;
    currentIndex = next;
    persistLocal();
    render();
  }

  function jumpTo(idx) {
    if (idx === currentIndex || idx < 0 || idx >= D.questions.length) return;
    trackTimeOnLeave();
    if (!isTeacher) scheduleSave(0);
    currentIndex = idx;
    persistLocal();
    render();
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

  prevBtn.addEventListener('click', () => move(-1));
  nextBtn.addEventListener('click', () => move(+1));
  finishBtn.addEventListener('click', () => finishTest(false));

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

  async function saveNow(isFinal = false) {
    if (isTeacher) return;
    if (!dirty && !isFinal) return;
    saveStatus.innerHTML = '<i class="bi bi-arrow-repeat"></i> Kaydediliyor';
    try {
      const url = isFinal
        ? `/student/tests/${D.assignment_id}/submit`
        : `/student/tests/${D.assignment_id}/autosave`;
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': D.csrf, 'Accept': isFinal ? 'application/json' : '' },
        body: JSON.stringify({ answers: answersForServer(), timings }),
        keepalive: true,
      });
      if (res.ok) {
        dirty = false;
        saveStatus.innerHTML = '<i class="bi bi-check2"></i> Kaydedildi';
        setTimeout(() => { if (!dirty) saveStatus.textContent = ''; }, 1500);
      } else {
        saveStatus.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Kaydedilemedi';
      }
    } catch {
      saveStatus.innerHTML = '<i class="bi bi-wifi-off"></i> Bağlantı hatası';
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

  render();
})();
