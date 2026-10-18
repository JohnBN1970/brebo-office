<?php

declare(strict_types=1);

namespace Brebo\Office\Intake;

/**
 * Office URL/cookie namespace. Production defaults remain unchanged.
 * The test mount must be explicitly enabled by trusted server-side config.
 */
final class OfficeMount {
  public static function basePath(): string {
    $path = getenv('OFFICE_BASE_PATH');
    if ($path === false || $path === '') {
      return '/office';
    }
    if (!in_array($path, ['/office', '/office-test'], true)) {
      throw new \RuntimeException('Unsupported Office mount.');
    }
    return $path;
  }

  public static function isTest(): bool {
    return self::basePath() === '/office-test';
  }

  public static function cookieName(string $kind): string {
    if (!in_array($kind, ['session', 'login_csrf'], true)) {
      throw new \InvalidArgumentException('Unknown cookie kind.');
    }
    $prefix = self::isTest() ? 'office_test_' : 'office_';
    return $prefix . $kind;
  }

  public static function cookiePath(string $kind): string {
    if (!in_array($kind, ['session', 'login_csrf'], true)) {
      throw new \InvalidArgumentException('Unknown cookie kind.');
    }
    if (self::isTest()) {
      return '/office-test';
    }
    return $kind === 'session' ? '/' : '/office';
  }

  public static function route(string $name, array $query = []): string {
    $paths = [
      'home' => '/',
      'inloggen' => '/inloggen',
      'uitloggen' => '/uitloggen',
      'crm/aanvragen' => '/crm/aanvragen',
      'crm/aanvragen/detail' => '/crm/aanvragen/detail',
      'calc-werkbank' => '/calc-werkbank',
      'projecten/detail' => '/projecten/detail',
      'inzet/importcontrole' => '/inzet/importcontrole',
    ];
    if (!isset($paths[$name])) {
      throw new \InvalidArgumentException('Unknown Office route.');
    }
    $url = self::basePath() . $paths[$name];
    if ($query !== []) {
      $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
    return $url;
  }
}
