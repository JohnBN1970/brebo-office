<?php
declare(strict_types=1);
namespace Brebo\Office\Documents;

require_once __DIR__.'/OfficeDossierArchiveInventory.php';
require_once __DIR__.'/OfficeDossierArchiveCompression.php';
require_once __DIR__.'/OfficeDossierArchiveCipher.php';
require_once __DIR__.'/OfficeDossierArchiveRestore.php';

/** Authorized internal worker only; no lifecycle publication or source deletion. */
final class OfficeDossierArchiveService
{
    public function __construct(
        private readonly OfficeDossierArchiveInventory $inventory,
        private readonly OfficeDossierArchiveCompression $compression,
        private readonly OfficeDossierArchiveRestore $restore
    ) {}

    public function stage(array $authorizedContexts,string $directory,string $key,int $maxArchiveBytes): array
    {
        $inventory=$this->inventory->collect($authorizedContexts);
        $zip=$this->compression->create($inventory['manifest'],$directory);
        $encrypted=null;
        $check=null;
        $success=false;
        try {
            $encrypted=OfficeDossierArchiveCipher::transform($zip['staged_path'],$directory,$key,false,$maxArchiveBytes);
            $check=OfficeDossierArchiveCipher::transform($encrypted,$directory,$key,true,$maxArchiveBytes);
            if (!hash_equals($zip['archive_sha256'],(string)hash_file('sha256',$check))) throw new \RuntimeException('Encryption roundtrip mismatch');
            $hash=hash_file('sha256',$encrypted);
            if ($hash===false) throw new \RuntimeException('Encrypted hash failed');
            $success=true;
            return ['staged_path'=>$encrypted,'encrypted_sha256'=>$hash,'archive_sha256'=>$zip['archive_sha256'],'manifest_sha256'=>$zip['manifest_sha256'],'manifest'=>$inventory['manifest'],'document_count'=>$inventory['document_count']];
        } finally {
            @unlink($zip['staged_path']);
            if ($check!==null) @unlink($check);
            if (!$success && $encrypted!==null) @unlink($encrypted);
        }
    }

    public function restoreStaged(string $encrypted,string $manifest,string $encryptedHash,string $archiveHash,string $manifestHash,string $directory,string $key,int $maxArchiveBytes,int $maxRestoredBytes): array
    {
        if (!hash_equals($manifestHash,hash('sha256',$manifest)) || !hash_equals($encryptedHash,(string)hash_file('sha256',$encrypted))) throw new \RuntimeException('Archive metadata mismatch');
        $zip=OfficeDossierArchiveCipher::transform($encrypted,$directory,$key,true,$maxArchiveBytes);
        try {
            return $this->restore->restore($zip,$manifest,$archiveHash,$directory,$maxRestoredBytes);
        } finally {
            @unlink($zip);
        }
    }
}
