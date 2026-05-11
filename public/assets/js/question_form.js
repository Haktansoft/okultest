(() => {
  const optsBox = document.getElementById('options');
  const tpl = document.getElementById('opt-template');
  const addBtn = document.getElementById('add-opt');
  const promptIdInput = document.getElementById('prompt_media_id');
  const promptSlot = document.getElementById('prompt-media-slot');
  const promptAudioIdInput = document.getElementById('prompt_audio_id');
  const promptAudioSlot = document.getElementById('prompt-audio-slot');

  // Modal
  const modalEl = document.getElementById('mediaModal');
  let _modal = null;
  const getModal = () => _modal ?? (_modal = bootstrap.Modal.getOrCreateInstance(modalEl));
  const tabs = document.querySelectorAll('#mediaTabs .nav-link');
  const grid = document.getElementById('media-grid');
  const empty = document.getElementById('media-empty');
  const loading = document.getElementById('media-loading');
  const search = document.getElementById('media-search');
  const confirmBtn = document.getElementById('media-confirm');
  const selectedName = document.getElementById('media-selected-name');

  let pickContext = null; // 'prompt' | 'prompt_audio' | { row, mediaInput, slot }
  let currentKind = 'image';
  let lockKind = null; // when set, the modal only shows this media kind
  let pickedItem = null;
  let searchTimer = null;

  const letter = i => String.fromCharCode(65 + i);
  const renumber = () => optsBox.querySelectorAll('.opt-letter').forEach((s, i) => s.textContent = letter(i));
  const fmtSize = bytes => {
    if (!bytes) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024*1024) return (bytes/1024).toFixed(0) + ' KB';
    return (bytes/(1024*1024)).toFixed(1) + ' MB';
  };

  // --------- Slot rendering (prompt or option) ---------
  function renderSlot(slot, mediaIdInput, mediaInfo) {
    slot.innerHTML = '';
    if (!mediaInfo || !mediaInfo.id) {
      slot.classList.add('empty');
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'media-add';
      btn.innerHTML = '<i class="bi bi-collection-play"></i> Medya ekle';
      btn.addEventListener('click', () => {
        if (slot.dataset.context === 'prompt') {
          pickContext = 'prompt';
        } else if (slot.dataset.context === 'prompt_audio') {
          pickContext = 'prompt_audio';
        } else {
          const row = slot.closest('.option-row');
          pickContext = { row, mediaInput: mediaIdInput, slot };
        }
        openPicker();
      });
      slot.appendChild(btn);
      return;
    }

    slot.classList.remove('empty');
    const thumb = document.createElement('div');
    thumb.className = 'ms-thumb';
    if (mediaInfo.kind === 'image') {
      const img = document.createElement('img');
      img.src = mediaInfo.url || ('/media/' + mediaInfo.id);
      img.alt = '';
      thumb.appendChild(img);
    } else if (mediaInfo.kind === 'audio') {
      thumb.innerHTML = '<i class="bi bi-music-note-beamed"></i>';
    } else {
      thumb.innerHTML = '<i class="bi bi-camera-video"></i>';
    }

    const info = document.createElement('div');
    info.className = 'ms-info';
    const nm = document.createElement('div');
    nm.className = 'nm';
    nm.textContent = mediaInfo.name || ('Medya #' + mediaInfo.id);
    info.appendChild(nm);

    if (mediaInfo.kind === 'audio') {
      const a = document.createElement('audio');
      a.controls = true; a.preload = 'metadata';
      a.src = mediaInfo.url || ('/media/' + mediaInfo.id);
      a.className = 'ms-preview';
      info.appendChild(a);
    } else if (mediaInfo.kind === 'video') {
      const v = document.createElement('video');
      v.controls = true; v.preload = 'metadata';
      v.src = mediaInfo.url || ('/media/' + mediaInfo.id);
      v.className = 'ms-preview';
      info.appendChild(v);
    } else {
      const meta = document.createElement('div');
      meta.className = 'meta';
      meta.textContent = (mediaInfo.kind || 'görsel');
      info.appendChild(meta);
    }

    const acts = document.createElement('div');
    acts.className = 'ms-actions';
    const change = document.createElement('button');
    change.type = 'button'; change.title = 'Değiştir'; change.innerHTML = '<i class="bi bi-arrow-repeat"></i>';
    change.addEventListener('click', () => {
      if (slot.dataset.context === 'prompt') pickContext = 'prompt';
      else if (slot.dataset.context === 'prompt_audio') pickContext = 'prompt_audio';
      else {
        const row = slot.closest('.option-row');
        pickContext = { row, mediaInput: mediaIdInput, slot };
      }
      openPicker();
    });
    const rm = document.createElement('button');
    rm.type = 'button'; rm.className = 'ms-rm'; rm.title = 'Kaldır';
    rm.innerHTML = '<i class="bi bi-x-lg"></i>';
    rm.addEventListener('click', () => {
      mediaIdInput.value = '';
      mediaIdInput.dataset.kind = '';
      mediaIdInput.dataset.name = '';
      renderSlot(slot, mediaIdInput, null);
    });
    acts.append(change, rm);

    slot.append(thumb, info, acts);
  }

  // --------- Prompt slot ---------
  promptSlot.dataset.context = 'prompt';
  const initialPromptMedia = promptIdInput.value
    ? {
        id: parseInt(promptIdInput.value, 10),
        kind: promptIdInput.dataset.kind || 'image',
        name: promptIdInput.dataset.name || '',
        url: '/media/' + promptIdInput.value,
      }
    : null;
  renderSlot(promptSlot, promptIdInput, initialPromptMedia);

  // --------- Prompt audio slot (ayrı ses) ---------
  if (promptAudioSlot && promptAudioIdInput) {
    promptAudioSlot.dataset.context = 'prompt_audio';
    const initialPromptAudio = promptAudioIdInput.value
      ? {
          id: parseInt(promptAudioIdInput.value, 10),
          kind: 'audio',
          name: promptAudioIdInput.dataset.name || '',
          url: '/media/' + promptAudioIdInput.value,
        }
      : null;
    renderSlot(promptAudioSlot, promptAudioIdInput, initialPromptAudio);
  }

  // --------- Options ---------
  function addRow(data = {}) {
    const node = tpl.content.cloneNode(true);
    const row = node.querySelector('.option-row');
    const labelInput  = row.querySelector('.opt-label');
    const scoreInput  = row.querySelector('.score-input');
    const mediaInput  = row.querySelector('.opt-media-id');
    const correctBtn  = row.querySelector('.correct-toggle');
    const correctInput= row.querySelector('.correct-input');
    const slot        = row.querySelector('.opt-media-slot');

    if (data.label != null) labelInput.value = data.label;
    if (data.score && Number(data.score) > 0) scoreInput.value = data.score;

    if (data.is_correct) {
      correctBtn.classList.add('active');
      correctInput.value = '1';
      row.classList.add('is-correct');
    }
    correctBtn.addEventListener('click', () => {
      const isOn = correctBtn.classList.toggle('active');
      correctInput.value = isOn ? '1' : '';
      row.classList.toggle('is-correct', isOn);
    });

    row.querySelector('[data-remove]').addEventListener('click', () => {
      if (optsBox.children.length <= 2) {
        alert('En az 2 şık bulunmalı.');
        return;
      }
      row.remove(); renumber();
    });

    let initMedia = null;
    if (data.media_id) {
      mediaInput.value = data.media_id;
      mediaInput.dataset.kind = data.media_kind || 'image';
      mediaInput.dataset.name = data.media_name || '';
      initMedia = {
        id: parseInt(data.media_id, 10),
        kind: data.media_kind || 'image',
        name: data.media_name || '',
        url: '/media/' + data.media_id,
      };
    }
    renderSlot(slot, mediaInput, initMedia);

    optsBox.appendChild(node);
    renumber();
  }

  addBtn.addEventListener('click', () => addRow({ is_correct: false }));

  // --------- Media picker (JSON) ---------
  tabs.forEach(t => t.addEventListener('click', e => {
    e.preventDefault();
    if (lockKind && t.dataset.kind !== lockKind) return;
    currentKind = t.dataset.kind;
    tabs.forEach(x => x.classList.toggle('active', x === t));
    pickedItem = null;
    refreshConfirm();
    loadMedia(true);
  }));

  search.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadMedia(true), 250);
  });

  confirmBtn.addEventListener('click', () => {
    if (!pickedItem) return;
    applyPick(pickedItem);
    getModal().hide();
  });

  let currentPage = 1;
  let hasMore = false;

  function openPicker() {
    pickedItem = null;
    refreshConfirm();
    search.value = '';
    lockKind = (pickContext === 'prompt_audio') ? 'audio' : null;
    currentKind = lockKind || 'image';
    tabs.forEach(t => {
      t.classList.toggle('active', t.dataset.kind === currentKind);
      t.classList.toggle('disabled', !!lockKind && t.dataset.kind !== lockKind);
      t.style.pointerEvents = (lockKind && t.dataset.kind !== lockKind) ? 'none' : '';
      t.style.opacity = (lockKind && t.dataset.kind !== lockKind) ? '0.4' : '';
    });
    loadMedia(true);
    getModal().show();
    setTimeout(() => search.focus(), 200);
  }

  function ensureMoreBtn() {
    let btn = document.getElementById('mp-more');
    if (btn) return btn;
    btn = document.createElement('button');
    btn.type = 'button';
    btn.id = 'mp-more';
    btn.className = 'btn btn-outline-secondary btn-sm w-100 mt-3 d-none';
    btn.textContent = 'Daha fazla yükle';
    btn.addEventListener('click', () => loadMedia(false));
    grid.parentNode.appendChild(btn);
    return btn;
  }

  async function loadMedia(reset) {
    const moreBtn = ensureMoreBtn();
    if (reset) {
      grid.innerHTML = '';
      currentPage = 1;
      empty.classList.add('d-none');
      moreBtn.classList.add('d-none');
    } else {
      currentPage++;
    }
    loading.classList.remove('d-none');
    try {
      const url = `/admin/media.json?kind=${encodeURIComponent(currentKind)}&q=${encodeURIComponent(search.value || '')}&page=${currentPage}`;
      const res = await fetch(url, { credentials: 'same-origin' });
      const data = await res.json();
      loading.classList.add('d-none');

      document.querySelectorAll('.mp-count').forEach(span => {
        const k = span.dataset.kind;
        span.textContent = (data.counts?.[k] ?? 0);
      });

      if (reset && (!data.items || !data.items.length)) {
        empty.classList.remove('d-none');
        moreBtn.classList.add('d-none');
        return;
      }
      (data.items || []).forEach(it => grid.appendChild(buildCard(it)));
      hasMore = !!data.hasMore;
      moreBtn.classList.toggle('d-none', !hasMore);
    } catch (e) {
      loading.classList.add('d-none');
      if (reset) grid.innerHTML = '<div class="text-danger small p-2">Yüklenemedi.</div>';
    }
  }

  function buildCard(item) {
    const card = document.createElement('div');
    card.className = 'mp-card';
    card.dataset.id = item.id;

    const thumb = document.createElement('div');
    thumb.className = 'mp-thumb';
    if (item.kind === 'image') {
      const img = document.createElement('img');
      img.src = item.url; img.alt = '';
      img.loading = 'lazy';
      thumb.appendChild(img);
    } else if (item.kind === 'audio') {
      thumb.innerHTML = '<i class="bi bi-music-note-beamed"></i>';
      const badge = document.createElement('span');
      badge.className = 'play-badge'; badge.textContent = 'ses';
      thumb.appendChild(badge);
    } else {
      // video — küçük preview olarak <video preload=metadata> kullan
      const v = document.createElement('video');
      v.src = item.url + '#t=0.1';
      v.preload = 'metadata';
      v.muted = true; v.playsInline = true;
      v.style.width = '100%'; v.style.height = '100%'; v.style.objectFit = 'cover';
      thumb.appendChild(v);
      const badge = document.createElement('span');
      badge.className = 'play-badge'; badge.textContent = 'video';
      thumb.appendChild(badge);
    }
    card.appendChild(thumb);

    const meta = document.createElement('div');
    meta.className = 'mp-meta';
    const nm = document.createElement('div');
    nm.className = 'mp-name'; nm.title = item.name; nm.textContent = item.name;
    meta.appendChild(nm);
    const sub = document.createElement('div');
    sub.className = 'mp-sub'; sub.textContent = fmtSize(item.size) + (item.mime ? ' · ' + item.mime.split('/')[1] : '');
    meta.appendChild(sub);
    card.appendChild(meta);

    // Sescil tile'a inline player ekle (audio için)
    if (item.kind === 'audio') {
      const a = document.createElement('audio');
      a.controls = true; a.preload = 'none';
      a.src = item.url;
      a.style.padding = '0 8px 8px';
      a.addEventListener('click', e => e.stopPropagation()); // play tıklayınca seçim olmasın
      card.appendChild(a);
    }

    card.addEventListener('click', () => {
      grid.querySelectorAll('.mp-card.selected').forEach(c => c.classList.remove('selected'));
      card.classList.add('selected');
      pickedItem = item;
      refreshConfirm();
    });
    card.addEventListener('dblclick', () => {
      pickedItem = item;
      applyPick(pickedItem);
      getModal().hide();
    });

    return card;
  }

  function refreshConfirm() {
    if (pickedItem) {
      confirmBtn.disabled = false;
      selectedName.textContent = 'Seçildi: ' + pickedItem.name;
    } else {
      confirmBtn.disabled = true;
      selectedName.textContent = '';
    }
  }

  function applyPick(item) {
    if (pickContext === 'prompt') {
      promptIdInput.value = item.id;
      promptIdInput.dataset.kind = item.kind;
      promptIdInput.dataset.name = item.name;
      renderSlot(promptSlot, promptIdInput, item);
    } else if (pickContext === 'prompt_audio') {
      if (item.kind !== 'audio') return;
      promptAudioIdInput.value = item.id;
      promptAudioIdInput.dataset.kind = 'audio';
      promptAudioIdInput.dataset.name = item.name;
      renderSlot(promptAudioSlot, promptAudioIdInput, item);
    } else if (pickContext && pickContext.mediaInput) {
      pickContext.mediaInput.value = item.id;
      pickContext.mediaInput.dataset.kind = item.kind;
      pickContext.mediaInput.dataset.name = item.name;
      renderSlot(pickContext.slot, pickContext.mediaInput, item);
    }
  }

  // --------- Preload ---------
  if (window.PRELOADED_OPTIONS && window.PRELOADED_OPTIONS.length) {
    window.PRELOADED_OPTIONS.forEach(o => addRow(o));
  } else {
    addRow({ is_correct: true });
    addRow({});
  }
})();
