<?php

declare(strict_types=1);

namespace Drupal\brebo_calculation\Domain;

/** Lifecycle of a calculation version. */
enum CalculationStatus: string {
  case Draft = 'draft';
  case InReview = 'in_review';
  case Established = 'established';
  case FinalBudget = 'final_budget';
  case Superseded = 'superseded';

  public function isLocked(): bool {
    return match ($this) {
      self::Established, self::FinalBudget, self::Superseded => TRUE,
      self::Draft, self::InReview => FALSE,
    };
  }

  public function mayCreateWorkBudget(): bool {
    return match ($this) {
      self::Established, self::FinalBudget => TRUE,
      default => FALSE,
    };
  }
}
