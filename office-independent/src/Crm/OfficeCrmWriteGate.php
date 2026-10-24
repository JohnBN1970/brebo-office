<?php
declare(strict_types=1);
namespace Brebo\Office\Crm;

use Brebo\Office\Intake\WebsiteCrmOperatorContext;
use DomainException;
use InvalidArgumentException;

/**
 * Defense-in-depth gate for future CRM POST controllers.
 * Only a verified server session context can authorize writes.
 */
final class OfficeCrmWriteGate {
  public static function authorize(
    WebsiteCrmOperatorContext $operator,
    string $method,
    string $host,
    string $allowedHost,
    string $origin,
    string $csrfToken,
    string $csrfSecret,
    string $action
  ): void {
    $operator->requireCrmEdit();
    if ($method !== 'POST' || $host === '' || $allowedHost === ''
      || !hash_equals($allowedHost, $host)
      || !hash_equals('https://' . $host, $origin)) {
      throw new DomainException('CRM request origin or method denied');
    }
    if (strlen($csrfSecret) < 32 || $csrfToken === '' || !hash_equals(
      self::token($operator, $csrfSecret, $action), $csrfToken
    )) {
      throw new DomainException('CRM CSRF validation failed');
    }
  }

  public static function token(WebsiteCrmOperatorContext $operator, string $secret, string $action): string {
    if (strlen($secret) < 32 || $action === '' || strlen($action) > 128) {
      throw new InvalidArgumentException('Invalid CRM CSRF configuration');
    }
    return hash_hmac('sha256', 'office-crm-v1|' . $operator->sessionId . '|' . $operator->operatorId . '|' . $action, $secret);
  }
}
