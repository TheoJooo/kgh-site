(function(){
  if (typeof KGHAvailabilityAdmin === 'undefined') return;
  const data = KGHAvailabilityAdmin;
  const root = document.getElementById('kgh-availability-app');
  if (!root) return;

  const state = {
    tourId: data.tours.length ? data.tours[0].id : 0,
    start: todayKST(),
    end: addDaysKST(todayKST(), 30),
    loading: false,
  };

  root.innerHTML = `
    <div class="kgh-avail-filters">
      <label>${esc(data.i18n.selectTour || 'Select a tour')}<select id="kgh-avail-tour"></select></label>
      <label>From<input type="date" id="kgh-avail-start"></label>
      <label>To<input type="date" id="kgh-avail-end"></label>
      <button class="button button-primary" id="kgh-avail-load">${esc(data.i18n.load || 'Load')}</button>
      <span style="opacity:.7;">${esc(data.i18n.allTimesKst || 'All times KST')}</span>
    </div>
    <div id="kgh-availability-status"></div>
    <div id="kgh-availability-table"></div>
    <div class="kgh-legend">
      <strong>Legend</strong>
      <p>left = effective capacity − (booked_site + external_booked + holds). Closed forces capacity to 0 even if overrides exist.</p>
    </div>
  `;

  const selTour = document.getElementById('kgh-avail-tour');
  const inputStart = document.getElementById('kgh-avail-start');
  const inputEnd = document.getElementById('kgh-avail-end');
  const btnLoad = document.getElementById('kgh-avail-load');
  const statusBox = document.getElementById('kgh-availability-status');
  const tableWrap = document.getElementById('kgh-availability-table');

  function esc(str){ return String(str || '').replace(/[&<>]/g, s=>({ '&':'&amp;','<':'&lt;','>':'&gt;' }[s])); }

  function todayKST(){
    const now = new Date();
    const utc = now.getTime() + (now.getTimezoneOffset() * 60000);
    const kst = new Date(utc + 9 * 3600000);
    return formatDate(kst);
  }
  function addDaysKST(start, days){
    const parts = start.split('-');
    if (parts.length!==3) return start;
    const base = new Date(Date.UTC(parseInt(parts[0],10), parseInt(parts[1],10)-1, parseInt(parts[2],10)));
    const kst = new Date(base.getTime() + 9*3600000);
    kst.setUTCDate(kst.getUTCDate() + days);
    return formatDate(kst);
  }
  function formatDate(d){
    const y = d.getUTCFullYear();
    const m = String(d.getUTCMonth()+1).padStart(2,'0');
    const da = String(d.getUTCDate()).padStart(2,'0');
    return `${y}-${m}-${da}`;
  }

  function populateTours(){
    selTour.innerHTML='';
    data.tours.forEach(t=>{
      const opt=document.createElement('option');
      opt.value = t.id;
      opt.textContent = t.title;
      selTour.appendChild(opt);
    });
    if (state.tourId) selTour.value = state.tourId;
  }

  function setFilters(){
    inputStart.value = state.start;
    inputEnd.value = state.end;
  }

  function setStatus(msg,type='info'){ statusBox.innerHTML = msg ? `<div class="notice notice-${type}"><p>${esc(msg)}</p></div>` : ''; }

  async function loadData(){
    if (!state.tourId){ setStatus('Select a tour','error'); return; }
    setStatus('');
    tableWrap.innerHTML = '<p>Loading…</p>';
    const params = new URLSearchParams({ tour_id: state.tourId, start: state.start, end: state.end });
    try {
      const res = await fetch(`${data.restBase}/admin/availability?${params.toString()}`, {
        headers:{ 'X-WP-Nonce': data.nonce }
      });
      const body = await res.json();
      if (!res.ok){
        tableWrap.innerHTML = '';
        setStatus(body.message || 'Error loading data','error');
        return;
      }
      renderTable(body);
    } catch(err) {
      tableWrap.innerHTML='';
      setStatus('Network error','error');
    }
  }

  function renderTable(payload){
    if (!payload || !payload.time_slots){ tableWrap.innerHTML='<p>No data.</p>'; return; }
    const table=document.createElement('table');
    table.className='widefat fixed striped kgh-avail-table';
    const thead=document.createElement('thead');
    const headRow=document.createElement('tr');
    const thDate=document.createElement('th'); thDate.textContent='Date'; headRow.appendChild(thDate);
    const times = (payload.meta && Array.isArray(payload.meta.time_slots) && payload.meta.time_slots.length) ? payload.meta.time_slots : payload.time_slots;
    times.forEach(time=>{
      const th=document.createElement('th'); th.textContent=time; headRow.appendChild(th);
    });
    thead.appendChild(headRow);
    table.appendChild(thead);

    const tbody=document.createElement('tbody');
    payload.days.forEach(day=>{
      const tr=document.createElement('tr');
      const tdDate=document.createElement('td');
      tdDate.innerHTML=`<strong>${esc(day.date)}</strong><br><span style="opacity:.7;">${esc(day.day_label || '')}</span>`;
      tr.appendChild(tdDate);
      times.forEach(time=>{
        const cellData = day.slots && day.slots[time] ? day.slots[time] : null;
        const td=document.createElement('td');
        td.dataset.date = day.date;
        td.dataset.time = time;
        if (!cellData) {
          td.innerHTML = '<em>—</em>';
        } else {
          td.innerHTML = cellMarkup(cellData);
        }
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
    table.appendChild(tbody);
    tableWrap.innerHTML='';
    tableWrap.appendChild(table);
  }

  function cellMarkup(cell){
    const badgeClass = cell.status ? `kgh-badge ${cell.status}` : 'kgh-badge';
    const badgeLabel = statusLabel(cell.status);
    const leftLabel = cell.left !== null && cell.left !== undefined ? `${esc(data.i18n.leftLabel || 'Left')}: ${cell.left}` : '';
    const capVal = cell.override && cell.override.cap !== null ? cell.override.cap : '';
    const priceVal = cell.override && cell.override.price_usd !== null ? cell.override.price_usd : '';
    const langVal = cell.override && cell.override.lang ? cell.override.lang : '';
    const externalVal = cell.external_qty || '';
    const note = (cell.closed && (cell.override && cell.override.cap !== null && cell.override.cap > 0)) ? '<div class="kgh-note">Closed overrides capacity to 0.</div>' : '';
    return `
      <div class="kgh-cell-status">
        <span class="${badgeClass}">${esc(badgeLabel)}</span>
        <span>${leftLabel}</span>
      </div>
      <div class="kgh-cell-controls">
        <label><input type="checkbox" class="kgh-field-closed" ${cell.closed ? 'checked' : ''}> Closed</label>
        <label>Override cap <input type="number" min="0" max="50" class="kgh-field-cap" value="${capVal}"></label>
        <label>Override price (USD cents) <input type="number" min="0" class="kgh-field-price" value="${priceVal}"></label>
        <label>Override language
          <select class="kgh-field-lang">
            <option value="">—</option>
            <option value="EN" ${langVal==='EN'?'selected':''}>EN</option>
            <option value="FR" ${langVal==='FR'?'selected':''}>FR</option>
            <option value="KO" ${langVal==='KO'?'selected':''}>KO</option>
          </select>
        </label>
        <label>External booked <input type="number" min="0" max="50" class="kgh-field-external" value="${externalVal}"></label>
      </div>
      <div class="kgh-cell-actions">
        <button type="button" class="button button-primary kgh-cell-save">${esc(data.i18n.save || 'Save')}</button>
        <button type="button" class="button kgh-cell-clear">${esc(data.i18n.clear || 'Clear')}</button>
      </div>
      ${note}
    `;
  }

  function statusLabel(status){
    switch(status){
      case 'closed': return data.i18n.statusClosed || 'Closed';
      case 'cutoff': return data.i18n.statusCutoff || 'Cutoff';
      case 'sold_out': return data.i18n.statusSoldOut || 'Sold out';
      default: return data.i18n.statusOpen || 'Open';
    }
  }

  tableWrap.addEventListener('click', async (e)=>{
    const saveBtn = e.target.closest('.kgh-cell-save');
    const clearBtn = e.target.closest('.kgh-cell-clear');
    if (!saveBtn && !clearBtn) return;
    const cell = e.target.closest('td');
    const date = cell ? cell.dataset.date : null;
    const time = cell ? cell.dataset.time : null;
    if (!date || !time){ setStatus('Invalid cell','error'); return; }

    if (clearBtn) {
      await sendSave({ action:'clear', date, time }, cell);
      return;
    }

    const closed = cell.querySelector('.kgh-field-closed')?.checked || false;
    const capField = cell.querySelector('.kgh-field-cap');
    const priceField = cell.querySelector('.kgh-field-price');
    const langField = cell.querySelector('.kgh-field-lang');
    const externalField = cell.querySelector('.kgh-field-external');

    const payload = { date, time };
    payload.closed = closed;
    if (capField) payload.override_cap = capField.value === '' ? null : parseInt(capField.value,10);
    if (priceField) payload.override_price_usd = priceField.value === '' ? null : parseInt(priceField.value,10);
    if (langField) payload.override_lang = langField.value === '' ? null : langField.value;
    if (externalField) payload.external_booked = externalField.value === '' ? null : parseInt(externalField.value,10);

    await sendSave(payload, cell);
  });

  async function sendSave(payload, cell){
    const body = Object.assign({ tour_id: state.tourId }, payload);
    try {
      const res = await fetch(`${data.restBase}/admin/availability`, {
        method:'POST',
        headers:{ 'Content-Type':'application/json','X-WP-Nonce': data.nonce },
        body: JSON.stringify(body)
      });
      const json = await res.json();
      if (!res.ok){
        setStatus(json.message || 'Save error','error');
        return;
      }
      setStatus(data.i18n.saved || 'Saved','success');
      if (cell && json.cell) {
        cell.innerHTML = cellMarkup(json.cell);
      } else {
        await loadData();
      }
    } catch(err) {
      setStatus('Network error','error');
    }
  }

  selTour.addEventListener('change', ()=>{
    state.tourId = parseInt(selTour.value,10) || 0;
  });
  inputStart.addEventListener('change', ()=>{ state.start = inputStart.value; });
  inputEnd.addEventListener('change', ()=>{ state.end = inputEnd.value; });
  btnLoad.addEventListener('click', ()=>{
    if (!state.tourId){ setStatus('Select a tour','error'); return; }
    if (!state.start || !state.end){ setStatus('Select a range','error'); return; }
    if (state.start > state.end) { setStatus('Start date must be before end date','error'); return; }
    loadData();
  });

  populateTours();
  setFilters();
  if (state.tourId) loadData();
})();
