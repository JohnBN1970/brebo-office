<?php

declare(strict_types=1);

namespace Drupal\brebo_mail_intake\Infrastructure;

use Drupal\brebo_mail_intake\Contract\OutboundMailRuntimeInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;

final class DrupalOutboundMailRuntime implements OutboundMailRuntimeInterface {

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly MailManagerInterface $mailManager,
    private readonly TimeInterface $time,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  public function currentUserId(): int {
    return (int) $this->currentUser->id();
  }

  public function canSend(): bool {
    return $this->currentUser->hasPermission('send brebo outbound mail');
  }

  public function transportEnabled(): bool {
    $runtimeEnabled = filter_var(getenv('BREBO_SMTP_ENABLED') ?: '0', FILTER_VALIDATE_BOOL);
    $smtpEnabled = (bool) $this->configFactory->get('smtp.settings')->get('smtp_on');
    return $runtimeEnabled && $smtpEnabled;
  }

  public function sanitizeHtml(string $html, array $allowedTags): string {
    return Xss::filter($html, $allowedTags);
  }

  public function send(string $to, array $params, string $from): bool {
    $result = $this->mailManager->mail(
      'brebo_mail_intake',
      'outbound',
      $to,
      'nl',
      $params,
      $from,
      TRUE,
    );
    return !empty($result['result']);
  }

  public function requestTime(): int {
    return $this->time->getRequestTime();
  }

}
