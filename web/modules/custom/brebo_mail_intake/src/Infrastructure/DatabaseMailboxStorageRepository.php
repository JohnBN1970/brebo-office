<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Infrastructure;

use Drupal\brebo_mail_intake\Contract\MailboxStorageRepositoryInterface;
use Drupal\Core\Database\Connection;

final class DatabaseMailboxStorageRepository implements MailboxStorageRepositoryInterface {
  public function __construct(private readonly Connection $database) {}

  public function messageRows(int $mailboxId, string $state, int $offset, int $limit): array {
    $query=$this->database->select('brebo_mailbox_message','bm');
    $query->join('node_field_data','n','n.nid = bm.communication_id AND n.default_langcode = 1');
    $query->leftJoin('node__field_brebo_mail_from','mf','mf.entity_id = n.nid AND mf.deleted = 0');
    $query->leftJoin('node__field_brebo_comm_subject','ms','ms.entity_id = n.nid AND ms.deleted = 0');
    $query->leftJoin('node__field_brebo_comm_datetime','md','md.entity_id = n.nid AND md.deleted = 0');
    $query->fields('bm',['communication_id','is_read','is_starred','needs_action','changed']);
    $query->addField('mf','field_brebo_mail_from_value','mail_from');
    $query->addField('ms','field_brebo_comm_subject_value','subject');
    $query->addField('md','field_brebo_comm_datetime_value','mail_datetime');
    return array_values(array_map('get_object_vars',$query
      ->condition('bm.mailbox_id',$mailboxId)->condition('bm.mail_state',$state)->condition('n.type','brebo_communication')
      ->orderBy('md.field_brebo_comm_datetime_value','DESC')->orderBy('bm.changed','DESC')
      ->range($offset,$limit)->execute()->fetchAll()));
  }

  public function tagsForCommunications(array $communicationIds): array {
    if($communicationIds===[] || !$this->database->schema()->tableExists('brebo_mail_tag')) return [];
    $result=[];
    $query=$this->database->select('brebo_mail_tag','t')->fields('t',['communication_id','tag']);
    $query->condition('communication_id',$communicationIds,'IN')->orderBy('tag');
    foreach($query->execute() as $row) $result[(int)$row->communication_id][]=(string)$row->tag;
    return $result;
  }

  public function messageBelongsToMailbox(int $mailboxId,int $communicationId): bool {
    return $this->database->select('brebo_mailbox_message','bm')->fields('bm',['communication_id'])
      ->condition('mailbox_id',$mailboxId)->condition('communication_id',$communicationId)->range(0,1)
      ->execute()->fetchField()!==FALSE;
  }

  public function tags(int $communicationId): array {
    if(!$this->database->schema()->tableExists('brebo_mail_tag')) return [];
    return array_values(array_map('strval',$this->database->select('brebo_mail_tag','t')->fields('t',['tag'])
      ->condition('communication_id',$communicationId)->orderBy('tag')->execute()->fetchCol()));
  }

  public function documentAttachments(int $communicationId): array {
    if(!$this->database->schema()->tableExists('brebo_document_communication') || !$this->database->schema()->tableExists('brebo_document')) return [];
    $query=$this->database->select('brebo_document_communication','dc');
    $query->join('brebo_document','d','d.id = dc.document_id');
    $query->fields('dc',['document_id','relation_role'])->fields('d',['title','original_filename']);
    $query->condition('dc.communication_nid',$communicationId)->condition('d.lifecycle_status','deleted','<>')->orderBy('dc.created')->orderBy('dc.id');
    return array_values(array_map('get_object_vars',$query->execute()->fetchAll()));
  }

  public function search(array $visibleMailboxIds,string $term,int $mailboxId,string $state,int $limit=100): array {
    if($visibleMailboxIds===[]) return [];
    $query=$this->database->select('brebo_mailbox_message','bm');
    $query->join('brebo_mailbox','mb','mb.id = bm.mailbox_id');
    $query->join('node_field_data','n','n.nid = bm.communication_id AND n.default_langcode = 1');
    $query->leftJoin('node__field_brebo_mail_from','mf','mf.entity_id = n.nid AND mf.deleted = 0');
    $query->leftJoin('node__field_brebo_mail_to','mt','mt.entity_id = n.nid AND mt.deleted = 0');
    $query->leftJoin('node__field_brebo_comm_subject','ms','ms.entity_id = n.nid AND ms.deleted = 0');
    $query->leftJoin('node__field_brebo_transcript','tr','tr.entity_id = n.nid AND tr.deleted = 0');
    $query->leftJoin('node__field_brebo_comm_datetime','md','md.entity_id = n.nid AND md.deleted = 0');
    $query->fields('bm',['mailbox_id','communication_id','mail_state'])->addField('mb','label','mailbox_label');
    $query->addField('mf','field_brebo_mail_from_value','mail_from')->addField('ms','field_brebo_comm_subject_value','subject')->addField('md','field_brebo_comm_datetime_value','mail_datetime');
    $query->condition('n.type','brebo_communication')->condition('bm.mailbox_id',$mailboxId>0?[$mailboxId]:$visibleMailboxIds,'IN');
    if($state!=='') $query->condition('bm.mail_state',$state);
    $needle='%'.$this->database->escapeLike($term).'%';
    $query->condition($query->orConditionGroup()->condition('ms.field_brebo_comm_subject_value',$needle,'LIKE')->condition('mf.field_brebo_mail_from_value',$needle,'LIKE')->condition('mt.field_brebo_mail_to_value',$needle,'LIKE')->condition('tr.field_brebo_transcript_value',$needle,'LIKE'));
    $query->orderBy('md.field_brebo_comm_datetime_value','DESC')->orderBy('bm.changed','DESC')->range(0,$limit);
    return array_values(array_map('get_object_vars',$query->execute()->fetchAll()));
  }

  public function messageState(int $mailboxId,int $communicationId): ?array {
    $row=$this->database->select('brebo_mailbox_message','bm')->fields('bm',['mail_state','is_read','is_starred','needs_action'])
      ->condition('mailbox_id',$mailboxId)->condition('communication_id',$communicationId)->range(0,1)->execute()->fetchAssoc();
    return $row===FALSE?NULL:$row;
  }

  public function updateMessage(int $mailboxId,int $communicationId,array $fields): void {
    $this->database->update('brebo_mailbox_message')->fields($fields)->condition('mailbox_id',$mailboxId)->condition('communication_id',$communicationId)->execute();
  }

  public function replaceTags(int $communicationId,array $tags,int $uid): void {
    if(!$this->database->schema()->tableExists('brebo_mail_tag')) throw new \RuntimeException('De tag-opslag is nog niet geinstalleerd.');
    $transaction=$this->database->startTransaction();
    try {
      $this->database->delete('brebo_mail_tag')->condition('communication_id',$communicationId)->execute();
      $now=time();
      foreach($tags as $tag) $this->database->insert('brebo_mail_tag')->fields(['communication_id'=>$communicationId,'tag'=>$tag,'created'=>$now,'uid'=>$uid])->execute();
    } catch(\Throwable $e){$transaction->rollBack(); throw $e;}
  }

  public function linkedDocumentIds(int $communicationId): array {
    $ids=[];
    foreach(['brebo_document_communication','brebo_document_source'] as $table){
      if(!$this->database->schema()->tableExists($table)) continue;
      foreach($this->database->select($table,'d')->fields('d',['document_id'])->condition('communication_nid',$communicationId)->distinct()->execute()->fetchCol() as $id) $ids[(int)$id]=TRUE;
    }
    return array_keys($ids);
  }
}
