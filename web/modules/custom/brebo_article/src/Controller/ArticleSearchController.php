<?php

declare(strict_types=1);

namespace Drupal\brebo_article\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Zoekt actuele leveranciersartikelen voor de calculatie-pop-up.
 */
final class ArticleSearchController extends ControllerBase {

  public function __construct(private readonly Connection $database) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'));
  }

  public function search(Request $request): JsonResponse {
    $term = trim((string) $request->query->get('q', ''));
    $supplier = trim((string) $request->query->get('supplier', ''));
    $category = trim((string) $request->query->get('category', ''));
    $limit = max(10, min(100, (int) $request->query->get('limit', 40)));

    $query = $this->database->select('brebo_supplier_article', 'sa');
    $query->join('brebo_article', 'a', 'a.id = sa.article_id');
    $query->join('brebo_supplier', 's', 's.id = sa.supplier_id');
    $query->join('brebo_article_price', 'p', 'p.supplier_article_id = sa.id');
    $query->join('brebo_catalog_import', 'ci', 'ci.id = p.catalog_import_id');
    $query->fields('a', ['id', 'code', 'description', 'base_unit', 'cost_category', 'nlsfb_code']);
    $query->addField('sa', 'id', 'supplier_article_id');
    $query->fields('sa', ['supplier_article_no', 'gtin', 'order_unit', 'use_unit', 'conversion_factor', 'minimum_order', 'product_group', 'product_url']);
    $query->addField('s', 'name', 'supplier_name');
    $query->addField('p', 'id', 'price_id');
    $query->fields('p', ['net_price', 'gross_price', 'currency', 'valid_from', 'quantity_from']);
    $query->addField('ci', 'id', 'catalog_import_id');
    $query->condition('a.active', 1);
    $query->condition('sa.active', 1);
    $query->condition('ci.status', 'actief');

    if ($term !== '') {
      $or = $query->orConditionGroup()
        ->condition('a.description', '%' . $this->database->escapeLike($term) . '%', 'LIKE')
        ->condition('a.code', '%' . $this->database->escapeLike($term) . '%', 'LIKE')
        ->condition('sa.supplier_article_no', '%' . $this->database->escapeLike($term) . '%', 'LIKE')
        ->condition('sa.gtin', '%' . $this->database->escapeLike($term) . '%', 'LIKE')
        ->condition('sa.product_group', '%' . $this->database->escapeLike($term) . '%', 'LIKE')
        ->condition('a.nlsfb_code', '%' . $this->database->escapeLike($term) . '%', 'LIKE');
      $query->condition($or);
    }
    if ($supplier !== '') {
      $query->condition('s.name', '%' . $this->database->escapeLike($supplier) . '%', 'LIKE');
    }
    if ($category !== '') {
      $query->condition('a.cost_category', $category);
    }

    $query->orderBy('a.description');
    $query->orderBy('p.valid_from', 'DESC');
    $query->range(0, $limit);

    $seen = [];
    $items = [];
    foreach ($query->execute() as $row) {
      $key = (int) $row->supplier_article_id;
      if (isset($seen[$key])) {
        continue;
      }
      $seen[$key] = TRUE;
      $items[] = [
        'article_id' => (int) $row->id,
        'supplier_article_id' => $key,
        'price_id' => (int) $row->price_id,
        'catalog_import_id' => (int) $row->catalog_import_id,
        'code' => $row->code,
        'description' => $row->description,
        'supplier' => $row->supplier_name,
        'supplier_article_no' => $row->supplier_article_no,
        'gtin' => $row->gtin,
        'product_group' => $row->product_group,
        'nlsfb_code' => $row->nlsfb_code,
        'unit' => $row->use_unit ?: $row->base_unit,
        'order_unit' => $row->order_unit,
        'conversion_factor' => (float) $row->conversion_factor,
        'minimum_order' => (float) $row->minimum_order,
        'net_price' => (float) $row->net_price,
        'gross_price' => $row->gross_price === NULL ? NULL : (float) $row->gross_price,
        'currency' => $row->currency,
        'price_date' => $row->valid_from,
        'quantity_from' => (float) $row->quantity_from,
        'product_url' => $row->product_url,
      ];
    }

    return new JsonResponse([
      'query' => $term,
      'count' => count($items),
      'items' => $items,
    ]);
  }

}
