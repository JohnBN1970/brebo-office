<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Controller;

use Drupal\brebo_calculation\Service\CalculationRowManager;
use Drupal\brebo_calculation\Service\CalculationStructureManager;
use Drupal\brebo_calculation\Service\RecipeManager;
use Drupal\brebo_calculation\Service\SubcalculationManager;
use Drupal\brebo_calculation\Service\CalculationWorkspaceResourceGuard;
use Drupal\brebo_calculation\Service\CalculationPriceSourceManager;
use Drupal\brebo_calculation\Service\ObjectExceptionLineManager;
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
    private readonly RecipeManager $recipeManager,
    private readonly SubcalculationManager $subcalculationManager,
    private readonly CalculationWorkspaceResourceGuard $resourceGuard,
    private readonly CalculationPriceSourceManager $priceSourceManager,
    private readonly ObjectExceptionLineManager $exceptionLineManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('brebo_calculation.row_manager'),
      $container->get('brebo_calculation.structure_manager'),
      $container->get('brebo_calculation.recipe_manager'),
      $container->get('brebo_calculation.subcalculation_manager'),
      $container->get('brebo_calculation.workspace_resource_guard'),
      $container->get('brebo_calculation.price_source_manager'),
      $container->get('brebo_calculation.object_exception_line_manager'),
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

  public function placeRecipe(Request $request, int $calculation): JsonResponse {
    return $this->command(function (array $input) use ($calculation): array {
      $parameters = is_array($input['parameters'] ?? NULL) ? $input['parameters'] : [];
      $instanceId = $this->recipeManager->placeRecipe(
        $calculation,
        $this->requiredString($input, 'version'),
        $this->requiredString($input, 'paragraph_key'),
        $this->requiredInt($input, 'recipe_version_id'),
        $this->requiredFloat($input, 'quantity'),
        $parameters,
        (int) $this->currentUser()->id(),
      );
      return ['recipe_instance_id' => $instanceId];
    }, $request, 201);
  }

  public function updateRecipe(Request $request, int $calculation, int $recipe): JsonResponse {
    return $this->command(function (array $input) use ($calculation, $recipe): array {
      $this->resourceGuard->assertRecipeInstance($calculation, $recipe);
      if (isset($input['quantity'])) {
        $this->recipeManager->updateQuantity($recipe, $this->requiredFloat($input, 'quantity'), (int) $this->currentUser()->id());
      }
      if (isset($input['parameters'])) {
        if (!is_array($input['parameters'])) {
          throw new \InvalidArgumentException('parameters must be an object.');
        }
        $this->recipeManager->updateParameters($recipe, $input['parameters'], (int) $this->currentUser()->id());
      }
      return ['recipe_instance_id' => $recipe];
    }, $request);
  }

  public function createSubcalculation(Request $request, int $calculation): JsonResponse {
    return $this->command(function (array $input) use ($calculation): array {
      $id = $this->subcalculationManager->create(
        $calculation,
        $this->requiredString($input, 'version'),
        $input,
        (int) $this->currentUser()->id(),
      );
      return ['subcalculation_id' => $id];
    }, $request, 201);
  }

  public function addSubcalculationScope(Request $request, int $calculation, int $subcalculation): JsonResponse {
    return $this->command(function (array $input) use ($calculation, $subcalculation): array {
      $this->resourceGuard->assertSubcalculation($calculation, $subcalculation);
      $id = $this->subcalculationManager->addScope(
        $subcalculation,
        $this->requiredString($input, 'scope_type'),
        $this->requiredString($input, 'scope_ref'),
        isset($input['multiplier']) && is_numeric($input['multiplier']) ? (float) $input['multiplier'] : 1.0,
        (int) $this->currentUser()->id(),
      );
      return ['scope_id' => $id, 'subcalculation_id' => $subcalculation];
    }, $request, 201);
  }

  public function createSubcalculationApplication(Request $request, int $calculation, int $subcalculation): JsonResponse {
    return $this->command(function (array $input) use ($calculation, $subcalculation): array {
      $this->resourceGuard->assertSubcalculation($calculation, $subcalculation);
      $id = $this->subcalculationManager->createApplication(
        $subcalculation,
        $input,
        (int) $this->currentUser()->id(),
      );
      return ['application_id' => $id, 'subcalculation_id' => $subcalculation];
    }, $request, 201);
  }

  public function addSubcalculationApplicationObject(Request $request, int $calculation, int $subcalculation, int $application): JsonResponse {
    return $this->command(function (array $input) use ($calculation, $subcalculation, $application): array {
      $this->resourceGuard->assertApplication($calculation, $subcalculation, $application);
      $id = $this->subcalculationManager->addApplicationObject(
        $application,
        $this->requiredString($input, 'object_type'),
        $this->requiredString($input, 'object_ref'),
        isset($input['factor']) && is_numeric($input['factor']) ? (float) $input['factor'] : 1.0,
        !empty($input['is_exception']),
        ($note = trim((string) ($input['exception_payload'] ?? ''))) !== '' ? $note : NULL,
        (int) $this->currentUser()->id(),
        is_array($input['exception_costs'] ?? NULL) ? $input['exception_costs'] : [],
      );
      return ['object_id' => $id, 'application_id' => $application];
    }, $request, 201);
  }

  public function addPriceSource(Request $request, int $calculation, int $row): JsonResponse {
    return $this->command(function (array $input) use ($calculation, $row): array {
      $sourceId = $this->priceSourceManager->createForLine(
        $calculation,
        $this->requiredString($input, 'version'),
        $row,
        $input,
        (int) $this->currentUser()->id(),
      );
      return ['price_source_id' => $sourceId, 'row_id' => $row];
    }, $request, 201);
  }

  public function approvePriceSource(Request $request, int $calculation, int $row, int $source): JsonResponse {
    return $this->command(function (array $input) use ($calculation, $row, $source): array {
      $this->priceSourceManager->approveForLine(
        $calculation,
        $this->requiredString($input, 'version'),
        $row,
        $source,
        $this->requiredString($input, 'cost_carrier'),
        $this->requiredFloat($input, 'unit_cost'),
        ($note = trim((string) ($input['note'] ?? ''))) !== '' ? $note : NULL,
        (int) $this->currentUser()->id(),
      );
      return ['price_source_id' => $source, 'row_id' => $row, 'approved' => TRUE];
    }, $request);
  }

  public function addObjectExceptionLine(Request $request, int $calculation, int $subcalculation, int $application, int $object): JsonResponse {
    return $this->command(function (array $input) use ($calculation, $subcalculation, $application, $object): array {
      $this->resourceGuard->assertApplicationObject($calculation, $subcalculation, $application, $object);
      $lineId = $this->exceptionLineManager->addLine(
        $object,
        $input,
        (int) $this->currentUser()->id(),
      );
      return ['exception_line_id' => $lineId, 'object_id' => $object];
    }, $request, 201);
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
