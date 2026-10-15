<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/** Server-rendered, accessible Office sign-in form. */
final class WebsiteOfficeLoginFormRenderer {

  public function render(string $csrfToken, bool $invalidCredentials = false): string {
    if (!preg_match('/^[a-f0-9]{64}$/D', $csrfToken)) {
      throw new \InvalidArgumentException('Invalid login CSRF token.');
    }
    $message = $invalidCredentials
      ? '<p role="alert" class="error">Aanmelden is niet gelukt. Controleer je gegevens en probeer opnieuw.</p>'
      : '';
    $queryRouter = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) === '/index.php';
    $action = $queryRouter ? '/index.php?office_route=inloggen' : '/office/inloggen';
    return '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
      . '<title>Aanmelden · BREBO Office</title><style>:root{font-family:system-ui,-apple-system,Segoe UI,sans-serif;color:#182638;background:#f4f6fa}'
      . '*{box-sizing:border-box}body{min-height:100vh;display:grid;place-items:center;margin:0;padding:1rem}main{width:min(100%,440px);background:white;border:1px solid #dce2ea;border-radius:14px;padding:clamp(1.5rem,4vw,2.5rem);box-shadow:0 10px 30px #18263812}'
      . 'h1{margin:0 0 .3rem;font-size:1.7rem}p{color:#48556a}label{display:block;font-weight:650;margin:1.1rem 0 .35rem}'
      . 'input{width:100%;font:inherit;padding:.85rem;border:1px solid #8797ab;border-radius:7px}input:focus-visible,button:focus-visible{outline:3px solid #3478c0;outline-offset:2px}'
      . 'button{width:100%;margin-top:1.5rem;background:#182638;color:#fff;border:0;border-radius:7px;padding:.9rem;font:inherit;font-weight:700;cursor:pointer}'
      . '.error{background:#fff2f2;color:#8b1c1c;padding:.8rem;border-radius:7px}</style></head><body><main>'
      . '<h1>BREBO Office</h1><p>Meld je aan met je Office-account.</p>' . $message
      . '<form method="post" action="' . $action . '" autocomplete="on">'
      . '<input type="hidden" name="csrf_token" value="' . $csrfToken . '">'
      . '<label for="username">Gebruikersnaam</label><input id="username" name="username" autocomplete="username" required maxlength="190">'
      . '<label for="password">Wachtwoord</label><input id="password" name="password" type="password" autocomplete="current-password" required>'
      . '<button type="submit">Aanmelden</button></form></main></body></html>';
  }
}
