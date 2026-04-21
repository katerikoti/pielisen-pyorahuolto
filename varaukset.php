<?php
/**
 * varaukset.php – Pielisen Pyörähuolto booking management
 *
 * Password-protected page showing all bookings from MySQL.
 * Admin can change booking status (uusi → vahvistettu → valmis / peruttu).
 *
 * Same DB credentials as varaus.php – change the VAIHDA placeholders below.
 */

/* ─── Configuration ─────────────────────────────────────── */
define('DB_HOST', 'localhost');
define('DB_NAME', 'pyorahuolto_db');       // VAIHDA
define('DB_USER', 'pyorahuolto_user');     // VAIHDA
define('DB_PASS', 'VAIHDA_TAMA');          // VAIHDA
// Password for this page (change to your real admin password)
define('ADMIN_PASSWORD', 'pyora2026');     // VAIHDA

/* ─── Session auth ──────────────────────────────────────── */
session_start();

if (isset($_POST['logout'])) {
    session_destroy();
    header('Location: varaukset.php');
    exit;
}

if (isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['auth'] = true;
    } else {
        $loginError = true;
    }
}

$loggedIn = !empty($_SESSION['auth']);

/* ─── Status update action ──────────────────────────────── */
$statusMsg = '';
if ($loggedIn && isset($_POST['aktion'], $_POST['id'])) {
    $id     = (int) $_POST['id'];
    $aktion = $_POST['aktion'];
    $allowed = ['vahvistettu', 'valmis', 'peruttu', 'uusi'];
    if ($id > 0 && in_array($aktion, $allowed, true)) {
        try {
            $pdo = new PDO(
                'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
            );
            $pdo->prepare("UPDATE varaukset SET tila = :tila WHERE id = :id")
                ->execute([':tila' => $aktion, ':id' => $id]);
            $statusMsg = 'ok';
        } catch (PDOException $e) {
            $statusMsg = 'error';
        }
    }
}

/* ─── Fetch bookings ────────────────────────────────────── */
$bookings = [];
$dbError  = false;
if ($loggedIn) {
    $filter = $_GET['nayta'] ?? 'tulevat';
    try {
        $pdo = $pdo ?? new PDO(
            'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
        );
        $where = match($filter) {
            'menneet' => "WHERE toivottu_pvm < CURDATE()",
            'kaikki'  => "",
            default   => "WHERE toivottu_pvm >= CURDATE() AND tila != 'peruttu'",
        };
        $stmt = $pdo->query(
            "SELECT * FROM varaukset $where ORDER BY toivottu_pvm ASC, toivottu_aika ASC"
        );
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $dbError = true;
    }
}

/* ─── Helpers ───────────────────────────────────────────── */
$palveluLabels = [
    'perushuolto'  => 'Perushuolto',
    'tayshuolto'   => 'Täyshuolto',
    'sahko_huolto' => 'Sähköpyörän huolto',
    'rengaskorjaus'=> 'Rengaskorjaus',
    'muu'          => 'Muu',
];
$pyoraLabels = [
    'tavallinen' => 'Polkupyörä',
    'sahkopyora' => 'Sähköpyörä',
    'lapsi'      => 'Lasten pyörä',
    'muu'        => 'Muu',
];
$tilaLabels = [
    'uusi'         => ['label' => 'Uusi',         'cls' => 'tila-uusi'],
    'vahvistettu'  => ['label' => 'Vahvistettu',  'cls' => 'tila-vahv'],
    'valmis'       => ['label' => 'Valmis',       'cls' => 'tila-valmis'],
    'peruttu'      => ['label' => 'Peruttu',      'cls' => 'tila-per'],
];
$filter  = $_GET['nayta'] ?? 'tulevat';
$fiMonths = ['','tammikuuta','helmikuuta','maaliskuuta','huhtikuuta','toukokuuta','kesäkuuta',
             'heinäkuuta','elokuuta','syyskuuta','lokakuuta','marraskuuta','joulukuuta'];
function fmtPvm(string $d): string {
    global $fiMonths;
    [$y,$m,$dd] = explode('-', $d);
    return (int)$dd . '. ' . $fiMonths[(int)$m] . ' ' . $y;
}
function esc(mixed $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="fi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Varaukset – Pielisen Pyörähuolto</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--blue:#1E3A5F;--blue-light:#2a5080;--yellow:#F5C518;--gray:#f7f8fa;--text:#1a1a1a;--muted:#555;--border:#e2e6ea;--white:#fff;--red:#c0392b;--green:#1a7a4a}
body{font-family:system-ui,-apple-system,sans-serif;color:var(--text);background:var(--gray);min-height:100vh}

/* ── Login ── */
.login-wrap{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:2rem}
.login-card{background:var(--white);border:1px solid var(--border);border-radius:14px;padding:2.5rem;width:100%;max-width:360px;text-align:center}
.login-logo{font-size:1.1rem;font-weight:700;color:var(--blue);margin-bottom:1.5rem}
.login-logo span{color:var(--yellow)}
.login-card h2{font-size:1.3rem;font-weight:800;margin-bottom:1.5rem}
.login-card input{width:100%;padding:.75rem 1rem;border:1px solid var(--border);border-radius:8px;font-size:1rem;margin-bottom:.75rem;outline:none}
.login-card input:focus{border-color:var(--blue)}
.login-card button{width:100%;background:var(--blue);color:var(--white);font-weight:700;font-size:1rem;padding:.8rem;border:none;border-radius:8px;cursor:pointer}
.login-err{color:var(--red);font-size:.85rem;margin-top:.5rem}

/* ── Header ── */
header{background:var(--blue);padding:0 2rem;height:60px;display:flex;align-items:center;justify-content:space-between}
.h-logo{color:var(--white);font-weight:700;font-size:1rem}
.h-logo span{color:var(--yellow)}
.h-right{display:flex;align-items:center;gap:1rem}
.h-link{color:rgba(255,255,255,.72);font-size:.85rem;text-decoration:none}
.h-link:hover{color:var(--yellow)}
.btn-logout{background:transparent;border:1px solid rgba(255,255,255,.3);color:rgba(255,255,255,.8);font-size:.82rem;padding:.35rem .85rem;border-radius:6px;cursor:pointer}

/* ── Main ── */
main{max-width:1000px;margin:0 auto;padding:2.5rem 1.5rem}
.page-title{font-size:1.5rem;font-weight:800;color:var(--text);margin-bottom:1.5rem}

/* ── Filter tabs ── */
.filter-tabs{display:flex;gap:.5rem;margin-bottom:1.5rem;flex-wrap:wrap}
.filter-tab{font-size:.88rem;font-weight:600;padding:.45rem 1.1rem;border:1.5px solid var(--border);border-radius:20px;text-decoration:none;color:var(--muted);background:var(--white);transition:all .15s}
.filter-tab:hover{border-color:var(--blue);color:var(--blue)}
.filter-tab.active{background:var(--blue);border-color:var(--blue);color:var(--white)}

/* ── DB error ── */
.db-error{background:#fdecea;border:1px solid #f5c6c6;border-radius:8px;padding:1.25rem 1.5rem;color:var(--red);font-size:.95rem;margin-bottom:1.5rem}
.db-error code{font-size:.83rem;background:#fff;padding:.1rem .4rem;border-radius:4px;border:1px solid #f5c6c6;display:inline-block;margin-top:.5rem;word-break:break-all}

/* ── Status msg ── */
.flash-ok{background:#d4edda;border:1px solid #c3e6cb;border-radius:8px;padding:.7rem 1.25rem;color:var(--green);font-size:.9rem;font-weight:600;margin-bottom:1rem}
.flash-err{background:#fdecea;border:1px solid #f5c6c6;border-radius:8px;padding:.7rem 1.25rem;color:var(--red);font-size:.9rem;font-weight:600;margin-bottom:1rem}

/* ── Table ── */
.table-wrap{background:var(--white);border:1px solid var(--border);border-radius:12px;overflow:hidden}
.bk-table{width:100%;border-collapse:collapse;font-size:.9rem}
.bk-table th{background:var(--gray);color:var(--muted);font-size:.75rem;font-weight:700;letter-spacing:.8px;text-transform:uppercase;padding:.75rem 1rem;text-align:left;border-bottom:1px solid var(--border)}
.bk-table td{padding:.8rem 1rem;border-bottom:1px solid #f0f0f0;vertical-align:top}
.bk-table tr:last-child td{border-bottom:none}
.bk-table tr:hover td{background:#fafbfc}

.date-cell{white-space:nowrap;font-weight:700;color:var(--text)}
.time-cell{font-weight:700;color:var(--blue)}
.name-cell{font-weight:600}
.contact-cell{font-size:.82rem;color:var(--muted);line-height:1.5}
.contact-cell a{color:var(--blue);text-decoration:none}
.contact-cell a:hover{text-decoration:underline}
.service-cell{font-size:.83rem}
.notes-cell{font-size:.8rem;color:var(--muted);max-width:180px;white-space:pre-wrap;word-break:break-word}

.tila-uusi  {display:inline-block;padding:.2rem .6rem;border-radius:10px;font-size:.74rem;font-weight:700;background:#fff3cd;color:#856404}
.tila-vahv  {display:inline-block;padding:.2rem .6rem;border-radius:10px;font-size:.74rem;font-weight:700;background:#d4edda;color:#155724}
.tila-valmis{display:inline-block;padding:.2rem .6rem;border-radius:10px;font-size:.74rem;font-weight:700;background:#cce5ff;color:#004085}
.tila-per   {display:inline-block;padding:.2rem .6rem;border-radius:10px;font-size:.74rem;font-weight:700;background:#e2e3e5;color:#383d41}

.action-form{display:flex;gap:.4rem;flex-wrap:wrap;margin-top:.4rem}
.btn-action{font-size:.76rem;padding:.25rem .65rem;border-radius:6px;border:1px solid var(--border);background:var(--white);cursor:pointer;font-weight:600;transition:all .15s;white-space:nowrap}
.btn-vahvista:hover{background:#d4edda;border-color:#c3e6cb;color:var(--green)}
.btn-valmis:hover{background:#cce5ff;border-color:#b8daff;color:#004085}
.btn-peru:hover{background:#fdecea;border-color:#f5c6c6;color:var(--red)}
.btn-uusi-a:hover{background:#fff3cd;border-color:#ffeeba;color:#856404}

.empty-msg{text-align:center;color:var(--muted);padding:3rem 1rem;font-size:.95rem}

@media(max-width:700px){
  main{padding:1.25rem 1rem}
  .bk-table th:nth-child(6),.bk-table td:nth-child(6){display:none}
  .bk-table th:nth-child(7),.bk-table td:nth-child(7){display:none}
}
</style>
</head>
<body>
<?php if (!$loggedIn): ?>

<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo"><span>Pielisen</span> Pyörähuolto</div>
    <h2>Varaukset</h2>
    <form method="POST">
      <input type="password" name="password" placeholder="Salasana" autofocus>
      <button type="submit">Kirjaudu sisään</button>
    </form>
    <?php if (!empty($loginError)): ?>
      <p class="login-err">Väärä salasana. Yritä uudelleen.</p>
    <?php endif ?>
  </div>
</div>

<?php else: ?>

<header>
  <div class="h-logo"><span>Pielisen</span> Pyörähuolto – Hallinta</div>
  <div class="h-right">
    <a class="h-link" href="admin.html">&#8592; Hallintapaneeli</a>
    <a class="h-link" href="index.html" target="_blank">&#8599; Sivusto</a>
    <form method="POST" style="display:inline">
      <button class="btn-logout" type="submit" name="logout" value="1">Kirjaudu ulos</button>
    </form>
  </div>
</header>

<main>
  <h1 class="page-title">Varaukset</h1>

  <?php if ($statusMsg === 'ok'): ?>
    <div class="flash-ok">Tila päivitetty.</div>
  <?php elseif ($statusMsg === 'error'): ?>
    <div class="flash-err">Päivitys epäonnistui. Tarkista tietokantayhteys.</div>
  <?php endif ?>

  <?php if ($dbError): ?>
    <div class="db-error">
      <strong>Tietokantayhteys epäonnistui.</strong><br>
      Tarkista tiedoston alussa olevat DB_HOST, DB_NAME, DB_USER ja DB_PASS -arvot.<br>
      <code>varaukset.php</code>
    </div>
  <?php else: ?>

  <div class="filter-tabs">
    <a href="varaukset.php?nayta=tulevat" class="filter-tab <?= $filter === 'tulevat' ? 'active' : '' ?>">Tulevat</a>
    <a href="varaukset.php?nayta=menneet" class="filter-tab <?= $filter === 'menneet' ? 'active' : '' ?>">Menneet</a>
    <a href="varaukset.php?nayta=kaikki"  class="filter-tab <?= $filter === 'kaikki'  ? 'active' : '' ?>">Kaikki</a>
  </div>

  <div class="table-wrap">
    <?php if (empty($bookings)): ?>
      <p class="empty-msg">Ei varauksia tällä hakuehdolla.</p>
    <?php else: ?>
    <table class="bk-table">
      <thead>
        <tr>
          <th>Päivä</th>
          <th>Aika</th>
          <th>Nimi</th>
          <th>Yhteystiedot</th>
          <th>Palvelu</th>
          <th>Pyörä</th>
          <th>Lisätiedot</th>
          <th>Tila</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bookings as $b):
          $tila = $tilaLabels[$b['tila']] ?? ['label' => esc($b['tila']), 'cls' => ''];
          $palveluNimi = $palveluLabels[$b['palvelu']] ?? esc($b['palvelu']);
          $pyoraNimi   = $pyoraLabels[$b['pyora_tyyppi']] ?? esc($b['pyora_tyyppi']);
        ?>
        <tr>
          <td class="date-cell"><?= fmtPvm($b['toivottu_pvm']) ?></td>
          <td class="time-cell"><?= esc($b['toivottu_aika']) ?></td>
          <td class="name-cell"><?= esc($b['nimi']) ?></td>
          <td class="contact-cell">
            <a href="tel:<?= esc($b['puhelin']) ?>"><?= esc($b['puhelin']) ?></a><br>
            <a href="mailto:<?= esc($b['email']) ?>"><?= esc($b['email']) ?></a>
          </td>
          <td class="service-cell"><?= esc($palveluNimi) ?></td>
          <td class="service-cell"><?= esc($pyoraNimi) ?></td>
          <td class="notes-cell"><?= esc($b['lisatiedot'] ?? '') ?></td>
          <td>
            <span class="<?= $tila['cls'] ?>"><?= $tila['label'] ?></span>
            <form method="POST" class="action-form">
              <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
              <input type="hidden" name="nayta" value="<?= esc($filter) ?>">
              <?php if ($b['tila'] !== 'vahvistettu' && $b['tila'] !== 'valmis' && $b['tila'] !== 'peruttu'): ?>
                <button class="btn-action btn-vahvista" name="aktion" value="vahvistettu">Vahvista</button>
              <?php endif ?>
              <?php if ($b['tila'] !== 'valmis' && $b['tila'] !== 'peruttu'): ?>
                <button class="btn-action btn-valmis" name="aktion" value="valmis">Valmis</button>
              <?php endif ?>
              <?php if ($b['tila'] !== 'peruttu'): ?>
                <button class="btn-action btn-peru" name="aktion" value="peruttu"
                  onclick="return confirm('Merkitäänkö varaus peruutetuksi?')">Peru</button>
              <?php endif ?>
              <?php if ($b['tila'] === 'peruttu' || $b['tila'] === 'valmis'): ?>
                <button class="btn-action btn-uusi-a" name="aktion" value="uusi">Palauta</button>
              <?php endif ?>
            </form>
          </td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
    <?php endif ?>
  </div>

  <?php endif ?>
</main>

<?php endif ?>
</body>
</html>
