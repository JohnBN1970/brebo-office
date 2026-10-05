<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Infrastructure;

use Drupal\brebo_mail_intake\Contract\MailComposeSourceRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;

final class DrupalMailComposeSourceRepository implements MailComposeSourceRepositoryInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  public function load(int $communicationId): ?array {
    $node = $this->entityTypeManager->getStorage('node')->load($communicationId);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_communication' || !$node->access('view', $this->currentUser)) {
      return NULL;
    }

    return [
      'subject' => trim((string) ($node->get('field_brebo_comm_subject')->value ?? $node->label())),
      'transcript' => trim((string) ($node->get('field_brebo_transcript')->value ?? '')),
      'html' => $node->hasField('field_brebo_mail_html') ? trim((string) ($node->get('field_brebo_mail_html')->value ?? '')) : '',
      'from' => trim((string) ($node->get('field_brebo_mail_from')->value ?? '')),
      'to' => trim((string) ($node->get('field_brebo_mail_to')->value ?? '')),
      'cc' => $node->hasField('field_brebo_mail_cc') ? trim((string) ($node->get('field_brebo_mail_cc')->value ?? '')) : '',
      'datetime' => trim((string) ($node->get('field_brebo_comm_datetime')->value ?? '')),
    ];
  }

}
