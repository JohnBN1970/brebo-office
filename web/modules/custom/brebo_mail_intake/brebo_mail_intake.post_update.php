<?php

declare(strict_types=1);

use Drupal\user\Entity\Role;

/**
 * Grants the Mail Intake review queue permission to operational roles.
 */
function brebo_mail_intake_post_update_review_queue_permission(array &$sandbox = NULL): string {
  $permission = 'review brebo mail intake';
  $updated = [];

  foreach (['brebo_projectleider', 'brebo_werkvoorbereider', 'brebo_kwaliteitsmanager'] as $role_id) {
    $role = Role::load($role_id);
    if (!$role) {
      continue;
    }
    if (!$role->hasPermission($permission)) {
      $role->grantPermission($permission)->save();
      $updated[] = $role->label();
    }
  }

  return $updated
    ? 'Mail Intake beoordelingsrecht toegekend aan: ' . implode(', ', $updated) . '.'
    : 'Mail Intake beoordelingsrecht was al correct of de beoogde rollen bestaan niet.';
}
