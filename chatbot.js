/* chatbot.js — Ketju for Pielisen Pyörähuolto */
(function(){
  const BOT_NAME = 'Ketju';
  const STORAGE_KEY = 'pielisen_ketju_v1';

  // Colors use site CSS variables when available
  const ACCENT = getComputedStyle(document.documentElement).getPropertyValue('--yellow') || '#F5C518';
  const PRIMARY = getComputedStyle(document.documentElement).getPropertyValue('--blue') || '#1E3A5F';

  // Create styles
  const style = document.createElement('style');
  style.textContent = `
    #ketju-toggle{position:fixed;right:1.5rem;bottom:1.5rem;width:60px;height:60px;border-radius:50%;background:${ACCENT};color:#fff;font-size:1.3rem;cursor:pointer;z-index:9999;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 30px rgba(0,0,0,.18);border:1px solid #fff;padding:0;line-height:0;overflow:visible}
    #ketju-window{position:fixed;right:1.5rem;bottom:6.25rem;width:360px;max-width:calc(100vw - 2rem);background:#fff;border-radius:12px;box-shadow:0 14px 40px rgba(0,0,0,.18);overflow:hidden;font-family:system-ui,Arial,sans-serif;z-index:9998;display:flex;flex-direction:column;border:1px solid #fff}
    #ketju-header{background:${PRIMARY};color:#fff;padding:.7rem 1rem;display:flex;align-items:center;gap:.6rem}
    #ketju-avatar{width:36px;height:36px;border-radius:50%;background:${ACCENT};display:flex;align-items:center;justify-content:center;font-weight:800}
    #ketju-avatar svg{width:20px;height:20px;display:block}
    #ketju-toggle svg{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:44px;height:44px;display:block}
    #ketju-messages{padding:1rem;max-height:320px;overflow:auto;display:flex;flex-direction:column;gap:.6rem;background:#fafafa}
    .k-msg{display:inline-block;padding:8px 10px;border-radius:12px;max-width:82%;word-break:break-word}
    .k-user{background:${ACCENT};color:#fff;align-self:flex-end}
    .k-bot{background:#eee;color:#000;align-self:flex-start}
    #ketju-input-row{display:flex;gap:.5rem;padding:.7rem;border-top:1px solid #eee;background:#fff}
    #ketju-input{flex:1;padding:.6rem .9rem;border-radius:18px;border:1px solid #e6e6e6}
    #ketju-send{background:${ACCENT};color:#000;border:none;border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center;cursor:pointer}
    #ketju-quick{padding:.5rem 1rem;display:flex;gap:.5rem;flex-wrap:wrap;background:#fff}
    .k-quick{background:${PRIMARY};color:#fff;padding:.4rem .7rem;border-radius:8px;font-size:.85rem;cursor:pointer}
    .k-hidden{display:none !important}
    /* new-message badge */
    /* place badge so its midpoint lies on the toggle border at 45° */
    #ketju-badge{position:absolute;top:50%;left:50%;width:20px;height:20px;border-radius:50%;background:#e11;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;box-shadow:0 4px 10px rgba(0,0,0,.18);--offset:21.213px;transform:translate(calc(-50% + var(--offset)), calc(-50% - var(--offset))) scale(0);opacity:0;transition:transform .18s ease,opacity .18s ease}
    #ketju-badge.show{transform:translate(calc(-50% + var(--offset)), calc(-50% - var(--offset))) scale(1);opacity:1;animation:pulse 1.4s infinite}
    @keyframes pulse{0%{box-shadow:0 0 0 0 rgba(225,17,17,0.7)}50%{box-shadow:0 0 0 8px rgba(225,17,17,0)}100%{box-shadow:0 0 0 0 rgba(225,17,17,0)}}
    .k-wizard{background:#fff;border:1.5px solid #dde4ef;border-radius:10px;padding:12px;width:calc(100% - 26px);align-self:stretch;font-size:.87rem;box-sizing:border-box}
    .k-wizard input,.k-wizard select,.k-wizard textarea{width:100%;padding:6px 9px;border:1px solid #ddd;border-radius:7px;font-size:.85rem;font-family:inherit;color:#111;box-sizing:border-box;margin-top:3px}
    .k-wizard textarea{resize:none;height:52px}
    .k-wizard-title{font-weight:700;font-size:.88rem;color:${PRIMARY};margin-bottom:8px}
    .kw-field{margin-top:7px}
    .kw-field label{font-size:.78rem;font-weight:700;color:#555;display:block}
    .k-slots{display:flex;flex-wrap:wrap;gap:5px;margin-top:8px}
    .k-slot{padding:5px 11px;border:1.5px solid ${PRIMARY};border-radius:7px;font-size:.82rem;cursor:pointer;background:#fff;color:${PRIMARY};font-weight:600;transition:all .15s}
    .k-slot:hover{background:${PRIMARY};color:#fff}
    .k-wiz-btn{width:100%;margin-top:10px;padding:8px;background:${ACCENT};border:none;border-radius:8px;font-weight:700;font-size:.88rem;cursor:pointer;color:#000;transition:filter .15s}
    .k-wiz-btn:hover{filter:brightness(.93)}
    .kw-err{color:#c00;font-size:.78rem;margin-top:5px;display:none}
    .k-wiz-done{opacity:.5;pointer-events:none}
    `;
  document.head.appendChild(style);

  // Build DOM
  const toggle = document.createElement('button'); toggle.id='ketju-toggle'; toggle.title='Ketju — keskustele'; toggle.innerHTML=`<svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><g fill="none" fill-rule="evenodd"><rect x="8" y="10" width="32" height="24" rx="4" fill="#fff"/><rect x="18" y="4" width="12" height="6" rx="3" fill="#fff"/><circle cx="18" cy="22" r="2" fill="#1E3A5F"/><circle cx="30" cy="22" r="2" fill="#1E3A5F"/><rect x="21" y="26" width="6" height="2" rx="1" fill="#1E3A5F"/></g></svg>`;
  // badge for new-message indicator (hidden initially)
  const badge = document.createElement('span'); badge.id = 'ketju-badge'; badge.textContent = '1'; badge.classList.remove('show');
  const win = document.createElement('div'); win.id='ketju-window'; win.classList.add('k-hidden');
  win.innerHTML = `
    <div id="ketju-header"><div id="ketju-avatar"><svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><g fill="none" fill-rule="evenodd"><rect x="6" y="12" width="36" height="26" rx="5" fill="#ffffff"/><rect x="18" y="6" width="12" height="6" rx="3" fill="#ffffff"/><circle cx="20" cy="26" r="3" fill="#1E3A5F"/><circle cx="28" cy="26" r="3" fill="#1E3A5F"/><rect x="21" y="30" width="6" height="2" rx="1" fill="#1E3A5F"/></g></svg></div><div style="flex:1"><strong>${BOT_NAME}</strong><div style="font-size:.8rem;color:rgba(255,255,255,.8)">Auttaa ajanvarauksissa ja kysymyksissä</div></div><button id="ketju-close" style="background:none;border:none;color:rgba(255,255,255,.9);font-size:1.1rem;cursor:pointer">✕</button></div>
    <div id="ketju-messages"></div>
    <div id="ketju-quick"><button class="k-quick" data-action="book">Varaa aika</button><button class="k-quick" data-action="hinta">Hinnoittelu</button><button class="k-quick" data-action="faq">UKK</button></div>
    <div id="ketju-input-row"><input id="ketju-input" placeholder="Kirjoita viesti..." autocomplete="off"><button id="ketju-send">➤</button></div>
  `;
  document.body.appendChild(toggle); document.body.appendChild(win);
  toggle.appendChild(badge);

  const msgs = win.querySelector('#ketju-messages');
  const input = win.querySelector('#ketju-input');
  const sendBtn = win.querySelector('#ketju-send');
  const closeBtn = win.querySelector('#ketju-close');

  /* ── Session persistence ───────────────────────────────────────────
   * Distinguish navigation (keep chat) from refresh/new-load (clear chat).
   * Before any same-site link click we set a one-shot 'navigating' flag.
   * On load: if the flag is present → navigation → restore state.
   * If absent → refresh or brand-new tab → clear state.
   */
  const wasNavigating = sessionStorage.getItem('ketju_nav') === '1';
  sessionStorage.removeItem('ketju_nav'); // consume immediately
  if(!wasNavigating){ try{ sessionStorage.removeItem(STORAGE_KEY); }catch(e){} }

  // Tag every same-origin link click so the next page knows it was a navigation
  document.addEventListener('click', e=>{
    const a = e.target.closest('a[href]');
    if(a && a.hostname === location.hostname && !a.target){
      try{ sessionStorage.setItem('ketju_nav','1'); }catch(e){}
    }
  }, true);

  function loadState(){ try{ return JSON.parse(sessionStorage.getItem(STORAGE_KEY) || 'null') || {history:[],msgs:[],badgeSeen:false} }catch(e){return {history:[],msgs:[],badgeSeen:false}} }
  function saveState(){ try{ sessionStorage.setItem(STORAGE_KEY, JSON.stringify({history, msgs: savedMsgs, badgeSeen})); }catch(e){} }

  const state = loadState();
  const history = state.history || [];
  const savedMsgs = state.msgs || [];
  let badgeSeen = state.badgeSeen || false;

  function pushHistory(role, content){ history.push({role, content}); if(history.length>12) history.shift(); }

  function addMsg(text, who='bot', save=true){
    const d = document.createElement('div'); d.className = 'k-msg ' + (who==='user'?'k-user':'k-bot'); d.innerHTML = text;
    msgs.appendChild(d); msgs.scrollTop = msgs.scrollHeight;
    if(save){ savedMsgs.push({text, who}); saveState(); }
  }

  // Restore previous messages from this session (navigation without refresh)
  savedMsgs.forEach(m => addMsg(m.text, m.who, false));

  // Greet on first open (no prior messages this session)
  if(!savedMsgs.length){
    addMsg('Hei, olen Ketju, Pielisen Pyörähuollon avustaja. Miten voin auttaa?', 'bot');
  }

  function show(){ win.classList.remove('k-hidden'); saveState(); }
  function hide(){ win.classList.add('k-hidden'); saveState(); }
  // Chat window always starts closed — user must click to open

  toggle.addEventListener('click', ()=>{
    if(win.classList.contains('k-hidden')) show(); else hide();
    badge.classList.remove('show');
    badgeSeen = true;
    saveState();
  });

  // Show badge after 3s only if not already seen this session
  if(!badgeSeen){
    setTimeout(()=>{ try{ badge.classList.add('show'); }catch(e){} }, 3000);
  }

  closeBtn.addEventListener('click', hide);

  // Quick actions
  win.querySelectorAll('.k-quick').forEach(b=>b.addEventListener('click', async ev=>{
    const action = ev.currentTarget.dataset.action;
    if (action==='book') return startBookingFlow();
    if (action==='hinta') return addMsg('Perushuolto 39 €, Täyshuolto 89 €, Sähköpyörä alkaen 55 €, Rengaskorjaus alkaen 15 €');
    if (action==='faq') return window.location.href='ukk.html';
  }));

  // Send chat to backend
  async function sendToBot(text){
    addMsg(text,'user');
    // Detect booking intent — launch inline wizard directly
    if(/varaa\s+aika|haluan\s+varata|haluaisin\s+varata|varaan\s+ajan|tahdon\s+varata/i.test(text)){
      addMsg('Tehdään varaus täällä suoraan! 👇','bot');
      startBookingFlow();
      return;
    }
    addMsg('<em>Kirjoittaa...</em>','bot', false);
    try{
      const r = await fetch('chatbot-api.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({message:text, history})});
      const data = await r.json();
      const t = msgs.querySelector('div em'); if (t) t.parentElement.remove();
      const reply = data.reply || 'En pysty vastaamaan juuri nyt. Soita 013 456 7890';
      addMsg(reply,'bot');
      pushHistory('user', text); pushHistory('assistant', reply); saveState();
    }catch(e){
      const t = msgs.querySelector('div em'); if (t) t.parentElement.remove();
      addMsg('Chatbot ei ole käytettävissä. Soita 013 456 7890','bot');
    }
  }

  sendBtn.addEventListener('click', ()=>{const v=input.value.trim(); if(!v) return; input.value=''; sendToBot(v);});
  input.addEventListener('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); sendBtn.click(); } });

  // Finnish date formatter
  function fmtDateFi(d){
    const [y,m,day]=d.split('-');
    const mo=['tammikuuta','helmikuuta','maaliskuuta','huhtikuuta','toukokuuta','kesäkuuta','heinäkuuta','elokuuta','syyskuuta','lokakuuta','marraskuuta','joulukuuta'];
    return `${parseInt(day)}. ${mo[parseInt(m)-1]} ${y}`;
  }

  // Render a wizard card into the chat (not saved to session)
  function wizCard(html){
    const d=document.createElement('div');
    d.className='k-msg k-bot k-wizard';
    d.innerHTML=html;
    msgs.appendChild(d);
    msgs.scrollTop=msgs.scrollHeight;
    return d;
  }

  // Booking flow — inline multi-step wizard
  function startBookingFlow(){
    const todayStr=new Date().toISOString().split('T')[0];

    // ── Step 1: date picker ──────────────────────────────
    const s1=wizCard(`
      <div class="k-wizard-title">📅 Valitse päivä</div>
      <input type="date" id="kw-date" min="${todayStr}">
      <button class="k-wiz-btn" id="kw-s1-ok">Hae vapaat ajat →</button>
    `);

    s1.querySelector('#kw-s1-ok').addEventListener('click', async ()=>{
      const date=s1.querySelector('#kw-date').value;
      if(!date) return;
      s1.classList.add('k-wiz-done');

      const loading=wizCard('<em>Haetaan vapaita aikoja…</em>');
      try{
        const r=await fetch('slots.php?date='+encodeURIComponent(date));
        const data=await r.json();
        loading.remove();
        const dow=new Date(date+'T12:00:00').getDay();
        const allSlots=dow===0?[]:(dow===6?['10:00','11:00','12:00','13:00']:['09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00']);
        const free=allSlots.filter(s=>!(data.booked||[]).includes(s));
        if(!free.length){
          addMsg('Ei vapaita aikoja sinä päivänä — valitse toinen päivä.','bot');
          s1.classList.remove('k-wiz-done');
          return;
        }

        // ── Step 2: time slot buttons ─────────────────────
        const s2=wizCard(`
          <div class="k-wizard-title">🕐 ${fmtDateFi(date)} — valitse aika</div>
          <div class="k-slots">${free.map(s=>`<button class="k-slot" data-s="${s}">${s}</button>`).join('')}</div>
        `);

        s2.querySelectorAll('.k-slot').forEach(btn=>{
          btn.addEventListener('click',()=>{
            const slot=btn.dataset.s;
            s2.classList.add('k-wiz-done');

            // ── Step 3: contact form ───────────────────────
            const s3=wizCard(`
              <div class="k-wizard-title">✏️ ${fmtDateFi(date)} klo ${slot}</div>
              <div class="kw-field"><label>Nimi *</label><input id="kw-nimi" type="text" placeholder="Etu- ja sukunimi"></div>
              <div class="kw-field"><label>Puhelin *</label><input id="kw-puh" type="tel" placeholder="040 1234567"></div>
              <div class="kw-field"><label>Sähköposti *</label><input id="kw-email" type="email" placeholder="nimi@esimerkki.fi"></div>
              <div class="kw-field"><label>Palvelu</label>
                <select id="kw-palvelu">
                  <option value="perushuolto">Perushuolto (39 €)</option>
                  <option value="tayshuolto">Täyshuolto (89 €)</option>
                  <option value="sahko_huolto">Sähköpyörän huolto (alkaen 55 €)</option>
                  <option value="rengaskorjaus">Rengaskorjaus (alkaen 15 €)</option>
                  <option value="muu">Muu / kerron lisää</option>
                </select>
              </div>
              <div class="kw-field"><label>Pyörätyyppi</label>
                <select id="kw-pyora">
                  <option value="tavallinen">Tavallinen polkupyörä</option>
                  <option value="sahkopyora">Sähköpyörä</option>
                  <option value="lapsi">Lasten pyörä</option>
                  <option value="muu">Muu</option>
                </select>
              </div>
              <div class="kw-field"><label>Lisätiedot</label><textarea id="kw-lisatiedot" placeholder="Valinnainen"></textarea></div>
              <p id="kw-err" class="kw-err">Täytä nimi, puhelin ja sähköposti.</p>
              <button class="k-wiz-btn" id="kw-submit">Vahvista varaus ✓</button>
            `);

            s3.querySelector('#kw-submit').addEventListener('click', async()=>{
              const nimi=s3.querySelector('#kw-nimi').value.trim();
              const puh=s3.querySelector('#kw-puh').value.trim();
              const eml=s3.querySelector('#kw-email').value.trim();
              const errEl=s3.querySelector('#kw-err');
              if(!nimi||!puh||!eml){errEl.style.display='block';return;}
              errEl.style.display='none';
              s3.classList.add('k-wiz-done');

              const loading2=wizCard('<em>Lähetetään varaus…</em>');
              const fd=new FormData();
              fd.append('nimi',nimi); fd.append('puhelin',puh); fd.append('email',eml);
              fd.append('toivottu_pvm',date); fd.append('toivottu_aika',slot);
              fd.append('pyora_tyyppi',s3.querySelector('#kw-pyora').value);
              fd.append('palvelu',s3.querySelector('#kw-palvelu').value);
              fd.append('lisatiedot',s3.querySelector('#kw-lisatiedot').value.trim());
              try{
                const res=await fetch('varaus.php',{method:'POST',body:fd});
                loading2.remove();
                if(res.url&&res.url.includes('status=ok')){
                  addMsg(`✅ <strong>Varaus vahvistettu!</strong><br>${fmtDateFi(date)} klo ${slot}<br>Vahvistus lähetetty: ${eml}`,'bot');
                }else{
                  s3.classList.remove('k-wiz-done');
                  addMsg('❌ Varaus ei onnistunut. Tarkista tiedot tai soita 013 456 7890','bot');
                }
              }catch(e){
                loading2.remove();
                s3.classList.remove('k-wiz-done');
                addMsg('Yhteysvirhe. Yritä uudelleen tai soita 013 456 7890','bot');
              }
            });
          });
        });
      }catch(e){
        loading.remove();
        addMsg('Virhe aikojen haussa. Yritä uudelleen tai soita 013 456 7890','bot');
        s1.classList.remove('k-wiz-done');
      }
    });
  }

})();
