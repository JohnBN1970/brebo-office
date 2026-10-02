<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Service;

use Drupal\Core\Database\Connection;

/**
 * Resolves reusable Calc recipe source references against current Office data.
 */
final class CalcSourceResolver {

  public function __construct(private readonly Connection $database) {}

  /**
   * @param list<array<string,mixed>> $requests
   * @return list<array<string,mixed>>
   */
  public function resolve(array $requests, ?int $projectId = NULL): array {
    if (count($requests) > 200) {
      throw new \InvalidArgumentException('At most 200 Calc sources can be resolved at once.');
    }

    $results = [];
    foreach ($requests as $index => $request) {
      if (!is_array($request)) {
        throw new \InvalidArgumentException('Each Calc source request must be an object.');
      }
      $type = trim((string) ($request['type'] ?? ''));
      $ref = trim((string) ($request['ref'] ?? ''));
      $context = is_array($request['context'] ?? NULL) ? $request['context'] : [];
      if ($type === '' || $ref === '') {
        throw new \InvalidArgumentException('Calc source type and ref are required.');
      }

      try {
        $resolved = match ($type) {
          'article' => $this->resolveArticle($ref, $context),
          'norm' => $this->resolveNorm($ref, $context),
          'project_labour' => $this->resolveProjectLabour($ref, $projectId),
          default => throw new \InvalidArgumentException('Unsupported Calc source type: ' . $type),
        };
        $results[] = ['request_index' => $index, 'type' => $type, 'ref' => $ref] + $resolved;
      }
      catch (\RuntimeException $e) {
        $results[] = [
          'request_index' => $index,
          'type' => $type,
          'ref' => $ref,
          'status' => 'unresolved',
          'value' => NULL,
          'unit' => NULL,
          'description' => '',
          'source' => [],
          'reason' => $e->getMessage(),
        ];
      }
    }
    return $results;
  }

  /** @param array<string,mixed> $context */
  private function resolveArticle(string $ref, array $context): array {
    $query = $this->database->select('brebo_supplier_article', 'sa');
    $query->join('brebo_article', 'a', 'a.id = sa.article_id');
    $query->join('brebo_supplier', 's', 's.id = sa.supplier_id');
    $query->join('brebo_article_price', 'p', 'p.supplier_article_id = sa.id');
    $query->join('brebo_catalog_import', 'ci', 'ci.id = p.catalog_import_id');
    $query->fields('a', ['id', 'code', 'description', 'base_unit']);
    $query->addField('sa', 'id', 'supplier_article_id');
    $query->fields('sa', ['supplier_article_no', 'use_unit', 'order_unit', 'conversion_factor', 'minimum_order']);
    $query->addField('s', 'name', 'supplier_name');
    $query->addField('p', 'id', 'price_id');
    $query->fields('p', ['net_price', 'currency', 'valid_from', 'quantity_from']);
    $query->addField('ci', 'id', 'catalog_import_id');
    $query->condition('a.active', 1);
    $query->condition('sa.active', 1);
    $query->condition('ci.status', 'actief');

    if (ctype_digit($ref)) {
      $query->condition('a.id', (int) $ref);
    }
    else {
      $query->condition('a.code', $ref);
    }
    if (($supplier = trim((string) ($context['supplier'] ?? ''))) !== '') {
      $query->condition('s.name', $supplier);
    }
    if (isset($context['supplier_article_id']) && is_numeric($context['supplier_article_id'])) {
      $query->condition('sa.id', (int) $context['supplier_article_id']);
    }

    $query->orderBy('p.valid_from', 'DESC');
    $query->orderBy('p.quantity_from', 'ASC');
    $query->range(0, 1);
    $row = $query->execute()->fetchAssoc();
    if ($row === FALSE) {
      throw new \RuntimeException('Office article source not found: ' . $ref);
    }

    return [
      'status' => 'resolved',
      'value' => (float) $row['net_price'],
      'unit' => (string) ($row['use_unit'] ?: $row['base_unit']),
      'description' => (string) $row['description'],
      'source' => [
        'article_id' => (int) $row['id'],
        'article_code' => (string) $row['code'],
        'supplier_article_id' => (int) $row['supplier_article_id'],
        'supplier_article_no' => (string) $row['supplier_article_no'],
        'supplier' => (string) $row['supplier_name'],
        'price_id' => (int) $row['price_id'],
        'catalog_import_id' => (int) $row['catalog_import_id'],
        'currency' => (string) $row['currency'],
        'price_date' => (string) $row['valid_from'],
        'quantity_from' => (float) $row['quantity_from'],
        'conversion_factor' => (float) $row['conversion_factor'],
        'minimum_order' => (float) $row['minimum_order'],
        'order_unit' => $row['order_unit'],
      ],
    ];
  }

  /** @param array<string,mixed> $context */
  private function resolveNorm(string $ref, array $context): array {
    [$domain, $key] = array_pad(explode(':', $ref, 2), 2, '');
    if ($domain === '' || $key === '') {
      throw new \InvalidArgumentException('Norm source ref must be domain:key.');
    }
    if (!$this->database->schema()->tableExists('brebo_calculation_norm')) {
      throw new \RuntimeException('Office norm library is not available.');
    }

    $rows = $this->database->select('brebo_calculation_norm', 'n')
      ->fields('n')
      ->condition('domain', $domain)
      ->condition('norm_key', $key)
      ->condition('active', 1)
      ->orderBy('priority', 'DESC')
      ->orderBy('id', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
      $conditions = json_decode((string) ($row['conditions_json'] ?? ''), TRUE);
      if (!is_array($conditions)) $conditions = [];
      if (!$this->matches($conditions, $context)) continue;
      return [
        'status' => 'resolved',
        'value' => (float) $row['value'],
        'unit' => $row['unit'] !== NULL ? (string) $row['unit'] : NULL,
        'description' => (string) $row['label'],
        'source' => [
          'norm_id' => (int) $row['id'],
          'domain' => (string) $row['domain'],
          'norm_key' => (string) $row['norm_key'],
          'priority' => (int) $row['priority'],
          'source' => $row['source'],
          'changed' => (int) $row['changed'],
        ],
      ];
    }
    throw new \RuntimeException('Office norm source not found for context: ' . $ref);
  }

  private function resolveProjectLabour(string $ref, ?int $projectId): array {
    if ($projectId === NULL || $projectId <= 0) {
      throw new \InvalidArgumentException('project_id is required for project_labour sources.');
    }
    if (!$this->database->schema()->tableExists('brebo_finance_budget_line')) {
      throw new \RuntimeException('Office labour budget is not available.');
    }

    $query = $this->database->select('brebo_finance_budget_line', 'l');
    $query->join('brebo_finance_budget', 'b', 'b.id = l.budget_id');
    $query->fields('l', ['id', 'work_package', 'description', 'hourly_cost_ex_vat']);
    $query->condition('b.project_nid', $projectId);
    $query->condition('b.budget_type', 'working');
    $query->condition('b.status', 'locked');
    $query->condition('l.cost_code', 'arbeid');
    if ($ref !== 'default') {
      $or = $query->orConditionGroup()
        ->condition('l.work_package', $ref)
        ->condition('l.description', '%' . $this->database->escapeLike($ref) . '%', 'LIKE');
      $query->condition($or);
    }
    $query->orderBy('l.id', 'ASC');
    $query->range(0, 1);
    $row = $query->execute()->fetchAssoc();
    if ($row === FALSE) {
      throw new \RuntimeException('Office project labour source not found: ' . $ref);
    }

    return [
      'status' => 'resolved',
      'value' => (float) $row['hourly_cost_ex_vat'],
      'unit' => 'uur',
      'description' => (string) $row['description'],
      'source' => [
        'budget_line_id' => (int) $row['id'],
        'work_package' => (string) $row['work_package'],
        'project_id' => $projectId,
      ],
    ];
  }

  /** @param array<string,mixed> $conditions @param array<string,mixed> $context */
  private function matches(array $conditions, array $context): bool {
    foreach ($conditions as $key => $expected) {
      $key = (string) $key;
      if (str_ends_with($key, '_min')) {
        $field = substr($key, 0, -4);
        if (!isset($context[$field]) || (float) $context[$field] < (float) $expected) return FALSE;
        continue;
      }
      if (str_ends_with($key, '_max')) {
        $field = substr($key, 0, -4);
        if (!isset($context[$field]) || (float) $context[$field] > (float) $expected) return FALSE;
        continue;
      }
      if (!array_key_exists($key, $context)) return FALSE;
      if (is_array($expected)) {
        if (!in_array($context[$key], $expected, TRUE)) return FALSE;
      }
      elseif ((string) $context[$key] !== (string) $expected) return FALSE;
    }
    return TRUE;
  }

}
