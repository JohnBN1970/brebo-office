<?php
declare(strict_types=1);

namespace Brebo\Office\Mail;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Controlled delivery orchestrator. Does not instantiate or configure Postfix.
 * Transport callback is injected server-side and must never come from request data.
 */
final class OfficeMailOutboundDispatchService {
  public function __construct(
    private readonly PDO $db,
    private readonly OfficeMailOutboundDeliveryRepository $ledger
  ) {}

  /**
   * @param callable(array):string $transportSend
   * @return array{delivery_id:int,status:string,reference:string}
   */
  public function dispatch(
    string $operatorId,
    int $mailboxId,
    string $recipient,
    string $subject,
    string $body,
    string $idempotencyKey,
    callable $transportSend
  ): array {
    $preflight = OfficeMailSendPreflight::check(
      $this->db, $operatorId, $mailboxId, $recipient, $subject, $body
    );
    // The outgoing flag is checked again immediately before transport invocation.
    $draft = OfficeMailOutgoingPolicy::validateDraft($recipient, $subject, $body);
    // Fail before reserving a delivery when the feature has been disabled.
    // A reserved record must represent a real transport attempt or an uncertain outcome.
    if (!OfficeMailOutgoingPolicy::enabled()) {
      throw new RuntimeException('Uitgaande Office e-mail staat uit.');
    }
    $deliveryId = $this->ledger->reserve($idempotencyKey, $mailboxId, $operatorId, $draft);
    try {
      if (!OfficeMailOutgoingPolicy::enabled()) {
        throw new RuntimeException('Uitgaande Office e-mail staat uit.');
      }
      $reference = $transportSend([
        'from'=>$preflight['from'],
        'to'=>$preflight['to'],
        'subject'=>$preflight['subject'],
        'text'=>$preflight['text'],
      ]);
      if (!is_string($reference) || $reference === '') {
        throw new RuntimeException('Transport gaf geen verzendbevestiging.');
      }
      $this->ledger->markAccepted($deliveryId, $reference);
      return ['delivery_id'=>$deliveryId, 'status'=>'accepted', 'reference'=>$reference];
    } catch (Throwable $e) {
      // Do not retry automatically: transport acceptance may be unknown.
      try {
        $this->ledger->markUnknown($deliveryId);
      } catch (Throwable $ledgerError) {
        error_log('office_mail_outbound_unknown_status_failed: '.$ledgerError::class);
      }
      throw $e;
    }
  }
}
