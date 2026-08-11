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

/**
 * Installs the BREBO mail formatting/transport building blocks.
 *
 * SMTP is deliberately kept disabled until Hostinger credentials are present
 * as runtime-only configuration. Installing these modules must never make a
 * previously approved concept leave the system by itself.
 */
function brebo_mail_intake_post_update_mail_stack_modules(array &$sandbox = NULL): string {
  $moduleHandler = \Drupal::moduleHandler();
  $installer = \Drupal::service('module_installer');
  $wanted = ['mailsystem', 'mimemail', 'smtp'];
  $missing = array_values(array_filter($wanted, static fn (string $module): bool => !$moduleHandler->moduleExists($module)));

  if ($missing && !$installer->install($missing, TRUE)) {
    throw new \RuntimeException('Mime Mail/Mail System/SMTP konden niet volledig worden geinstalleerd.');
  }

  $smtp = \Drupal::configFactory()->getEditable('smtp.settings');
  if (!$smtp->isNew()) {
    $smtp->set('smtp_on', FALSE)->save(TRUE);
  }

  return 'Mail System, Mime Mail en SMTP zijn beschikbaar; SMTP blijft bewust uit tot runtime-configuratie en expliciete activering.';
}
