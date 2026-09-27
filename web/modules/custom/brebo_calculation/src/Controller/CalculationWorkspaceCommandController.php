<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Controller;

use Drupal\brebo_calculation\Service\CalculationRowManager;
use Drupal\brebo_calculation\Service\CalculationStructureManager;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Explicit command endpoints for BREBO calculation mutations.
 *
 * Commands accept only scalar/domain values; no Drupal entities cross this boundary.
 */
final class CalculationWorkspaceCommandController extends ControllerBase {

  public function __construct(
    private readonly CalculationRowManager $rowManager,
    private readonly CalculationStructureManager $structureManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_calculation.row_manager'),
      $container->get('brebo_calculation.structure_manager'),
    );
  }

  public function addRow(Request $request, int $calculation): JsonResponse {
    return $this->command(function (array $input) use ($calculation): array {
      $rowId = $this->rowManager->add(
        $calculation,
        $this->requiredString($input, 'version'),
        $this->requiredString($input, 'paragraph_key'),
        (int) $this->currentUser()->id(),
      );
      return ['row_id' => $rowId];
    }, $request, 201);
  }

  public function updateRow(Request $request, int $calculation, int $row): JsonResponse {
    return $this->command(function (array $input) use ($calculation, $row): array {
      $costs = is_array($input['unit_costs'] ?? NULL) ? $input['unit_costs'] : [];
      $this->rowManager->updateQuickEntry(
        $calculation,
        $this->requiredString($input, 'version'),
        $row,
        $this->requiredString($input, 'description'),
        $this->requiredString($input, 'unit'),
        $this->requiredFloat($input, 'quantity'),
        $costs,
        (int) $this->currentUser()->id(),
      );
      return ['row_id' => $row];
    }, $request);
  }

  public function deleteRow(Request $request, int $calculation, int $row): JsonResponse {
    return $this->command(function (array $input) use ($calculation, $row): array {
      $this->rowManager->delete(
        $calculation,
        $this->requiredString($input, 'version'),
        $row,
        (int) $this->currentUser()->id(),
      );
      return ['row_id' => $row, 'deleted' => TRUE];
    }, $request);
  }

  public function moveRow(Request $request, int $calculation, int $row): JsonResponse {
    return $this->command(function (array $input) use ($calculation, $row): array {
      $this->rowManager->move(
        $calculation,
        $this->requiredString($input, 'version'),
        $row,
        $this->requiredString($input, 'paragraph_key'),
        (int) $this->currentUser()->id(),
      );
      return ['row_id' => $row];
    }, $request);
  }

  public function addMainGroup(Request $request, int $calculation): JsonResponse {
    return $this->command(function (array $input) use ($calculation): array {
      $key = $this->structureManager->addMainGroup(
        $calculation,
        $this->requiredString($input, 'version'),
        trim((string) ($input['code'] ?? '')),
        $this->requiredString($input, 'label'),
        (int) $this->currentUser()->id(),
      );
      return ['structure_key' => $key];
    }, $request, 201);
  }

  public function addParagraph(Request $request, int $calculation): JsonResponse {
    return $this->command(function (array $input) use ($calculation): array {
      $key = $this->structureManager->addParagraph(
        $calculation,
        $this->requiredString($input, 'version'),
        $this->requiredString($input, 'parent_key'),
        trim((string) ($input['code'] ?? '')),
        $this->requiredString($input, 'label'),
        ($location = trim((string) ($input['location_ref'] ?? ''))) !== '' ? $location : NULL,
        (int) $this->currentUser()->id(),
      );
      return ['structure_key' => $key];
    }, $request, 201);
  }

  public function reorderStructure(Request $request, int $calculation, string $structure): JsonResponse {
    return $this->command(function (array $input) use ($calculation, $structure): array {
      $this->structureManager->reorder(
        $calculation,
        $this->requiredString($input, 'version'),
        $structure,
        $this->requiredInt($input, 'sort_order'),
        (int) $this->currentUser()->id(),
      );
      return ['structure_key' => $structure];
    }, $request);
  }

  /**
   * @param callable(array<string,mixed>):array<string,mixed> $handler
   */
  private function command(callable $handler, Request $request, int $successStatus = 200): JsonResponse {
    if (!str_starts_with(strtolower((string) $request->headers->get('Content-Type', '')), 'application/json')) {
      return new JsonResponse(['error' => 'content_type', 'message' => 'application/json is required.'], 415);
    }

    try {
      $decoded = json_decode($request->getContent(), TRUE, 512, JSON_THROW_ON_ERROR);
      if (!is_array($decoded)) {
        throw new \InvalidArgumentException('JSON object expected.');
      }
      return new JsonResponse(['ok' => TRUE] + $handler($decoded), $successStatus);
    }
    catch (\JsonException|\InvalidArgumentException $e) {
      return new JsonResponse(['error' => 'invalid_request', 'message' => $e->getMessage()], 400);
    }
    catch (\RuntimeException $e) {
      return new JsonResponse(['error' => 'command_rejected', 'message' => $e->getMessage()], 409);
    }
  }

  /** @param array<string,mixed> $input */
  private function requiredString(array $input, string $key): string {
    $value = trim((string) ($input[$key] ?? ''));
    if ($value === '') {
      throw new \InvalidArgumentException($key . ' is required.');
    }
    return $value;
  }

  /** @param array<string,mixed> $input */
  private function requiredFloat(array $input, string $key): float {
    if (!isset($input[$key]) || !is_numeric($input[$key])) {
      throw new \InvalidArgumentException($key . ' must be numeric.');
    }
    return (float) $input[$key];
  }

  /** @param array<string,mixed> $input */
  private function requiredInt(array $input, string $key): int {
    if (!isset($input[$key]) || !is_numeric($input[$key])) {
      throw new \InvalidArgumentException($key . ' must be an integer.');
    }
    return (int) $input[$key];
  }

}
