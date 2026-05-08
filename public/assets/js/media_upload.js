(() => {
  const dz = document.getElementById('dropzone');
  const input = document.getElementById('file-input');
  const wrap = document.getElementById('upload-progress');
  const bar = wrap?.querySelector('.progress-bar');
  const status = document.getElementById('upload-status');
  if (!dz) return;

  function pickFiles() { input.click(); }
  dz.addEventListener('click', e => { if (e.target.tagName !== 'LABEL') pickFiles(); });
  dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag'); });
  dz.addEventListener('dragleave', () => dz.classList.remove('drag'));
  dz.addEventListener('drop', e => {
    e.preventDefault(); dz.classList.remove('drag');
    if (e.dataTransfer.files?.length) upload(e.dataTransfer.files);
  });
  input.addEventListener('change', () => {
    if (input.files?.length) upload(input.files);
  });

  async function upload(files) {
    wrap.classList.remove('d-none');
    bar.style.width = '0%';
    let uploaded = 0;
    const total = files.length;
    let okCount = 0, failCount = 0;

    for (const f of files) {
      status.textContent = `Yükleniyor (${uploaded+1}/${total}): ${f.name}`;
      const fd = new FormData();
      fd.append('files[]', f);
      fd.append('_csrf', window.CSRF_TOKEN);
      try {
        const res = await fetch('/admin/media/upload', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok && data.items?.[0]?.ok) okCount++;
        else failCount++;
      } catch (e) {
        failCount++;
      }
      uploaded++;
      bar.style.width = ((uploaded/total)*100).toFixed(0) + '%';
    }
    status.textContent = `Bitti — ${okCount} yüklendi, ${failCount} başarısız.`;
    setTimeout(() => location.reload(), 600);
  }
})();
