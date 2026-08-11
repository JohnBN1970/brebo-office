<?php

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\NodeType;

/**
 * Canonicalizes Cluster -> Building while preserving legacy Project links.
 *
 * Run audit-only (default):
 *   vendor/bin/drush php:script scripts/canonicalize-building-relations.php
 *
 * Apply only after reviewing the audit output:
 *   BREBO_APPLY_CANONICAL_RELATIONS=1 vendor/bin/drush php:script scripts/canonicalize-building-relations.php
 *
 * After a successful apply, export the complete active Drupal configuration:
 *   vendor/bin/drush cex -y
 *
 * This script is intentionally conservative:
 * - Building is the permanent parent of Cluster.
 * - Project -> Cluster remains as a legacy/history link, but is made optional.
 * - No ambiguous building relation is guessed.
 * - field_brebo_building_ref is only made required when every Cluster has one.
 */

$apply = getenv('BREBO_APPLY_CANONICAL_RELATIONS') === '1';
$storage = \Drupal::entityTypeManager()->getStorage('node');

$cluster_type = NodeType::load('brebo_cluster');
$building_type = NodeType::load('brebo_building');
$project_type = NodeType::load('brebo_project');

if (!$cluster_type || !$building_type || !$project_type) {
  throw new RuntimeException('Canonieke objecttypen ontbreken. Verwacht brebo_cluster, brebo_building en brebo_project.');
}

$project_field = FieldConfig::loadByName('node', 'brebo_cluster', 'field_brebo_project_ref');
$building_field = FieldConfig::loadByName('node', 'brebo_cluster', 'field_brebo_building_ref');

if (!$project_field || !$building_field) {
  throw new RuntimeException('Verwachte Cluster-relatievelden ontbreken. Voer eerst de bestaande BREBO Office database-updates uit.');
}

$cluster_ids = $storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'brebo_cluster')
  ->execute();

$stats = [
  'clusters' => count($cluster_ids),
  'already_canonical' => 0,
  'backfillable_unique_project_building' => 0,
  'backfillable_scope' => 0,
  'unresolved' => 0,
  'updated' => 0,
];
$unresolved = [];

foreach ($storage->loadMultiple($cluster_ids) as $cluster) {
  if (!$cluster instanceof NodeInterface) {
    continue;
  }

  if (!$cluster->get('field_brebo_building_ref')->isEmpty()) {
    $stats['already_canonical']++;
    continue;
  }

  $candidate_ids = [];
  $project = NULL;
  if (!$cluster->get('field_brebo_project_ref')->isEmpty()) {
    $project = $cluster->get('field_brebo_project_ref')->entity;
  }

  if ($project instanceof NodeInterface && $project->bundle() === 'brebo_project' && $project->hasField('field_brebo_building_refs')) {
    foreach ($project->get('field_brebo_building_refs')->referencedEntities() as $building) {
      if ($building instanceof NodeInterface && $building->bundle() === 'brebo_building') {
        $candidate_ids[(int) $building->id()] = (int) $building->id();
      }
    }
  }

  $resolution = NULL;
  if (count($candidate_ids) === 1) {
    $resolution = reset($candidate_ids);
    $stats['backfillable_unique_project_building']++;
  }
  elseif ($project instanceof NodeInterface) {
    // If a project spans multiple buildings, only use an explicit project scope
    // that actually contains this cluster. Never guess from address or title.
    $scope_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'brebo_project_scope')
      ->condition('field_brebo_project_ref.target_id', $project->id())
      ->condition('field_brebo_scope_clusters.target_id', $cluster->id())
      ->execute();

    $scope_buildings = [];
    foreach ($storage->loadMultiple($scope_ids) as $scope) {
      if ($scope instanceof NodeInterface && !$scope->get('field_brebo_building_ref')->isEmpty()) {
        $building = $scope->get('field_brebo_building_ref')->entity;
        if ($building instanceof NodeInterface && $building->bundle() === 'brebo_building') {
          $scope_buildings[(int) $building->id()] = (int) $building->id();
        }
      }
    }

    if (count($scope_buildings) === 1) {
      $resolution = reset($scope_buildings);
      $stats['backfillable_scope']++;
    }
  }

  if (!$resolution) {
    $stats['unresolved']++;
    $unresolved[] = sprintf(
      'Cluster #%d "%s" heeft geen eenduidig permanent gebouw.',
      $cluster->id(),
      $cluster->label(),
    );
    continue;
  }

  if ($apply) {
    $cluster->set('field_brebo_building_ref', ['target_id' => $resolution]);
    $cluster->setNewRevision(TRUE);
    $cluster->setRevisionLogMessage('Canonieke gebouwrelatie aangevuld; legacy projectrelatie behouden.');
    $cluster->save();
    $stats['updated']++;
  }
}

$all_have_building = $stats['unresolved'] === 0;

if ($apply) {
  $cluster_type
    ->setDescription('Gebouwdeel, bouwblok of werkgebied binnen een permanent gebouwmodel.')
    ->save();

  $project_field
    ->setRequired(FALSE)
    ->setDescription('Legacy/historische projectkoppeling. De permanente parent van een cluster is het gebouw; tijdelijke projectselectie loopt via Projectscope per gebouw.')
    ->save();

  if ($all_have_building) {
    $building_field
      ->setRequired(TRUE)
      ->setDescription('Verplicht permanent gebouw waartoe dit cluster behoort.')
      ->save();
  }
  else {
    $building_field
      ->setRequired(FALSE)
      ->setDescription('Permanent gebouw waartoe dit cluster behoort. Nog niet verplicht zolang historische clusters niet eenduidig zijn gemigreerd.')
      ->save();
  }
}

print json_encode([
  'mode' => $apply ? 'apply' : 'audit',
  'canonical_rule' => 'Building -> Cluster; Projectscope selects permanent building objects for temporary projects.',
  'stats' => $stats,
  'building_field_can_be_required' => $all_have_building,
  'unresolved' => $unresolved,
  'next_step' => $apply
    ? ($all_have_building
      ? 'Run drush cex -y and commit the complete configuration export.'
      : 'Resolve ambiguous clusters, rerun apply, then run drush cex -y.')
    : 'Review output. If correct, rerun with BREBO_APPLY_CANONICAL_RELATIONS=1.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
