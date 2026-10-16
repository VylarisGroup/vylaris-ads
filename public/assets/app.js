// VylarisAdvertise – kleine Progressive-Enhancement-Helfer (CSP-konform, ohne Abhängigkeiten)
document.addEventListener('DOMContentLoaded', () => {
  // Sicherheitsabfrage für destruktive Formulare
  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (e) => {
      if (!window.confirm(form.dataset.confirm)) e.preventDefault();
    });
  });

  // Änderungsprotokoll nach einem Update: einmalig als Dialog; jedes Schliessen gilt als „gesehen“
  document.querySelectorAll('dialog[data-changelog]').forEach((dlg) => {
    if (typeof dlg.showModal !== 'function') return;
    const form = dlg.querySelector('form');
    let sent = false;
    const done = () => { if (!sent) { sent = true; form.submit(); } };
    dlg.addEventListener('close', done);
    dlg.querySelector('[data-changelog-close]').addEventListener('click', (e) => { e.preventDefault(); dlg.close(); });
    dlg.showModal();
  });

  document.querySelectorAll('[data-print]').forEach((b) => b.addEventListener('click', () => window.print()));
  document.querySelectorAll('[data-back]').forEach((a) => a.addEventListener('click', (e) => {
    if (history.length > 1) { e.preventDefault(); history.back(); }
  }));

  document.querySelectorAll('[data-select]').forEach((t) => t.addEventListener('focus', () => t.select()));
  document.querySelectorAll('[data-copy]').forEach((btn) => btn.addEventListener('click', async () => {
    const field = btn.parentElement.querySelector('[data-select]');
    if (!field) return;
    try { await navigator.clipboard.writeText(field.value); } catch { field.select(); document.execCommand('copy'); }
    const old = btn.innerHTML; btn.textContent = 'Kopiert ✓'; setTimeout(() => { btn.innerHTML = old; }, 1500);
  }));

  // Preisvorschau auf der Werbeplatz-Seite (nur Anzeige – verbindlich rechnet der Server)
  document.querySelectorAll('form[data-quote]').forEach((form) => {
    const out = form.querySelector('[data-quote-output]');
    const start = form.querySelector('[name=start_date]');
    const end = form.querySelector('[name=end_date]');
    const price = parseInt(form.dataset.price, 10);
    const min = parseInt(form.dataset.min, 10);
    const max = parseInt(form.dataset.max, 10);
    const cur = form.dataset.currency;
    const fmt = (c) => cur + ' ' + (c / 100).toLocaleString('de-CH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const update = () => {
      if (start.value) end.min = start.value;
      if (!start.value || !end.value) return;
      const days = Math.round((Date.parse(end.value) - Date.parse(start.value)) / 86400000) + 1;
      if (days < 1) { out.innerHTML = '<span class="text-rose-600">Das Ende muss nach dem Start liegen.</span>'; return; }
      const warn = days < min || days > max ? `<p class="mt-1 text-rose-600">Erlaubt sind ${min}–${max} Tage.</p>` : '';
      out.innerHTML = `<div class="flex justify-between"><span>${days} Tage × ${fmt(price)}</span><strong>${fmt(days * price)}</strong></div>${warn}`;
    };
    start.addEventListener('change', update);
    end.addEventListener('change', update);
    update();
  });

  // Eigenes Werbeformat ein-/ausblenden
  document.querySelectorAll('form[data-format-form]').forEach((form) => {
    const select = form.querySelector('[name=format]');
    const toggle = () => form.querySelectorAll('[data-custom-size]').forEach((el) => {
      el.classList.toggle('hidden', select.value !== 'custom');
      el.querySelector('input').required = select.value === 'custom';
    });
    select.addEventListener('change', toggle);
    toggle();
  });
});
