<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/** Builds a sender-specific signature from canonical user and company data. */
final class MailSignatureBuilder {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /** @return array{name:string,roles:string,company:string,email:string,phone:string,address:string} */
  public function build(NodeInterface $communication): array {
    $account = $communication->getOwner();
    $roleLabels = [];
    if ($account) {
      $roleStorage = $this->entityTypeManager->getStorage('user_role');
      foreach ($account->getRoles(TRUE) as $roleId) {
        $role = $roleStorage->load($roleId);
        if ($role) {
          $roleLabels[] = (string) $role->label();
        }
      }
    }

    $config = $this->configFactory->get('brebo_office_core.settings');
    $email = $communication->hasField('field_brebo_mail_from')
      ? trim((string) $communication->get('field_brebo_mail_from')->value)
      : '';
    if ($email === '') {
      $email = trim((string) ($config->get('mail.sender_address') ?: $config->get('organization.general_email')));
    }

    return [
      'name' => $account ? trim((string) $account->getDisplayName()) : '',
      'roles' => implode(' · ', array_unique($roleLabels)),
      'company' => trim((string) ($config->get('organization.trade_name') ?: 'BREBO')),
      'email' => $email,
      'phone' => trim((string) $config->get('organization.general_phone')),
      'address' => trim((string) $config->get('organization.address')),
    ];
  }

}
