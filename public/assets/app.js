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

  // Preisvorschau (nur Anzeige – verbindlich rechnet der Server). Werte werden live aus dem Formular gelesen,
  // damit der Buchungsassistent sie beim Wechsel des Werbeplatzes ändern kann.
  document.querySelectorAll('form[data-quote]').forEach((form) => {
    const outs = form.querySelectorAll('[data-quote-output]');
    const start = form.querySelector('[name=start_date]');
    const end = form.querySelector('[name=end_date]');
    const wiz = form.hasAttribute('data-wizard');
    const update = () => {
      const price = parseInt(form.dataset.price, 10);
      const min = parseInt(form.dataset.min, 10);
      const max = parseInt(form.dataset.max, 10);
      const cur = form.dataset.currency;
      const fmt = (c) => cur + ' ' + (c / 100).toLocaleString('de-CH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      const show = (html) => outs.forEach((o) => { o.innerHTML = html; });
      end.setCustomValidity('');
      if (start.value) end.min = start.value;
      if (!start.value || !end.value || !price) { show('<span class="text-slate-500">Zeitraum wählen, um den Preis zu berechnen.</span>'); return; }
      const days = Math.round((Date.parse(end.value) - Date.parse(start.value)) / 86400000) + 1;
      if (days < 1) { show('<span class="text-rose-600">Das Ende muss nach dem Start liegen.</span>'); if (wiz) end.setCustomValidity('Das Ende muss nach dem Start liegen.'); return; }
      const bad = days < min || days > max;
      if (bad && wiz) end.setCustomValidity('Erlaubt sind ' + min + '–' + max + ' Tage.');
      const warn = bad ? `<p class="mt-1 text-rose-600">Erlaubt sind ${min}–${max} Tage.</p>` : '';
      show(`<div class="flex justify-between"><span>${days} Tage × ${fmt(price)}</span><strong>${fmt(days * price)}</strong></div>${warn}`);
    };
    start.addEventListener('change', update);
    end.addEventListener('change', update);
    form.addEventListener('quote-refresh', update);
    update();
  });

  // Eigenes Werbeformat ein-/ausblenden
  document.querySelectorAll('form[data-format-form]').forEach((form) => {
    const select = form.querySelector('[data-format-select], [name=format]');
    const toggle = () => form.querySelectorAll('[data-custom-size]').forEach((el) => {
      el.classList.toggle('hidden', select.value !== 'custom');
      el.querySelector('input').required = select.value === 'custom';
    });
    select.addEventListener('change', toggle);
    toggle();
  });
  // ----- Seitenleiste: ausklappbare Gruppen, Zustand pro Browser merken ------------------------------------------
  const NAV_KEY = 'vyad-nav';
  const navState = (() => { try { return JSON.parse(localStorage.getItem(NAV_KEY) || '{}') || {}; } catch { return {}; } })();
  const saveNav = () => { try { localStorage.setItem(NAV_KEY, JSON.stringify(navState)); } catch { /* Speicher gesperrt */ } };
  document.querySelectorAll('[data-nav-group]').forEach((g) => {
    const key = g.dataset.navGroup;
    if (!g.hasAttribute('data-active') && key in navState) g.open = !!navState[key]; // aktive Gruppe bleibt offen
    g.addEventListener('toggle', () => {
      navState[key] = g.open;
      saveNav();
      document.querySelectorAll('[data-nav-group="' + key + '"]').forEach((o) => { if (o !== g && o.open !== g.open) o.open = g.open; });
    });
  });
  // Mobiles Menü schliessen bei Klick ausserhalb
  document.addEventListener('click', (e) => {
    document.querySelectorAll('details[data-menu][open]').forEach((d) => { if (!d.contains(e.target)) d.open = false; });
  });

  // ----- Assistenten ------------------------------------------------------------------------------------------------
  const make = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text) n.textContent = text; return n; };

  document.querySelectorAll('form[data-wizard]').forEach((form) => {
    const steps = [...form.querySelectorAll(':scope > fieldset[data-step]')];
    const navBox = form.querySelector('[data-wizard-steps]');
    const prev = form.querySelector('[data-wizard-prev]');
    const next = form.querySelector('[data-wizard-next]');
    const submit = form.querySelector('[data-wizard-submit]');
    if (!steps.length || !prev || !next || !submit) return;
    form.noValidate = true; // wir prüfen schrittweise selbst
    let cur = 0;
    const last = steps.length - 1;

    const fields = (i) => [...steps[i].querySelectorAll('input, select, textarea')].filter((el) => !el.disabled && el.type !== 'hidden');
    const stepValid = (i, report) => {
      for (const el of fields(i)) {
        if (el.dataset.match) { const o = form.querySelector('[name="' + el.dataset.match + '"]'); el.setCustomValidity(o && o.value !== el.value ? (el.dataset.matchMsg || 'Die Eingaben stimmen nicht überein.') : ''); }
        if (!el.checkValidity()) {
          if (report) { show(i); el.reportValidity(); }
          return false;
        }
      }
      return true;
    };

    const renderNav = () => {
      if (!navBox) return;
      navBox.textContent = '';
      const ol = make('ol', 'flex items-center gap-2 text-sm');
      steps.forEach((st, i) => {
        const done = i < cur, on = i === cur;
        const li = make('li', 'flex items-center gap-2' + (i < last ? ' flex-1' : ''));
        const dot = make(done ? 'button' : 'span', 'grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs font-semibold ' + (on ? 'bg-brand-600 text-white' : done ? 'bg-brand-100 text-brand-700 hover:bg-brand-200' : 'bg-slate-200 text-slate-600'), done ? '✓' : String(i + 1));
        if (done) { dot.type = 'button'; dot.setAttribute('aria-label', 'Zurück zu Schritt ' + (i + 1) + ': ' + st.dataset.step); dot.addEventListener('click', () => show(i)); }
        if (on) dot.setAttribute('aria-current', 'step');
        li.append(dot);
        li.append(make('span', (on ? 'font-medium text-slate-900' : 'hidden text-slate-500 sm:inline'), st.dataset.step));
        if (i < last) li.append(make('span', 'h-px flex-1 ' + (done ? 'bg-brand-300' : 'bg-slate-200')));
        ol.append(li);
      });
      navBox.append(ol);
    };

    const buildSummary = () => {
      const dl = form.querySelector('[data-summary-list]');
      if (!dl) return;
      dl.textContent = '';
      form.querySelectorAll('[data-sum]').forEach((el) => {
        if (el.closest('[data-custom-size].hidden')) return;
        let v = '';
        if (el.type === 'radio' || el.type === 'checkbox') { if (!el.checked) return; v = el.dataset.text || (el.type === 'checkbox' ? 'Ja' : el.value); }
        else if (el.tagName === 'SELECT') { v = el.value && el.selectedOptions[0] ? el.selectedOptions[0].textContent.replace(/\s+/g, ' ').trim() : ''; }
        else if (el.type === 'file') { v = el.files && el.files[0] ? el.files[0].name : ''; }
        else if (el.type === 'password') { v = el.value ? '••••••••••' : ''; }
        else { v = el.value.trim(); }
        if (el.type === 'date' && /^\d{4}-\d{2}-\d{2}$/.test(v)) v = v.split('-').reverse().join('.');
        const idx = steps.findIndex((st) => st.contains(el));
        const row = make('div', 'flex items-start justify-between gap-4 py-2.5 text-sm');
        row.append(make('dt', 'shrink-0 text-slate-500', el.dataset.sum));
        const dd = make('dd', 'min-w-0 text-right font-medium text-slate-900 break-words whitespace-pre-line', v === '' ? '–' : v);
        if (idx >= 0 && idx !== last) {
          const edit = make('button', 'link ml-2 text-xs font-normal', 'Ändern');
          edit.type = 'button';
          edit.addEventListener('click', () => show(idx));
          dd.append(edit);
        }
        row.append(dd);
        dl.append(row);
      });
    };

    function show(i) {
      cur = Math.max(0, Math.min(last, i));
      steps.forEach((st, k) => st.classList.toggle('hidden', k !== cur));
      prev.classList.toggle('hidden', cur === 0);
      next.classList.toggle('hidden', cur === last);
      submit.classList.toggle('hidden', cur !== last);
      const cancel = form.querySelector('[data-wizard-cancel]');
      if (cancel) cancel.classList.toggle('hidden', cur !== 0);
      if (cur === last && steps[last].hasAttribute('data-summary')) buildSummary();
      renderNav();
      const first = fields(cur)[0];
      if (first && cur !== last) { try { first.focus({ preventScroll: true }); } catch { /* ignorieren */ } }
      const top = form.getBoundingClientRect().top;
      if (top < 0) window.scrollTo({ top: window.scrollY + top - 80, behavior: 'smooth' });
    }

    const go = (d) => {
      if (d > 0 && !stepValid(cur, true)) return;
      show(cur + d);
    };
    prev.addEventListener('click', () => go(-1));
    next.addEventListener('click', () => go(1));
    form.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && cur < last && e.target.tagName === 'INPUT' && e.target.type !== 'submit') { e.preventDefault(); go(1); }
    });
    form.addEventListener('submit', (e) => {
      if (cur < last) { e.preventDefault(); go(1); return; }
      for (let i = 0; i <= last; i++) { if (!stepValid(i, true)) { e.preventDefault(); return; } }
    });
    form.addEventListener('change', () => { if (cur === last) buildSummary(); });

    const startAt = form.dataset.start === 'last' ? last : (parseInt(form.dataset.start || '0', 10) || 0);
    show(startAt);
  });

  // Banner-Vorschau im Kampagnen-Assistenten (Vorschau per data:-URL, CSP-konform)
  document.querySelectorAll('form[data-banner-formats]').forEach((form) => {
    const input = form.querySelector('[data-banner-input]');
    const box = form.querySelector('[data-banner-preview]');
    if (!input || !box) return;
    const img = box.querySelector('img'), size = box.querySelector('[data-banner-size]'), fit = box.querySelector('[data-banner-fit]');
    const formats = JSON.parse(form.dataset.bannerFormats || '[]');
    const maxBytes = parseInt(form.dataset.bannerMax || '0', 10);
    const types = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
    input.addEventListener('change', () => {
      input.setCustomValidity('');
      const f = input.files && input.files[0];
      if (!f) { box.classList.add('hidden'); return; }
      if (!types.includes(f.type)) { input.setCustomValidity('Bitte PNG, JPG, GIF oder WebP wählen.'); input.reportValidity(); box.classList.add('hidden'); return; }
      if (maxBytes && f.size > maxBytes) { input.setCustomValidity('Die Datei ist zu gross (max. ' + Math.round(maxBytes / 1024) + ' KB).'); input.reportValidity(); box.classList.add('hidden'); return; }
      const fr = new FileReader();
      fr.onload = () => {
        const probe = new Image();
        probe.onload = () => {
          const key = probe.naturalWidth + 'x' + probe.naturalHeight;
          img.src = fr.result;
          size.textContent = probe.naturalWidth + ' × ' + probe.naturalHeight + ' px · ' + Math.round(f.size / 1024) + ' KB';
          const ok = formats.includes(key);
          fit.className = 'mt-1 text-sm ' + (ok ? 'text-emerald-700' : 'text-amber-700');
          fit.textContent = ok ? 'Entspricht dem Standardformat ' + key.replace('x', '×') + '.' : 'Kein Standardformat – das Banner passt nur zu Werbeplätzen mit genau dieser Grösse.';
          box.classList.remove('hidden');
        };
        probe.onerror = () => { input.setCustomValidity('Die Datei lässt sich nicht als Bild lesen.'); input.reportValidity(); };
        probe.src = fr.result;
      };
      fr.readAsDataURL(f);
    });
  });

  // Buchungsassistent: Werbeplatz bestimmt Preis, Zeitraum und passende Kampagnen
  document.querySelectorAll('form[data-wizard-booking]').forEach((form) => {
    const slotSel = form.querySelector('[data-slot-select]');
    const campSel = form.querySelector('[data-campaign-select]');
    const info = form.querySelector('[data-slot-info]');
    const noCamp = form.querySelector('[data-no-campaign]');
    const hint = form.querySelector('[data-duration-hint]');
    const start = form.querySelector('[name=start_date]'), end = form.querySelector('[name=end_date]');
    if (!slotSel || !campSel) return;
    const apply = () => {
      const o = slotSel.selectedOptions[0];
      const has = o && o.value;
      info.classList.toggle('hidden', !has);
      if (has) {
        const d = o.dataset;
        info.querySelector('[data-info-title]').textContent = d.site + ' – ' + o.textContent.split(' – ').slice(1).join(' – ').replace(/\s*\(.*$/, '');
        info.querySelector('[data-info-detail]').textContent = d.cat + ' · ' + d.w + '×' + d.h + ' px · ' + d.pricetext + ' pro Tag · Laufzeit ' + d.min + '–' + d.max + ' Tage';
        info.querySelector('[data-info-link]').href = 'index.php?p=slot&id=' + encodeURIComponent(o.value);
        form.dataset.price = d.price; form.dataset.min = d.min; form.dataset.max = d.max;
        [start, end].forEach((el) => { el.min = d.from; if (d.until) el.max = d.until; else el.removeAttribute('max'); });
        if (hint) hint.textContent = 'Laufzeit ' + d.min + '–' + d.max + ' Tage' + (d.until ? ', verfügbar bis ' + d.until.split('-').reverse().join('.') : '') + '. Start und Ende zählen mit.';
      } else { form.dataset.price = '0'; }
      // Kampagnen filtern: nur solche mit passender Bannergrösse
      let matches = 0;
      [...campSel.options].forEach((c) => {
        if (!c.value) return;
        const ok = has && c.dataset.w === o.dataset.w && c.dataset.h === o.dataset.h;
        c.hidden = !ok; c.disabled = !ok;
        if (ok) matches++;
        if (!ok && c.selected) campSel.value = '';
      });
      const none = has && matches === 0;
      noCamp.classList.toggle('hidden', !none);
      if (none) noCamp.querySelector('[data-no-campaign-text]').textContent = 'Sie haben keine freigegebene Kampagne mit einem Banner im Format ' + o.dataset.w + '×' + o.dataset.h + ' px.';
      campSel.setCustomValidity(none ? 'Für diesen Werbeplatz fehlt eine passende, freigegebene Kampagne.' : '');
      form.dispatchEvent(new Event('quote-refresh'));
    };
    slotSel.addEventListener('change', apply);
    apply();
  });
});
