<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/** Strict parser for an hours-review POST; no client-provided duration is trusted. */
final class OnSiteHoursReviewRequest {
  /** @return array{project_id:int,person_id:int,in_event_id:string,out_event_id:string,decision:string,corrected_seconds:?int,reason:?string,request_key:string} */
  public static function parse(array $input): array {
    $id = static function (mixed $value):int {
      if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,17}$/D', $value)) throw new \InvalidArgumentException('Ongeldig ID.');
      $parsed = filter_var($value, FILTER_VALIDATE_INT);
      if (!is_int($parsed)) throw new \InvalidArgumentException('Ongeldig ID.');
      return $parsed;
    };
    $event = static function (mixed $value):string {
      if (!is_string($value) || !preg_match('/^[a-zA-Z0-9_.:\\-]{1,100}$/D', $value)) throw new \InvalidArgumentException('Ongeldig event-ID.');
      return $value;
    };
    $decision = $input['decision'] ?? null;
    if (!in_array($decision, ['approve','reject'], true)) throw new \InvalidArgumentException('Ongeldige beslissing.');
    $seconds = null;
    if (isset($input['corrected_seconds']) && $input['corrected_seconds'] !== '') {
      $raw = $input['corrected_seconds'];
      if (!is_string($raw) || !preg_match('/^(0|[1-9][0-9]{0,5})$/D', $raw)) throw new \InvalidArgumentException('Ongeldige urencorrectie.');
      $seconds = (int)$raw;
      if ($seconds > 86400) throw new \InvalidArgumentException('Correctie vereist handmatige controle.');
    }
    if ($decision === 'reject' && $seconds !== null) throw new \InvalidArgumentException('Afwijzing mag geen geaccepteerde uren bevatten.');
    $reason = $input['reason'] ?? '';
    if (!is_string($reason) || strlen($reason) > 2000) throw new \InvalidArgumentException('Ongeldige toelichting.');
    $reason = trim($reason);
    $requestKey = $input['request_key'] ?? null;
    if (!is_string($requestKey) || !preg_match('/^[a-f0-9]{64}$/D', $requestKey)) throw new \InvalidArgumentException('Ongeldige verzoekcode.');
    return [
      'project_id'=>$id($input['project_id'] ?? null),
      'person_id'=>$id($input['person_id'] ?? null),
      'in_event_id'=>$event($input['in_event_id'] ?? null),
      'out_event_id'=>$event($input['out_event_id'] ?? null),
      'decision'=>$decision,
      'corrected_seconds'=>$seconds,
      'reason'=>$reason !== '' ? $reason : null,
      'request_key'=>$requestKey,
    ];
  }
}
