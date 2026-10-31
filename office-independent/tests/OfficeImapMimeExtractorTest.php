<?php
declare(strict_types=1);

namespace Brebo\Office\Communication {
    function imap_fetchstructure(mixed $stream, int $uid, int $flags = 0): object {
        return $stream->structure;
    }
    function imap_fetchbody(mixed $stream, int $uid, string $section, int $flags = 0): string|false {
        return $stream->sections[$section] ?? false;
    }
    function imap_body(mixed $stream, int $uid, int $flags = 0): string|false {
        return $stream->sections[''] ?? false;
    }
}
namespace {
    require_once dirname(__DIR__) . '/src/Communication/OfficeImapMimeExtractor.php';
    use Brebo\Office\Communication\OfficeImapMimeExtractor;

    if (!defined('FT_UID')) define('FT_UID', 1);
    if (!defined('FT_PEEK')) define('FT_PEEK', 2);

    $plain = (object) ['type'=>0,'subtype'=>'PLAIN','encoding'=>0];
    $html = (object) ['type'=>0,'subtype'=>'HTML','encoding'=>4];
    $inline = (object) ['type'=>5,'subtype'=>'PNG','encoding'=>3,'disposition'=>'inline'];
    $pdf = (object) [
        'type'=>3,'subtype'=>'PDF','encoding'=>3,'disposition'=>'attachment',
        'dparameters'=>[(object)['attribute'=>'filename','value'=>'factuur.pdf']]
    ];
    $stream = (object)[
        'structure'=>(object)['type'=>1,'subtype'=>'MIXED','parts'=>[
            (object)['type'=>1,'subtype'=>'ALTERNATIVE','parts'=>[$plain,$html]],
            $inline,$pdf
        ]],
        'sections'=>[
            '1.1'=>'Factuur tekst',
            '1.2'=>quoted_printable_encode('<p>Factuur HTML</p>'),
            '2'=>base64_encode('pngdata'),
            '3'=>base64_encode('%PDF-1.4 test')
        ]
    ];
    $result = (new OfficeImapMimeExtractor())->extract($stream, 123);
    if ($result['body'] !== 'Factuur tekst') throw new \RuntimeException('Plain text not preferred.');
    if (count($result['attachments']) !== 1) throw new \RuntimeException('Inline image misclassified.');
    if ($result['attachments'][0]['filename'] !== 'factuur.pdf' || $result['attachments'][0]['bytes'] !== '%PDF-1.4 test') {
        throw new \RuntimeException('PDF attachment extraction failed.');
    }
    $stream->structure->parts[2]->dparameters[0]->value = '';
    try {
        (new OfficeImapMimeExtractor())->extract($stream, 123);
        throw new \RuntimeException('Unnamed attachment accepted.');
    } catch (\RuntimeException $e) {
        if ($e->getMessage() !== 'Unnamed attachment requires review.') throw $e;
    }
    echo "OfficeImapMimeExtractorTest PASS\n";
}
