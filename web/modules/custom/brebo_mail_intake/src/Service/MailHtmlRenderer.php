<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

/**
 * Renders approved dossier text into the fixed BREBO HTML mail shell.
 *
 * AI and editors own the variable message text. This renderer owns the
 * organisation presentation and escapes variable content so AI cannot inject
 * arbitrary markup, scripts or hidden external content into outgoing mail.
 */
final class MailHtmlRenderer {

  public function render(string $subject, string $body): string {
    $subject = trim($subject);
    $body = trim($body);

    if ($subject === '' || $body === '') {
      throw new \InvalidArgumentException('Onderwerp en berichtinhoud zijn verplicht voor HTML-rendering.');
    }

    $safeSubject = htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeBody = $this->paragraphs($body);

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
            <td style="padding:8px 32px 32px 32px;font-size:16px;line-height:25px;color:#2b2b2b;">
              {$safeBody}
            </td>
          </tr>
          <tr>
            <td style="padding:22px 32px;background:#f0f0f0;border-top:1px solid #dddddd;font-size:13px;line-height:20px;color:#555555;">
              <strong>BREBO</strong><br>
              E-mail: <a href="mailto:info@brebobv.nl" style="color:#222222;text-decoration:underline;">info@brebobv.nl</a><br>
              <span style="font-size:12px;color:#777777;">Dit bericht is vanuit BREBO Office verzonden na gecontroleerde vrijgave.</span>
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
      $escaped = nl2br($escaped, FALSE);
      $html[] = '<p style="margin:0 0 18px 0;">' . $escaped . '</p>';
    }

    return implode("\n", $html);
  }

}
