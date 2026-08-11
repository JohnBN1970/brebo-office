<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Shows non-secret readiness for BREBO mail runtime configuration.
 */
final class MailRuntimeReadinessController extends ControllerBase {

  public function page(): array {
    $imapHost = trim((string) getenv('BREBO_IMAP_HOST'));
    $imapUser = trim((string) getenv('BREBO_IMAP_USER'));
    $imapPasswordPresent = trim((string) getenv('BREBO_IMAP_PASSWORD')) !== '';
    $mailAddress = trim((string) getenv('BREBO_MAIL_ADDRESS')) ?: 'info@brebobv.nl';

    $smtpEnabled = filter_var(getenv('BREBO_SMTP_ENABLED') ?: '0', FILTER_VALIDATE_BOOL);
    $smtpConfigEnabled = (bool) $this->config('smtp.settings')->get('smtp_on');

    $rows = [
      ['Mailboxadres', $mailAddress],
      ['PHP IMAP beschikbaar', function_exists('imap_open') ? 'Ja' : 'Nee'],
      ['IMAP host ingesteld', $imapHost !== '' ? 'Ja' : 'Nee'],
      ['IMAP gebruiker ingesteld', $imapUser !== '' ? 'Ja' : 'Nee'],
      ['IMAP wachtwoord aanwezig', $imapPasswordPresent ? 'Ja (waarde verborgen)' : 'Nee'],
      ['IMAP bron gereed', ($imapHost !== '' && $imapUser !== '' && $imapPasswordPresent && function_exists('imap_open')) ? 'Ja' : 'Nee'],
      ['SMTP runtime-vrijgave', $smtpEnabled ? 'Aan' : 'Uit'],
      ['SMTP module-vrijgave', $smtpConfigEnabled ? 'Aan' : 'Uit'],
      ['Externe verzending mogelijk', ($smtpEnabled && $smtpConfigEnabled) ? 'Technisch vrijgegeven; berichtvrijgave blijft vereist' : 'Nee'],
    ];

    return [
      '#type' => 'container',
      'intro' => [
        '#markup' => '<p>Deze pagina toont alleen aanwezigheid en status. Wachtwoorden, tokens en andere secrets worden nooit weergegeven.</p>',
      ],
      'table' => [
        '#type' => 'table',
        '#header' => ['Controle', 'Status'],
        '#rows' => $rows,
        '#empty' => 'Geen controles beschikbaar.',
      ],
      'note' => [
        '#markup' => '<p><strong>Veiligheidsgrens:</strong> SMTP blijft uit totdat zowel de runtime-vrijgave als de SMTP-module expliciet actief zijn. Daarnaast blijft per bericht menselijke verzendgoedkeuring vereist.</p>',
      ],
    ];
  }

}
