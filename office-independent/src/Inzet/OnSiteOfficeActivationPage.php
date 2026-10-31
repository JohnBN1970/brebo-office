<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/** Presentation only: opening or previewing a link never redeems an invitation. */
final class OnSiteOfficeActivationPage {
  public static function script(): string {
    return <<<'JS'
const token = new URLSearchParams(window.location.hash.slice(1)).get('activation');
const button = document.getElementById('open-onsite');
const status = document.getElementById('activation-status');
if (token && /^[A-Za-z0-9_-]{43}$/.test(token)) {
  button.href = 'brebo-onsite://activate?token=' + encodeURIComponent(token);
  button.hidden = false;
  status.textContent = 'Open de Office-testversie van OnSite om dit toestel te koppelen.';
} else {
  status.textContent = 'De activatielink ontbreekt of is ongeldig. Vraag een nieuwe uitnodiging aan.';
}
JS;
  }

  public static function landingCsp(): string {
    $hash = base64_encode(hash('sha256', self::script(), true));
    return "default-src 'none'; script-src 'sha256-$hash'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'";
  }

  public static function landing(): string {
    return self::document('<h1>BREBO OnSite</h1><p>Office-test</p>'
      . '<p id="activation-status">Open deze persoonlijke link op je telefoon. JavaScript is nodig om de app te openen.</p>'
      . '<p><a id="open-onsite" hidden>Open OnSite</a></p>'
      . self::installButtons()
      . '<p>Installeer eerst de testapp voor jouw toestel. Open daarna je persoonlijke activatielink opnieuw. Deze link is eenmalig.</p>'
      . '<script>' . self::script() . '</script>');
  }

  /** @param array{activation_token:string,expires_at:string} $invitation */
  public static function issued(array $invitation): string {
    $token = $invitation['activation_token'];
    if (!preg_match('/^[A-Za-z0-9_-]{43}$/D', $token)) {
      throw new \RuntimeException('Invalid activation invitation');
    }
    // A URL fragment stays out of the landing request, server logs and previews.
    $link = 'https://brebobv.nl/office-test/inzet/onsite/activatie-uitgeven#activation=' . $token;
    $text = 'BREBO OnSite: open deze persoonlijke link op je iPhone of Android-toestel met de BREBO OnSite-testapp: '
      . $link . "\nEenmalig te gebruiken; geldig tot " . $invitation['expires_at'] . ' UTC.';
    $whatsapp = 'https://wa.me/?text=' . rawurlencode($text);
    $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return self::document('<h1>OnSite-uitnodiging</h1><p>Stuur de persoonlijke activatielink via WhatsApp naar de gecontroleerde medewerker.</p>'
      . '<p><a href="' . $h($whatsapp) . '" target="_blank" rel="noopener noreferrer">Deel via WhatsApp</a></p>'
      . '<p><a href="' . $h($link) . '" rel="noreferrer">Open de activatielink op je telefoon</a></p>'
      . '<p>Geldig tot (UTC): ' . $h($invitation['expires_at']) . '</p>'
      . '<p>Alleen voor de BREBO OnSite-testversie. Opnieuw uitgeven maakt een eerdere ongebruikte link ongeldig.</p>');
  }

  private static function installButtons(): string {
    $links = [
      ['OFFICE_ONSITE_IOS_INSTALL_URL', 'iPhone / iOS', 'App installeren voor iPhone'],
      ['OFFICE_ONSITE_ANDROID_INSTALL_URL', 'Android', 'App installeren voor Android'],
    ];
    $html = '<div class="install-options" aria-label="BREBO OnSite installeren">';
    foreach ($links as [$key, $platform, $label]) {
      $url = getenv($key);
      if (!is_string($url) || !preg_match('~^https://[^\\s<>"]+$~iD', $url)) {
        $html .= '<div class="install-unavailable"><strong>' . $platform . '</strong><span>Downloadlink volgt</span></div>';
        continue;
      }
      $html .= '<a class="install-choice" href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" rel="noopener noreferrer"><strong>' . $platform . '</strong><span>' . $label . '</span></a>';
    }
    return $html . '</div>';
  }

  private static function document(string $body): string {
    return '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
      . '<meta name="referrer" content="no-referrer"><title>OnSite activeren · BREBO Office</title>'
      . '<style>body{font:17px system-ui,sans-serif;background:#f4f6fa;color:#182638;margin:0;padding:24px}main{max-width:540px;margin:40px auto;padding:28px;background:white;border-radius:16px}a{display:inline-block;padding:14px 18px;background:#5b2c83;color:white;border-radius:9px;text-decoration:none}a[hidden]{display:none}p{line-height:1.5}.install-options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:18px 0}.install-choice,.install-unavailable{display:flex;flex-direction:column;justify-content:center;gap:5px;min-height:70px;padding:12px;border-radius:12px;text-align:center;box-sizing:border-box}.install-choice{background:#182638;color:#fff}.install-unavailable{background:#e9edf3;color:#536171}.install-options span{font-size:12px}@media(max-width:400px){.install-options{grid-template-columns:1fr}}</style></head><body><main>'
      . $body . '</main></body></html>';
  }
}
