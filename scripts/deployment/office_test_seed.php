<?php
declare(strict_types=1);
// Included by acceptance only after the isolated environment check. No external transports.
function seedOfficeTest(PDO $db, string $release): array {
    if (!preg_match('/^[0-9a-f]{40}$/D',$release)) throw new RuntimeException('INVALID_SEED_RELEASE');
    $code='BREBO-CI-'.substr($release,0,16);
    $title='FICTIEF - automatische acceptatie Startdossier';
    $db->beginTransaction();
    try {
        $q=$db->prepare('SELECT id,title FROM office_projects WHERE code=? FOR UPDATE');$q->execute([$code]);$project=$q->fetch(PDO::FETCH_ASSOC);
        if ($project && $project['title']!==$title) throw new RuntimeException('SEED_PROJECT_COLLISION');
        if (!$project) {
            $q=$db->prepare("INSERT INTO office_projects (code,title,status,description) VALUES (?,?,'preparation',?)");$q->execute([$code,$title,'Uitsluitend fictieve CI-testgegevens; geen externe acties.']);
            $projectId=(int)$db->lastInsertId();
        } else {
            $projectId=(int)$project['id'];
            $q=$db->prepare('SELECT id FROM office_project_start_dossiers WHERE project_id=?');$q->execute([$projectId]);$ids=$q->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ids as $id) {
                foreach (['questions','approvals','events'] as $suffix) {
                    $q=$db->prepare('DELETE FROM office_project_start_'.$suffix.' WHERE dossier_id=?');$q->execute([$id]);
                }
            }
            $q=$db->prepare('DELETE FROM office_project_start_dossiers WHERE project_id=?');$q->execute([$projectId]);
            $q=$db->prepare("UPDATE office_projects SET status='preparation' WHERE id=?");$q->execute([$projectId]);
        }
        $users=[];
        foreach (['leader','reviewer','reader','outsider'] as $i=>$role) {
            $id=(string)(990000001+$i);$username='brebo-ci-'.$role;
            $password=bin2hex(random_bytes(24));
            $q=$db->prepare('SELECT username FROM office_operator_accounts WHERE id=? FOR UPDATE');$q->execute([$id]);$existing=$q->fetchColumn();
            if ($existing!==false && $existing!==$username) throw new RuntimeException('SEED_ACCOUNT_COLLISION');
            if ($existing===false) {
                $q=$db->prepare('INSERT INTO office_operator_accounts (id,username,password_hash,can_edit_crm,active) VALUES (?,?,?,0,1)');$q->execute([$id,$username,password_hash($password,PASSWORD_DEFAULT)]);
            } else {
                $q=$db->prepare('UPDATE office_operator_accounts SET password_hash=?,active=1 WHERE id=? AND username=?');$q->execute([password_hash($password,PASSWORD_DEFAULT),$id,$username]);
            }
            if ($role!=='outsider') {
                $q=$db->prepare('INSERT INTO office_project_memberships (project_id,operator_id,permission) VALUES (?,?,?) ON DUPLICATE KEY UPDATE permission=VALUES(permission)');$q->execute([$projectId,$id,$role==='reader'?'read':'manage']);
            }
            // Only disposable test accounts receive additional module grants.
            if (in_array($role,['leader','reviewer'],true)) {
                $q=$db->prepare("INSERT IGNORE INTO office_module_permissions (operator_id,module,permission) VALUES (?,'finance','read')");
                $q->execute([$id]);
            }
            if ($role==='leader') {
                $q=$db->prepare("INSERT IGNORE INTO office_module_permissions (operator_id,module,permission) VALUES (?,'finance','draft_edit')");$q->execute([$id]);
            }
            $users[$role]=['id'=>$id,'username'=>$username,'password'=>$password];
        }
        // Make fictitious acceptance data reviewable to existing project-1 managers
        // who already hold Finance read. Never grant Finance or access to real data.
        $q=$db->prepare("INSERT IGNORE INTO office_project_memberships (project_id,operator_id,permission)
            SELECT ?,m.operator_id,'manage' FROM office_project_memberships m
            JOIN office_operator_accounts a ON a.id=m.operator_id AND a.active=1
            JOIN office_module_permissions g ON g.operator_id=a.id AND g.module='finance' AND g.permission='read'
            WHERE m.project_id=1 AND m.permission='manage' AND a.username NOT LIKE 'brebo-ci-%'");
        $q->execute([$projectId]);
        $db->commit();
        return ['project_id'=>$projectId,'users'=>$users];
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}
