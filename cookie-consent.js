(function(){
  var GA_ID = 'G-KCTE2H0J0N';
  var STORAGE_KEY = 'pp_cookie_consent';

  function loadGA(){
    var s = document.createElement('script');
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + GA_ID;
    document.head.appendChild(s);
    window.dataLayer = window.dataLayer || [];
    window.gtag = function(){ window.dataLayer.push(arguments); };
    window.gtag('js', new Date());
    window.gtag('config', GA_ID);
  }

  function getConsent(){ try{ return localStorage.getItem(STORAGE_KEY); }catch(e){ return null; } }
  function setConsent(v){ try{ localStorage.setItem(STORAGE_KEY, v); }catch(e){} }

  function showMiniBtn(){
    if(document.getElementById('pp-cookie-mini')) return;
    var btn = document.createElement('button');
    btn.id = 'pp-cookie-mini';
    btn.title = 'Evästeasetukset';
    btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#1E3A5F"><path d="M21.9 12.5a9.9 9.9 0 0 1-9.4 9.5A10 10 0 0 1 2 12C2 6.5 6.5 2 12 2c.3 0 .7 0 1 .1a3 3 0 0 0 3 3 3 3 0 0 0 3 3 3 3 0 0 0 2.9 4.4zM10 8.5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm-2 5a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm5 4a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm4-6a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/></svg>';
    btn.style.cssText = 'position:fixed;bottom:1rem;left:1rem;z-index:998;background:transparent;border:2px solid #1E3A5F;border-radius:50%;width:36px;height:36px;padding:6px;cursor:pointer;opacity:.6;transition:opacity .2s;display:flex;align-items:center;justify-content:center';
    btn.onmouseenter = function(){ btn.style.opacity='1'; };
    btn.onmouseleave = function(){ btn.style.opacity='.75'; };
    btn.onclick = function(){
      btn.remove();
      showBanner();
    };
    document.body.appendChild(btn);
  }

  function showBanner(){
    if(document.getElementById('pp-cookie-banner')) return;
    var banner = document.createElement('div');
    banner.id = 'pp-cookie-banner';
    banner.innerHTML =
      '<p>Käytämme Google Analytics -evästeitä sivuston käytön analysointiin. ' +
      'Tiedot ovat anonyymejä. <a href="tietosuoja.html">Lue lisää</a>.</p>' +
      '<div class="pp-cookie-btns">' +
        '<button id="pp-cookie-accept">Hyväksy</button>' +
        '<button id="pp-cookie-reject">Hylkää</button>' +
      '</div>';
    document.body.appendChild(banner);

    document.getElementById('pp-cookie-accept').addEventListener('click', function(){
      setConsent('accepted');
      banner.remove();
      loadGA();
      showMiniBtn();
    });
    document.getElementById('pp-cookie-reject').addEventListener('click', function(){
      setConsent('rejected');
      banner.remove();
      showMiniBtn();
    });
  }

  // If already decided, load GA if accepted and show mini button
  var consent = getConsent();
  if(consent === 'accepted') { loadGA(); }
  if(consent !== null) {
    if(document.readyState === 'loading'){
      document.addEventListener('DOMContentLoaded', showMiniBtn);
    } else {
      showMiniBtn();
    }
    return;
  }

  // First visit — show full banner
  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', showBanner);
  } else {
    showBanner();
  }
})();
