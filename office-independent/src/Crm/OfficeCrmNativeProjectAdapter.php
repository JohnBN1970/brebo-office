<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

use DomainException;
use PDO;
use RuntimeException;

/**
 * Native project writer for an accepted direct CRM order.
 * Invoke only as the createProject callback of OfficeCrmDirectProjectTransfer:
 * it must share that service's existing transaction and operator identity.
 * The server must grant the actor an explicit native projects/create permission.
 */
final class OfficeCrmNativeProjectAdapter
{
    public function __construct(private readonly int $operatorId) {}

    /** @param array<string,mixed> $opportunity */
    public function __invoke(PDO $db, array $opportunity): int
    {
        if ($this->operatorId < 1 || !$db->inTransaction()) {
            throw new DomainException('Project creation requires an authorized transaction');
        }
        $actor = $db->prepare(
            "SELECT 1 FROM office_operator_accounts a
             JOIN office_module_permissions g ON g.operator_id=a.id
             WHERE a.id=? AND a.active=1 AND g.module='projects'
               AND g.permission='create' LIMIT 1"
        );
        $actor->execute([$this->operatorId]);
        if ($actor->fetchColumn() === false) {
            throw new DomainException('Native project creation permission required');
        }

        $opportunityId = (int)($opportunity['id'] ?? 0);
        $title = trim((string)($opportunity['title'] ?? ''));
        if ($opportunityId < 1 || $title === '' || mb_strlen($title) > 255) {
            throw new DomainException('Invalid accepted opportunity');
        }
        $columns = self::projectColumns($db);
        foreach (['id','title','status'] as $column) {
            if (!isset($columns[$column])) throw new RuntimeException('Unsupported native project schema: '.$column);
        }
        // No guesses about other non-nullable project attributes.
        foreach ($columns as $column => $meta) {
            if (in_array($column, ['id','title','status','code','description'], true)) continue;
            if ($meta['required'] && !$meta['auto']) {
                throw new RuntimeException('Project schema requires unmapped attribute: '.$column);
            }
        }

        $values = ['title'=>$title,'status'=>'preparation'];
        if (isset($columns['code'])) {
            $code = 'CRM-DIRECT-'.$opportunityId;
            if (($columns['code']['max'] ?? 255) < strlen($code)) {
                throw new RuntimeException('Project code column too short');
            }
            $values['code'] = $code;
        }
        if (isset($columns['description'])) {
            $values['description'] = 'Directe CRM-opdracht; projectvoorbereiding en vierogen-vrijgave vereist.';
        }
        $sql = 'INSERT INTO office_projects ('.implode(',',array_keys($values)).') VALUES ('.
            implode(',',array_fill(0,count($values),'?')).')';
        $insert = $db->prepare($sql);
        $insert->execute(array_values($values));
        $projectId = (int)$db->lastInsertId();
        if ($projectId < 1) throw new RuntimeException('Native project ID missing');
        // Access is scoped to the creator; no arbitrary project grants.
        $membership = $db->prepare(
            "INSERT INTO office_project_memberships (project_id,operator_id,permission) VALUES (?,?,'manage')"
        );
        $membership->execute([$projectId,$this->operatorId]);
        // Optional Deal housing handoff runs atomically with project creation.
        // Never overwrite an existing project's housing distribution.
        if (getenv('OFFICE_TEST_CRM_DEAL_HOUSING_TRANSFER_ENABLED')==='enabled'
            && getenv('OFFICE_TEST_CRM_DEAL_HOUSING_SCHEMA_VERIFIED')==='enabled') {
            require_once __DIR__.'/OfficeCrmDealHousingProjectTransfer.php';
            (new OfficeCrmDealHousingProjectTransfer($db))->transfer($opportunityId,$projectId);
        }
        return $projectId;
    }

    /** @return array<string,array{required:bool,auto:bool,max:int}> */
    private static function projectColumns(PDO $db): array
    {
        $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $rows = $db->query('PRAGMA table_info(office_projects)')->fetchAll(PDO::FETCH_ASSOC);
            $columns = [];
            foreach ($rows as $row) {
                $type = strtoupper((string)$row['type']);
                preg_match('/\((\d+)\)/', $type, $length);
                $auto = (int)$row['pk'] === 1 && str_contains($type,'INT');
                $columns[(string)$row['name']] = [
                    'required'=>(bool)$row['notnull'] && $row['dflt_value']===null,
                    'auto'=>$auto,
                    'max'=>isset($length[1])?(int)$length[1]:255,
                ];
            }
            return $columns;
        }
        if ($driver !== 'mysql') throw new RuntimeException('Unsupported project database driver');
        $stmt = $db->query(
            "SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLUMN_TYPE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='office_projects'"
        );
        $columns = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            preg_match('/\((\d+)\)/', (string)$row['COLUMN_TYPE'], $length);
            $columns[(string)$row['COLUMN_NAME']] = [
                'required'=>$row['IS_NULLABLE']==='NO' && $row['COLUMN_DEFAULT']===null,
                'auto'=>str_contains((string)$row['EXTRA'],'auto_increment'),
                'max'=>isset($length[1])?(int)$length[1]:255,
            ];
        }
        return $columns;
    }
}
