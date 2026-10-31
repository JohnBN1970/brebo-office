<?php

declare(strict_types=1);

namespace Brebo\Office\Communication;

use InvalidArgumentException;
use PDO;
use Throwable;
use RuntimeException;

/**
 * Human-only review transition; never automatically links a CRM or Finance record.
 * Caller must verify the authenticated operator has Communication review permission.
 */
final class OfficeCommunicationReviewService
{
    public function __construct(private readonly PDO $pdo)
    {
        if ($pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
            throw new InvalidArgumentException('PDO must use ERRMODE_EXCEPTION.');
        }
    }

    public function decide(int $messageId, string $operatorId, string $decision): void
    {
        if ($messageId < 1 || trim($operatorId) === '' || strlen($operatorId) > 128) {
            throw new InvalidArgumentException('Invalid review identity.');
        }
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException('Invalid review decision.');
        }
        $nested = $this->pdo->inTransaction();
        if ($nested) {
            $this->pdo->exec('SAVEPOINT office_communication_review');
        } else {
            $this->pdo->beginTransaction();
        }
        try {
            $lockingSuffix = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                ? '' : ' FOR UPDATE';
            $select = $this->pdo->prepare(
                'SELECT review_status FROM office_communication_messages WHERE id = :id' . $lockingSuffix
            );
            $select->execute([':id' => $messageId]);
            $status = $select->fetchColumn();
            if ($status !== 'pending') {
                throw new RuntimeException('Message is not pending review.');
            }

            $update = $this->pdo->prepare(
                'UPDATE office_communication_messages SET review_status = :decision WHERE id = :id'
            );
            $update->execute([':decision' => $decision, ':id' => $messageId]);

            $event = $this->pdo->prepare(
                'INSERT INTO office_communication_review_events
                 (message_id, operator_id, event_type)
                 VALUES (:message_id, :operator_id, :event_type)'
            );
            $event->execute([
                ':message_id' => $messageId,
                ':operator_id' => trim($operatorId),
                ':event_type' => $decision,
            ]);
            if ($nested) {
                $this->pdo->exec('RELEASE SAVEPOINT office_communication_review');
            } else {
                $this->pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                if ($nested) {
                    $this->pdo->exec('ROLLBACK TO SAVEPOINT office_communication_review');
                } else {
                    $this->pdo->rollBack();
                }
            }
            throw $exception;
        }
    }
}
