<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Documents/OfficeSourceAddressExtractor.php';

use Brebo\Office\Documents\OfficeSourceAddressExtractor;

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$extractor = new OfficeSourceAddressExtractor();
$tmp = tempnam(sys_get_temp_dir(), 'office-address-');
try {
    file_put_contents($tmp, "Adres;Postcode;Plaats;Naam;Email\nKade 1;1234 AB;Gouda;J. Jansen;j@example.nl\nKade 2;1234AB;Gouda;J. Jansen;\nKade 3;;Gouda;B. Visser;\n");
    $one = $extractor->extract($tmp, 'text/csv', 'bewoners.csv');
    check(count($one['rows']) === 2, 'multiple_addresses_or_missing_fields');
    check($one['rows'][0]['data']['address'] === 'Kade 1', 'first_address');
    check($one['rows'][1]['data']['name'] === 'J. Jansen', 'duplicate_names_must_remain_separate');
    check(count($one['issues']) === 1 && $one['issues'][0]['reason'] === 'incomplete_address', 'incomplete_review');
    file_put_contents($tmp, "From: test@example.nl\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nKade 4, 1234 AB, Gouda");
    $two = $extractor->extract($tmp, 'message/rfc822', 'bericht.eml');
    check(count($two['rows']) === 1 && $two['rows'][0]['data']['city'] === 'Gouda', 'email_inline');
    file_put_contents($tmp, "MIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=x\r\n\r\n--x\r\nbinary");
    $three = $extractor->extract($tmp, 'message/rfc822', 'bericht.eml');
    check(count($three['rows']) === 0 && $three['issues'][0]['reason'] === 'multipart_email_requires_review', 'multipart_fail_closed');
    if (class_exists(ZipArchive::class)) {
        $zip = new ZipArchive();
        check($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'xlsx_fixture_open');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Adres</t></is></c><c r="B1" t="inlineStr"><is><t>Postcode</t></is></c><c r="C1" t="inlineStr"><is><t>Plaats</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>Haven 7</t></is></c><c r="B2" t="inlineStr"><is><t>2345CD</t></is></c><c r="C2" t="inlineStr"><is><t>Utrecht</t></is></c></row></sheetData></worksheet>');
        $zip->addFromString('xl/worksheets/sheet2.xml', '<worksheet><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Naam</t></is></c><c r="B1" t="inlineStr"><is><t>Notitie</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>Niet een adres</t></is></c><c r="B2" t="inlineStr"><is><t>1234 AB</t></is></c></row></sheetData></worksheet>');
        $zip->close();
        $four = $extractor->extract($tmp, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'adressen.xlsx');
        check(count($four['rows']) === 1 && $four['rows'][0]['data']['address'] === 'Haven 7', 'xlsx_sheet_headers_isolated');
    }
} finally {
    @unlink($tmp);
}
echo "OFFICE_SOURCE_ADDRESS_EXTRACTOR_OK\n";
