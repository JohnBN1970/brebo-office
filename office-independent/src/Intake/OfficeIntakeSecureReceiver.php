<?php
declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Framework-independent authenticated internal receiver.
 * HTTPS, rate limiting, secret provisioning and request routing remain upstream.
 * A nonce is reserved after authentication and validation, before persistence.
 */
final class OfficeIntakeSecureReceiver
{
    public function __construct(
        private readonly OfficeIntakeSourceAuthenticator $authenticator,
        private readonly OfficeIntakeReplayGuard $replays,
        private readonly OfficeCanonicalIntakeBuildingHandoff $handoff
    ) {
    }

    /** @return array{request_id:int,building_resolution:string,building_id:?string,candidates:int} */
    public function receive(
        string $source,
        string $rawBody,
        string $timestamp,
        string $signature,
        string $nonce,
        int $now
    ): array {
        if (strlen($rawBody) > 8192 || $rawBody === '') {
            throw new \InvalidArgumentException('intake_payload_size_invalid');
        }
        $this->authenticator->verify($source, $rawBody, $timestamp, $signature, $now);
        if (!preg_match('/^[a-f0-9]{64}$/D', $nonce)) {
            throw new \InvalidArgumentException('invalid_intake_nonce');
        }
        try {
            $envelope = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new \InvalidArgumentException('invalid_intake_json', 0, $error);
        }
        if (!is_array($envelope) || array_is_list($envelope)) {
            throw new \InvalidArgumentException('invalid_intake_envelope');
        }
        if (($envelope['source'] ?? null) !== $source) {
            throw new \DomainException('intake_source_mismatch');
        }
        // Validate the canonical contract before consuming the one-time nonce.
        OfficeCanonicalIntakeAdapter::map($envelope);
        $this->replays->reserve($source, $nonce);
        return $this->handoff->register($envelope);
    }
}
