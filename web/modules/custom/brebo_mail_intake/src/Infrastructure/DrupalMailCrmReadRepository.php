<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Infrastructure;

use Drupal\brebo_mail_intake\Contract\MailCrmReadRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/** Drupal node adapter for canonical CRM context reads. */
final class DrupalMailCrmReadRepository implements MailCrmReadRepositoryInterface {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function contactByEmail(string $email): ?array {
    $contact = $this->findUniqueByField('brebo_contact', 'field_brebo_contact_email', $email);
    if (!$contact instanceof NodeInterface) {
      return NULL;
    }

    $organizationId = NULL;
    if ($contact->hasField('field_brebo_org_ref') && !$contact->get('field_brebo_org_ref')->isEmpty()) {
      $candidateId = (int) $contact->get('field_brebo_org_ref')->target_id;
      $organization = $candidateId > 0
        ? $this->entityTypeManager->getStorage('node')->load($candidateId)
        : NULL;
      if ($organization instanceof NodeInterface && $organization->bundle() === 'brebo_organization' && $organization->isPublished()) {
        $organizationId = $candidateId;
      }
    }

    return [
      'id' => (int) $contact->id(),
      'organization_id' => $organizationId,
    ];
  }

  public function organizationByEmail(string $email): ?array {
    $organization = $this->findUniqueByField('brebo_organization', 'field_brebo_org_email', $email);
    return $organization instanceof NodeInterface ? ['id' => (int) $organization->id()] : NULL;
  }

  public function uniqueOrganizationByDomain(string $domain): ?array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_organization')
      ->condition('status', 1)
      ->condition('field_brebo_org_email', '%@' . $domain, 'LIKE')
      ->range(0, 2)
      ->execute();
    if (count($ids) !== 1) {
      return NULL;
    }
    $node = $storage->load((int) reset($ids));
    return $node instanceof NodeInterface ? ['id' => (int) $node->id()] : NULL;
  }

  private function findUniqueByField(string $bundle, string $field, string $value): ?NodeInterface {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $bundle)
      ->condition('status', 1)
      ->condition($field, $value)
      ->range(0, 2)
      ->execute();
    if (count($ids) !== 1) {
      return NULL;
    }
    $node = $storage->load((int) reset($ids));
    return $node instanceof NodeInterface ? $node : NULL;
  }

}
