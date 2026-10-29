<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Questions/OfficeConversationAccess.php';
require_once __DIR__ . '/../src/Questions/OfficeQuestionService.php';

use Brebo\Office\Questions\OfficeConversationAccess;
use Brebo\Office\Questions\OfficeQuestionService;

$db = new Pdo\Sqlite('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// MySQL DATE_FORMAT nabootsen voor deze SQLite-unit-test.
$db->createFunction(
    'DATE_FORMAT',
    static function ($value, $format) {
        if ($value === null) {
            return null;
        }

        $format = str_replace(
            ['%Y', '%m', '%d', '%H', '%i', '%s'],
            ['Y', 'm', 'd', 'H', 'i', 's'],
            $format
        );

        return date($format, strtotime((string)$value));
    },
    2
);

$db->exec("
    CREATE TABLE office_questions (
        id INTEGER PRIMARY KEY,
        module TEXT,
        entity_type TEXT,
        entity_id INTEGER,
        status TEXT,
        subject TEXT,
        created_by TEXT,
        created_at TEXT
    );

    CREATE TABLE office_question_recipients (
        question_id INTEGER,
        operator_id TEXT
    );

    CREATE TABLE office_question_messages (
        id INTEGER PRIMARY KEY,
        question_id INTEGER,
        operator_id TEXT,
        message TEXT,
        created_at TEXT
    );

    CREATE TABLE office_operator_people (
        operator_id TEXT,
        person_id INTEGER
    );

    CREATE TABLE office_inzet_people (
        id INTEGER PRIMARY KEY,
        display_name TEXT
    );

    CREATE TABLE office_operator_accounts (
        id TEXT PRIMARY KEY,
        username TEXT
    );

    INSERT INTO office_questions VALUES
      (1,'finance','invoice',100,'open','Vraag A','manager','2026-10-29'),
      (2,'finance','invoice',100,'open','Vraag B','manager','2026-10-29');

    INSERT INTO office_question_recipients VALUES
      (1,'viewer'),
      (2,'other');

    INSERT INTO office_question_messages VALUES
      (1,1,'manager','Vertrouwelijk A','2026-10-29'),
      (2,2,'manager','Vertrouwelijk B','2026-10-29');
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
        && $action === 'read'
        && in_array($operator, ['manager','viewer','other','outsider'], true)
);

$service = new OfficeQuestionService($db, $access);

$expected = [
    'manager'  => [1, 2],
    'viewer'   => [1],
    'other'    => [2],
    'outsider' => [],
];

foreach ($expected as $operator => $ids) {
    $questions = $service->listForEntity(
        $operator, 'finance', 'invoice', 100
    );

    $actual = array_map(
        static fn(array $q): int => (int)$q['id'],
        $questions
    );

    sort($actual);
    sort($ids);

    if ($actual !== $ids) {
        throw new RuntimeException(
            "Onjuist leesrecht voor $operator: " . json_encode($actual)
        );
    }

    echo "OK: $operator ziet " . count($actual) . " gesprek(ken)\n";
}

echo "Leesautorisatie: 4 controles geslaagd\n";
