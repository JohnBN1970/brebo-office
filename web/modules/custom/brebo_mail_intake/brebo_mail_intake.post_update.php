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

/**
 * Gives only the Projectleider role the initial outbound send mandate.
 */
function brebo_mail_intake_post_update_outbound_mail_permission(array &$sandbox = NULL): string {
  $role = Role::load('brebo_projectleider');
  if (!$role) {
    return 'Projectleiderrol bestaat niet; uitgaand mailmandaat is niet automatisch toegekend.';
  }

  $permission = 'send brebo outbound mail';
  if (!$role->hasPermission($permission)) {
    $role->grantPermission($permission)->save();
    return 'Uitgaand mailmandaat toegekend aan Projectleider; verzending blijft daarnaast expliciete vrijgave per bericht vereisen.';
  }

  return 'Uitgaand mailmandaat voor Projectleider was al aanwezig.';
}
