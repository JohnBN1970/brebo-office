<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Service;

use Drupal\brebo_building_data\Contract\BuildingRelationRepositoryInterface;
use Drupal\brebo_mail_intake\Contract\ProvisionalContextRepositoryInterface;

/** Creates unpublished review artifacts only for evidenced candidates. */
final class ProvisionalContextMaterializer {

  public function __construct(
    private readonly ProvisionalContextRepositoryInterface $contextRepository,
    private readonly ?BuildingRelationRepositoryInterface $buildingRelations = NULL,
  ) {}

  /** @param array<string,mixed> $resolution */
  public function materialize(int $communicationId, int $actorId, array $resolution): array {
    if ($actorId <= 0) {
      throw new \InvalidArgumentException('Een geldige beoordelaar is verplicht voor voorlopige context.');
    }

    $communication = $this->contextRepository->communication($communicationId);
    if ($communication === NULL) {
      throw new \InvalidArgumentException('Voorlopige context mag alleen uit BREBO Communication ontstaan.');
    }

    $projectId = isset($resolution['project_id']) ? (int) $resolution['project_id'] : 0;
    $buildingId = isset($resolution['building_id']) ? (int) $resolution['building_id'] : 0;

    if ($projectId <= 0 && ($resolution['project_state'] ?? NULL) === 'provisional_required') {
      $projectId = $this->createProject($communication, $actorId, $resolution);
    }

    if ($buildingId <= 0 && ($resolution['building_state'] ?? NULL) === 'provisional_required') {
      $buildingId = $this->createBuilding($communication, $actorId, $resolution);
    }

    return [
      'project_id' => $projectId > 0 ? $projectId : NULL,
      'building_id' => $buildingId > 0 ? $buildingId : NULL,
    ];
  }

  /** @param array{id:int,subject:string,from:string,received_at:string} $communication @param array<string,mixed> $resolution */
  private function createProject(array $communication, int $actorId, array $resolution): int {
    $seed = (int) $communication['id'];
    $location = $this->candidateDisplayName($resolution) ?: 'Locatie nog te bepalen';

    return $this->contextRepository->createProject([
      'title' => 'MOGELIJK NIEUW PROJECT - ' . $location,
      'uid' => $actorId,
      'status' => 0,
      'field_brebo_project_code' => 'MAIL-' . $seed,
      'field_brebo_client' => 'Te beoordelen',
      'field_brebo_location' => $location,
      'field_brebo_status' => 'concept',
      'field_brebo_description' => $this->sourceDescription($communication, $resolution, 'project'),
    ], 'Projectkandidaat uit Mail Intake; bewijs en bron zichtbaar voor beoordeling.');
  }

  /** @param array{id:int,subject:string,from:string,received_at:string} $communication @param array<string,mixed> $resolution */
  private function createBuilding(array $communication, int $actorId, array $resolution): int {
    $seed = (int) $communication['id'];
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

    $buildingId = $this->contextRepository->createBuilding([
      'title' => 'MOGELIJK NIEUW GEBOUW - ' . ($display !== '' ? $display : $address),
      'uid' => $actorId,
      'status' => 0,
      'field_brebo_building_code' => 'MAIL-BLD-' . $seed,
      'field_brebo_address' => $address,
      'field_brebo_postal_code' => $postalCode,
      'field_brebo_city' => $city,
      'field_brebo_country' => 'Nederland',
      'field_brebo_status' => 'Mogelijk nieuw - te beoordelen',
      'field_brebo_description' => $this->sourceDescription($communication, $resolution, 'gebouw'),
    ], 'Mogelijk nieuw gebouw uit Mail Intake/PDOK; bronmail en reden zichtbaar voor beoordeling.');

    $this->persistBuildingRelations($buildingId, $candidate, $properties, (int) $communication['id']);
    return $buildingId;
  }

  /** @param array<string,mixed> $candidate @param array<string,mixed> $properties */
  private function persistBuildingRelations(int $buildingId, array $candidate, array $properties, int $communicationId): void {
    if ($this->buildingRelations === NULL) {
      return;
    }

    $source = trim((string) ($candidate['source'] ?? 'PDOK Locatieserver'));
    $sourceRef = trim((string) ($candidate['feature_id'] ?? ''));
    $retrievedAt = trim((string) ($candidate['retrieved_at'] ?? ''));
    $address = [
      'street' => $properties['straatnaam'] ?? NULL,
      'house_number' => $properties['huisnummer'] ?? NULL,
      'house_letter' => $properties['huisletter'] ?? NULL,
      'addition' => $properties['huisnummertoevoeging'] ?? NULL,
      'postal_code' => $properties['postcode'] ?? NULL,
      'city' => $properties['woonplaatsnaam'] ?? NULL,
      'country' => 'Nederland',
      'is_primary' => TRUE,
      'source' => $source,
      'source_ref' => $sourceRef !== '' ? $sourceRef : ('communication:' . $communicationId),
    ];
    if ($this->hasAddressIdentity($address)) {
      $this->buildingRelations->upsertAddress($buildingId, $address);
    }

    foreach ([
      'pand_id' => 'pand',
      'adresseerbaarobject_id' => 'adresseerbaarobject',
      'verblijfsobject_id' => 'verblijfsobject',
      'nummeraanduiding_id' => 'nummeraanduiding',
    ] as $propertyKey => $bagType) {
      $bagId = trim((string) ($properties[$propertyKey] ?? ''));
      if ($bagId === '') {
        continue;
      }
      $this->buildingRelations->upsertBagIdentity($buildingId, $bagType, $bagId, [
        'is_primary' => $bagType === 'pand',
        'source' => $source,
        'source_ref' => $sourceRef,
        'retrieved_at' => $retrievedAt,
      ]);
    }
  }

  /** @param array<string,mixed> $address */
  private function hasAddressIdentity(array $address): bool {
    $postcode = trim((string) ($address['postal_code'] ?? ''));
    $houseNumber = trim((string) ($address['house_number'] ?? ''));
    return ($postcode !== '' && $houseNumber !== '')
      || (trim((string) ($address['street'] ?? '')) !== '' && trim((string) ($address['city'] ?? '')) !== '');
  }

  /** @param array<string,mixed> $resolution */
  private function firstCandidate(array $resolution): array {
    $candidates = $resolution['pdok_address_candidates'] ?? [];
    return is_array($candidates) && isset($candidates[0]) && is_array($candidates[0])
      ? $candidates[0]
      : [];
  }

  /** @param array<string,mixed> $resolution */
  private function candidateDisplayName(array $resolution): string {
    return trim((string) ($this->firstCandidate($resolution)['display_name'] ?? ''));
  }

  /** @param array{id:int,subject:string,from:string,received_at:string} $communication @param array<string,mixed> $resolution */
  private function sourceDescription(array $communication, array $resolution, string $kind): string {
    $candidate = $this->firstCandidate($resolution);
    $source = trim((string) ($candidate['source'] ?? 'Mail Intake'));
    $retrievedAt = trim((string) ($candidate['retrieved_at'] ?? ''));
    $featureId = trim((string) ($candidate['feature_id'] ?? ''));
    $basis = trim((string) ($resolution['basis'] ?? ''));

    $parts = [
      'STATUS: MOGELIJK NIEUW ' . mb_strtoupper($kind) . ' - nog niet bevestigd.',
      'BEOORDELING NODIG: koppel aan bestaand of bevestig nieuw.',
      'Broncommunicatie: #' . (int) $communication['id'] . ' (/node/' . (int) $communication['id'] . ').',
    ];
    if ($communication['subject'] !== '') {
      $parts[] = 'E-mail onderwerp: ' . $communication['subject'] . '.';
    }
    if ($communication['from'] !== '') {
      $parts[] = 'E-mail van: ' . $communication['from'] . '.';
    }
    if ($communication['received_at'] !== '') {
      $parts[] = 'E-mail datum/tijd: ' . $communication['received_at'] . '.';
    }
    $parts[] = 'Waarom voorgesteld: ' . ($basis !== '' ? $basis : 'Voldoende concrete aanwijzingen voor menselijke beoordeling.');
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

}
