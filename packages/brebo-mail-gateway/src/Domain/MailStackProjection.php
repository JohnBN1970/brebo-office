<?php

declare(strict_types=1);

namespace Brebo\MailGateway\Domain;

final readonly class MailStackProjection {

  /** @param array<int,string> $domains
   *  @param array<int,string> $mailboxes
   *  @param array<string,string> $aliases
   *  @param array<string,array{selector:string,private_key_reference:string}> $dkim
   */
  public function __construct(
    public array $domains,
    public array $mailboxes,
    public array $aliases,
    public array $dkim,
  ) {}
}
