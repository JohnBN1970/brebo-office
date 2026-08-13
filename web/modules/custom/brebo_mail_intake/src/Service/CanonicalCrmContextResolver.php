<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Resolves existing BREBO CRM context from normalized mail metadata.
 *
 * This service is deliberately read-only. It never creates, publishes, merges
 * or mutates organizations or contacts. Exact contact e-mail matches have
 * precedence. Organization context may then come from that contact's explicit
 * organization reference, or from an exact organization e-mail/domain match.
 */
final class CanonicalCrmContextResolver {

  /** @var string[] */
  private const GENERIC_DOMAINS = [
    'gmail.com',
    'googlemail.com',
    'outlook.com',
    'hotmail.com',
    'live.com',
    'icloud.com',
    'me.com',
    'yahoo.com',
    'yahoo.nl',
    'proton.me',
    'protonmail.com',
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * @param array<string, mixed> $mail
   *
   * @return array<string, mixed>
   */
  public function resolve(array $mail): array {
    $addresses = $this->candidateAddresses($mail);
    if ($addresses === []) {
      return $this->emptyResult('Geen bruikbaar extern e-mailadres gevonden.');
    }

    foreach ($addresses as $email) {
      $contact = $this->findContactByEmail($email);
      if ($contact instanceof NodeInterface) {
        $organizationId = $this->organizationIdFromContact($contact);
        return [
          'contact_id' => (int) $contact->id(),
          'contact_state' => 'canonical',
          'organization_id' => $organizationId,
          'organization_state' => $organizationId !== NULL ? 'canonical' : 'unknown',
          'matched_email' => $email,
          'matched_domain' => $this->domain($email),
          'confidence' => 1.0,
          'basis' => 'Exact e-mailadres komt overeen met bestaande BREBO-contactpersoon.'
            . ($organizationId !== NULL ? ' Organisatie volgt uit de expliciete contactrelatie.' : ''),
        ];
      }
    }

    foreach ($addresses as $email) {
      $organization = $this->findOrganizationByEmail($email);
      if ($organization instanceof NodeInterface) {
        return [
          'contact_id' => NULL,
          'contact_state' => 'provisional_required',
          'organization_id' => (int) $organization->id(),
          'organization_state' => 'canonical',
          'matched_email' => $email,
          'matched_domain' => $this->domain($email),
          'confidence' => 0.95,
          'basis' => 'Exact e-mailadres komt overeen met bestaande BREBO-organisatie; contactpersoon is nog onbekend.',
        ];
      }
    }

    foreach ($addresses as $email) {
      $domain = $this->domain($email);
      if ($domain === '' || in_array($domain, self::GENERIC_DOMAINS, TRUE)) {
        continue;
      }
      $organization = $this->findUniqueOrganizationByDomain($domain);
      if ($organization instanceof NodeInterface) {
        return [
          'contact_id' => NULL,
          'contact_state' => 'provisional_required',
          'organization_id' => (int) $organization->id(),
          'organization_state' => 'canonical',
          'matched_email' => $email,
          'matched_domain' => $domain,
          'confidence' => 0.85,
          'basis' => 'Niet-generiek e-maildomein komt uniek overeen met een bestaande BREBO-organisatie; contactpersoon is nog onbekend.',
        ];
      }
    }

    $email = $addresses[0];
    return [
      'contact_id' => NULL,
      'contact_state' => 'provisional_required',
      'organization_id' => NULL,
      'organization_state' => 'provisional_required',
      'matched_email' => $email,
      'matched_domain' => $this->domain($email),
      'confidence' => 0.0,
      'basis' => 'Geen bestaande unieke CRM-match gevonden; menselijke beoordeling/provisional CRM-context vereist.',
    ];
  }

  /** @return string[] */
  private function candidateAddresses(array $mail): array {
    $own = mb_strtolower(trim((string) (getenv('BREBO_MAIL_ADDRESS') ?: '')));
    $values = [(string) ($mail['from'] ?? ''), (string) ($mail['to'] ?? '')];
    $addresses = [];
    foreach ($values as $value) {
      if ($value === '') {
        continue;
      }
      preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches);
      foreach ($matches[0] ?? [] as $address) {
        $address = mb_strtolower(trim((string) $address));
        if ($address === '' || ($own !== '' && $address === $own)) {
          continue;
        }
        $addresses[$address] = $address;
      }
    }
    return array_values($addresses);
  }

  private function findContactByEmail(string $email): ?NodeInterface {
    return $this->findUniqueByField('brebo_contact', 'field_brebo_contact_email', $email);
  }

  private function findOrganizationByEmail(string $email): ?NodeInterface {
    return $this->findUniqueByField('brebo_organization', 'field_brebo_org_email', $email);
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

  private function findUniqueOrganizationByDomain(string $domain): ?NodeInterface {
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
    return $node instanceof NodeInterface ? $node : NULL;
  }

  private function organizationIdFromContact(NodeInterface $contact): ?int {
    if (!$contact->hasField('field_brebo_org_ref') || $contact->get('field_brebo_org_ref')->isEmpty()) {
      return NULL;
    }
    $targetId = (int) $contact->get('field_brebo_org_ref')->target_id;
    if ($targetId <= 0) {
      return NULL;
    }
    $organization = $this->entityTypeManager->getStorage('node')->load($targetId);
    if (!$organization instanceof NodeInterface || $organization->bundle() !== 'brebo_organization' || !$organization->isPublished()) {
      return NULL;
    }
    return $targetId;
  }

  private function domain(string $email): string {
    $parts = explode('@', mb_strtolower($email), 2);
    return count($parts) === 2 ? trim($parts[1]) : '';
  }

  /** @return array<string, mixed> */
  private function emptyResult(string $basis): array {
    return [
      'contact_id' => NULL,
      'contact_state' => 'unknown',
      'organization_id' => NULL,
      'organization_state' => 'unknown',
      'matched_email' => '',
      'matched_domain' => '',
      'confidence' => 0.0,
      'basis' => $basis,
    ];
  }

}
