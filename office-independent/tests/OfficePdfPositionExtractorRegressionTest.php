<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/Finance/OfficeInvoiceLayout.php';
require_once dirname(__DIR__) . '/src/Finance/OfficePdfPositionExtractor.php';
use Brebo\Office\Finance\OfficePdfPositionExtractor;
$xml = '<?xml version="1.0"?><html><body><doc><page width="595" height="842"><flow><block><line>'
 . '<word xMin="51" yMin="327" xMax="99" yMax="339">PVC40101</word>'
 . '<word xMin="422.7" yMin="327" xMax="448" yMax="339">96,00</word>'
 . '<word xMin="452.4" yMin="327" xMax="468" yMax="339">m1</word>'
 . '<word xMin="492.7" yMin="327" xMax="514" yMax="339">2,23</word>'
 . '<word xMin="545.1" yMin="327" xMax="581" yMax="339">214,08</word>'
 . '</line></block></flow></page></doc></body></html>';
$blocks = OfficePdfPositionExtractor::fromBboxXml($xml);
if (count($blocks) !== 5 || $blocks[0]['text'] !== 'PVC40101'
  || abs($blocks[0]['x'] - 51/595) > 0.0001) throw new RuntimeException('BBox coordinates incorrect');
if (OfficePdfPositionExtractor::fromBboxXml('<!DOCTYPE a [<!ENTITY x SYSTEM "file:///etc/passwd">]><a>&x;</a>') !== [])
 throw new RuntimeException('Unexpected XML entity data');
echo "PDF_BBOX_POSITION_EXTRACTOR_OK\n";
