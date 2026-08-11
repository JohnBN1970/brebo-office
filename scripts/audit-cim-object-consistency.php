<?php

declare(strict_types=1);

use Drupal\node\NodeInterface;

$storage = \Drupal::entityTypeManager()->getStorage('node');

$issues = [];
$stats = [
  'projects' => 0,
  'project_scopes' => 0,
  'communications' => 0,
  'clusters' => 0,
  'dwellings' => 0,
  'product_positions' => 0,
  'building_zones' => 0,
  'issues' => 0,
];

/** @return int[] */
function brebo_ref_ids(NodeInterface $node, string $field): array {
  if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
    return [];
  }
  return array_values(array_filter(array_map(
    static fn(array $item): int => (int) ($item['target_id'] ?? 0),
    $node->get($field)->getValue(),
  )));
}

function brebo_single_ref(NodeInterface $node, string $field): ?int {
  $ids = brebo_ref_ids($node, $field);
  return $ids[0] ?? NULL;
}

function brebo_issue(array &$issues, string $code, NodeInterface $node, string $message, array $context = []): void {
  $issues[] = [
    'code' => $code,
    'entity' => $node->bundle(),
    'nid' => (int) $node->id(),
    'title' => $node->label(),
    'message' => $message,
    'context' => $context,
  ];
}

/** @return NodeInterface[] */
function brebo_nodes(string $bundle): array {
  $ids = \Drupal::entityQuery('node')
    ->accessCheck(FALSE)
    ->condition('type', $bundle)
    ->execute();
  return $ids ? array_values(\Drupal::entityTypeManager()->getStorage('node')->loadMultiple($ids)) : [];
}

/**
 * Resolve the permanent building for a known physical object.
 */
function brebo_physical_building(NodeInterface $node, array &$cache): ?int {
  $nid = (int) $node->id();
  if (array_key_exists($nid, $cache)) {
    return $cache[$nid];
  }

  $building = NULL;
  switch ($node->bundle()) {
    case 'brebo_building':
      $building = $nid;
      break;

    case 'brebo_cluster':
    case 'brebo_building_zone':
      $building = brebo_single_ref($node, 'field_brebo_building_ref');
      break;

    case 'brebo_dwelling':
      $cluster_id = brebo_single_ref($node, 'field_brebo_cluster_ref');
      if ($cluster_id && ($cluster = \Drupal::entityTypeManager()->getStorage('node')->load($cluster_id)) instanceof NodeInterface) {
        $building = brebo_physical_building($cluster, $cache);
      }
      break;

    case 'brebo_product_position':
      $dwelling_id = brebo_single_ref($node, 'field_brebo_dwelling_ref');
      if ($dwelling_id && ($dwelling = \Drupal::entityTypeManager()->getStorage('node')->load($dwelling_id)) instanceof NodeInterface) {
        $building = brebo_physical_building($dwelling, $cache);
      }
      break;
  }

  $cache[$nid] = $building;
  return $building;
}

$physical_cache = [];
$projects = [];
foreach (brebo_nodes('brebo_project') as $project) {
  $stats['projects']++;
  $projects[(int) $project->id()] = brebo_ref_ids($project, 'field_brebo_building_refs');
}

foreach (['brebo_cluster' => 'clusters', 'brebo_dwelling' => 'dwellings', 'brebo_product_position' => 'product_positions', 'brebo_building_zone' => 'building_zones'] as $bundle => $stat) {
  foreach (brebo_nodes($bundle) as $node) {
    $stats[$stat]++;
    if (brebo_physical_building($node, $physical_cache) === NULL) {
      brebo_issue($issues, 'physical_object_without_building', $node, 'Permanent physical object cannot be resolved to exactly one building.');
    }
  }
}

$scope_fields = [
  'field_brebo_scope_clusters' => 'cluster',
  'field_brebo_scope_dwellings' => 'dwelling',
  'field_brebo_scope_positions' => 'product_position',
  'field_brebo_scope_zones' => 'building_zone',
];

foreach (brebo_nodes('brebo_project_scope') as $scope) {
  $stats['project_scopes']++;
  $project_id = brebo_single_ref($scope, 'field_brebo_project_ref');
  $building_id = brebo_single_ref($scope, 'field_brebo_building_ref');

  if (!$project_id || !$building_id) {
    brebo_issue($issues, 'scope_missing_parent', $scope, 'Projectscope must reference exactly one project and one building.', [
      'project_id' => $project_id,
      'building_id' => $building_id,
    ]);
    continue;
  }

  $project_buildings = $projects[$project_id] ?? [];
  if (!in_array($building_id, $project_buildings, TRUE)) {
    brebo_issue($issues, 'scope_building_not_in_project', $scope, 'Projectscope building is not one of the buildings linked to its project.', [
      'project_id' => $project_id,
      'building_id' => $building_id,
      'project_building_ids' => $project_buildings,
    ]);
  }

  foreach ($scope_fields as $field => $kind) {
    foreach (brebo_ref_ids($scope, $field) as $target_id) {
      $target = $storage->load($target_id);
      if (!$target instanceof NodeInterface) {
        brebo_issue($issues, 'scope_target_missing', $scope, 'Projectscope references a missing node.', ['field' => $field, 'target_id' => $target_id]);
        continue;
      }
      $target_building = brebo_physical_building($target, $physical_cache);
      if ($target_building !== $building_id) {
        brebo_issue($issues, 'cross_building_scope_target', $scope, 'A selected permanent object belongs to another building than the projectscope.', [
          'field' => $field,
          'kind' => $kind,
          'target_id' => $target_id,
          'target_bundle' => $target->bundle(),
          'scope_building_id' => $building_id,
          'target_building_id' => $target_building,
        ]);
      }
    }
  }
}

foreach (brebo_nodes('brebo_communication') as $communication) {
  $stats['communications']++;
  $project_id = brebo_single_ref($communication, 'field_brebo_project_ref');
  $building_id = brebo_single_ref($communication, 'field_brebo_building_ref');

  if ($project_id && $building_id) {
    $project_buildings = $projects[$project_id] ?? [];
    if (!in_array($building_id, $project_buildings, TRUE)) {
      brebo_issue($issues, 'communication_project_building_mismatch', $communication, 'Communication references a project and building that are not linked.', [
        'project_id' => $project_id,
        'building_id' => $building_id,
        'project_building_ids' => $project_buildings,
      ]);
    }
  }

  foreach (brebo_ref_ids($communication, 'field_brebo_comm_scope_target') as $target_id) {
    $target = $storage->load($target_id);
    if (!$target instanceof NodeInterface) {
      brebo_issue($issues, 'communication_scope_target_missing', $communication, 'Communication references a missing scope target.', ['target_id' => $target_id]);
      continue;
    }

    if (in_array($target->bundle(), ['brebo_building', 'brebo_cluster', 'brebo_dwelling', 'brebo_product_position', 'brebo_building_zone'], TRUE)) {
      $target_building = brebo_physical_building($target, $physical_cache);
      if ($building_id && $target_building !== $building_id) {
        brebo_issue($issues, 'communication_scope_building_mismatch', $communication, 'Communication scope target belongs to another building than the communication.', [
          'target_id' => $target_id,
          'target_bundle' => $target->bundle(),
          'communication_building_id' => $building_id,
          'target_building_id' => $target_building,
        ]);
      }
      if ($project_id && $target_building && !in_array($target_building, $projects[$project_id] ?? [], TRUE)) {
        brebo_issue($issues, 'communication_scope_project_mismatch', $communication, 'Communication scope target belongs to a building outside the linked project.', [
          'target_id' => $target_id,
          'target_building_id' => $target_building,
          'project_id' => $project_id,
        ]);
      }
    }
  }
}

$stats['issues'] = count($issues);

$output = [
  'mode' => 'audit',
  'rule' => 'Permanent physical objects belong to one building; projects select buildings and scopes may only select permanent objects from their own building.',
  'stats' => $stats,
  'consistent' => $stats['issues'] === 0,
  'issues' => $issues,
  'next_step' => $stats['issues'] === 0
    ? 'CIM object relations are consistent for the audited rules. Continue with the canonical action/signal/risk layer assessment.'
    : 'Resolve every reported cross-building or missing-parent relation before adding the next dossier-layer objects.',
];

print json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
