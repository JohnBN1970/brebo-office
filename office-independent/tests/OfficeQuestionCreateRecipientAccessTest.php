<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Questions/OfficeConversationAccess.php';
require_once __DIR__ . '/../src/Questions/OfficeQuestionService.php';

use Brebo\Office\Questions\OfficeConversationAccess;
use Brebo\Office\Questions\OfficeQuestionService;

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec("
    CREATE TABLE office_operator_accounts (
        id TEXT PRIMARY KEY,
        active INTEGER NOT NULL
    );

    CREATE TABLE office_questions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        module TEXT,
        entity_type TEXT,
        entity_id INTEGER,
        question_type TEXT,
        subject TEXT,
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

    INSERT INTO office_operator_accounts VALUES
      ('1',1),('2',1),('3',1),('4',0);
");

$access = new OfficeConversationAccess(
    static function (
        string $operator,
        string $module,
        string $type,
        int $id,
        string $action
    ): bool {
        if ($module !== 'finance' || $type !== 'invoice' || $id !== 100) {
            return false;
        }

        if ($action === 'create') {
            return $operator === '1';
        }

        if ($action === 'read') {
            return in_array($operator, ['1', '2'], true);
        }

        return false;
    }
);

$service = new OfficeQuestionService($db, $access);

$cases = [
    ['actieve bevoegde ontvanger', ['2'], true],
    ['actieve onbevoegde ontvanger', ['3'], false],
    ['inactieve ontvanger', ['4'], false],
    ['gemengde ontvangers', ['2', '3'], false],
];

foreach ($cases as [$label, $recipients, $expected]) {
    $before = (int)$db->query(
        'SELECT COUNT(*) FROM office_questions'
    )->fetchColumn();

    try {
        $service->create(
            '1',
            'finance',
            'invoice',
            100,
            'Testvraag',
            'Testbericht',
            $recipients
        );
        $allowed = true;
    } catch (DomainException $e) {
        $allowed = false;
    }

    if ($allowed !== $expected) {
        throw new RuntimeException("FOUT: $label");
    }

    $after = (int)$db->query(
        'SELECT COUNT(*) FROM office_questions'
    )->fetchColumn();

    if ($after !== $before + ($expected ? 1 : 0)) {
        throw new RuntimeException("Onverwachte databasewijziging: $label");
    }

    echo "OK: $label" . PHP_EOL;
}

echo "Ontvangersautorisatie: 4 controles geslaagd" . PHP_EOL;
