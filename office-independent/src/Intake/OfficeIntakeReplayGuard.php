<?php
declare(strict_types=1);

namespace Brebo\Office\Intake;

use PDO;

/**
 * Single-use signed message nonce reservation.
 * Call only AFTER successful HMAC authentication, in an authorized endpoint.
 * A duplicate signed message must be rejected even inside the 5-minute window.
 */
final class OfficeIntakeReplayGuard
{
    public function __construct(private readonly PDO $db)
    {
    }

    public static function schema(): string
    {
        return 'CREATE TABLE IF NOT EXISTS office_intake_signed_nonces ('
            . 'source VARCHAR(32) NOT NULL,'
            . 'nonce CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,'
            . 'received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,'
            . 'PRIMARY KEY (source, nonce),'
            . 'KEY idx_received_at (received_at)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    }

    public function reserve(string $source, string $nonce): void
    {
        if (!preg_match('/^[a-z][a-z0-9_-]{1,31}$/D', $source)
            || !preg_match('/^[a-f0-9]{64}$/D', $nonce)) {
            throw new \InvalidArgumentException('invalid_intake_nonce');
        }
        try {
            $statement = $this->db->prepare(
                'INSERT INTO office_intake_signed_nonces (source, nonce) VALUES (?, ?)'
            );
            $statement->execute([$source, $nonce]);
        } catch (\PDOException $error) {
            if ($error->getCode() === '23000') {
                throw new \DomainException('intake_message_replayed', 0, $error);
            }
            throw $error;
        }
    }
}
