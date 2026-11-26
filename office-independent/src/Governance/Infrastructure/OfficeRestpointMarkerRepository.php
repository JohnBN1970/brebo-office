<?php
declare(strict_types=1);
namespace Brebo\Office\Governance\Infrastructure;

use PDO;
use DomainException;
use InvalidArgumentException;
use Brebo\Office\Governance\Contract\OfficeGovernanceStore;

final class OfficeRestpointMarkerRepository
{
    // The trusted document resolver must enforce document ACL and actual page bounds.
    public function __construct(private readonly PDO $db, private readonly OfficeGovernanceStore $governance, private readonly \Closure $documentResolver) {}

    public function listing(string $actor,int $project,int $building,int $document,int $page): array
    {
        if (min($project,$building,$document,$page)<1) throw new InvalidArgumentException('Ongeldige documentcontext.');
        if (!$this->governance->allowed($actor,'quality','read',$project,$building)) throw new DomainException('Geen toegang.');
        $original=($this->documentResolver)($actor,$project,$building,$document,$page);
        if (!is_int($original)||$original<1) throw new DomainException('Document of pagina niet toegankelijk.');
        $q=$this->db->prepare('SELECT m.id,m.restpoint_id,m.x_millionths,m.y_millionths,m.revision,q.title,q.status,q.payload_json FROM office_restpoint_document_markers m JOIN office_quality_checks q ON q.id=m.restpoint_id JOIN office_documents d ON d.id=m.document_id AND d.original_id=m.original_id WHERE q.project_id=? AND q.building_id=? AND m.document_id=? AND m.original_id=? AND m.page_number=? AND m.active=1 AND d.lifecycle_status=? ORDER BY m.id');
        $q->execute([$project,$building,$document,$original,$page,'active']);
        $out=[];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ((json_decode($row['payload_json'],true,512,JSON_THROW_ON_ERROR)['record_kind']??'')!=='restpunt') continue;
            unset($row['payload_json']);
            $out[]=$row;
        }
        return $out;
    }

    public function save(string $actor,int $project,int $building,int $restpoint,int $document,int $page,int $x,int $y,?int $marker=null,int $revision=0,bool $active=true): int
    {
        if (min($project,$building,$restpoint,$document,$page)<1 || min($x,$y)<0 || max($x,$y)>1000000 || ($marker===null && (!$active || $revision!==0))) throw new InvalidArgumentException('Ongeldige markering.');
        $this->db->beginTransaction();
        try {
            if (!$this->governance->allowed($actor,'quality','write',$project,$building)) throw new DomainException('Geen toegang.');
            $q=$this->db->prepare('SELECT payload_json FROM office_quality_checks WHERE id=? AND project_id=? AND building_id=? FOR UPDATE');
            $q->execute([$restpoint,$project,$building]);
            $payload=$q->fetchColumn();
            if (!$payload || (json_decode($payload,true,512,JSON_THROW_ON_ERROR)['record_kind']??'')!=='restpunt') throw new DomainException('Restpunt niet gevonden.');
            $original=($this->documentResolver)($actor,$project,$building,$document,$page);
            if (!is_int($original)||$original<1) throw new DomainException('Document of pagina niet toegankelijk.');
            $q=$this->db->prepare('SELECT id FROM office_documents WHERE id=? AND original_id=? AND lifecycle_status=? FOR UPDATE');
            $q->execute([$document,$original,'active']);
            if (!$q->fetchColumn()) throw new DomainException('Documentversie gewijzigd.');
            if ($marker===null) {
                $q=$this->db->prepare('INSERT INTO office_restpoint_document_markers (restpoint_id,document_id,original_id,page_number,x_millionths,y_millionths,created_by,updated_by) VALUES (?,?,?,?,?,?,?,?)');
                $q->execute([$restpoint,$document,$original,$page,$x,$y,$actor,$actor]);
                $marker=(int)$this->db->lastInsertId();
                $next=1;$event='created';
            } else {
                $q=$this->db->prepare('SELECT revision,active FROM office_restpoint_document_markers WHERE id=? AND restpoint_id=? AND document_id=? AND original_id=? AND page_number=? FOR UPDATE');
                $q->execute([$marker,$restpoint,$document,$original,$page]);
                $old=$q->fetch(PDO::FETCH_ASSOC);
                if (!$old || !(int)$old['active'] || (int)$old['revision']!==$revision) throw new DomainException('Markering gewijzigd of niet toegankelijk.');
                $next=$revision+1;$event=$active?'moved':'removed';
                $q=$this->db->prepare('UPDATE office_restpoint_document_markers SET x_millionths=?,y_millionths=?,active=?,revision=?,updated_by=? WHERE id=? AND revision=?');
                $q->execute([$x,$y,(int)$active,$next,$actor,$marker,$revision]);
                if ($q->rowCount()!==1) throw new DomainException('Revisie gewijzigd.');
            }
            $snapshot=json_encode(['restpoint_id'=>$restpoint,'document_id'=>$document,'original_id'=>$original,'page_number'=>$page,'x_millionths'=>$x,'y_millionths'=>$y,'active'=>$active],JSON_THROW_ON_ERROR);
            $q=$this->db->prepare('INSERT INTO office_restpoint_document_marker_events (marker_id,revision,actor_operator_id,event_type,snapshot_json) VALUES (?,?,?,?,?)');
            $q->execute([$marker,$next,$actor,$event,$snapshot]);
            $this->db->commit();
            return $marker;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
}
