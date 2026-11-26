<?php
declare(strict_types=1);
namespace Brebo\Office\Inzet;

use PDO;
use Brebo\Office\Approval\OfficeTestSiteAdministratorAccess;

require_once __DIR__.'/../Approval/OfficeTestSiteAdministratorAccess.php';
require_once __DIR__.'/OfficeInzetPersonnelProfileCsrf.php';

final class OfficePersonnelAccountLink {
    public function __construct(private readonly PDO $db) {}

    public function link(string $actor, string $session, string $secret, array $input, bool $isTest): bool {
        if (!(new OfficeTestSiteAdministratorAccess($this->db))->isAuthorized($actor, $isTest)
            || !OfficeInzetPersonnelProfileCsrf::verify($input['csrf_token'] ?? null, $session, $secret)) return false;
        $person = filter_var($input['person_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $account = filter_var($input['account_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        $project = filter_var($input['project_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!$person || !$account || !$project) throw new \InvalidArgumentException('Invalid identity selection');
        $q = $this->db->prepare("INSERT INTO office_account_personnel_links (operator_id, person_id, linked_by_operator_id)
            SELECT a.id, p.id, actor.id FROM office_operator_accounts a
            CROSS JOIN office_inzet_people p
            INNER JOIN office_inzet_project_team t ON t.person_id=p.id
            INNER JOIN office_project_memberships m ON m.project_id=t.project_id
            INNER JOIN office_operator_accounts actor ON actor.id=m.operator_id
            INNER JOIN office_test_site_administrators admin ON admin.operator_id=actor.id
            WHERE a.id=? AND p.id=? AND t.project_id=? AND actor.id=?
            AND a.active=1 AND p.active=1 AND actor.active=1 AND m.permission='manage'
            AND NOT EXISTS (SELECT 1 FROM office_account_personnel_links l WHERE l.operator_id=a.id OR l.person_id=p.id)
            LIMIT 1");
        $q->execute([$account,$person,$project,$actor]);
        return $q->rowCount() === 1;
    }
}
