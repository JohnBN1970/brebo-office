<?php

declare(strict_types=1);

namespace Brebo\Office\RecentWork;

/**
 * Builds a local Office route only for already-authorized recent-work events.
 * No external URL, token or arbitrary path may be supplied by persisted rows.
 * Modules without a verified destination deliberately have no resume link.
 */
final class RecentWorkResumeLinks {
  public static function forEvent(RecentWorkEvent $event, bool $queryRouter): ?string {
    // Module-specific route and object authorization must be integrated first.
    return null;
  }
}
