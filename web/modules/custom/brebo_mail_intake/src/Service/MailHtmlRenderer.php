<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\Component\Utility\Xss;

/** Renders approved content into the controlled BREBO HTML mail shell. */
final class MailHtmlRenderer {

  /**
   * @param array{name?:string,roles?:string,company?:string,email?:string,phone?:string,address?:string} $signature
   */
  public function render(string $subject, string $body, string $bodyHtml = '', array $signature = []): string {
    $subject = trim($subject);
    $body = trim($body);
    $bodyHtml = trim($bodyHtml);
    if ($subject === '' || ($body === '' && $bodyHtml === '')) {
      throw new \InvalidArgumentException('Onderwerp en berichtinhoud zijn verplicht voor HTML-rendering.');
    }

    $safeSubject = htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeBody = $bodyHtml !== ''
      ? Xss::filter($bodyHtml, [
        'a', 'b', 'blockquote', 'br', 'code', 'em', 'h2', 'h3', 'h4',
        'hr', 'i', 'li', 'ol', 'p', 'pre', 'strong', 'u', 'ul',
      ])
      : $this->paragraphs($body);
    $safeSignature = $this->signature($signature);

    return <<<HTML
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{$safeSubject}</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;color:#1f1f1f;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#f4f4f4;margin:0;padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:640px;background:#ffffff;border-collapse:collapse;">
          <tr>
            <td style="padding:28px 32px 20px 32px;border-bottom:4px solid #222222;">
              <div style="font-size:28px;line-height:32px;font-weight:700;letter-spacing:1px;">BREBO</div>
              <div style="margin-top:6px;font-size:13px;line-height:18px;color:#666666;">Bouw, renovatie en onderhoud</div>
            </td>
          </tr>
          <tr>
            <td style="padding:28px 32px 12px 32px;">
              <h1 style="margin:0;font-size:22px;line-height:30px;font-weight:700;color:#1f1f1f;">{$safeSubject}</h1>
            </td>
          </tr>
          <tr>
            <td style="padding:8px 32px 24px 32px;font-size:16px;line-height:25px;color:#2b2b2b;">
              {$safeBody}
            </td>
          </tr>
          <tr>
            <td style="padding:0 32px 30px 32px;font-size:14px;line-height:21px;color:#333333;">
              {$safeSignature}
            </td>
          </tr>
          <tr>
            <td style="padding:18px 32px;background:#f0f0f0;border-top:1px solid #dddddd;font-size:12px;line-height:18px;color:#777777;">
              Dit bericht is vanuit BREBO Office verzonden na gecontroleerde vrijgave.
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
  }

  /** @param array<string,string> $signature */
  private function signature(array $signature): string {
    $escape = static fn(string $value): string => htmlspecialchars(trim($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $name = $escape((string) ($signature['name'] ?? ''));
    $roles = $escape((string) ($signature['roles'] ?? ''));
    $company = $escape((string) ($signature['company'] ?? 'BREBO'));
    $email = trim((string) ($signature['email'] ?? ''));
    $phone = $escape((string) ($signature['phone'] ?? ''));
    $address = $escape((string) ($signature['address'] ?? ''));

    $lines = ['<div style="border-top:1px solid #dddddd;padding-top:18px;">'];
    if ($name !== '') {
      $lines[] = '<strong>' . $name . '</strong><br>';
    }
    if ($roles !== '') {
      $lines[] = $roles . '<br>';
    }
    $lines[] = '<strong>' . $company . '</strong><br>';
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $safeEmail = $escape($email);
      $lines[] = 'E-mail: <a href="mailto:' . $safeEmail . '" style="color:#222222;text-decoration:underline;">' . $safeEmail . '</a><br>';
    }
    if ($phone !== '') {
      $lines[] = 'Telefoon: ' . $phone . '<br>';
    }
    if ($address !== '') {
      $lines[] = $address;
    }
    $lines[] = '</div>';
    return implode("\n", $lines);
  }

  private function paragraphs(string $body): string {
    $normalized = preg_replace("/\r\n?|\n/", "\n", $body) ?? $body;
    $blocks = preg_split('/\n{2,}/', $normalized) ?: [$normalized];
    $html = [];
    foreach ($blocks as $block) {
      $block = trim($block);
      if ($block === '') {
        continue;
      }
      $escaped = htmlspecialchars($block, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      $html[] = '<p style="margin:0 0 18px 0;">' . nl2br($escaped, FALSE) . '</p>';
    }
    return implode("\n", $html);
  }

}
