<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Controller;

use Drupal\brebo_office_core\Service\ClassificationMaster;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/** Read-only classification options for calculation forms. */
final class ClassificationOptionsController extends ControllerBase {

  public function __construct(private readonly ClassificationMaster $master) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('brebo_office_core.classification_master'));
  }

  public function list(string $system): JsonResponse {
    $system = strtolower(trim($system));
    if (!in_array($system, [ClassificationMaster::SYSTEM_NLSFB, ClassificationMaster::SYSTEM_STABU, ClassificationMaster::SYSTEM_BREBO], TRUE)) {
      return new JsonResponse(['error' => 'unsupported_classification_system'], 400);
    }

    $items = array_map(static fn (array $row): array => [
      'code' => (string) $row['code'],
      'description' => (string) $row['description'],
      'parent_code' => $row['parent_code'] ?? NULL,
      'level' => (int) ($row['level'] ?? 1),
      'source' => $row['source'] ?? NULL,
      'source_version' => $row['source_version'] ?? NULL,
    ], $this->master->options($system));

    return new JsonResponse([
      'system' => $system,
      'count' => count($items),
      'items' => $items,
    ], 200, ['Cache-Control' => 'private, no-store']);
  }
}
