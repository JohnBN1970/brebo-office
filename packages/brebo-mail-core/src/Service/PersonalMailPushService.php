<?php

declare(strict_types=1);

namespace Brebo\Mail\Service;

use Brebo\Mail\Domain\NormalizedMailMessage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Explicit personal-mail admission boundary.
 *
 * This service does not scan personal mailboxes or create dossier links.
 * The caller must authenticate the actor and supply the selected message.
 */
final class PersonalMailPushService {

  /**
   * @param callable(NormalizedMailMessage):array<string,mixed> $enqueue
   */
  public function __construct(private readonly mixed $enqueue) {}

  /**
   * @param array<string,mixed> $mailbox
   * @return array<string,mixed>
   */
  public function push(int $actorUid, array $mailbox, NormalizedMailMessage $message): array {
    if ($actorUid <= 0) {
      throw new RuntimeException('Persoonlijke mailpush vereist een ingelogde gebruiker.');
    }
    if (($mailbox['privacy_type'] ?? '') !== 'personal'
      || empty($mailbox['active'])
      || (int) ($mailbox['owner_uid'] ?? 0) !== $actorUid) {
      throw new RuntimeException('Geen toegang tot persoonlijke mailpush.');
    }

    $sourceId = trim($message->sourceId);
    if ($sourceId === '') {
      throw new InvalidArgumentException('Bronidentiteit van bericht ontbreekt.');
    }

    // The sink must enforce a unique source identity and store an audit event.
    // The classification/review pipeline is responsible for any proposed links.
    $result = ($this->enqueue)($message);
    if (!is_array($result) || !in_array(($result['state'] ?? ''), ['created', 'existing', 'queued'], TRUE)) {
      throw new RuntimeException('Persoonlijke mail kon niet veilig in de controlewachtrij worden geplaatst.');
    }
    return $result;
  }
}
