<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

final class OfficeOutgoingInvoiceDraft {
  public static function validate(array $input, array $activeVatRegimes = []): array {
    if (!is_string($input['customer_name'] ?? null)) throw new \InvalidArgumentException('Invalid customer name');
    $customer = trim($input['customer_name']);
    $projectId = filter_var($input['office_project_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $lines = $input['lines'] ?? null;
    if ($customer === '' || strlen($customer) > 160 || !$projectId || !is_array($lines) || count($lines) < 1 || count($lines) > 100) {
      throw new \InvalidArgumentException('Invalid invoice draft');
    }
    if ($activeVatRegimes === []) throw new \DomainException('Active VAT regimes unavailable');
    $normalized = [];
    foreach ($lines as $line) {
      if (!is_array($line)) throw new \InvalidArgumentException('Invalid line');
      if (!is_string($line['description'] ?? null)) throw new \InvalidArgumentException('Invalid description');
      $description = trim($line['description']);
      $quantity = $line['quantity'] ?? null;
      $price = $line['unit_price'] ?? null;
      $vat = $line['vat_regime_id'] ?? null;
      if ($description === '' || strlen($description) > 240 || !is_string($quantity) || !is_string($price) || !is_string($vat)
        || !preg_match('/^[0-9]{1,10}(?:[.][0-9]{1,3})?$/D', $quantity)
        || !preg_match('/^[0-9]{1,10}(?:[.][0-9]{1,2})?$/D', $price)
        || (float)$quantity <= 0 || (float)$price < 0
        || !preg_match('/^[A-Za-z0-9_-]{1,80}$/D', $vat)
        || !in_array($vat, $activeVatRegimes, true)) throw new \InvalidArgumentException('Invalid invoice line');
      $normalized[] = ['description'=>$description,'quantity'=>$quantity,'unit_price'=>$price,'vat_regime_id'=>$vat];
    }
    return ['customer_name'=>$customer,'office_project_id'=>(int)$projectId,'lines'=>$normalized,'status'=>'draft'];
  }
}
