<?php
declare(strict_types=1);
namespace Brebo\Office\Process;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Generic storage for controlled forms. Caller enforces authentication,
 * contextual access, CSRF, mandate and document-specific field validation.
 * No automatic approval, workflow bypass or runtime DDL.
 */
final class OfficeProcessFormRepository
{
    public function __construct(private readonly PDO $db) {}

    public function create(string $code, string $version, string $contextType, int $contextId, string $actor, array $data, string $reason): int
    {
        require_once __DIR__.'/OfficeProcessFormCatalog.php';
        OfficeProcessFormCatalog::requireCode($code);
        require_once __DIR__.'/OfficeProcessFormContext.php';
        if (!OfficeProcessFormContext::accepts($code,$contextType)) {
            throw new InvalidArgumentException('Formuliercode hoort niet bij deze modulecontext');
        }
        self::validate($version,$contextType,$contextId,$actor,$data,$reason);
        self::validateDocumentData($code,$data);
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("INSERT INTO office_process_form_instances (document_code,document_version,context_type,context_id,status,revision,data_json,created_by,updated_by) VALUES (?,?,?,?,'concept',1,?,?,?)");
            $stmt->execute([$code,$version,$contextType,$contextId,$json,$actor,$actor]);
            $id = (int)$this->db->lastInsertId();
            $this->appendEvent($id,1,$actor,'created',$reason,$json);
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function load(int $id): ?array
    {
        if ($id < 1) throw new InvalidArgumentException('Invalid form ID');
        $stmt = $this->db->prepare('SELECT * FROM office_process_form_instances WHERE id=?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['data'] = json_decode((string)$row['data_json'], true, 512, JSON_THROW_ON_ERROR);
        unset($row['data_json']);
        return $row;
    }


    /**
     * Fetch a CL-204 opname only within its building context.
     * The host MUST authorize the current operator for this building first.
     * Never use the unscoped load() with a browser-supplied form ID.
     */
    public function loadBuildingOpname(int $buildingId, int $formId): ?array
    {
        if ($buildingId < 1 || $formId < 1) {
            throw new InvalidArgumentException('Invalid building or opname ID');
        }
        $stmt = $this->db->prepare(
            "SELECT * FROM office_process_form_instances
             WHERE id=? AND document_code='CL-204' AND context_type='building' AND context_id=?"
        );
        $stmt->execute([$formId, $buildingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['data'] = json_decode((string)$row['data_json'], true, 512, JSON_THROW_ON_ERROR);
        unset($row['data_json']);
        return $row;
    }

    /**
     * List CL-204 opname metadata, restricted to an already authorized building.
     * Missing schema is an error and must not be represented as an empty list.
     */
    public function listBuildingOpnames(int $buildingId, int $limit = 50): array
    {
        if ($buildingId < 1 || $limit < 1 || $limit > 200) {
            throw new InvalidArgumentException('Invalid building or limit');
        }
        $stmt = $this->db->prepare(
            "SELECT id,status,revision,updated_at FROM office_process_form_instances
             WHERE document_code='CL-204' AND context_type='building' AND context_id=?
             ORDER BY updated_at DESC,id DESC LIMIT " . $limit
        );
        $stmt->execute([$buildingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function revise(int $id, int $expectedRevision, string $actor, array $data, string $reason): int
    {
        if ($id < 1 || $expectedRevision < 1) throw new InvalidArgumentException('Invalid revision');
        self::validatePayload($actor,$data,$reason,true);
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $this->db->beginTransaction();
        try {
            $codeStmt=$this->db->prepare('SELECT document_code FROM office_process_form_instances WHERE id=?');
            $codeStmt->execute([$id]);
            $code=(string)$codeStmt->fetchColumn();
            if ($code==='') throw new RuntimeException('Formulier niet gevonden');
            self::validateDocumentData($code,$data);
            $stmt = $this->db->prepare("UPDATE office_process_form_instances SET revision=revision+1,data_json=?,updated_by=? WHERE id=? AND revision=? AND status='concept'");
            $stmt->execute([$json,$actor,$id,$expectedRevision]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException('Formulier gewijzigd of niet meer in concept');
            $revision = $expectedRevision + 1;
            $this->appendEvent($id,$revision,$actor,'revised',$reason,$json);
            $this->db->commit();
            return $revision;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Persist a controlled status decision and its immutable snapshot atomically.
     * The caller must validate real Office role/mandate and CSRF before calling.
     * Mandate is deliberately passed from the server-side authorization layer,
     * never from a request parameter.
     */
    public function transition(int $id, int $expectedRevision, string $action, string $actor, bool $fourEyes, bool $mandateGranted, string $reason): array
    {
        if ($id < 1 || $expectedRevision < 1 || trim($actor) === '' || strlen($actor) > 128) {
            throw new InvalidArgumentException('Invalid transition identity');
        }
        require_once __DIR__.'/OfficeProcessFormWorkflow.php';
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT status,revision,data_json FROM office_process_form_instances WHERE id=? FOR UPDATE');
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || (int)$row['revision'] !== $expectedRevision) {
                throw new RuntimeException('Formulier gewijzigd; ververs voor een nieuw besluit');
            }
            // The latest submission, not the original creator, defines the
            // independent-review boundary. Reopened forms can be resubmitted.
            $submitter = '';
            $stmt = $this->db->prepare("SELECT actor_operator_id FROM office_process_form_events WHERE form_id=? AND event_type='submit' ORDER BY revision DESC LIMIT 1");
            $stmt->execute([$id]);
            $submitter = (string)($stmt->fetchColumn() ?: '');
            // Never approve a form without a recorded submission, even when
            // the optional independent-review rule is disabled.
            if ($action === 'approve' && $submitter === '') {
                throw new RuntimeException('Goedkeuring vereist een geregistreerde indiening');
            }
            if ($action === 'submit') {
                // Revalidate against the locked database snapshot, not a stale
                // browser copy. FR-201 is the first source-verified schema.
                $codeStmt=$this->db->prepare('SELECT document_code FROM office_process_form_instances WHERE id=?');
                $codeStmt->execute([$id]);
                if ($codeStmt->fetchColumn()==='FR-201') {
                    require_once __DIR__.'/OfficeProcessFormFr201.php';
                    $data=json_decode((string)$row['data_json'],true,512,JSON_THROW_ON_ERROR);
                    if (!is_array($data) || OfficeProcessFormFr201::missingRequired($data)!==[]) {
                        throw new InvalidArgumentException('FR-201 verplichte velden ontbreken');
                    }
                }
            }
            $next = OfficeProcessFormWorkflow::target((string)$row['status'],$action,$actor,$submitter,$fourEyes,$mandateGranted,$reason);
            $revision = $expectedRevision + 1;
            $stmt = $this->db->prepare('UPDATE office_process_form_instances SET status=?,revision=?,updated_by=? WHERE id=? AND revision=?');
            $stmt->execute([$next,$revision,$actor,$id,$expectedRevision]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException('Concurrent form decision');
            $snapshot = json_encode(['status'=>$next,'data'=>json_decode((string)$row['data_json'],true,512,JSON_THROW_ON_ERROR)],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
            $this->appendEvent($id,$revision,$actor,$action,trim($reason),$snapshot);
            $this->db->commit();
            return ['status'=>$next,'revision'=>$revision];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    private function appendEvent(int $id, int $revision, string $actor, string $type, string $reason, string $json): void
    {
        $stmt = $this->db->prepare('INSERT INTO office_process_form_events (form_id,revision,actor_operator_id,event_type,reason,snapshot_json) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$id,$revision,$actor,$type,$reason,$json]);
    }

    private static function validatePayload(string $actor, array $data, string $reason, bool $allowNested = false): void
    {
        if (trim($actor)==='' || strlen($actor)>128 || trim($reason)==='' || mb_strlen($reason)>500) {
            throw new InvalidArgumentException('Invalid controlled form actor or reason');
        }
        if (count($data)>250) throw new InvalidArgumentException('Too many fields');
        if ($allowNested && strlen(json_encode($data, JSON_THROW_ON_ERROR)) > 262144) {
            throw new InvalidArgumentException('Formuliergegevens te groot');
        }
        foreach ($data as $key=>$value) {
            if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]{0,79}$/',$key)
                || !($allowNested && is_array($value) || is_scalar($value) || $value===null) || (is_string($value) && mb_strlen($value)>10000)) {
                throw new InvalidArgumentException('Invalid controlled form field');
            }
        }
    }

    private static function validate(string $version, string $contextType, int $contextId, string $actor, array $data, string $reason): void
    {
        if (trim($version)==='' || strlen($version)>32 || !preg_match('/^[a-z_]{2,32}$/',$contextType)
            || $contextId<1 || trim($actor)==='' || strlen($actor)>128 || trim($reason)==='' || mb_strlen($reason)>500) {
            throw new InvalidArgumentException('Invalid controlled form metadata');
        }
        self::validatePayload($actor,$data,$reason,true);
    }

    private static function validateDocumentData(string $code, array $data): void
    {
        if ($code === 'CL-204') {
            require_once __DIR__.'/OfficeCl204Projectopname.php';
            $errors=OfficeCl204Projectopname::validateDraft($data);
            if ($errors !== []) throw new InvalidArgumentException('Ongeldige CL-204-gegevens: '.implode(', ',array_keys($errors)));
        } else {
            foreach ($data as $value) {
                if (is_array($value)) throw new InvalidArgumentException('Nested fields not supported for this form');
            }
        }
    }
}
