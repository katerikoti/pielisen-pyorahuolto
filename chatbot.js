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
    #ketju-toggle{position:fixed;right:1.5rem;bottom:1.5rem;width:56px;height:56px;border-radius:50%;background:${ACCENT};color:#fff;font-size:1.2rem;cursor:pointer;z-index:9999;display:flex;align-items:center;justify-content:center;box-shadow:0 10px 30px rgba(0,0,0,.18);border:1px solid #fff}
    #ketju-window{position:fixed;right:1.5rem;bottom:6.25rem;width:360px;max-width:calc(100vw - 2rem);background:#fff;border-radius:12px;box-shadow:0 14px 40px rgba(0,0,0,.18);overflow:hidden;font-family:system-ui,Arial,sans-serif;z-index:9998;display:flex;flex-direction:column;border:1px solid #fff}
    #ketju-header{background:${PRIMARY};color:#fff;padding:.7rem 1rem;display:flex;align-items:center;gap:.6rem}
    #ketju-avatar{width:36px;height:36px;border-radius:50%;background:${ACCENT};display:flex;align-items:center;justify-content:center;font-weight:800}
    #ketju-avatar svg{width:20px;height:20px;display:block}
    #ketju-messages{padding:1rem;max-height:320px;overflow:auto;display:flex;flex-direction:column;gap:.6rem;background:#fafafa}
    .k-msg{display:inline-block;padding:8px 10px;border-radius:12px;max-width:82%;word-break:break-word}
    .k-user{background:${ACCENT};color:#fff;align-self:flex-end}
    .k-bot{background:#eee;color:#000;align-self:flex-start}
    #ketju-input-row{display:flex;gap:.5rem;padding:.7rem;border-top:1px solid #eee;background:#fff}
    #ketju-input{flex:1;padding:.6rem .9rem;border-radius:18px;border:1px solid #e6e6e6}
    #ketju-send{background:${ACCENT};color:#000;border:none;border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center;cursor:pointer}
    #ketju-quick{padding:.5rem 1rem;display:flex;gap:.5rem;flex-wrap:wrap;background:#fff}
    .k-quick{background:${PRIMARY};color:#fff;padding:.4rem .7rem;border-radius:8px;font-size:.85rem;cursor:pointer}
    .k-hidden{display:none}
    `;
  document.head.appendChild(style);

  // Build DOM
  const toggle = document.createElement('button'); toggle.id='ketju-toggle'; toggle.title='Ketju — keskustele'; toggle.innerHTML=`<svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><g fill="none" fill-rule="evenodd"><rect x="8" y="10" width="32" height="24" rx="4" fill="#fff"/><rect x="18" y="4" width="12" height="6" rx="3" fill="#fff"/><circle cx="18" cy="22" r="2" fill="#1E3A5F"/><circle cx="30" cy="22" r="2" fill="#1E3A5F"/><rect x="21" y="26" width="6" height="2" rx="1" fill="#1E3A5F"/></g></svg>`;
  const win = document.createElement('div'); win.id='ketju-window'; win.classList.add('k-hidden');
  win.innerHTML = `
    <div id="ketju-header"><div id="ketju-avatar"><svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><g fill="none" fill-rule="evenodd"><rect x="6" y="12" width="36" height="26" rx="5" fill="#ffffff"/><rect x="18" y="6" width="12" height="6" rx="3" fill="#ffffff"/><circle cx="20" cy="26" r="3" fill="#1E3A5F"/><circle cx="28" cy="26" r="3" fill="#1E3A5F"/><rect x="21" y="30" width="6" height="2" rx="1" fill="#1E3A5F"/></g></svg></div><div style="flex:1"><strong>${BOT_NAME}</strong><div style="font-size:.8rem;color:rgba(255,255,255,.8)">Auttaa ajanvarauksissa ja kysymyksissä</div></div><button id="ketju-close" style="background:none;border:none;color:rgba(255,255,255,.9);font-size:1.1rem;cursor:pointer">✕</button></div>
    <div id="ketju-messages"></div>
    <div id="ketju-quick"><button class="k-quick" data-action="book">Varaa aika</button><button class="k-quick" data-action="hinta">Hinnoittelu</button><button class="k-quick" data-action="faq">UKK</button></div>
    <div id="ketju-input-row"><input id="ketju-input" placeholder="Kirjoita viesti..." autocomplete="off"><button id="ketju-send">➤</button></div>
  `;
  document.body.appendChild(toggle); document.body.appendChild(win);

  const msgs = win.querySelector('#ketju-messages');
  const input = win.querySelector('#ketju-input');
  const sendBtn = win.querySelector('#ketju-send');
  const closeBtn = win.querySelector('#ketju-close');

  function addMsg(text, who='bot'){
    const d = document.createElement('div'); d.className = 'k-msg ' + (who==='user'?'k-user':'k-bot'); d.innerHTML = text; msgs.appendChild(d); msgs.scrollTop = msgs.scrollHeight;
  }

  function show(){ win.classList.remove('k-hidden'); }
  function hide(){ win.classList.add('k-hidden'); }

  toggle.addEventListener('click', ()=>{ if (win.classList.contains('k-hidden')) show(); else hide(); });
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
    addMsg('<em>Kirjoittaa...</em>','bot');
    try{
      const state = loadState();
      const history = state?.history || [];
      const r = await fetch('chatbot-api.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({message:text, history})});
      const data = await r.json();
      // remove typing
      const t = msgs.querySelector('div em'); if (t) t.parentElement.remove();
      const reply = data.reply || 'En pysty vastaamaan juuri nyt. Soita 013 456 7890';
      addMsg(reply,'bot');
      pushHistory('user', text); pushHistory('assistant', reply); saveState();
      // If bot asked to book (heuristic)
      if (/varaa|ajanvaraus|varaus|varaa aika/i.test(text) || /varaa|ajanvaraus|varaus|varaa aika/i.test(reply)){
        // do not auto-start, but suggest booking quick action
        addMsg('<span style="color:'+PRIMARY+';font-weight:700">Voit varata ajan painamalla "Varaa aika" -painiketta.</span>','bot');
      }
    }catch(e){
      const t = msgs.querySelector('div em'); if (t) t.parentElement.remove();
      addMsg('Chatbot ei ole käytettävissä. Soita 013 456 7890','bot');
    }
  }

  sendBtn.addEventListener('click', ()=>{const v=input.value.trim(); if(!v) return; input.value=''; sendToBot(v);});
  input.addEventListener('keydown', e=>{ if(e.key==='Enter'){ e.preventDefault(); sendBtn.click(); } });

  // Minimal state for history
  function loadState(){ try{ return JSON.parse(sessionStorage.getItem(STORAGE_KEY) || 'null') || {history:[]} }catch(e){return {history:[]}} }
  function saveState(){ try{ sessionStorage.setItem(STORAGE_KEY, JSON.stringify({history:history})) }catch(e){}
  }
  const history = loadState().history || [];
  function pushHistory(role, content){ history.push({role, content}); if(history.length>12) history.shift(); }

  // Booking flow: simple inline wizard
  async function startBookingFlow(){
    // Step: ask date
    const date = prompt('Valitse päivämäärä (YYYY-MM-DD). Huom: ei menneitä päiviä.');
    if(!date) return addMsg('Peruttu. Jos haluat varata myöhemmin, paina "Varaa aika".','bot');
    // Fetch booked slots
    addMsg('Haetaan vapaita aikoja '+date+'...','bot');
    try{
      const r = await fetch('slots.php?date='+encodeURIComponent(date));
      const data = await r.json();
      const booked = data.booked || [];
      // Allowed slots same as varaus.php
      const dow = new Date(date).getDay();
      const allowed = dow===0?[]:(dow===6?['10:00','11:00','12:00','13:00']:['09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00']);
      const free = allowed.filter(s=>!booked.includes(s));
      if(!free.length) return addMsg('Valitettavasti valitsemanasi päivänä ei ole vapaita aikoja. Yritä toinen päivä.','bot');
      const slot = prompt('Vapaat ajat: '+free.join(', ')+'\nKirjoita valitsemasi kellonaika (esim. 10:00)');
      if(!slot || !free.includes(slot)) return addMsg('Aikavalinta ei kelpaa tai peruutettu.','bot');
      // collect contact info
      const nimi = prompt('Anna nimesi'); if(!nimi) return addMsg('Peruttu.','bot');
      const puhelin = prompt('Puhelinnumero'); if(!puhelin) return addMsg('Peruttu.','bot');
      const email = prompt('Sähköposti (vahvistus lähetetään tähän)'); if(!email) return addMsg('Peruttu.','bot');
      const pyora = prompt('Pyörätyyppi (tavallinen / sahkopyora / lapsi / muu)', 'tavallinen');
      const palvelu = prompt('Palvelu (perushuolto / tayshuolto / sahko_huolto / rengaskorjaus / muu)', 'perushuolto');
      const lisatiedot = prompt('Lisätiedot (valinnainen)', '');

      // Confirm
      const confirmMsg = `Vahvista varaus: ${date} klo ${slot} - ${palvelu} - ${nimi} (${puhelin})`;
      if(!confirm(confirmMsg)) return addMsg('Peruutettu.','bot');

      // Submit to varaus.php
      const fd = new FormData();
      fd.append('nimi', nimi);
      fd.append('puhelin', puhelin);
      fd.append('email', email);
      fd.append('toivottu_pvm', date);
      fd.append('toivottu_aika', slot);
      fd.append('pyora_tyyppi', pyora);
      fd.append('palvelu', palvelu);
      fd.append('lisatiedot', lisatiedot);

      addMsg('Lähetetään varaus...','bot');
      const submit = await fetch('varaus.php', {method:'POST', body:fd, redirect:'manual'});
      if (submit.status===302 || submit.redirected) {
        addMsg('Varaus vastaanotettu. Saat vahvistuksen sähköpostiisi. Kiitos!','bot');
      } else {
        // varaus.php redirects on success; if not, try to detect error
        addMsg('Varaus lähetetty — tarkista sähköposti. Jos ongelma, soita 013 456 7890','bot');
      }

    }catch(e){
      addMsg('Virhe haettaessa vapaita aikoja. Yritä uudelleen tai soita 013 456 7890','bot');
    }
  }

})();
