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
    $location = $this->candidateDisplayName($resolution) ?: 'Locatie nog te bepalen';
    $title = 'MOGELIJK NIEUW PROJECT - ' . $location;

    $node = $storage->create([
      'type' => 'brebo_project',
      'title' => $title,
      'uid' => (int) $this->currentUser->id(),
      'status' => 0,
      'field_brebo_project_code' => 'MAIL-' . $seed,
      'field_brebo_client' => 'Te beoordelen',
      'field_brebo_location' => $location,
      'field_brebo_status' => 'Mogelijk nieuw - te beoordelen',
      'field_brebo_description' => $this->sourceDescription($communication, $resolution, 'project'),
    ]);
    if (!$node instanceof NodeInterface) {
      throw new \RuntimeException('Voorlopig project kon niet worden aangemaakt.');
    }

    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Mogelijk nieuw project uit Mail Intake; bronmail en reden zichtbaar voor beoordeling.');
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

    $title = 'MOGELIJK NIEUW GEBOUW - ' . ($display !== '' ? $display : $address);
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
      'field_brebo_status' => 'Mogelijk nieuw - te beoordelen',
      'field_brebo_description' => $this->sourceDescription($communication, $resolution, 'gebouw'),
    ]);
    if (!$node instanceof NodeInterface) {
      throw new \RuntimeException('Voorlopig gebouw kon niet worden aangemaakt.');
    }

    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage('Mogelijk nieuw gebouw uit Mail Intake/PDOK; bronmail en reden zichtbaar voor beoordeling.');
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
    $subject = $this->fieldValue($communication, 'field_brebo_comm_subject');
    $from = $this->fieldValue($communication, 'field_brebo_mail_from');
    $receivedAt = $this->fieldValue($communication, 'field_brebo_comm_datetime');

    $parts = [
      'STATUS: MOGELIJK NIEUW ' . mb_strtoupper($kind) . ' - nog niet bevestigd.',
      'BEOORDELING NODIG: koppel aan een bestaand ' . $kind . ' of bevestig dat dit een nieuw ' . $kind . ' is.',
      'Broncommunicatie: #' . (int) $communication->id() . ' (/node/' . (int) $communication->id() . ').',
    ];
    if ($subject !== '') {
      $parts[] = 'E-mail onderwerp: ' . $subject . '.';
    }
    if ($from !== '') {
      $parts[] = 'E-mail van: ' . $from . '.';
    }
    if ($receivedAt !== '') {
      $parts[] = 'E-mail datum/tijd: ' . $receivedAt . '.';
    }
    $parts[] = 'Waarom voorgesteld: ' . ($basis !== '' ? $basis : 'Geen bestaande canonieke match gevonden.') . '';
    $parts[] = 'Externe bron: ' . ($source !== '' ? $source : 'Mail Intake') . '.';
    if ($retrievedAt !== '') {
      $parts[] = 'Externe gegevens opgehaald: ' . $retrievedAt . '.';
    }
    if ($featureId !== '') {
      $parts[] = 'Externe feature-id: ' . $featureId . '.';
    }
    $parts[] = 'Dit voorstel mag pas na menselijke bevestiging gepubliceerd of als canonieke waarheid gebruikt worden.';
    return implode("\n", $parts);
  }

  private function fieldValue(NodeInterface $node, string $fieldName): string {
    if (!$node->hasField($fieldName) || $node->get($fieldName)->isEmpty()) {
      return '';
    }
    return trim((string) $node->get($fieldName)->value);
  }

}
