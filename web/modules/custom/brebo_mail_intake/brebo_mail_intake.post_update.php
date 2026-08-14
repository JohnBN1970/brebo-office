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

/**
 * Creates the provider-independent BREBO mailbox foundation.
 */
function brebo_mail_intake_post_update_mailbox_foundation(array &$sandbox = NULL): string {
  $schema = \Drupal::database()->schema();

  if (!$schema->tableExists('brebo_mailbox')) {
    $schema->createTable('brebo_mailbox', [
      'description' => 'Logical BREBO mailboxes, independent from provider folders.',
      'fields' => [
        'id' => ['type' => 'serial', 'not null' => TRUE],
        'machine_name' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE],
        'label' => ['type' => 'varchar', 'length' => 255, 'not null' => TRUE],
        'address' => ['type' => 'varchar', 'length' => 320, 'not null' => TRUE, 'default' => ''],
        'privacy_type' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE, 'default' => 'functional'],
        'active' => ['type' => 'int', 'size' => 'tiny', 'not null' => TRUE, 'default' => 1],
        'created' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
        'changed' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
      ],
      'primary key' => ['id'],
      'unique keys' => ['machine_name' => ['machine_name']],
      'indexes' => ['address' => ['address'], 'privacy_type' => ['privacy_type']],
    ]);
  }

  if (!$schema->tableExists('brebo_mailbox_role')) {
    $schema->createTable('brebo_mailbox_role', [
      'description' => 'Role capabilities per logical BREBO mailbox.',
      'fields' => [
        'mailbox_id' => ['type' => 'int', 'not null' => TRUE],
        'role_id' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE],
        'capability' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE],
      ],
      'primary key' => ['mailbox_id', 'role_id', 'capability'],
      'indexes' => ['role_capability' => ['role_id', 'capability']],
    ]);
  }

  if (!$schema->tableExists('brebo_mailbox_message')) {
    $schema->createTable('brebo_mailbox_message', [
      'description' => 'Mailbox state projection for canonical BREBO communication objects.',
      'fields' => [
        'mailbox_id' => ['type' => 'int', 'not null' => TRUE],
        'communication_id' => ['type' => 'int', 'not null' => TRUE],
        'mail_state' => ['type' => 'varchar', 'length' => 32, 'not null' => TRUE, 'default' => 'inbox'],
        'is_read' => ['type' => 'int', 'size' => 'tiny', 'not null' => TRUE, 'default' => 0],
        'is_starred' => ['type' => 'int', 'size' => 'tiny', 'not null' => TRUE, 'default' => 0],
        'needs_action' => ['type' => 'int', 'size' => 'tiny', 'not null' => TRUE, 'default' => 0],
        'changed' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
      ],
      'primary key' => ['mailbox_id', 'communication_id'],
      'indexes' => ['mailbox_state' => ['mailbox_id', 'mail_state'], 'communication' => ['communication_id']],
    ]);
  }

  return 'BREBO mailbox foundation aangemaakt: logische mailboxen, rolcapaciteiten en berichtstatussen staan los van providerfolders.';
}

/**
 * Creates lightweight tags for canonical BREBO communication objects.
 */
function brebo_mail_intake_post_update_mail_tags(array &$sandbox = NULL): string {
  $schema = \Drupal::database()->schema();
  if ($schema->tableExists('brebo_mail_tag')) {
    return 'BREBO mail-tags waren al beschikbaar.';
  }

  $schema->createTable('brebo_mail_tag', [
    'description' => 'User-managed tags for canonical BREBO communication objects.',
    'fields' => [
      'communication_id' => ['type' => 'int', 'not null' => TRUE],
      'tag' => ['type' => 'varchar', 'length' => 64, 'not null' => TRUE],
      'created' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
      'uid' => ['type' => 'int', 'not null' => TRUE, 'default' => 0],
    ],
    'primary key' => ['communication_id', 'tag'],
    'indexes' => [
      'communication' => ['communication_id'],
      'tag' => ['tag'],
    ],
  ]);

  return 'BREBO mail-tags aangemaakt; tags blijven los van formele Office-koppelingen.';
}
