<?php

declare(strict_types=1);

/** Create supplier offer comparison storage. */
function brebo_procurement_post_update_create_offer_domain(&$sandbox = NULL): string {
  $schema = \Drupal::database()->schema();
  if ($schema->tableExists('brebo_procurement_offer')) return 'BREBO Procurement offer domain already exists.';
  $schema->createTable('brebo_procurement_offer', [
    'description'=>'Supplier offers received for procurement requests.',
    'fields'=>[
      'id'=>['type'=>'serial','not null'=>TRUE],'request_id'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
      'supplier_ref'=>['type'=>'varchar','length'=>255,'not null'=>FALSE],'supplier_name'=>['type'=>'varchar','length'=>255,'not null'=>TRUE],
      'offer_number'=>['type'=>'varchar','length'=>128,'not null'=>FALSE],'offer_date'=>['type'=>'varchar_ascii','length'=>10,'not null'=>FALSE],
      'valid_until'=>['type'=>'varchar_ascii','length'=>10,'not null'=>FALSE],'quoted_total'=>['type'=>'numeric','precision'=>18,'scale'=>4,'not null'=>TRUE,'default'=>0],
      'currency'=>['type'=>'varchar_ascii','length'=>3,'not null'=>TRUE,'default'=>'EUR'],'delivery_date'=>['type'=>'varchar_ascii','length'=>10,'not null'=>FALSE],
      'lead_time_days'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],'technical_deviation'=>['type'=>'text','not null'=>FALSE],
      'conditions_summary'=>['type'=>'text','size'=>'big','not null'=>FALSE],'status'=>['type'=>'varchar_ascii','length'=>32,'not null'=>TRUE,'default'=>'received'],
      'created'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],'created_by'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],
      'changed'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],'selected_at'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],'selected_by'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],
    ],
    'primary key'=>['id'],'indexes'=>['request_status'=>['request_id','status'],'supplier'=>['supplier_ref']],
  ]);
  return 'BREBO Procurement supplier offer comparison domain created.';
}

/** Create auditable receipt inspections for procurement orders. */
function brebo_procurement_post_update_create_receipt_domain(&$sandbox = NULL): string {
  $schema = \Drupal::database()->schema();
  if ($schema->tableExists('brebo_procurement_receipt')) return 'BREBO Procurement receipt domain already exists.';
  $schema->createTable('brebo_procurement_receipt', [
    'description'=>'Controlled receipt inspections for BREBO procurement orders.',
    'fields'=>[
      'id'=>['type'=>'serial','not null'=>TRUE],'order_id'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],
      'received_at'=>['type'=>'int','unsigned'=>TRUE,'not null'=>TRUE],'received_by'=>['type'=>'int','unsigned'=>TRUE,'not null'=>FALSE],
      'quantity_ok'=>['type'=>'int','size'=>'tiny','unsigned'=>TRUE,'not null'=>TRUE,'default'=>0],
      'dimensions_ok'=>['type'=>'int','size'=>'tiny','unsigned'=>TRUE,'not null'=>TRUE,'default'=>0],
      'specification_ok'=>['type'=>'int','size'=>'tiny','unsigned'=>TRUE,'not null'=>TRUE,'default'=>0],
      'damage_free'=>['type'=>'int','size'=>'tiny','unsigned'=>TRUE,'not null'=>TRUE,'default'=>0],
      'checksum_ok'=>['type'=>'int','size'=>'tiny','unsigned'=>TRUE,'not null'=>TRUE,'default'=>0],
      'accepted'=>['type'=>'int','size'=>'tiny','unsigned'=>TRUE,'not null'=>TRUE,'default'=>0],
      'note'=>['type'=>'text','size'=>'big','not null'=>FALSE],
    ],
    'primary key'=>['id'],'indexes'=>['order'=>['order_id'],'accepted'=>['accepted']],
  ]);
  return 'BREBO Procurement controlled receipt domain created.';
}
