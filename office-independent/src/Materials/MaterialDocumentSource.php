<?php
declare(strict_types=1);
namespace Brebo\Office\Materials;
use PDO;
use Brebo\Office\Documents\{OfficeDocumentAccessGate,OfficePrivateFileStorage};

/** Reuses an existing Office original: selecting a mail/project document never duplicates its binary. */
final class MaterialDocumentSource
{
    public function __construct(private readonly PDO $db,private readonly OfficePrivateFileStorage $storage,private readonly OfficeDocumentAccessGate $access,private readonly MaterialSourceReader $reader){}
    private function original(string $actorId,int $documentId):array
    {
        if($documentId<1)throw new \InvalidArgumentException('Ongeldig document.');
        $q=$this->db->prepare('SELECT context_type,context_id FROM office_document_contexts WHERE document_id=?');$q->execute([$documentId]);
        $this->access->requireAccess($actorId,$q->fetchAll(PDO::FETCH_ASSOC),'read');
        $q=$this->db->prepare("SELECT d.original_filename,d.mime_type,o.storage_provider,o.storage_key,o.sha256 FROM office_documents d JOIN office_document_originals o ON o.id=d.original_id WHERE d.id=? AND d.lifecycle_status='active'");$q->execute([$documentId]);$doc=$q->fetch(PDO::FETCH_ASSOC);
        if(!$doc||$doc['storage_provider']!=='office_private')throw new \OutOfBoundsException('Documentbron niet beschikbaar.');
        $doc['path']=$this->storage->authorizedPath((string)$doc['storage_key']);
        if(filesize($doc['path'])>20000000)throw new \DomainException('Document is groter dan 20 MB.');
        return $doc;
    }
    public function read(string $actorId,int $documentId,array $defaults=[]):array
    {
        $doc=$this->original($actorId,$documentId);
        $bytes=file_get_contents($doc['path']);if(!is_string($bytes))throw new \RuntimeException('Document kon niet worden gelezen.');
        $result=$this->reader->read((string)$doc['original_filename'],(string)$doc['mime_type'],$bytes,$defaults);
        $result['source']=['kind'=>'office_document','document_id'=>$documentId,'reference'=>'office:document:'.$documentId,'sha256'=>(string)$doc['sha256']];
        return $result;
    }
    public function verify(string $actorId,array $source):void
    {
        $current=$this->original($actorId,(int)($source['document_id']??0));
        if(!hash_equals((string)($source['sha256']??''),$current['sha256']))throw new \DomainException('Documentbron is gewijzigd. Lees het document opnieuw in.');
    }
}
