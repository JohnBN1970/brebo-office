<?php
declare(strict_types=1);
namespace Brebo\Office\Governance\Infrastructure;

use PDO;
use Brebo\Office\Documents\OfficeProjectDocumentService;

final class OfficeRestpointDocumentResolver
{
    public function __construct(private readonly PDO $db, private readonly OfficeProjectDocumentService $documents) {}

    public function __invoke(string $actor,int $project,int $building,int $document,int $page): ?int
    {
        if (!ctype_digit($actor) || (string)(int)$actor!==$actor || min($project,$building,$document,$page)<1) return null;
        $q=$this->db->prepare('SELECT 1 FROM office_project_buildings WHERE project_id=? AND building_id=?');
        $q->execute([$project,$building]);
        if (!$q->fetchColumn()) return null;
        $file=$this->documents->authorizedDownload((int)$actor,$project,$document);
        // Initial photo-only implementation: PDFs fail closed until a trusted page renderer exists.
        if ($page!==1 || !in_array($file['mime_type'],['image/jpeg','image/png'],true)) return null;
        $size=@getimagesize($file['path']);
        if (!$size || ($size['mime']??null)!==$file['mime_type']) return null;
        $q=$this->db->prepare('SELECT d.original_id,o.sha256 FROM office_documents d JOIN office_document_originals o ON o.id=d.original_id WHERE d.id=? AND d.lifecycle_status=?');
        $q->execute([$document,'active']);
        $row=$q->fetch(PDO::FETCH_ASSOC);
        if (!$row || !hash_equals($row['sha256'],(string)hash_file('sha256',$file['path']))) return null;
        return (int)$row['original_id'];
    }
}
