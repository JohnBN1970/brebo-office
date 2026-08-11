<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;

/**
 * Creates auditable outbound drafts and sends only explicitly approved mail.
 *
 * Dossier logic stays independent from the formatter and transport. Mime Mail
 * formats the message, SMTP transports it, and this service controls mandate,
 * approval and audit history.
 */
final class OutboundMailService {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
    private readonly MailManagerInterface $mailManager,
    private readonly TimeInterface $time,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Creates an outbound communication draft; it does not send anything.
   *
   * @param array{to:string,subject:string,body:string,building_id?:int,project_id?:int,context_id?:int} $draft
   */
  public function createDraft(array $draft): NodeInterface {
    $to = trim((string) ($draft['to'] ?? ''));
    $subject = trim((string) ($draft['subject'] ?? ''));
    $body = trim((string) ($draft['body'] ?? ''));
    if ($to === '' || $subject === '' || $body === '') {
      throw new \InvalidArgumentException('Ontvanger, onderwerp en berichtinhoud zijn verplicht voor een mailconcept.');
    }

    $values = [
      'type' => 'brebo_communication',
      'title' => '[CONCEPT] ' . $subject,
      'uid' => (int) $this->currentUser->id(),
      'status' => 1,
      'field_brebo_source_id' => 'outbound-draft:' . bin2hex(random_bytes(16)),
      'field_brebo_comm_channel' => 'E-mail',
      'field_brebo_comm_direction' => 'Uitgaand',
      'field_brebo_comm_subject' => $subject,
      'field_brebo_transcript' => $body,
      'field_brebo_mail_to' => $to,
      'field_brebo_comm_status' => 'Concept',
      'field_brebo_formal_status' => 'Concept - goedkeuring vereist',
      'field_brebo_ai_status' => 'Concept',
      'field_brebo_intake_status' => 'Verwerkt',
    ];

    foreach ([
      'building_id' => 'field_brebo_building_ref',
      'project_id' => 'field_brebo_project_ref',
      'context_id' => 'field_brebo_comm_scope_target',
    ] as $input => $field) {
      $targetId = (int) ($draft[$input] ?? 0);
      if ($targetId > 0) {
        $values[$field] = ['target_id' => $targetId];
      }
    }

    $node = $this->entityTypeManager->getStorage('node')->create($values);
    if (!$node instanceof NodeInterface) {
      throw new \RuntimeException('Uitgaand communicatieconcept kon niet worden aangemaakt.');
    }
    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Uitgaand mailconcept aangemaakt; nog niet verzonden en expliciete goedkeuring vereist.');
    $node->save();
    return $node;
  }

  /**
   * Sends one approved outbound communication through Drupal's mail system.
   *
   * Approval is represented by the exact formal status "Verzenden goedgekeurd".
   * AI or background processing must never set that status itself.
   */
  public function send(NodeInterface $communication): void {
    if (!$this->currentUser->hasPermission('send brebo outbound mail')) {
      throw new \RuntimeException('Gebruiker heeft geen mandaat om BREBO-mail te verzenden.');
    }
    if ($communication->bundle() !== 'brebo_communication') {
      throw new \InvalidArgumentException('Alleen BREBO Communication kan als e-mail worden verzonden.');
    }
    if (trim((string) $communication->get('field_brebo_comm_direction')->value) !== 'Uitgaand') {
      throw new \RuntimeException('Alleen uitgaande communicatie mag via deze service worden verzonden.');
    }
    if (trim((string) $communication->get('field_brebo_formal_status')->value) !== 'Verzenden goedgekeurd') {
      throw new \RuntimeException('Mail is niet expliciet vrijgegeven voor verzending.');
    }

    $runtimeEnabled = filter_var(getenv('BREBO_SMTP_ENABLED') ?: '0', FILTER_VALIDATE_BOOL);
    $smtpEnabled = (bool) $this->configFactory->get('smtp.settings')->get('smtp_on');
    if (!$runtimeEnabled || !$smtpEnabled) {
      throw new \RuntimeException('BREBO SMTP-transport is nog niet expliciet geactiveerd; bericht blijft ongewijzigd als goedgekeurd concept staan.');
    }

    $to = trim((string) $communication->get('field_brebo_mail_to')->value);
    $subject = trim((string) $communication->get('field_brebo_comm_subject')->value);
    $body = trim((string) $communication->get('field_brebo_transcript')->value);
    if ($to === '' || $subject === '' || $body === '') {
      throw new \RuntimeException('Goedgekeurde mail mist ontvanger, onderwerp of inhoud.');
    }

    $from = trim((string) getenv('BREBO_MAIL_ADDRESS')) ?: 'info@brebobv.nl';
    $result = $this->mailManager->mail(
      'brebo_mail_intake',
      'outbound',
      $to,
      'nl',
      [
        'subject' => $subject,
        'body' => $body,
        'communication_id' => (int) $communication->id(),
      ],
      $from,
      TRUE,
    );

    if (empty($result['result'])) {
      throw new \RuntimeException('Drupal mailtransport meldde dat de e-mail niet is verzonden.');
    }

    $communication->set('field_brebo_comm_status', 'Verzonden');
    $communication->set('field_brebo_formal_status', 'Verzonden');
    $communication->set('field_brebo_processed_at', gmdate('Y-m-d\TH:i:s', $this->time->getRequestTime()));
    $communication->setNewRevision(TRUE);
    $communication->setRevisionLogMessage('E-mail na expliciete vrijgave verzonden via Drupal mail system; bron blijft in communicatie-dossier herleidbaar.');
    $communication->save();
  }

}
