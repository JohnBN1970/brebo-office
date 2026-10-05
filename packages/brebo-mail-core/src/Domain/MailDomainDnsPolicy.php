<?php

declare(strict_types=1);

namespace Brebo\Mail\Domain;

final class MailDomainDnsPolicy {

  /**
   * @param string[] $rootTxt
   * @param string[] $dmarcTxt
   * @param array<int,array{host:string,priority:int}> $mx
   */
  public function evaluate(
    string $verificationValue,
    array $rootTxt,
    array $dmarcTxt,
    array $mx,
    string $dkimStatus = 'unknown',
  ): DnsPolicyResult {
    $verified = in_array($verificationValue, $rootTxt, TRUE);

    $spfRecords = array_values(array_filter(
      $rootTxt,
      static fn(string $value): bool => preg_match('/^v=spf1(?:\s|$)/i', trim($value)) === 1,
    ));
    $dmarcRecords = array_values(array_filter(
      $dmarcTxt,
      static fn(string $value): bool => preg_match('/^v=dmarc1(?:;|\s|$)/i', trim($value)) === 1,
    ));

    return new DnsPolicyResult(
      $verified,
      [
        'mx_status' => $mx !== [] ? 'ok' : 'missing',
        'spf_status' => count($spfRecords) === 1 ? 'ok' : 'invalid',
        'dkim_status' => $dkimStatus,
        'dmarc_status' => count($dmarcRecords) === 1 ? 'ok' : 'invalid',
      ],
    );
  }
}
