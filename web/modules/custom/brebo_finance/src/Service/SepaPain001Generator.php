<?php

declare(strict_types=1);

namespace Drupal\brebo_finance\Service;

use DOMDocument;
use Drupal\Core\Site\Settings;
use RuntimeException;

/** Generates a SEPA pain.001 fallback from one sealed BREBO payment batch. */
final class SepaPain001Generator {

  public function __construct(private readonly PaymentBatchManager $batchManager) {}

  /** @return array{filename:string,xml:string,sha256:string,message_id:string,control_sum:string,item_count:int} */
  public function generate(int $batchId): array {
    $sealed = $this->batchManager->sealedPayload($batchId);
    $batch = $sealed['batch'];
    $items = $sealed['items'];
    if ($items === []) {
      throw new RuntimeException('A sealed payment batch contains no instructions.');
    }

    $debtorName = trim((string) Settings::get('brebo_bank_account_name', 'BREBO Bouw en Advies BV'));
    $debtorIban = $this->normaliseIban((string) Settings::get('brebo_bank_iban', ''));
    $debtorBic = strtoupper(trim((string) Settings::get('brebo_bank_bic', '')));
    if ($debtorName === '' || !$this->validIban($debtorIban)) {
      throw new RuntimeException('BREBO bankrekeningconfiguratie is onvolledig; SEPA-export is geblokkeerd.');
    }

    $messageId = substr((string) $batch['batch_number'], 0, 35);
    $controlSum = $this->controlSum($items);
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->formatOutput = TRUE;

    $root = $document->createElementNS('urn:iso:std:iso:20022:tech:xsd:pain.001.001.03', 'Document');
    $document->appendChild($root);
    $initiation = $document->createElement('CstmrCdtTrfInitn');
    $root->appendChild($initiation);

    $groupHeader = $document->createElement('GrpHdr');
    $initiation->appendChild($groupHeader);
    $this->text($document, $groupHeader, 'MsgId', $messageId);
    $this->text($document, $groupHeader, 'CreDtTm', gmdate('Y-m-d\TH:i:s\Z'));
    $this->text($document, $groupHeader, 'NbOfTxs', (string) count($items));
    $this->text($document, $groupHeader, 'CtrlSum', $controlSum);
    $initiatingParty = $document->createElement('InitgPty');
    $groupHeader->appendChild($initiatingParty);
    $this->text($document, $initiatingParty, 'Nm', $debtorName);

    $payment = $document->createElement('PmtInf');
    $initiation->appendChild($payment);
    $this->text($document, $payment, 'PmtInfId', substr('PMT-' . $messageId, 0, 35));
    $this->text($document, $payment, 'PmtMtd', 'TRF');
    $this->text($document, $payment, 'BtchBookg', 'true');
    $this->text($document, $payment, 'NbOfTxs', (string) count($items));
    $this->text($document, $payment, 'CtrlSum', $controlSum);
    $paymentType = $document->createElement('PmtTpInf');
    $payment->appendChild($paymentType);
    $serviceLevel = $document->createElement('SvcLvl');
    $paymentType->appendChild($serviceLevel);
    $this->text($document, $serviceLevel, 'Cd', 'SEPA');
    $this->text($document, $payment, 'ReqdExctnDt', (string) $batch['execution_date']);

    $debtor = $document->createElement('Dbtr');
    $payment->appendChild($debtor);
    $this->text($document, $debtor, 'Nm', $debtorName);
    $debtorAccount = $document->createElement('DbtrAcct');
    $payment->appendChild($debtorAccount);
    $debtorAccountId = $document->createElement('Id');
    $debtorAccount->appendChild($debtorAccountId);
    $this->text($document, $debtorAccountId, 'IBAN', $debtorIban);
    $debtorAgent = $document->createElement('DbtrAgt');
    $payment->appendChild($debtorAgent);
    $debtorFinancial = $document->createElement('FinInstnId');
    $debtorAgent->appendChild($debtorFinancial);
    if ($debtorBic !== '') {
      $this->text($document, $debtorFinancial, 'BIC', $debtorBic);
    }
    else {
      $other = $document->createElement('Othr');
      $debtorFinancial->appendChild($other);
      $this->text($document, $other, 'Id', 'NOTPROVIDED');
    }
    $this->text($document, $payment, 'ChrgBr', 'SLEV');

    foreach ($items as $item) {
      if ((string) $item['currency'] !== 'EUR') {
        throw new RuntimeException('SEPA fallback only supports EUR instructions.');
      }
      $creditorIban = $this->normaliseIban((string) $item['creditor_iban']);
      if (!$this->validIban($creditorIban)) {
        throw new RuntimeException('A sealed instruction contains an invalid creditor IBAN.');
      }

      $transfer = $document->createElement('CdtTrfTxInf');
      $payment->appendChild($transfer);
      $paymentId = $document->createElement('PmtId');
      $transfer->appendChild($paymentId);
      $this->text($document, $paymentId, 'EndToEndId', (string) $item['end_to_end_id']);
      $amount = $document->createElement('Amt');
      $transfer->appendChild($amount);
      $instructed = $document->createElement('InstdAmt', number_format((float) $item['amount'], 2, '.', ''));
      $instructed->setAttribute('Ccy', 'EUR');
      $amount->appendChild($instructed);

      $creditorBic = strtoupper(trim((string) ($item['creditor_bic'] ?? '')));
      if ($creditorBic !== '') {
        $creditorAgent = $document->createElement('CdtrAgt');
        $transfer->appendChild($creditorAgent);
        $creditorFinancial = $document->createElement('FinInstnId');
        $creditorAgent->appendChild($creditorFinancial);
        $this->text($document, $creditorFinancial, 'BIC', $creditorBic);
      }
      $creditor = $document->createElement('Cdtr');
      $transfer->appendChild($creditor);
      $this->text($document, $creditor, 'Nm', (string) $item['creditor_name']);
      $creditorAccount = $document->createElement('CdtrAcct');
      $transfer->appendChild($creditorAccount);
      $creditorAccountId = $document->createElement('Id');
      $creditorAccount->appendChild($creditorAccountId);
      $this->text($document, $creditorAccountId, 'IBAN', $creditorIban);
      $remittance = $document->createElement('RmtInf');
      $transfer->appendChild($remittance);
      $this->text($document, $remittance, 'Ustrd', (string) $item['remittance_information']);
    }

    $xml = $document->saveXML();
    if (!is_string($xml) || $xml === '') {
      throw new RuntimeException('SEPA XML generation failed.');
    }

    return [
      'filename' => preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $batch['batch_number']) . '.xml',
      'xml' => $xml,
      'sha256' => hash('sha256', $xml),
      'message_id' => $messageId,
      'control_sum' => $controlSum,
      'item_count' => count($items),
    ];
  }

  private function text(DOMDocument $document, \DOMElement $parent, string $name, string $value): void {
    $parent->appendChild($document->createElement($name, htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8')));
  }

  private function controlSum(array $items): string {
    $cents = 0;
    foreach ($items as $item) {
      $amount = (string) $item['amount'];
      if (!preg_match('/^-?\d+(?:\.\d{1,4})?$/', $amount)) {
        throw new RuntimeException('Invalid monetary amount in sealed payment instruction.');
      }
      $cents += (int) round(((float) $amount) * 100);
    }
    return number_format($cents / 100, 2, '.', '');
  }

  private function normaliseIban(string $iban): string {
    return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $iban));
  }

  private function validIban(string $iban): bool {
    if (!preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban)) {
      return FALSE;
    }
    $rearranged = substr($iban, 4) . substr($iban, 0, 4);
    $numeric = '';
    foreach (str_split($rearranged) as $character) {
      $numeric .= ctype_alpha($character) ? (string) (ord($character) - 55) : $character;
    }
    $remainder = 0;
    foreach (str_split($numeric) as $digit) {
      $remainder = (($remainder * 10) + (int) $digit) % 97;
    }
    return $remainder === 1;
  }

}
