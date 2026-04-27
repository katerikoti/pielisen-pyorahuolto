<?php
/**
 * chatbot-api.php — Pielisen Pyörähuolto AI middleware
 * Uses Groq chat completions. API key loaded from config.php (env or local).
 * The key is never exposed to the browser.
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$message = isset($body['message']) ? trim((string)$body['message']) : '';
$historyInput = isset($body['history']) && is_array($body['history']) ? $body['history'] : [];

$messageLength = function_exists('mb_strlen') ? mb_strlen($message) : strlen($message);
if (empty($message) || $messageLength > 1000) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid message']);
    exit;
}

$historyMessages = [];
foreach (array_slice($historyInput, -12) as $item) {
    if (!is_array($item)) continue;
    $role = isset($item['role']) ? $item['role'] : '';
    $content = isset($item['content']) ? trim((string)$item['content']) : '';
    if (!in_array($role, ['user', 'assistant'], true)) continue;
    if ($content === '') continue;
    $historyMessages[] = ['role' => $role, 'content' => $content];
}

// System prompt tailored for Pielisen Pyörähuolto
$systemPrompt = "Olet 'Ketju', Pielisen Pyörähuollon asiakaspalvelubotti.

Vastaat lyhyesti ja selkeästi – max 2–3 lyhyttä lausetta tai lyhyt lista. Käytä tarvittaessa kappalejakoja. Älä toista kysymystä takaisin äläkä käytä turhia tervehdyksiä tai lopetuksia.

TÄRKEÄÄ – ajanvaraus: Et itse tee varauksia. Jos käyttäjä haluaa varata ajan, kehota painamaan \"Varaa aika\" -painiketta – ajanvarauslomake aukeaa suoraan tähän chatiin. ÄLÄ KOSKAAN väitä tehneesi tai vahvistaneesi varausta.

Tiedot:
- Pielisen Pyörähuolto, Kauppakatu 14, 80100 Joensuu
- Palvelut ja hinnat:
  * Perushuolto 39 € (kiinteä): jarrujen tarkistus/säätö, vaihteiston säätö, ketjun puhdistus, renkaiden ilmanpaine
  * Täyshuolto 89 € (kiinteä): kaikki perushuollon sisältö + laakerit, kaapelit, puolien kireys, koko pyörän läpikäynti
  * Sähköpyörän huolto alkaen 55 €: perushuolto + akun tarkistus, moottorin tarkistus, elektroniikka
  * Lasten pyörän huolto 25 €
  * Rengaskorjaus: paikkaus 15 €, sisäkumin vaihto 20 € + osa, renkaanvaihto 25 € + osat
  * Jarrusäätö 15 €, vaihteiston säätö 15 €, ketjun vaihto 15 € + osa
  * Puolien säätö/vanteensuoristus alkaen 20 €
  * Laakereiden vaihto alkaen 20 € + osa, vaijerien vaihto alkaen 15 € + osat
  * Työtuntihinta 45 €/h
  * Huoltoarvio on aina ilmainen
  * Varaosista veloitetaan erikseen
- Sähköpyörän lataus: voi tulla lataamaan akun milloin tahansa aukioloaikana ilmaiseksi, ei tarvitse varata aikaa. Tarjoamme myös ilmaisen kahvin kahviautomaatista.
- Aukiolo: Ma–Pe 9–17, La 10–14 (huhtikuu–toukokuu La 10–16), Su suljettu
- Puh: 013 456 7890 | Sähköposti: huolto@pielisenpyora.fi

Jos kysymys ei liity pyörähuoltoon tai yritykseen, sano ettei se kuulu osaamiseesi.";

$messages = array_merge(
    [['role' => 'system', 'content' => $systemPrompt]],
    $historyMessages,
    [['role' => 'user', 'content' => $message]]
);

if (empty(GROQ_API_KEY)) {
    http_response_code(500);
    echo json_encode(['reply' => 'Chatbot ei ole saatavilla juuri nyt. Ota yhteyttä: 013 456 7890']);
    exit;
}

$data = json_encode([
    'model'       => 'llama-3.3-70b-versatile',
    'messages'    => $messages,
    'max_tokens'  => 180,
    'temperature' => 0.45,
]);

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $data,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY,
    ],
    CURLOPT_TIMEOUT        => 12,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    http_response_code(500);
    echo json_encode(['reply' => 'Chatbot ei ole käytettävissä. Ota yhteyttä: 013 456 7890']);
    exit;
}

$result = json_decode($response, true);
$reply  = $result['choices'][0]['message']['content'] ?? 'En pysty vastaamaan juuri nyt. Soita: 013 456 7890';

// Strip markdown, convert newlines to <br> for chat rendering
$reply = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $reply);
$reply = preg_replace('/\*(.*?)\*/', '$1', $reply);
$reply = nl2br(trim($reply));

echo json_encode(['reply' => $reply]);
