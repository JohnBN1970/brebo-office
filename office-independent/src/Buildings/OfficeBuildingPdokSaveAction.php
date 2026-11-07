<?php
declare(strict_types=1);
namespace Brebo\Office\Buildings;

require_once __DIR__ . '/OfficeBuildingPdokRequestGuard.php';
require_once __DIR__ . '/OfficeBuildingPdokClient.php';
require_once __DIR__ . '/OfficeBuildingPdokLocationRepository.php';

final class OfficeBuildingPdokSaveAction {
  public function __construct(private OfficeBuildingPdokLocationRepository $repository, private OfficeBuildingPdokClient $client) {}

  public function execute(string $operatorId, int $buildingId, string $sessionSecret, string $mount, string $publicHost, array $server, array $post, int $now): void {
    OfficeBuildingPdokRequestGuard::verify($server, $post['csrf'] ?? null, $sessionSecret, $buildingId, $mount, $publicHost, $now);
    if (($post['action'] ?? null) !== 'save_bag' || !is_string($post['pdok_id'] ?? null)) throw new \InvalidArgumentException('invalid_pdok_action');
    // Refuse unauthorized calls before requesting external data; repository
    // checks again under a transaction immediately before the update.
    if (!$this->repository->canManage($operatorId, $buildingId)) throw new \DomainException('building_manage_permission_required');
    $location = $this->client->lookup($post['pdok_id']);
    $this->repository->save($operatorId, $buildingId, $location['bag_id'], $location['address'],
      $location['postal_code'], $location['city'], $location['lat'], $location['lon']);
  }
}
