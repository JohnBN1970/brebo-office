<?php
declare(strict_types=1);
namespace Brebo\Office\Governance\Infrastructure;

use PDO;
use DomainException;
use Brebo\Office\Governance\Contract\OfficeGovernanceStore;
use Brebo\Office\Documents\OfficeProjectDocumentService;

final class OfficeRestpointPhotoService
{
    public function __construct(private readonly PDO $db,private readonly OfficeGovernanceStore $governance,private readonly OfficeProjectDocumentService $documents) {}

    private function requireRestpoint(string $actor,int $project,int $building,int $restpoint,string $permission): void
    {
        if (!ctype_digit($actor) || (string)(int)$actor!==$actor || min($project,$building,$restpoint)<1 || !$this->governance->allowed($actor,'quality',$permission,$project,$building)) throw new DomainException('Geen toegang.');
        $r=$this->governance->find('quality',$restpoint);
        if (!$r || (int)$r['project_id']!==$project || (int)$r['building_id']!==$building || ($r['payload']['record_kind']??'')!=='restpunt') throw new DomainException('Restpunt niet gevonden.');
    }

    public function listing(string $actor,int $project,int $building,int $restpoint): array
    {
        $this->requireRestpoint($actor,$project,$building,$restpoint,'read');
        $q=$this->db->prepare('SELECT p.document_id,p.photo_role,p.created_by,p.created_at,d.title FROM office_restpoint_photos p JOIN office_documents d ON d.id=p.document_id WHERE p.restpoint_id=? AND d.lifecycle_status=? ORDER BY p.created_at,p.id');
        $q->execute([$restpoint,'active']);
        $out=[];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            try {
                $file=$this->documents->authorizedDownload((int)$actor,$project,(int)$row['document_id']);
            } catch (\RuntimeException $e) {
                continue;
            }
            if (!in_array($file['mime_type'],['image/jpeg','image/png'],true)) continue;
            $out[]=$row;
        }
        return $out;
    }

    public function upload(string $actor,int $project,int $building,int $restpoint,string $role,array $file): int
    {
        $this->requireRestpoint($actor,$project,$building,$restpoint,'write');
        if (!in_array($role,['before','after','review'],true) || ($file['error']??null)!==UPLOAD_ERR_OK || !is_string($file['tmp_name']??null) || !is_string($file['name']??null) || !is_uploaded_file($file['tmp_name'])) throw new DomainException('Ongeldige foto.');
        $size=filesize($file['tmp_name']);
        if ($size===false || $size<1 || $size>25*1024*1024) throw new DomainException('Ongeldige fotogrootte.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $image=@getimagesize($file['tmp_name']);
        if (!in_array($mime,['image/jpeg','image/png'],true) || !$image || ($image['mime']??null)!==$mime) throw new DomainException('Alleen JPEG en PNG zijn toegestaan.');
        // Existing document service owns its transaction and immutable file storage.
        $document=$this->documents->upload((int)$actor,$project,$file['tmp_name'],$file['name'],'Restpunt '.$restpoint.' — '.$role);
        $this->db->beginTransaction();
        try {
            $this->requireRestpoint($actor,$project,$building,$restpoint,'write');
            $q=$this->db->prepare('INSERT INTO office_restpoint_photos (restpoint_id,document_id,photo_role,created_by) VALUES (?,?,?,?)');
            $q->execute([$restpoint,$document,$role,$actor]);
            $this->db->commit();
            return $document;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            // Registered project document may remain; never delete immutable originals here.
            error_log('Restpoint photo association failed for document '. $document);
            throw $e;
        }
    }
}
