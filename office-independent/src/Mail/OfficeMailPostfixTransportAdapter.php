<?php
declare(strict_types=1);

namespace Brebo\Office\Mail;

use Brebo\MailGateway\Contract\SmtpTransportInterface;
use RuntimeException;

/**
 * Connect Office outbound dispatch to the existing gateway transport.
 * Transport construction/configuration remains with the server bootstrap.
 * This adapter never modifies the local gateway implementation.
 */
final class OfficeMailPostfixTransportAdapter {
  public function __construct(private readonly SmtpTransportInterface $transport) {}

  /** @param array{from:string,to:string,subject:string,text:string} $message */
  public function send(array $message): string {
    if (!OfficeMailOutgoingPolicy::enabled()) {
      throw new RuntimeException('Office uitgaande e-mail is uitgeschakeld.');
    }
    $health = $this->transport->health();
    if (($health['available'] ?? false) !== true) {
      throw new RuntimeException('Postfix transport is niet beschikbaar.');
    }
    foreach (['from','to','subject','text'] as $field) {
      if (!isset($message[$field]) || !is_string($message[$field])) {
        throw new RuntimeException('Ongeldig uitgaand bericht.');
      }
    }
    $draft = OfficeMailOutgoingPolicy::validateDraft(
      $message['to'], $message['subject'], $message['text']
    );
    if (!filter_var($message['from'], FILTER_VALIDATE_EMAIL) ||
        preg_match('/[\r\n\x00-\x1F\x7F]/', $message['from'])) {
      throw new RuntimeException('Ongeldig afzendadres.');
    }
    $reference = $this->transport->send([
      'from'=>$message['from'],
      'to'=>$draft['recipient'],
      'subject'=>$draft['subject'],
      'text'=>$draft['body'],
    ]);
    if ($reference === '') throw new RuntimeException('Transportbevestiging ontbreekt.');
    return $reference;
  }
}
