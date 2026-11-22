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
        // History must be append-only per revision; evidence and events must
        // never become orphaned after a schema drift or incomplete migration.
        foreach (['office_process_form_events'=>'uq_process_form_event_revision',
                  'office_process_form_instances'=>'idx_process_forms_context',
                  'office_process_form_evidence'=>'idx_process_form_evidence_form'] as $table=>$index) {
            try {
                $stmt=$db->query('SHOW INDEX FROM `'.$table.'`');
                $indexes=$stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!is_array($indexes) || !in_array($index,array_column($indexes,'Key_name'),true)) {
                    $errors[]=$table.'.'.$index.' index ontbreekt';
                }
                if ($index==='uq_process_form_event_revision') {
                    $parts=array_values(array_filter($indexes,static fn(array $row): bool => ($row['Key_name']??'')===$index));
                    usort($parts,static fn(array $a,array $b): int => (int)$a['Seq_in_index']<=>(int)$b['Seq_in_index']);
                    if (array_column($parts,'Column_name')!==['form_id','revision'] || count(array_filter($parts,static fn(array $row): bool => (int)$row['Non_unique']!==0))>0) {
                        $errors[]='Historie mist unieke formulier-revisiecontrole';
                    }
                }
            } catch (\Throwable $e) {
                $errors[]=$table.' indexen niet verifieerbaar';
            }
        }
        return ['ready'=>$errors===[], 'errors'=>$errors];
    }
}
