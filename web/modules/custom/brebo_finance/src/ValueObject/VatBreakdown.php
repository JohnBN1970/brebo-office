<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\ValueObject;

/**
 * Immutable VAT calculation result, represented in decimal strings.
 */
final readonly class VatBreakdown {

  public function __construct(
    public string $amountExVat,
    public string $vatRate,
    public string $vatAmount,
    public string $amountIncVat,
    public bool $reverseCharge,
    public string $deductibleVatAmount,
    public string $nonDeductibleVatAmount,
  ) {}

  /**
   * Returns values suitable for persistence or API serialization.
   */
  public function toArray(): array {
    return [
      'amount_ex_vat' => $this->amountExVat,
      'vat_rate' => $this->vatRate,
      'vat_amount' => $this->vatAmount,
      'amount_inc_vat' => $this->amountIncVat,
      'vat_reverse_charge' => $this->reverseCharge,
      'deductible_vat_amount' => $this->deductibleVatAmount,
      'non_deductible_vat_amount' => $this->nonDeductibleVatAmount,
    ];
  }

}
