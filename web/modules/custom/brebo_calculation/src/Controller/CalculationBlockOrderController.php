<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Reads and persists mixed row/recipe ordering inside calculation paragraphs. */
final class CalculationBlockOrderController extends ControllerBase {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager'),
    );
  }

  public function status(NodeInterface $node): JsonResponse {
    [$calculationId, $version] = $this->editableContext($node, FALSE);
    return new JsonResponse([
      'calculationId' => $calculationId,
      'version' => $version,
      'paragraphs' => $this->currentOrder($calculationId, $version),
    ]);
  }

  public function save(NodeInterface $node, Request $request): JsonResponse {
    [$calculationId, $version] = $this->editableContext($node, TRUE);
    $payload = json_decode((string) $request->getContent(), TRUE);
    if (!is_array($payload)) {
      throw new BadRequestHttpException('Ongeldige sorteeropdracht.');
    }

    $paragraphKey = trim((string) ($payload['paragraphKey'] ?? ''));
    $blocks = $payload['blocks'] ?? NULL;
    if ($paragraphKey === '' || !is_array($blocks)) {
      throw new BadRequestHttpException('Paragraaf en blokvolgorde zijn verplicht.');
    }

    $paragraph = $this->database->select('brebo_calculation_structure', 's')
      ->fields('s', ['node_key', 'node_type'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('node_key', $paragraphKey)
      ->execute()
      ->fetchAssoc();
    if (!$paragraph || $paragraph['node_type'] !== 'paragraph') {
      throw new BadRequestHttpException('Doel is geen geldige calculatieparagraaf.');
    }

    $expected = $this->blockSet($calculationId, $version, $paragraphKey);
    $received = [];
    foreach ($blocks as $block) {
      if (!is_array($block)) {
        throw new BadRequestHttpException('Ongeldig blok in sorteeropdracht.');
      }
      $type = (string) ($block['type'] ?? '');
      $id = (int) ($block['id'] ?? 0);
      if (!in_array($type, ['row', 'recipe'], TRUE) || $id <= 0) {
        throw new BadRequestHttpException('Onbekend calculatieblok.');
      }
      $key = $type . ':' . $id;
      if (isset($received[$key])) {
        throw new BadRequestHttpException('Dubbel calculatieblok in sorteeropdracht.');
      }
      $received[$key] = ['type' => $type, 'id' => $id];
    }

    if (array_keys($expected) !== array_keys($received)) {
      $expectedKeys = array_keys($expected);
      $receivedKeys = array_keys($received);
      sort($expectedKeys);
      sort($receivedKeys);
      if ($expectedKeys !== $receivedKeys) {
        throw new BadRequestHttpException('De blokvolgorde is verouderd. Vernieuw de werkbank en probeer opnieuw.');
      }
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $transaction = $this->database->startTransaction();
    try {
      $sortOrder = 10;
      foreach ($blocks as $block) {
        $type = (string) $block['type'];
        $id = (int) $block['id'];
        if ($type === 'row') {
          $line = $storage->load($id);
          if (!$line instanceof NodeInterface || $line->bundle() !== 'brebo_calc_line' || !$line->hasField('field_brebo_line_sequence')) {
            throw new BadRequestHttpException('Calculatieregel kan niet veilig worden gesorteerd.');
          }
          $line->set('field_brebo_line_sequence', $sortOrder);
          $line->setNewRevision(TRUE);
          $line->setRevisionLogMessage('Volgorde aangepast via calculatiewerkbank.');
          $line->save();
        }
        else {
          $updated = $this->database->update('brebo_calculation_recipe_instance')
            ->fields(['sort_order' => $sortOrder])
            ->condition('id', $id)
            ->condition('calculation_id', $calculationId)
            ->condition('calculation_version', $version)
            ->condition('paragraph_key', $paragraphKey)
            ->execute();
          if ($updated !== 1) {
            throw new BadRequestHttpException('Receptblok kan niet veilig worden gesorteerd.');
          }
        }
        $sortOrder += 10;
      }
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }

    return new JsonResponse([
      'ok' => TRUE,
      'paragraphKey' => $paragraphKey,
      'blocks' => array_values($received),
    ]);
  }

  /** @return array{0:int,1:string} */
  private function editableContext(NodeInterface $node, bool $requireEdit): array {
    if ($node->bundle() !== 'brebo_calculation' || $node->id() === NULL) {
      throw new NotFoundHttpException();
    }
    if (!$node->access('update') || !$this->currentUser()->hasPermission('edit brebo calculation workbench')) {
      throw new AccessDeniedHttpException();
    }

    $calculationId = (int) $node->id();
    $version = $this->database->select('brebo_calculation_version', 'v')
      ->fields('v', ['version', 'status', 'locked_at'])
      ->condition('calculation_id', $calculationId)
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();
    if (!$version) {
      throw new AccessDeniedHttpException('Calculatieversie ontbreekt.');
    }
    if ($requireEdit && ($version['status'] !== 'draft' || $version['locked_at'] !== NULL)) {
      throw new AccessDeniedHttpException('Alleen een ontgrendelde conceptcalculatie kan worden gesorteerd.');
    }
    return [$calculationId, (string) $version['version']];
  }

  /** @return array<string,array<int,array{type:string,id:int,sortOrder:int}>> */
  private function currentOrder(int $calculationId, string $version): array {
    $paragraphs = [];
    $storage = $this->entityTypeManager->getStorage('node');

    $rows = $this->database->select('brebo_calculation_row_domain', 'r')
      ->fields('r', ['calc_line_id', 'paragraph_key'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    $rowIds = array_map(static fn (array $row): int => (int) $row['calc_line_id'], $rows);
    $nodes = $rowIds ? $storage->loadMultiple($rowIds) : [];
    foreach ($rows as $row) {
      $id = (int) $row['calc_line_id'];
      $line = $nodes[$id] ?? NULL;
      $sortOrder = $line instanceof NodeInterface && $line->hasField('field_brebo_line_sequence')
        ? (int) ($line->get('field_brebo_line_sequence')->value ?? 0)
        : 0;
      $paragraphs[(string) $row['paragraph_key']][] = ['type' => 'row', 'id' => $id, 'sortOrder' => $sortOrder];
    }

    $recipes = $this->database->select('brebo_calculation_recipe_instance', 'i')
      ->fields('i', ['id', 'paragraph_key', 'sort_order'])
      ->condition('calculation_id', $calculationId)
      ->condition('calculation_version', $version)
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($recipes as $recipe) {
      $paragraphs[(string) $recipe['paragraph_key']][] = ['type' => 'recipe', 'id' => (int) $recipe['id'], 'sortOrder' => (int) $recipe['sort_order']];
    }

    foreach ($paragraphs as &$blocks) {
      usort($blocks, static function (array $a, array $b): int {
        $order = $a['sortOrder'] <=> $b['sortOrder'];
        if ($order !== 0) {
          return $order;
        }
        return ($a['type'] . ':' . $a['id']) <=> ($b['type'] . ':' . $b['id']);
      });
    }
    unset($blocks);
    return $paragraphs;
  }

  /** @return array<string,array{type:string,id:int}> */
  private function blockSet(int $calculationId, string $version, string $paragraphKey): array {
    $set = [];
    $rowIds = $this->database->select('brebo_calculation_row_domain', 'r')
      ->fields('r', ['calc_line_id'])
      ->condition('calculation_id', $calculationId)
      ->condition('version', $version)
      ->condition('paragraph_key', $paragraphKey)
      ->execute()
      ->fetchCol();
    foreach ($rowIds as $id) {
      $key = 'row:' . (int) $id;
      $set[$key] = ['type' => 'row', 'id' => (int) $id];
    }
    $recipeIds = $this->database->select('brebo_calculation_recipe_instance', 'i')
      ->fields('i', ['id'])
      ->condition('calculation_id', $calculationId)
      ->condition('calculation_version', $version)
      ->condition('paragraph_key', $paragraphKey)
      ->execute()
      ->fetchCol();
    foreach ($recipeIds as $id) {
      $key = 'recipe:' . (int) $id;
      $set[$key] = ['type' => 'recipe', 'id' => (int) $id];
    }
    ksort($set);
    return $set;
  }

}
