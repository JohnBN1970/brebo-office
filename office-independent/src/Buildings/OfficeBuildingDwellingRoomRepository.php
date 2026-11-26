<?php
declare(strict_types=1);
namespace Brebo\Office\Buildings;
use PDO;
use DomainException;
use InvalidArgumentException;

final class OfficeBuildingDwellingRoomRepository
{
    public function __construct(private readonly PDO $db) {}
    public function listing(int $operatorId,int $buildingId,int $dwellingId): array
    {
        $q=$this->db->prepare('SELECT r.id,r.room_code,r.room_name,r.floor_label,r.active FROM office_building_dwelling_rooms r JOIN office_building_dwellings d ON d.id=r.dwelling_id JOIN office_buildings b ON b.id=d.building_id JOIN office_building_register_grants g ON g.building_id=b.id JOIN office_operator_accounts a ON a.id=g.operator_id AND a.active=1 WHERE b.id=? AND d.id=? AND b.active=1 AND g.operator_id=? ORDER BY r.room_code,r.id');
        $q->execute([$buildingId,$dwellingId,$operatorId]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function revision(array $row): string
    {
        return hash('sha256',json_encode(array_map(static fn($v)=>$v===null?null:(string)$v,[$row['id'],$row['room_code'],$row['room_name'],$row['floor_label'],$row['active']]),JSON_THROW_ON_ERROR));
    }
    public function save(int $operatorId,int $buildingId,int $dwellingId,array $input,?int $roomId=null,?string $revision=null): int
    {
        $values=[];
        foreach (['room_code'=>100,'room_name'=>160,'floor_label'=>80] as $key=>$limit) {
            $v=$input[$key]??'';
            if (!is_string($v)||strlen(trim($v))>$limit||($key!=='floor_label'&&trim($v)==='')) throw new InvalidArgumentException('Ongeldig veld: '.$key);
            $values[$key]=trim($v);
        }
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT a.id FROM office_operator_accounts a WHERE a.id=? AND a.active=1 AND a.can_edit_crm=1 FOR UPDATE');
            $q->execute([$operatorId]);
            if (!$q->fetchColumn()) throw new DomainException('Geen schrijfrecht.');
            $q=$this->db->prepare('SELECT d.id FROM office_building_dwellings d JOIN office_buildings b ON b.id=d.building_id JOIN office_building_register_grants g ON g.building_id=b.id WHERE d.id=? AND b.id=? AND d.active=1 AND b.active=1 AND g.operator_id=? FOR UPDATE');
            $q->execute([$dwellingId,$buildingId,$operatorId]);
            if (!$q->fetchColumn()) throw new DomainException('Geen woningtoegang.');
            $fields=[$values['room_code'],$values['room_name'],$values['floor_label']===''?null:$values['floor_label']];
            if ($roomId===null) {
                $q=$this->db->prepare('INSERT INTO office_building_dwelling_rooms (room_code,room_name,floor_label,dwelling_id) VALUES (?,?,?,?)');
                $q->execute([...$fields,$dwellingId]);
                $roomId=(int)$this->db->lastInsertId();
            } else {
                $q=$this->db->prepare('SELECT id,room_code,room_name,floor_label,active FROM office_building_dwelling_rooms WHERE id=? AND dwelling_id=? FOR UPDATE');
                $q->execute([$roomId,$dwellingId]);
                $old=$q->fetch(PDO::FETCH_ASSOC);
                if (!$old||$revision===null||!hash_equals(self::revision($old),$revision)) throw new DomainException('Ruimte gewijzigd of niet toegankelijk.');
                $q=$this->db->prepare('UPDATE office_building_dwelling_rooms SET room_code=?,room_name=?,floor_label=? WHERE id=? AND dwelling_id=?');
                $q->execute([...$fields,$roomId,$dwellingId]);
            }
            $this->db->commit();
            return $roomId;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
