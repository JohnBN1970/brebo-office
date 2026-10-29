<?php

declare(strict_types=1);

namespace Brebo\Office\Questions;

use PDO;
use DomainException;
use InvalidArgumentException;

final class OfficeQuestionService
{
    public function __construct(
        private readonly PDO $db,
        private readonly OfficeConversationAccess $access
    ) {}

    public function create(
        string $operatorId,
        string $module,
        string $entityType,
        int $entityId,
        string $subject,
        string $message,
        array $recipients,
        bool $blocking = true
    ): int {
        $this->access->require($operatorId, $module, $entityType, $entityId, 'create');
        $subject = trim($subject);
        $message = trim($message);

        if ($operatorId === '' || $entityId < 1 ||
            !preg_match('/^[a-z_]{2,32}$/D', $module) ||
            !preg_match('/^[a-z_]{2,64}$/D', $entityType) ||
            $subject === '' || mb_strlen($subject) > 255 ||
            $message === '' || mb_strlen($message) > 10000 ||
            count($recipients) < 1 || count($recipients) > 25) {
            throw new InvalidArgumentException('Vraaggegevens ongeldig');
        }

        $recipients = array_values(array_unique(
            array_map('strval', $recipients)
        ));

        foreach ($recipients as $recipient) {
            if (!preg_match('/^[0-9]{1,20}$/D', $recipient)) {
                throw new InvalidArgumentException('Ontvanger ongeldig');
            }
        }

        $placeholders = implode(',', array_fill(0, count($recipients), '?'));
        $check = $this->db->prepare(
            "SELECT CAST(id AS CHAR) FROM office_operator_accounts
             WHERE active=1 AND CAST(id AS CHAR) IN ($placeholders)"
        );
        $check->execute($recipients);

        if (count($check->fetchAll(PDO::FETCH_COLUMN)) !== count($recipients)) {
            throw new DomainException('Een ontvanger is niet actief');
        }

        $this->db->beginTransaction();

        try {
            $q = $this->db->prepare(
                "INSERT INTO office_questions
                (module,entity_type,entity_id,question_type,subject,created_by)
                VALUES (?,?,?,?,?,?)"
            );
            $q->execute([
                $module,
                $entityType,
                $entityId,
                $blocking ? 'blocking_question' : 'remark',
                $subject,
                $operatorId
            ]);

            $id = (int)$this->db->lastInsertId();

            $q = $this->db->prepare(
                "INSERT INTO office_question_recipients
                (question_id,operator_id) VALUES (?,?)"
            );

            foreach ($recipients as $recipient) {
                $q->execute([$id, $recipient]);
            }

            $q = $this->db->prepare(
                "INSERT INTO office_question_messages
                (question_id,operator_id,message)
                VALUES (?,?,?)"
            );
            $q->execute([$id, $operatorId, $message]);

            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function listForEntity(
        string $operatorId,
        string $module,
        string $entityType,
        int $entityId
    ): array {
        $this->access->require($operatorId, $module, $entityType, $entityId, 'read');
        $q = $this->db->prepare(
            "SELECT q.*,
                (SELECT COUNT(*)
                 FROM office_question_messages m
                 WHERE m.question_id=q.id) AS message_count
             FROM office_questions q
             WHERE q.module=? AND q.entity_type=? AND q.entity_id=?
               AND (
                 CAST(q.created_by AS CHAR)=?
                 OR EXISTS (
                   SELECT 1 FROM office_question_recipients r
                   WHERE r.question_id=q.id
                     AND CAST(r.operator_id AS CHAR)=?
                 )
               )
             ORDER BY q.created_at DESC,q.id DESC"
        );
        $q->execute([
            $module, $entityType, $entityId,
            $operatorId, $operatorId
        ]);

        $questions = $q->fetchAll(PDO::FETCH_ASSOC);

        if (!$questions) {
            return [];
        }

        $ids = array_map(
            static fn(array $question): int => (int)$question['id'],
            $questions
        );

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $messages = $this->db->prepare(
            "SELECT m.id,m.question_id,m.operator_id,m.message,m.created_at,
                    COALESCE(
                      NULLIF(p.display_name, ''),
                      NULLIF(a.username, ''),
                      m.operator_id
                    ) AS author_name,
                    DATE_FORMAT(m.created_at, '%d-%m-%Y %H:%i') AS created_nl
             FROM office_question_messages m
             LEFT JOIN office_operator_people op
               ON op.operator_id = m.operator_id
             LEFT JOIN office_inzet_people p
               ON p.id = op.person_id
             LEFT JOIN office_operator_accounts a
               ON a.id = m.operator_id
             WHERE m.question_id IN ($placeholders)
             ORDER BY m.created_at ASC,m.id ASC"
        );
        $messages->execute($ids);

        $byQuestion = [];

        foreach ($messages->fetchAll(PDO::FETCH_ASSOC) as $message) {
            $byQuestion[(int)$message['question_id']][] = $message;
        }

        foreach ($questions as &$question) {
            $question['messages'] =
                $byQuestion[(int)$question['id']] ?? [];
        }
        unset($question);

        return $questions;
    }

    public function reply(
        string $operatorId,
        string $module,
        string $entityType,
        int $entityId,
        int $questionId,
        string $message
    ): void {
        $this->access->require($operatorId, $module, $entityType, $entityId, 'reply');
        $message = trim($message);

        if ($operatorId === '' || $entityId < 1 || $questionId < 1
            || $message === '' || mb_strlen($message) > 10000) {
            throw new InvalidArgumentException('Antwoord ongeldig');
        }

        $q = $this->db->prepare(
            "SELECT q.id FROM office_questions q
             WHERE q.id=? AND q.module=? AND q.entity_type=? AND q.entity_id=?
               AND q.status='open'
               AND (
                 CAST(q.created_by AS CHAR)=?
                 OR EXISTS (
                   SELECT 1 FROM office_question_recipients r
                   WHERE r.question_id=q.id
                     AND CAST(r.operator_id AS CHAR)=?
                 )
               )
             LIMIT 1"
        );
        $q->execute([
            $questionId, $module, $entityType, $entityId,
            $operatorId, $operatorId
        ]);

        if (!$q->fetchColumn()) {
            throw new DomainException('Vraag niet beschikbaar of afgesloten');
        }

        $q = $this->db->prepare(
            "INSERT INTO office_question_messages
             (question_id,operator_id,message)
             VALUES (?,?,?)"
        );
        $q->execute([$questionId, $operatorId, $message]);
    }

    public function hasBlockingQuestions(
        string $operatorId,
        string $module,
        string $entityType,
        int $entityId
    ): bool {
        $this->access->require($operatorId, $module, $entityType, $entityId, 'read');
        $q = $this->db->prepare(
            "SELECT 1 FROM office_questions
             WHERE module=? AND entity_type=? AND entity_id=?
               AND question_type='blocking_question'
               AND status='open'
             LIMIT 1"
        );
        $q->execute([$module, $entityType, $entityId]);

        return $q->fetchColumn() !== false;
    }
}
