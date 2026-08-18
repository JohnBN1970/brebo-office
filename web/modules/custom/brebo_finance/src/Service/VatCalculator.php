<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use Drupal\brebo_finance\ValueObject\VatBreakdown;
use InvalidArgumentException;

/**
 * Calculates VAT without binary floating-point arithmetic.
 */
final class VatCalculator {

  private const int MONEY_SCALE = 4;
  private const int RATE_SCALE = 4;
  private const int SCALE_FACTOR = 10_000;
  private const array SUPPORTED_RATES = ['0.0000', '9.0000', '21.0000'];

  /**
   * Calculates VAT over an amount excluding VAT.
   *
   * A reverse-charge line records zero invoice VAT. Non-deductible percentage
   * applies to purchase VAT and is stored separately so project cost and VAT
   * reporting remain auditable.
   */
  public function calculate(
    string $amountExVat,
    string $vatRate,
    bool $reverseCharge = FALSE,
    string $nonDeductiblePercentage = '0',
  ): VatBreakdown {
    $amountUnits = $this->parseDecimal($amountExVat, self::MONEY_SCALE, 'amountExVat');
    $rateUnits = $this->parseDecimal($vatRate, self::RATE_SCALE, 'vatRate');
    $nonDeductibleUnits = $this->parseDecimal($nonDeductiblePercentage, self::RATE_SCALE, 'nonDeductiblePercentage');

    $normalizedRate = $this->formatDecimal($rateUnits, self::RATE_SCALE);
    if (!in_array($normalizedRate, self::SUPPORTED_RATES, TRUE)) {
      throw new InvalidArgumentException('Supported VAT rates are 0%, 9% and 21%.');
    }
    if ($nonDeductibleUnits < 0 || $nonDeductibleUnits > 100 * self::SCALE_FACTOR) {
      throw new InvalidArgumentException('Non-deductible VAT percentage must be between 0 and 100.');
    }

    $vatUnits = $reverseCharge
      ? 0
      : $this->roundDivide($amountUnits * $rateUnits, 100 * self::SCALE_FACTOR);
    $nonDeductibleVatUnits = $this->roundDivide(
      $vatUnits * $nonDeductibleUnits,
      100 * self::SCALE_FACTOR,
    );
    $deductibleVatUnits = $vatUnits - $nonDeductibleVatUnits;

    return new VatBreakdown(
      $this->formatDecimal($amountUnits, self::MONEY_SCALE),
      $normalizedRate,
      $this->formatDecimal($vatUnits, self::MONEY_SCALE),
      $this->formatDecimal($amountUnits + $vatUnits, self::MONEY_SCALE),
      $reverseCharge,
      $this->formatDecimal($deductibleVatUnits, self::MONEY_SCALE),
      $this->formatDecimal($nonDeductibleVatUnits, self::MONEY_SCALE),
    );
  }

  /**
   * Multiplies two four-decimal values using half-up rounding.
   */
  public function multiply(string $left, string $right): string {
    $leftUnits = $this->parseDecimal($left, self::MONEY_SCALE, 'left');
    $rightUnits = $this->parseDecimal($right, self::MONEY_SCALE, 'right');
    return $this->formatDecimal(
      $this->roundDivide($leftUnits * $rightUnits, self::SCALE_FACTOR),
      self::MONEY_SCALE,
    );
  }

  /**
   * Compares two four-decimal values.
   */
  public function compare(string $left, string $right): int {
    return $this->parseDecimal($left, self::MONEY_SCALE, 'left')
      <=> $this->parseDecimal($right, self::MONEY_SCALE, 'right');
  }

  /**
   * Adds two four-decimal values.
   */
  public function add(string $left, string $right): string {
    return $this->formatDecimal(
      $this->parseDecimal($left, self::MONEY_SCALE, 'left')
      + $this->parseDecimal($right, self::MONEY_SCALE, 'right'),
      self::MONEY_SCALE,
    );
  }

  /**
   * Subtracts two four-decimal values.
   */
  public function subtract(string $left, string $right): string {
    return $this->formatDecimal(
      $this->parseDecimal($left, self::MONEY_SCALE, 'left')
      - $this->parseDecimal($right, self::MONEY_SCALE, 'right'),
      self::MONEY_SCALE,
    );
  }

  /**
   * Parses a signed decimal into a scaled integer.
   */
  private function parseDecimal(string $value, int $scale, string $field): int {
    $value = trim(str_replace(',', '.', $value));
    if (!preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/', $value, $matches)) {
      throw new InvalidArgumentException("$field must be a decimal number.");
    }

    $fraction = $matches[3] ?? '';
    if (strlen($fraction) > $scale) {
      throw new InvalidArgumentException("$field supports at most $scale decimal places.");
    }

    $factor = 10 ** $scale;
    $units = ((int) $matches[2] * $factor)
      + (int) str_pad($fraction, $scale, '0');
    return ($matches[1] ?? '') === '-' ? -$units : $units;
  }

  /**
   * Formats a scaled integer as a fixed decimal string.
   */
  private function formatDecimal(int $units, int $scale): string {
    $factor = 10 ** $scale;
    $sign = $units < 0 ? '-' : '';
    $absolute = abs($units);
    return sprintf('%s%d.%0' . $scale . 'd', $sign, intdiv($absolute, $factor), $absolute % $factor);
  }

  /**
   * Divides integers using commercial half-up rounding.
   */
  private function roundDivide(int $numerator, int $denominator): int {
    $sign = $numerator < 0 ? -1 : 1;
    return $sign * intdiv(abs($numerator) + intdiv($denominator, 2), $denominator);
  }

}
