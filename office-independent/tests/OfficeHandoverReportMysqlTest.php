<?php
declare(strict_types=1);
$dsn = getenv('HANDOVER_CI_DSN');
if (!is_string($dsn) || !str_contains($dsn, 'dbname=brebo_handover_ci;')) {
    fwrite(STDERR, "HANDOVER_MYSQL=BLOCKED_ISOLATION\n"); exit(2);
}
try {
    $db = new PDO($dsn, (string)getenv('HANDOVER_CI_USER'), (string)getenv('HANDOVER_CI_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if ($db->query('SELECT DATABASE()')->fetchColumn() !== 'brebo_handover_ci') {
        throw new RuntimeException('WRONG_DATABASE');
    }
    $sql = (string)file_get_contents(__DIR__.'/../sql/20261127_project_handover_reports.sql');
    $sql = trim((string)preg_replace('/--[^\n]*/', '', $sql));
    $db->exec($sql);
    $insert = $db->prepare("INSERT INTO office_project_handover_reports (project_id,title,notes,status,created_by_operator_id,created_at) VALUES (?, ?, ?, 'draft', ?, CURRENT_TIMESTAMP)");
    $insert->execute([101, 'Eerste opleverrapportage', 'Conceptnotitie', 42]);
    $id = (int)$db->lastInsertId();
    if ($id <= 0) throw new RuntimeException('INSERT_FAILED');
    $read = $db->prepare('SELECT project_id,title,notes,status,created_by_operator_id FROM office_project_handover_reports WHERE project_id=? AND id=?');
    $read->execute([101,$id]);
    $row = $read->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int)$row['project_id'] !== 101 || $row['title'] !== 'Eerste opleverrapportage'
        || $row['notes'] !== 'Conceptnotitie' || $row['status'] !== 'draft'
        || (int)$row['created_by_operator_id'] !== 42) {
        throw new RuntimeException('DRAFT_PERSISTENCE_MISMATCH');
    }
    $read->execute([102,$id]);
    if ($read->fetch(PDO::FETCH_ASSOC) !== false) throw new RuntimeException('CROSS_PROJECT_LEAK');
    echo "HANDOVER_MYSQL=PASS_DRAFT_AND_PROJECT_ISOLATION\n";
} catch (Throwable $e) {
    fwrite(STDERR, "HANDOVER_MYSQL=FAIL ".$e->getMessage()."\n"); exit(1);
}
