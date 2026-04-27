<?php
/**
 * peruuta.php - Pielisen Pyörähuolto booking cancellation
 *
 * GET:  Shows booking summary and confirm button.
 * POST: Sets tila='peruttu' and clears cancel_token.
 */
require_once __DIR__ . '/config.php';

$token    = trim($_GET['token'] ?? $_POST['token'] ?? '');
$booking  = null;
$error    = null;
$cancelled = false;

// Validate token format (64 hex chars)
if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
    $error = 'Virheellinen peruutuslinkki.';
} else {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        $stmt = $pdo->prepare("SELECT * FROM varaukset WHERE cancel_token = :token LIMIT 1");
        $stmt->execute([':token' => $token]);
        $booking = $stmt->fetch();

        if (!$booking) {
            $error = 'Varauksia ei löydy tai peruutuslinkki on jo käytetty.';
        } elseif ($booking['tila'] === 'peruttu') {
            $error = 'Tämä varaus on jo peruttu.';
        } elseif ($booking['tila'] === 'valmis') {
            $error = 'Tätä varausta ei voi enää peruuttaa verkossa. Ota yhteyttä puhelimitse: 013 456 7890.';
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $upd = $pdo->prepare(
                "UPDATE varaukset SET tila='peruttu', cancel_token=NULL WHERE cancel_token=:token AND tila != 'peruttu'"
            );
            $upd->execute([':token' => $token]);
            $cancelled = true;
        }

    } catch (PDOException $e) {
        error_log('Peruuta DB error: ' . $e->getMessage());
        $error = 'Tekninen virhe. Yritä myöhemmin uudelleen.';
    }
}

$fiMonths = [
    'tammikuuta','helmikuuta','maaliskuuta','huhtikuuta','toukokuuta','kesäkuuta',
    'heinäkuuta','elokuuta','syyskuuta','lokakuuta','marraskuuta','joulukuuta',
];

function fmtDate(string $pvm): string {
    global $fiMonths;
    $d = new DateTime($pvm);
    return $d->format('j') . '. ' . $fiMonths[(int)$d->format('n') - 1] . ' ' . $d->format('Y');
}

$palveluLabels = [
    'perushuolto'  => 'Perushuolto',
    'tayshuolto'   => 'Täyshuolto',
    'sahko_huolto' => 'Sähköpyörän huolto',
    'rengaskorjaus'=> 'Rengaskorjaus',
    'muu'          => 'Muu / kerron lisää',
];
?><!DOCTYPE html>
<html lang="fi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Peruuta varaus – Pielisen Pyörähuolto</title>
<style>
:root{--blue:#1E3A5F;--yellow:#F5C518;--gray:#eceef2;--text:#1a1a1a;--muted:#555;--border:#e2e6ea;--white:#fff;--red:#c0392b}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',system-ui,sans-serif;background:var(--gray);color:var(--text);min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:2rem 1rem}
.card{background:var(--white);border-radius:14px;box-shadow:0 4px 24px rgba(0,0,0,.08);padding:2.5rem 2rem;max-width:480px;width:100%;text-align:center}
.logo{font-size:1rem;font-weight:800;color:var(--blue);margin-bottom:1.5rem}
.logo span{color:var(--yellow)}
h1{font-size:1.4rem;font-weight:800;color:var(--text);margin-bottom:.5rem}
.sub{color:var(--muted);font-size:.93rem;margin-bottom:1.5rem;line-height:1.6}
.summary{background:var(--gray);border-radius:10px;padding:1rem 1.25rem;margin:0 0 1.5rem;text-align:left}
.row{display:flex;justify-content:space-between;gap:1rem;font-size:.9rem;padding:.3rem 0;border-bottom:1px solid var(--border)}
.row:last-child{border-bottom:none}
.row span{color:var(--muted)}
.row strong{color:var(--text)}
.btn-cancel{display:inline-block;background:var(--red);color:#fff;font-size:.95rem;font-weight:700;padding:.75rem 2rem;border-radius:8px;border:none;cursor:pointer;text-decoration:none;transition:opacity .15s}
.btn-cancel:hover{opacity:.88}
.btn-back{display:inline-block;margin-top:1.25rem;color:var(--blue);font-size:.9rem;font-weight:600;text-decoration:none}
.btn-back:hover{text-decoration:underline}
.msg-ok{background:#d4edda;border:1px solid #c3e6cb;color:#155724;border-radius:8px;padding:1rem;font-size:.93rem;margin-bottom:1rem}
.msg-err{background:#fdecea;border:1px solid #f5c6c6;color:var(--red);border-radius:8px;padding:1rem;font-size:.93rem;margin-bottom:1rem}
</style>
</head>
<body>
<div class="card">
  <div class="logo"><span>Pielisen</span> Pyörähuolto</div>

  <?php if ($cancelled): ?>
    <div class="msg-ok">✓ Varauksesi on peruttu onnistuneesti.</div>
    <h1>Varaus peruttu</h1>
    <p class="sub">Toivomme näkevämme sinut uudelleen ensi kerralla!</p>

  <?php elseif ($error): ?>
    <div class="msg-err"><?= htmlspecialchars($error) ?></div>
    <h1>Varauksen peruutus</h1>
    <p class="sub">Tarvitsetko apua? Soita meille: <strong>013 456 7890</strong></p>

  <?php else: ?>
    <h1>Peruuta varaus</h1>
    <p class="sub">Haluatko peruuttaa seuraavan varauksen?</p>
    <div class="summary">
      <div class="row"><span>Nimi</span><strong><?= htmlspecialchars($booking['nimi']) ?></strong></div>
      <div class="row"><span>Päivä</span><strong><?= htmlspecialchars(fmtDate($booking['toivottu_pvm'])) ?></strong></div>
      <div class="row"><span>Aika</span><strong><?= htmlspecialchars($booking['toivottu_aika']) ?></strong></div>
      <div class="row"><span>Palvelu</span><strong><?= htmlspecialchars($palveluLabels[$booking['palvelu']] ?? $booking['palvelu']) ?></strong></div>
    </div>
    <form method="post">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
      <button type="submit" class="btn-cancel">Peruuta varaus</button>
    </form>
  <?php endif; ?>

  <a href="index.html" class="btn-back">← Takaisin etusivulle</a>
</div>
</body>
</html>
