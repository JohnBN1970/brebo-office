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
    $imapBatchLimit = max(1, min(500, (int) (getenv('BREBO_IMAP_BATCH_LIMIT') ?: 100)));
    $imapLastUid = (int) \Drupal::state()->get('brebo_mail_intake.imap_last_uid', 0);

    $zohoHost = trim((string) getenv('BREBO_ZOHO_IMAP_HOST'));
    $zohoUser = trim((string) getenv('BREBO_ZOHO_IMAP_USER'));
    $zohoPasswordPresent = trim((string) getenv('BREBO_ZOHO_IMAP_PASSWORD')) !== '';
    $zohoEnabled = filter_var(getenv('BREBO_ZOHO_MIGRATION_ENABLED') ?: '0', FILTER_VALIDATE_BOOL);
    $zohoBatchLimit = max(1, min(500, (int) (getenv('BREBO_ZOHO_IMAP_BATCH_LIMIT') ?: 100)));
    $zohoLastUid = (int) \Drupal::state()->get('brebo_mail_intake.zoho_migration_last_uid', 0);
    $zohoConfigured = $zohoHost !== '' && $zohoUser !== '' && $zohoPasswordPresent && function_exists('imap_open');

    $smtpEnabled = filter_var(getenv('BREBO_SMTP_ENABLED') ?: '0', FILTER_VALIDATE_BOOL);
    $smtpConfigEnabled = (bool) $this->config('smtp.settings')->get('smtp_on');

    $rows = [
      ['Mailboxadres', $mailAddress],
      ['PHP IMAP beschikbaar', function_exists('imap_open') ? 'Ja' : 'Nee'],
      ['Hostinger IMAP host ingesteld', $imapHost !== '' ? 'Ja' : 'Nee'],
      ['Hostinger IMAP gebruiker ingesteld', $imapUser !== '' ? 'Ja' : 'Nee'],
      ['Hostinger IMAP wachtwoord aanwezig', $imapPasswordPresent ? 'Ja (waarde verborgen)' : 'Nee'],
      ['Hostinger IMAP bron gereed', ($imapHost !== '' && $imapUser !== '' && $imapPasswordPresent && function_exists('imap_open')) ? 'Ja' : 'Nee'],
      ['Hostinger batchlimiet', (string) $imapBatchLimit],
      ['Hostinger laatst verwerkte UID', (string) $imapLastUid],
      ['Zoho migratie runtime-vrijgave', $zohoEnabled ? 'Aan' : 'Uit'],
      ['Zoho IMAP host ingesteld', $zohoHost !== '' ? 'Ja' : 'Nee'],
      ['Zoho IMAP gebruiker ingesteld', $zohoUser !== '' ? 'Ja' : 'Nee'],
      ['Zoho IMAP wachtwoord aanwezig', $zohoPasswordPresent ? 'Ja (waarde verborgen)' : 'Nee'],
      ['Zoho migratiebron gereed', $zohoConfigured ? 'Ja' : 'Nee'],
      ['Zoho batchlimiet', (string) $zohoBatchLimit],
      ['Zoho laatst verwerkte UID', (string) $zohoLastUid],
      ['SMTP runtime-vrijgave', $smtpEnabled ? 'Aan' : 'Uit'],
      ['SMTP module-vrijgave', $smtpConfigEnabled ? 'Aan' : 'Uit'],
      ['Externe verzending mogelijk', ($smtpEnabled && $smtpConfigEnabled) ? 'Technisch vrijgegeven; berichtvrijgave blijft vereist' : 'Nee'],
    ];

    return [
      '#type' => 'container',
      'intro' => [
        '#markup' => '<p>Deze pagina toont alleen aanwezigheid, voortgang en vrijgavestatus. Wachtwoorden, tokens, mailinhoud en andere secrets worden nooit weergegeven.</p>',
      ],
      'table' => [
        '#type' => 'table',
        '#header' => ['Controle', 'Status'],
        '#rows' => $rows,
        '#empty' => 'Geen controles beschikbaar.',
      ],
      'note' => [
        '#markup' => '<p><strong>Veiligheidsgrens:</strong> Zoho-migratie start pas wanneer de aparte runtime-vrijgave actief is. SMTP blijft uit totdat zowel de runtime-vrijgave als de SMTP-module expliciet actief zijn; daarnaast blijft per bericht menselijke verzendgoedkeuring vereist.</p>',
      ],
    ];
  }

}
