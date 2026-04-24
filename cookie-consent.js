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

  // If already accepted, load GA immediately
  var consent = getConsent();
  if(consent === 'accepted') { loadGA(); return; }
  if(consent === 'rejected') { return; }

  // Show banner on first visit
  function showBanner(){
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
    });
    document.getElementById('pp-cookie-reject').addEventListener('click', function(){
      setConsent('rejected');
      banner.remove();
    });
  }

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', showBanner);
  } else {
    showBanner();
  }
})();
