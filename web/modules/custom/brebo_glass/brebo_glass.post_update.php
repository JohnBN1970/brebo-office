<?php

declare(strict_types=1);

/** Creates the minimal project-wide glass stock event ledger. */
function brebo_glass_post_update_create_stock_event_ledger(&$sandbox = NULL): string {
  $schema = \Drupal::database()->schema();
  $table = 'brebo_glass_stock_event';
  if ($schema->tableExists($table)) return 'BREBO Glass stock event ledger already exists.';
  $schema->createTable($table, [
    'description' => 'Minimal actual glass stock events: delivered, installed and damaged.',
    'fields' => [
      'id' => ['type'=>'serial','unsigned'=>TRUE,'not null'=>TRUE],
      'project_nid' => ['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
      'glass_group_key' => ['type'=>'varchar_ascii','length'=>64,'not null'=>TRUE],
      'event_type' => ['type'=>'varchar_ascii','length'=>24,'not null'=>TRUE],
      'quantity' => ['type'=>'numeric','precision'=>18,'scale'=>4,'not null'=>TRUE],
      'source_reference' => ['type'=>'varchar','length'=>255,'not null'=>FALSE],
      'note' => ['type'=>'text','size'=>'normal','not null'=>FALSE],
      'happened_at' => ['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
      'created_by' => ['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
      'created' => ['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
    ],
    'primary key' => ['id'],
    'indexes' => [
      'project_group' => ['project_nid','glass_group_key'],
      'event_type' => ['event_type'],
      'happened_at' => ['happened_at'],
    ],
  ]);
  return 'BREBO Glass minimal project stock event ledger created.';
}
