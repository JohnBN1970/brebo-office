<?php
declare(strict_types=1);
$source=file_get_contents(dirname(__DIR__).'/src/Inspection/OfficeInspectionEvidenceUploadAction.php');
$checks=[
 'upload only'=>str_contains($source,'is_uploaded_file(')&&str_contains($source,'move_uploaded_file('),
 'size limit'=>str_contains($source,'10*1024*1024'),
 'mime allowlist'=>str_contains($source,"'image/jpeg'")&&str_contains($source,"'image/png'")&&str_contains($source,"'image/webp'"),
 'image validation'=>str_contains($source,'getimagesize('),
 'draft access'=>str_contains($source,"i.status='draft'")&&str_contains($source,"f.status='draft'"),
 'manager access'=>str_contains($source,"m.permission='manage'"),
 'private root'=>str_contains($source,'realpath($this->storageRoot)'),
 'random storage key'=>str_contains($source,'random_bytes(24)'),
 'database rollback cleanup'=>str_contains($source,'@unlink($target)'),
];
foreach($checks as $label=>$pass){echo ($pass?'PASS ':'FAIL ').$label."\n";if(!$pass)exit(1);}
