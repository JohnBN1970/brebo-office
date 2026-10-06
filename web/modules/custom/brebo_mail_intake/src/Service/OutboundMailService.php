<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\brebo_mail_intake\Contract\OutboundMailPersistenceInterface;
use Drupal\brebo_mail_intake\Contract\OutboundMailRuntimeInterface;

/**
 * Creates auditable outbound drafts and sends only explicitly approved mail.
 */
final class OutboundMailService {

  public function __construct(
    private readonly OutboundMailPersistenceInterface $persistence,
    private readonly OutboundMailRuntimeInterface $runtime,
    private readonly MailSignatureBuilder $signatureBuilder,
    private readonly OutboundAttachmentService $attachmentService,
  ) {}

  /**
   * Creates an outbound communication draft; it does not send anything.
   *
   * @param array{from?:string,to:string,cc?:string,bcc?:string,subject:string,body:string,body_html?:string,building_id?:int,project_id?:int,context_id?:int} $draft
   */
  public function createDraft(array $draft): int {
    $this->persistence->ensureOutboundFields();

    $normalized = [
      'from' => trim((string) ($draft['from'] ?? '')),
      'to' => $this->validatedAddresses((string) ($draft['to'] ?? ''), TRUE),
      'cc' => $this->validatedAddresses((string) ($draft['cc'] ?? ''), FALSE),
      'bcc' => $this->validatedAddresses((string) ($draft['bcc'] ?? ''), FALSE),
      'subject' => trim((string) ($draft['subject'] ?? '')),
      'body' => trim((string) ($draft['body'] ?? '')),
      'body_html' => trim((string) ($draft['body_html'] ?? '')),
      'building_id' => (int) ($draft['building_id'] ?? 0),
      'project_id' => (int) ($draft['project_id'] ?? 0),
      'context_id' => (int) ($draft['context_id'] ?? 0),
    ];

    if ($normalized['body_html'] !== '') {
      $normalized['body_html'] = $this->runtime->sanitizeHtml($normalized['body_html'], [
        'a', 'b', 'blockquote', 'br', 'code', 'em', 'h2', 'h3', 'h4',
        'hr', 'i', 'li', 'ol', 'p', 'pre', 'strong', 'u', 'ul',
      ]);
    }

    if ($normalized['to'] === '' || $normalized['subject'] === '' || $normalized['body'] === '') {
      throw new \InvalidArgumentException('Ontvanger, onderwerp en berichtinhoud zijn verplicht voor een mailconcept.');
    }

    return $this->persistence->createDraft(
      $normalized,
      $this->runtime->currentUserId(),
      'outbound-draft:' . bin2hex(random_bytes(16)),
      'Uitgaand mailconcept aangemaakt; nog niet verzonden en expliciete goedkeuring vereist.',
    );
  }

  /**
   * Sends one approved outbound communication.
   *
   * Approval is represented by the exact formal status "Verzenden goedgekeurd".
   * AI or background processing must never set that status itself.
   */
  public function send(int $communicationId): void {
    if (!$this->runtime->canSend()) {
      throw new \RuntimeException('Gebruiker heeft geen mandaat om BREBO-mail te verzenden.');
    }

    $communication = $this->persistence->outboundMessage($communicationId);
    if ($communication === NULL) {
      throw new \InvalidArgumentException('Alleen BREBO Communication kan als e-mail worden verzonden.');
    }
    if ($communication['direction'] !== 'Uitgaand') {
      throw new \RuntimeException('Alleen uitgaande communicatie mag via deze service worden verzonden.');
    }
    if ($communication['formal_status'] !== 'Verzenden goedgekeurd') {
      throw new \RuntimeException('Mail is niet expliciet vrijgegeven voor verzending.');
    }
    if (!$this->runtime->transportEnabled()) {
      throw new \RuntimeException('BREBO SMTP-transport is nog niet expliciet geactiveerd; bericht blijft ongewijzigd als goedgekeurd concept staan.');
    }

    $to = $this->validatedAddresses($communication['to'], TRUE);
    $cc = $this->validatedAddresses($communication['cc'], FALSE);
    $bcc = $this->validatedAddresses($communication['bcc'], FALSE);
    $subject = trim($communication['subject']);
    $body = trim($communication['body']);
    $bodyHtml = trim($communication['body_html']);
    if ($to === '' || $subject === '' || $body === '') {
      throw new \RuntimeException('Goedgekeurde mail mist ontvanger, onderwerp of inhoud.');
    }

    $from = trim((string) getenv('BREBO_MAIL_ADDRESS')) ?: 'info@brebobv.nl';
    $sent = $this->runtime->send(
      $to,
      [
        'subject' => $subject,
        'body' => $body,
        'body_html' => $bodyHtml,
        'cc' => $cc,
        'bcc' => $bcc,
        'signature' => $this->signatureBuilder->build($communicationId),
        'attachments' => $this->attachmentService->resolve($communicationId),
        'communication_id' => $communicationId,
      ],
      $from,
    );

    if (!$sent) {
      throw new \RuntimeException('Mailtransport meldde dat de e-mail niet is verzonden.');
    }

    $this->persistence->markSent(
      $communicationId,
      gmdate('Y-m-d\\TH:i:s', $this->runtime->requestTime()),
      'E-mail na expliciete vrijgave verzonden via mailtransport; bron blijft in communicatie-dossier herleidbaar.',
    );
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

  public function addDraftRevisionNote(int $communicationId, string $revisionMessage): void {
    $this->persistence->addRevisionNote($communicationId, $revisionMessage);
  }

}
