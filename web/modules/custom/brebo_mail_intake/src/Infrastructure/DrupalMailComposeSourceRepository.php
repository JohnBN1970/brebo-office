<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Infrastructure;

use Drupal\brebo_mail_intake\Contract\MailComposeSourceRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\UserSession;
use Drupal\node\NodeInterface;

final class DrupalMailComposeSourceRepository implements MailComposeSourceRepositoryInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountSwitcherInterface $accountSwitcher,
  ) {}

  public function load(int $communicationId, int $viewerId): ?array {
    $node = $this->entityTypeManager->getStorage('node')->load($communicationId);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'brebo_communication') {
      return NULL;
    }

    $account = new UserSession(['uid' => $viewerId]);
    $this->accountSwitcher->switchTo($account);
    try {
      if (!$node->access('view', $account)) {
        return NULL;
      }
    }
    finally {
      $this->accountSwitcher->switchBack();
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
