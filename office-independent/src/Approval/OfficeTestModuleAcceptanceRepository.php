<?php
declare(strict_types=1);
namespace Brebo\Office\Approval;

use PDO;
use InvalidArgumentException;
use RuntimeException;

final class OfficeTestModuleAcceptanceRepository
{
    public function __construct(private PDO $db)
    {
        if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'brebo_office_test') {
            throw new RuntimeException('Testdatabase niet bevestigd.');
        }
    }

    private function validate(string $module, string $test, string $release): void
    {
        if ($module === '' || strlen($module) > 100 ||
            !preg_match('/\A[a-z0-9_-]{1,100}\z/D', $test) ||
            !preg_match('/\A[0-9a-f]{40}\z/D', $release)) {
            throw new InvalidArgumentException('Ongeldig acceptatiepunt.');
        }
    }

    public function record(string $module, string $test, string $release, string $status, string $notes, int $operator): void
    {
        $this->validate($module, $test, $release);
        if (!in_array($status, ['pending','passed','failed','retest'], true) ||
            strlen($notes) > 8000 || $operator <= 0 ||
            ($status === 'failed' && trim($notes) === '')) {
            throw new InvalidArgumentException('Ongeldig testresultaat.');
        }
        if (!(new OfficeTestSiteAdministratorAccess($this->db))->isAuthorized((string)$operator, true)) {
            throw new RuntimeException('Alleen sitebeheerder.');
        }
        $stmt = $this->db->prepare('INSERT INTO office_test_module_acceptance
            (module_key,test_key,release_sha,status,notes,operator_id) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$module,$test,$release,$status,$notes,$operator]);
    }

    public function latest(string $module, string $test, string $release): ?array
    {
        $this->validate($module, $test, $release);
        $stmt = $this->db->prepare('SELECT status,notes,recorded_at,release_sha FROM office_test_module_acceptance
            WHERE module_key=? AND test_key=? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$module,$test]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { return null; }
        if ($row['release_sha'] !== $release) {
            $row['status'] = 'retest';
        }
        return $row;
    }
}
