<?php
declare(strict_types=1);
namespace Brebo\Office\Buildings;
use PDO;
use DomainException;
final class OfficeBuildingAudit {
    public function __construct(private PDO $db) {}
    public function record(int $buildingId,int $operatorId,string $event,string $context,?int $contextId,?array $before,?array $after): void {
        if (!$this->db->inTransaction() || $buildingId<1 || $operatorId<1 || !in_array($event,['created','updated','linked','unlinked'],true) || !in_array($context,['register','lead','deal','project'],true) || ($context!=='register' && ($contextId===null || $contextId<1))) throw new DomainException('invalid_building_audit_event');
        $q=$this->db->prepare('INSERT INTO office_building_audit_events (building_id,operator_id,event_type,context_type,context_id,before_json,after_json) VALUES (?,?,?,?,?,?,?)');
        $q->execute([$buildingId,$operatorId,$event,$context,$contextId,$before===null?null:json_encode($before,JSON_THROW_ON_ERROR),$after===null?null:json_encode($after,JSON_THROW_ON_ERROR)]);
    }
}
