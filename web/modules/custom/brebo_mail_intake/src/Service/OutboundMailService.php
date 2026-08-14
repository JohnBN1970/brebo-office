<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
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
    private readonly MailSignatureBuilder $signatureBuilder,
  ) {}

  /**
   * Creates an outbound communication draft; it does not send anything.
   *
   * @param array{from?:string,to:string,cc?:string,bcc?:string,subject:string,body:string,body_html?:string,building_id?:int,project_id?:int,context_id?:int} $draft
   */
  public function createDraft(array $draft): NodeInterface {
    $this->ensureOutboundFields();
    $from = trim((string) ($draft['from'] ?? ''));
    $to = $this->validatedAddresses((string) ($draft['to'] ?? ''), TRUE);
    $cc = $this->validatedAddresses((string) ($draft['cc'] ?? ''), FALSE);
    $bcc = $this->validatedAddresses((string) ($draft['bcc'] ?? ''), FALSE);
    $subject = trim((string) ($draft['subject'] ?? ''));
    $body = trim((string) ($draft['body'] ?? ''));
    $bodyHtml = trim((string) ($draft['body_html'] ?? ''));
    if ($bodyHtml !== '') {
      $bodyHtml = Xss::filter($bodyHtml, [
        'a', 'b', 'blockquote', 'br', 'code', 'em', 'h2', 'h3', 'h4',
        'hr', 'i', 'li', 'ol', 'p', 'pre', 'strong', 'u', 'ul',
      ]);
    }
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
      'field_brebo_mail_html' => $bodyHtml,
      'field_brebo_mail_from' => $from,
      'field_brebo_mail_to' => $to,
      'field_brebo_mail_cc' => $cc,
      'field_brebo_mail_bcc' => $bcc,
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

    $to = $this->validatedAddresses((string) $communication->get('field_brebo_mail_to')->value, TRUE);
    $cc = $communication->hasField('field_brebo_mail_cc')
      ? $this->validatedAddresses((string) $communication->get('field_brebo_mail_cc')->value, FALSE)
      : '';
    $bcc = $communication->hasField('field_brebo_mail_bcc')
      ? $this->validatedAddresses((string) $communication->get('field_brebo_mail_bcc')->value, FALSE)
      : '';
    $subject = trim((string) $communication->get('field_brebo_comm_subject')->value);
    $body = trim((string) $communication->get('field_brebo_transcript')->value);
    $bodyHtml = $communication->hasField('field_brebo_mail_html')
      ? trim((string) $communication->get('field_brebo_mail_html')->value)
      : '';
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
        'body_html' => $bodyHtml,
        'cc' => $cc,
        'bcc' => $bcc,
        'signature' => $this->signatureBuilder->build($communication),
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


  private function validatedAddresses(string $value, bool $required): string {
    $addresses = array_values(array_filter(
      array_map('trim', preg_split('/[;,\n]+/', $value) ?: []),
      static fn(string $address): bool => $address !== '',
    ));
    if ($required && $addresses === []) {
      throw new \InvalidArgumentException('Minimaal één ontvanger is verplicht.');
    }
    foreach ($addresses as $address) {
      if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
        throw new \InvalidArgumentException('Ongeldig e-mailadres in uitgaande mail.');
      }
    }
    return implode(', ', array_unique($addresses));
  }

  private function ensureOutboundFields(): void {
    foreach ([
      'field_brebo_mail_cc' => ['string_long', '0ab046bf-8494-44cd-aa03-4a4ffab9e531', '996e360c-e015-4861-9531-fd40d6db555a', 'CC', 'Zichtbare kopieontvangers van de e-mail.'],
      'field_brebo_mail_bcc' => ['string_long', 'd96dafc6-a3a4-4c25-b7b1-b98c46b75cd0', '326437b5-1f4f-4ae0-a95c-a2e0ba3d1888', 'BCC', 'Niet-zichtbare kopieontvangers; alleen voor gecontroleerde uitgaande verzending.'],
    ] as $fieldName => [$type, $storageUuid, $fieldUuid, $label, $description]) {
      if (!FieldStorageConfig::loadByName('node', $fieldName)) {
        FieldStorageConfig::create([
          'uuid' => $storageUuid,
          'field_name' => $fieldName,
          'entity_type' => 'node',
          'type' => $type,
          'module' => 'core',
          'cardinality' => 1,
          'translatable' => TRUE,
        ])->save();
      }
      if (!FieldConfig::loadByName('node', 'brebo_communication', $fieldName)) {
        FieldConfig::create([
          'uuid' => $fieldUuid,
          'field_name' => $fieldName,
          'entity_type' => 'node',
          'bundle' => 'brebo_communication',
          'label' => $label,
          'description' => $description,
          'required' => FALSE,
          'translatable' => TRUE,
        ])->save();
      }
    }
  }

}
