<?php
declare(strict_types=1);

namespace Brebo\Office\Documents;

use InvalidArgumentException;
use RuntimeException;

/**
 * Non-canonical evidence extraction. Never creates buildings or residents.
 * The caller must supply an authenticated, privately stored original.
 */
final class OfficeSourceAddressExtractor
{
    /** @return array{rows:array,issues:array} */
    public function extract(string $path, string $mimeType, string $filename): array
    {
        if (!is_file($path) || is_link($path) || !is_readable($path)) {
            throw new InvalidArgumentException('source_unavailable');
        }
        $size = filesize($path);
        if ($size === false || $size < 1 || $size > 25 * 1024 * 1024) {
            throw new InvalidArgumentException('source_size_invalid');
        }
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed = [
            'csv' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
            'eml' => ['message/rfc822', 'text/plain', 'application/octet-stream'],
        ];
        if (!isset($allowed[$extension]) || !in_array(strtolower($mimeType), $allowed[$extension], true)) {
            throw new InvalidArgumentException('unsupported_source_type');
        }
        if ($extension === 'xlsx') {
            $rows = $this->xlsx($path);
        } elseif ($extension === 'csv') {
            $rows = $this->csv($path);
        } else {
            $rows = $this->eml($path);
        }
        return $this->interpret($rows);
    }

    /** @return array<int,array{location:string,values:array}> */
    private function csv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) throw new RuntimeException('csv_open_failed');
        try {
            $sample = fgets($handle, 8192);
            if ($sample === false) return [];
            $delimiter = substr_count($sample, ';') > substr_count($sample, ',') ? ';' : ',';
            rewind($handle);
            $rows = [];
            for ($line = 1; $line <= 1001 && ($values = fgetcsv($handle, 0, $delimiter, '"', '')) !== false; $line++) {
                $rows[] = ['location' => 'row:' . $line, 'values' => array_map('strval', $values)];
            }
            if (!feof($handle)) throw new InvalidArgumentException('row_limit_exceeded');
            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /** @return array<int,array{location:string,values:array}> */
    private function xlsx(string $path): array
    {
        // Reuse the existing Office-native, non-executing XLSX reader.
        $readerPath = dirname(__DIR__) . '/Inzet/OnSiteXlsxCellReader.php';
        require_once $readerPath;
        $reader = new \Brebo\Office\Inzet\OnSiteXlsxCellReader();
        $book = $reader->read($path);
        $result = [];
        foreach ($book['cells'] as $sheet => $cells) {
            $table = [];
            foreach ($cells as $coordinate => $value) {
                if (!preg_match('/^([A-Z]{1,3})([1-9][0-9]*)$/D', $coordinate, $m)) continue;
                $number = (int) $m[2];
                if ($number > 1001) throw new InvalidArgumentException('row_limit_exceeded');
                $table[$number][$this->columnIndex($m[1])] = (string) $value;
            }
            ksort($table);
            foreach ($table as $number => $values) {
                ksort($values);
                $result[] = [
                    'location' => 'sheet:' . ($book['sheet_names'][$sheet] ?? $sheet) . ':row:' . $number,
                    'values' => $values,
                ];
            }
        }
        return $result;
    }

    private function columnIndex(string $column): int
    {
        $index = 0;
        foreach (str_split($column) as $letter) $index = $index * 26 + ord($letter) - 64;
        return $index - 1;
    }

    /** @return array<int,array{location:string,values:array}> */
    private function eml(string $path): array
    {
        $raw = file_get_contents($path);
        if (!is_string($raw)) throw new RuntimeException('email_read_failed');
        [$headers, $body] = array_pad(preg_split("/\r?\n\r?\n/", $raw, 2), 2, '');
        // MIME multipart attachments require a dedicated parser; never mix attachment
        // binary or encoded content with address evidence.
        if (preg_match('/^Content-Type:\s*multipart\//im', $headers)) {
            return [['location' => 'email:body', 'values' => ['_unsupported_multipart_']]];
        }
        if (preg_match('/^Content-Transfer-Encoding:\s*base64/im', $headers)) {
            $decoded = base64_decode(preg_replace('/\s+/', '', $body), true);
            if ($decoded === false) throw new InvalidArgumentException('invalid_email_encoding');
            $body = $decoded;
        } elseif (preg_match('/^Content-Transfer-Encoding:\s*quoted-printable/im', $headers)) {
            $body = quoted_printable_decode($body);
        }
        if (preg_match('/^Content-Type:\s*text\/html/im', $headers)) $body = strip_tags($body);
        elseif (preg_match('/^Content-Type:\s*(?!text\/plain|text\/html)[^\r\n]+/im', $headers)) {
            return [['location' => 'email:body', 'values' => ['_unsupported_multipart_']]];
        }
        $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];
        $result = [];
        foreach (array_slice($lines, 0, 1000) as $index => $line) {
            if (trim($line) !== '') $result[] = ['location' => 'email:line:' . ($index + 1), 'values' => [$line]];
        }
        return $result;
    }

    /** @return array{rows:array,issues:array} */
    private function interpret(array $raw): array
    {
        $rows = [];
        $issues = [];
        $headers = null;
        $headerKeys = ['address', 'postal_code', 'city', 'name', 'email', 'phone'];
        foreach ($raw as $entry) {
            $values = array_map(static fn($v): string => trim((string) $v), $entry['values']);
            if (isset($values[0]) && $values[0] === '_unsupported_multipart_') {
                $issues[] = ['location' => $entry['location'], 'reason' => 'multipart_email_requires_review'];
                continue;
            }
            $mapped = [];
            foreach ($values as $index => $value) {
                $normalized = mb_strtolower(trim($value), 'UTF-8');
                $aliases = [
                    'adres'=>'address', 'straat en huisnummer'=>'address', 'straatadres'=>'address', 'address'=>'address',
                    'postcode'=>'postal_code', 'postal code'=>'postal_code',
                    'plaats'=>'city', 'woonplaats'=>'city', 'city'=>'city',
                    'naam'=>'name', 'bewoner'=>'name', 'bewonersnaam'=>'name', 'name'=>'name',
                    'e-mail'=>'email', 'email'=>'email', 'telefoon'=>'phone', 'telefoonnummer'=>'phone', 'phone'=>'phone',
                ];
                if (isset($aliases[$normalized])) $mapped[$index] = $aliases[$normalized];
            }
            if (in_array('address', $mapped, true) && (in_array('postal_code', $mapped, true) || in_array('city', $mapped, true))) {
                $headers = $mapped;
                continue;
            }
            $candidate = [];
            if ($headers !== null) {
                foreach ($headers as $index => $key) $candidate[$key] = $values[$index] ?? '';
            } elseif (count($values) === 1) {
                // Conservative, Dutch postal code anchored, inline evidence only.
                if (preg_match('/\b([1-9][0-9]{3})\s?([A-Za-z]{2})\b/u', $values[0], $match, PREG_OFFSET_CAPTURE)) {
                    $offset = $match[0][1];
                    $candidate = [
                        'address' => trim(substr($values[0], 0, $offset), " ,;\t"),
                        'postal_code' => strtoupper($match[1][0] . $match[2][0]),
                        'city' => trim(substr($values[0], $offset + strlen($match[0][0])), " ,;\t"),
                    ];
                }
            } else {
                $issues[] = ['location' => $entry['location'], 'reason' => 'unmapped_columns'];
                continue;
            }
            if ($candidate === []) {
                if (count($values) === 1 && $values[0] !== '') {
                    $issues[] = ['location' => $entry['location'], 'reason' => 'unrecognized_address_text'];
                }
                continue;
            }
            foreach ($headerKeys as $key) $candidate[$key] = trim((string)($candidate[$key] ?? ''));
            if ($candidate['address'] === '' || $candidate['postal_code'] === '' || $candidate['city'] === '') {
                $issues[] = ['location' => $entry['location'], 'reason' => 'incomplete_address'];
                continue;
            }
            if (!preg_match('/^[1-9][0-9]{3}\s?[A-Za-z]{2}$/D', $candidate['postal_code'])) {
                $issues[] = ['location' => $entry['location'], 'reason' => 'invalid_postal_code'];
                continue;
            }
            $candidate['postal_code'] = strtoupper(preg_replace('/\s+/', '', $candidate['postal_code']));
            foreach ($headerKeys as $key) if (strlen($candidate[$key]) > 255) {
                $issues[] = ['location' => $entry['location'], 'reason' => 'field_too_long'];
                continue 2;
            }
            $rows[] = ['location' => $entry['location'], 'data' => $candidate];
        }
        return ['rows' => $rows, 'issues' => $issues];
    }
}
