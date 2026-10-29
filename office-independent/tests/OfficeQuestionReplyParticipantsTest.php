<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Questions/OfficeConversationAccess.php';
require_once __DIR__ . '/../src/Questions/OfficeQuestionService.php';

use Brebo\Office\Questions\OfficeConversationAccess;
use Brebo\Office\Questions\OfficeQuestionService;

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec("
    CREATE TABLE office_questions (
        id INTEGER PRIMARY KEY,
        module TEXT,
        entity_type TEXT,
        entity_id INTEGER,
        status TEXT,
        created_by TEXT
    );

    CREATE TABLE office_question_recipients (
        question_id INTEGER,
        operator_id TEXT
    );

    CREATE TABLE office_question_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        question_id INTEGER,
        operator_id TEXT,
        message TEXT
    );

    INSERT INTO office_questions VALUES
      (1,'finance','invoice',100,'open','manager'),
      (2,'finance','invoice',100,'closed','manager');

    INSERT INTO office_question_recipients VALUES
      (1,'viewer'),
      (2,'viewer');
");

$access = new OfficeConversationAccess(
    static fn(
        string $operator,
        string $module,
        string $type,
        int $id,
        string $action
    ): bool =>
        $module === 'finance'
        && $type === 'invoice'
        && $id === 100
        && $action === 'reply'
);

$service = new OfficeQuestionService($db, $access);

$checks = [
    ['manager', 1, true],
    ['viewer', 1, true],
    ['outsider', 1, false],
    ['viewer', 2, false],
    ['viewer', 999, false],
];

foreach ($checks as [$operator, $questionId, $expected]) {
    try {
        $service->reply(
            $operator,
            'finance',
            'invoice',
            100,
            $questionId,
            'Testantwoord'
        );
        $allowed = true;
    } catch (DomainException $e) {
        $allowed = false;
    }

    if ($allowed !== $expected) {
        throw new RuntimeException(
            "Onjuist antwoordrecht: $operator / vraag $questionId"
        );
    }

    echo "OK: $operator / vraag $questionId / "
       . ($allowed ? 'toegestaan' : 'geweigerd') . PHP_EOL;
}

$count = (int)$db->query(
    'SELECT COUNT(*) FROM office_question_messages'
)->fetchColumn();

if ($count !== 2) {
    throw new RuntimeException(
        "Onjuist aantal opgeslagen antwoorden: $count"
    );
}

echo "Deelnemersautorisatie: 5 checks geslaagd" . PHP_EOL;
