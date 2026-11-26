<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use Closure;
use DomainException;
use PDO;

require_once __DIR__.'/OfficeBuildingRepository.php';

/** Shared creation boundary; callers must supply verified context authorization. */
final class OfficeBuildingCreateService {
    public function __construct(private PDO $db) {}

    /** Create and grant access atomically; requires verified register-grants schema. */
    public function createForOperator(array $input, int $operatorId, Closure $authorized): string {
        if ($operatorId<=0 || $authorized()!==true) throw new DomainException('building_create_forbidden');
        $this->db->beginTransaction();
        try {
            $id=$this->create($input,$authorized);
            $grant=$this->db->prepare('INSERT INTO office_building_register_grants (building_id,operator_id) SELECT ?,id FROM office_operator_accounts WHERE id=? AND active=1 AND can_edit_crm=1');
            $grant->execute([$id,$operatorId]);
            if ($grant->rowCount()!==1) throw new DomainException('building_create_forbidden');
            if (getenv('OFFICE_BUILDING_AUDIT_SCHEMA_VERIFIED')!=='enabled') throw new DomainException('building_audit_unavailable');
            require_once __DIR__.'/OfficeBuildingAudit.php';
            $snapshot=$this->db->prepare('SELECT building_name,street_address,postal_code,city FROM office_buildings WHERE id=?');
            $snapshot->execute([$id]);
            $row=$snapshot->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new DomainException('building_snapshot_unavailable');
            (new OfficeBuildingAudit($this->db))->record((int)$id,$operatorId,'created','register',null,null,$row);
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function create(array $input, Closure $authorized): string {
        if ($authorized() !== true) {
            throw new DomainException('building_create_forbidden');
        }
        $clean=[];
        foreach (['building_name','street_address','postal_code','city'] as $field) {
            $value=$input[$field]??'';
            if (!is_string($value)) throw new DomainException('invalid_building_input');
            $clean[$field]=trim($value);
        }
        $bag = null;
        if (isset($input['bag_id']) && $input['bag_id'] !== '') {
            if (!is_string($input['bag_id']) || getenv('OFFICE_BUILDING_BAG_SCHEMA_VERIFIED') !== 'enabled' || !$this->db->inTransaction()) throw new DomainException('bag_storage_not_ready');
            require_once __DIR__.'/OfficePdokAddressLookup.php';
            $bag=(new OfficePdokAddressLookup())->verify($input['bag_id']);
            $clean=array_replace($clean,$bag);
        }
        if ($clean['street_address']!=='' && $clean['postal_code']!=='') {
            $q=$this->db->prepare("SELECT id FROM office_buildings WHERE UPPER(REPLACE(postal_code,' ',''))=? AND LOWER(TRIM(street_address))=LOWER(?) LIMIT 1");
            $q->execute([strtoupper(str_replace(' ','',$clean['postal_code'])),$clean['street_address']]);
            if ($q->fetchColumn()!==false) throw new DomainException('building_address_candidate_exists');
        }
        $id=(new OfficeBuildingRepository($this->db))->create($clean);
        if ($bag !== null) {
            $q=$this->db->prepare('UPDATE office_buildings SET bag_id=?,bag_verified_at=CURRENT_TIMESTAMP,address_line=? WHERE id=?');
            $q->execute([$bag['bag_id'],$bag['street_address'],$id]);
            if ($q->rowCount()!==1) throw new DomainException('bag_storage_failed');
        }
        return $id;
    }
}
