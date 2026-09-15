<?php

declare(strict_types=1);

use Drupal\brebo_office_core\Project\ProjectLifecycle;
use Drupal\brebo_office_core\Project\ProjectLifecycleStatus;
use Drupal\node\NodeInterface;

/**
 * Audits and optionally normalizes stored project lifecycle values.
 *
 * Audit-only (default):
 *   vendor/bin/drush php:script scripts/normalize-project-lifecycle.php
 *
 * Apply after reviewing the audit output:
 *   BREBO_APPLY_PROJECT_LIFECYCLE=1 vendor/bin/drush php:script scripts/normalize-project-lifecycle.php
 *
 * The script is intentionally conservative:
 * - only brebo_project nodes are touched;
 * - the existing status field is discovered via ProjectLifecycleStatus;
 * - known legacy Dutch/English values are normalized to canonical machine values;
 * - unknown values are reported and never guessed or overwritten;
 * - every changed project gets a new revision with an explicit revision log.
 */

$apply = getenv('BREBO_APPLY_PROJECT_LIFECYCLE') === '1';
$storage = \Drupal::entityTypeManager()->getStorage('node');

$project_ids = array_values($storage->getQuery()
  ->accessCheck(FALSE)
  ->condition('type', 'brebo_project')
  ->execute());

$stats = [
  'projects' => count($project_ids),
  'already_canonical' => 0,
  'normalizable' => 0,
  'unknown' => 0,
  'missing_status_field' => 0,
  'updated' => 0,
];
$changes = [];
$unknown = [];

foreach ($storage->loadMultiple($project_ids) as $project) {
  if (!$project instanceof NodeInterface) {
    continue;
  }

  $field_name = ProjectLifecycleStatus::fieldName($project);
  if ($field_name === NULL) {
    $stats['missing_status_field']++;
    $unknown[] = sprintf('Project #%d "%s" heeft geen ondersteund statusveld.', $project->id(), $project->label());
    continue;
  }

  $raw = $project->get($field_name)->isEmpty()
    ? ''
    : (string) $project->get($field_name)->value;
  $canonical = ProjectLifecycle::normalize($raw);

  if (!ProjectLifecycle::isKnown($canonical)) {
    $stats['unknown']++;
    $unknown[] = sprintf(
      'Project #%d "%s": onbekende status "%s"; niet aangepast.',
      $project->id(),
      $project->label(),
      $raw,
    );
    continue;
  }

  if ($raw === $canonical) {
    $stats['already_canonical']++;
    continue;
  }

  $stats['normalizable']++;
  $changes[] = [
    'project_id' => (int) $project->id(),
    'project' => (string) $project->label(),
    'field' => $field_name,
    'from' => $raw === '' ? '(leeg)' : $raw,
    'to' => $canonical,
    'label' => ProjectLifecycle::label($canonical),
  ];

  if ($apply) {
    $project->set($field_name, $canonical);
    $project->setNewRevision(TRUE);
    $project->setRevisionLogMessage(sprintf(
      'Projectstatus genormaliseerd naar canonieke lifecycle: %s -> %s.',
      $raw === '' ? '(leeg)' : $raw,
      $canonical,
    ));
    $project->save();
    $stats['updated']++;
  }
}

print json_encode([
  'mode' => $apply ? 'apply' : 'audit',
  'canonical_lifecycle' => ProjectLifecycle::options(),
  'stats' => $stats,
  'changes' => $changes,
  'unknown' => $unknown,
  'safe_to_apply' => $stats['unknown'] === 0 && $stats['missing_status_field'] === 0,
  'next_step' => $apply
    ? ($stats['unknown'] === 0 && $stats['missing_status_field'] === 0
      ? 'Stored project statuses now use canonical machine values.'
      : 'Resolve reported unknown/missing statuses; no guesses were made for those projects.')
    : ($stats['unknown'] === 0 && $stats['missing_status_field'] === 0
      ? 'Audit is clean. Re-run with BREBO_APPLY_PROJECT_LIFECYCLE=1 to persist canonical values.'
      : 'Resolve the reported exceptions before applying.'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
