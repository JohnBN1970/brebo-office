<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;

/**
 * Creates unpublished project/building proposals from unresolved mail context.
 *
 * Provisional objects are review artifacts only. They are deliberately
 * unpublished and may never be treated as canonical truth before a human has
 * reviewed and promoted them.
 */
final class ProvisionalContextMaterializer {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * @param array<string, mixed> $resolution
   *
   * @return array{project_id:?int,building_id:?int}
   */
  public function materialize(NodeInterface $communication, array $resolution): array {
    if ($communication->bundle() !== 'brebo_communication') {
      throw new \InvalidArgumentException('Voorlopige context mag alleen uit BREBO Communication ontstaan.');
    }

    $projectId = isset($resolution['project_id']) ? (int) $resolution['project_id'] : 0;
    $buildingId = isset($resolution['building_id']) ? (int) $resolution['building_id'] : 0;

    if ($projectId <= 0 && ($resolution['project_state'] ?? NULL) === 'provisional_required') {
      $projectId = $this->createProject($communication, $resolution);
    }

    if ($buildingId <= 0 && ($resolution['building_state'] ?? NULL) === 'provisional_required') {
      $buildingId = $this->createBuilding($communication, $resolution);
    }

    return [
      'project_id' => $projectId > 0 ? $projectId : NULL,
      'building_id' => $buildingId > 0 ? $buildingId : NULL,
    ];
  }

  /** @param array<string, mixed> $resolution */
  private function createProject(NodeInterface $communication, array $resolution): int {
    $storage = $this->entityTypeManager->getStorage('node');
    $seed = (int) $communication->id();
    $title = '[VOORLOPIG] Project uit communicatie #' . $seed;
    $location = $this->candidateDisplayName($resolution) ?: 'Nog te bepalen uit communicatie';

    $node = $storage->create([
      'type' => 'brebo_project',
      'title' => $title,
      'uid' => (int) $this->currentUser->id(),
      'status' => 0,
      'field_brebo_project_code' => 'MAIL-' . $seed,
      'field_brebo_client' => 'Te beoordelen',
      'field_brebo_location' => $location,
      'field_brebo_status' => 'Te beoordelen',
      'field_brebo_description' => $this->sourceDescription($communication, $resolution, 'Voorlopig project'),
    ]);
    if (!$node instanceof NodeInterface) {
      throw new \RuntimeException('Voorlopig project kon niet worden aangemaakt.');
    }

    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Voorlopig project uit Mail Intake; menselijke beoordeling vereist.');
    $node->save();
    return (int) $node->id();
  }

  /** @param array<string, mixed> $resolution */
  private function createBuilding(NodeInterface $communication, array $resolution): int {
    $storage = $this->entityTypeManager->getStorage('node');
    $seed = (int) $communication->id();
    $candidate = $this->firstCandidate($resolution);
    $properties = is_array($candidate['properties'] ?? NULL) ? $candidate['properties'] : [];

    $street = trim((string) ($properties['straatnaam'] ?? ''));
    $houseNumber = trim((string) ($properties['huisnummer'] ?? ''));
    $houseLetter = trim((string) ($properties['huisletter'] ?? ''));
    $addition = trim((string) ($properties['huisnummertoevoeging'] ?? ''));
    $postalCode = strtoupper(trim((string) ($properties['postcode'] ?? '')));
    $city = trim((string) ($properties['woonplaatsnaam'] ?? ''));
    $number = trim($houseNumber . $houseLetter . ($addition !== '' ? '-' . $addition : ''));
    $address = trim($street . ($number !== '' ? ' ' . $number : ''));
    $display = $this->candidateDisplayName($resolution);

    if ($address === '') {
      $address = $display !== '' ? $display : 'Adres te beoordelen';
    }

    $title = '[VOORLOPIG] ' . ($display !== '' ? $display : ('Gebouw uit communicatie #' . $seed));
    $node = $storage->create([
      'type' => 'brebo_building',
      'title' => $title,
      'uid' => (int) $this->currentUser->id(),
      'status' => 0,
      'field_brebo_building_code' => 'MAIL-BLD-' . $seed,
      'field_brebo_address' => $address,
      'field_brebo_postal_code' => $postalCode,
      'field_brebo_city' => $city,
      'field_brebo_country' => 'Nederland',
      'field_brebo_status' => 'Te beoordelen',
      'field_brebo_description' => $this->sourceDescription($communication, $resolution, 'Voorlopig gebouw'),
    ]);
    if (!$node instanceof NodeInterface) {
      throw new \RuntimeException('Voorlopig gebouw kon niet worden aangemaakt.');
    }

    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Voorlopig gebouw uit Mail Intake/PDOK; menselijke beoordeling vereist.');
    $node->save();
    return (int) $node->id();
  }

  /** @param array<string, mixed> $resolution */
  private function firstCandidate(array $resolution): array {
    $candidates = $resolution['pdok_address_candidates'] ?? [];
    return is_array($candidates) && isset($candidates[0]) && is_array($candidates[0]) ? $candidates[0] : [];
  }

  /** @param array<string, mixed> $resolution */
  private function candidateDisplayName(array $resolution): string {
    $candidate = $this->firstCandidate($resolution);
    return trim((string) ($candidate['display_name'] ?? ''));
  }

  /** @param array<string, mixed> $resolution */
  private function sourceDescription(NodeInterface $communication, array $resolution, string $kind): string {
    $candidate = $this->firstCandidate($resolution);
    $source = trim((string) ($candidate['source'] ?? 'Mail Intake'));
    $retrievedAt = trim((string) ($candidate['retrieved_at'] ?? ''));
    $featureId = trim((string) ($candidate['feature_id'] ?? ''));
    $basis = trim((string) ($resolution['basis'] ?? ''));

    $parts = [
      $kind . '; nog niet canoniek.',
      'Broncommunicatie: #' . (int) $communication->id() . '.',
      'Bron: ' . ($source !== '' ? $source : 'Mail Intake') . '.',
    ];
    if ($retrievedAt !== '') {
      $parts[] = 'Opgehaald: ' . $retrievedAt . '.';
    }
    if ($featureId !== '') {
      $parts[] = 'Externe feature-id: ' . $featureId . '.';
    }
    if ($basis !== '') {
      $parts[] = 'Resolverbasis: ' . $basis;
    }
    $parts[] = 'Menselijke beoordeling en expliciete bevestiging vereist voor publicatie/canonieke status.';
    return implode(' ', $parts);
  }

}
