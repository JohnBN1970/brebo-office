<?php
declare(strict_types=1);
namespace Brebo\Office\Process;

use PDO;

/** Read-only schema readiness gate; never creates or modifies tables. */
final class OfficeProcessFormSchemaPreflight
{
    private const REQUIRED = [
        'office_process_form_instances'=>['id','document_code','document_version','context_type','context_id','status','revision','data_json','created_by','updated_by','created_at','updated_at'],
        'office_process_form_events'=>['id','form_id','revision','actor_operator_id','event_type','reason','snapshot_json','created_at'],
        'office_process_form_evidence'=>['id','form_id','document_id','evidence_ref','linked_by','linked_at'],
    ];

    public static function verify(PDO $db): array
    {
        $errors=[];
        foreach (self::REQUIRED as $table=>$columns) {
            try {
                $found=$db->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_COLUMN);
                if (!is_array($found)) throw new \RuntimeException('No column metadata');
                foreach ($columns as $column) {
                    if (!in_array($column,$found,true)) $errors[]=$table.'.'.$column.' ontbreekt';
                }
            } catch (\Throwable $e) {
                $errors[]=$table.' niet verifieerbaar';
            }
        }
        return ['ready'=>$errors===[], 'errors'=>$errors];
    }
}
