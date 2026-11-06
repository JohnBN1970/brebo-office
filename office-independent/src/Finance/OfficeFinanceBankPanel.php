<?php
declare(strict_types=1);
namespace Brebo\Office\Finance;

/** Safe read-only bank panel: no inferred balances or confirmed payments. */
final class OfficeFinanceBankPanel {
  private static function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
  private static function amount(string $amount): string {
    if (!preg_match('/^-?\\d{1,30}(?:\\.\\d{1,4})?$/D', $amount)) throw new \InvalidArgumentException('Invalid booked amount');
    $negative=str_starts_with($amount,'-');
    $parts=explode('.', $negative ? substr($amount,1) : $amount, 2);
    return ($negative ? '-' : '').preg_replace('/\\B(?=(\\d{3})+(?!\\d))/', '.', $parts[0]).','.str_pad($parts[1]??'',2,'0');
  }
  public static function render(?array $snapshot): string {
    $html = '<section aria-label="Bankgegevens"><h2>Bankgegevens · Moneybird</h2>';
    if ($snapshot === null) {
      return $html . '<p>Bankgegevens nog niet verbonden. Geen saldo beschikbaar.</p></section>';
    }
    if (($snapshot['provider'] ?? null) !== 'moneybird' || ($snapshot['balances_available'] ?? null) !== false
        || ($snapshot['period'] ?? null) !== 'this_month') {
      throw new \InvalidArgumentException('Unverified bank snapshot');
    }
    $booked=$snapshot['booked_balances']??null;
    $balances=[];
    if ($booked!==null) {
      if (!is_array($booked) || ($booked['source']??'')!=='moneybird_bank_balance_report' || ($booked['period']??'')!=='this_month'
          || !is_array($booked['accounts']??null) || !is_string($booked['retrieved_at']??null)
          || !preg_match('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}\\.\\d{3}Z$/D',$booked['retrieved_at'])) throw new \InvalidArgumentException('Unverified booked balance report');
      foreach ($booked['accounts'] as $row) {
        if (!is_array($row) || !is_string($row['financial_account_id']??null) || isset($balances[$row['financial_account_id']]) || !is_string($row['amount']??null)) throw new \InvalidArgumentException('Invalid booked balance row');
        $balances[$row['financial_account_id']]=self::amount($row['amount']);
      }
      $html .= '<p>Boekhoudkundig eindsaldo van deze maand volgens Moneybird. Geen door de bank bevestigd actueel of beschikbaar saldo.</p>';
      $html .= '<p>Rapport opgevraagd: '.self::e((new \DateTimeImmutable($booked['retrieved_at']))->setTimezone(new \DateTimeZone('Europe/Amsterdam'))->format('d-m-Y H:i T')).'.</p>';
    }
    $html .= '<p class="muted">Alleen lezen · Saldi niet geverifieerd bij de bank · Geen automatische betalingsbevestiging.</p>';
    $html .= '<p>Bankmutaties: deze maand volgens Moneybird. Geen volledig historisch overzicht.</p>';
    $html .= '<h3>Bankrekeningen</h3><div class="table"><table><thead><tr><th>Rekening</th><th>Identificatie</th><th>Valuta</th><th>Boekhoudkundig saldo Moneybird</th></tr></thead><tbody>';
    foreach (($snapshot['accounts'] ?? []) as $account) {
      $html .= '<tr><td>'.self::e($account['name'] ?? '').'</td><td>'.self::e($account['identifier'] ?? '').'</td><td>'.self::e($account['currency'] ?? '').'</td><td>'.self::e($balances[$account['id']??''] ?? ($booked===null ? 'Niet beschikbaar' : 'Niet aangeleverd')).'</td></tr>';
    }
    $html .= '</tbody></table></div><h3>Bankmutaties</h3><div class="table"><table><thead><tr><th>Datum</th><th>Omschrijving</th><th>Bedrag</th><th>Controle</th></tr></thead><tbody>';
    foreach (array_slice($snapshot['transactions'] ?? [], 0, 100) as $transaction) {
      $html .= '<tr><td>'.self::e($transaction['booking_date'] ?? '').'</td><td>'.self::e($transaction['description'] ?? '').'</td><td>'.self::e($transaction['amount'] ?? '').' '.self::e($transaction['currency'] ?? '').'</td><td>Niet geverifieerd</td></tr>';
    }
    return $html . '</tbody></table></div></section>';
  }
}

