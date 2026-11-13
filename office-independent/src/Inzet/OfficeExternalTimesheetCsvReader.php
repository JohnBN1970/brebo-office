<?php
declare(strict_types=1);

namespace Brebo\Office\Inzet;

/**
 * Review-only CSV extraction. Never resolves identities or posts approved hours.
 * Import caller must explicitly map supplier, project and person after review.
 */
final class OfficeExternalTimesheetCsvReader
{
    public static function preview(string $csv, string $delimiter = ';'): array
    {
        if (strlen($csv) > 5_000_000 || $csv === '' || !in_array($delimiter, [';', ','], true)) {
            throw new \DomainException('Unsupported timesheet input');
        }
        $handle=fopen('php://temp/maxmemory:5242880','w+');
        if ($handle===false) throw new \RuntimeException('CSV buffer unavailable');
        try {
            fwrite($handle, $csv);
            rewind($handle);
            $header=fgetcsv($handle,0,$delimiter,'"','');
            if (!is_array($header)) throw new \DomainException('Missing CSV header');
            $header=array_map(static fn(string $v):string=>mb_strtolower(trim(preg_replace('/^\xEF\xBB\xBF/','',$v))),$header);
            $required=['project_id','person_id','work_date','claimed_hours','supplier_reference'];
            if (count($header)!==count(array_unique($header)) || array_diff($required,$header)!==[]) {
                throw new \DomainException('Missing or duplicated required CSV columns');
            }
            $rows=[];
            $lineNumber=1;
            while (($values=fgetcsv($handle,0,$delimiter,'"',''))!==false) {
                $lineNumber++;
                if ($values===[null]) continue;
                if (count($values)!==count($header)) throw new \DomainException('Malformed CSV row '.$lineNumber);
                $raw=array_combine($header,$values);
                if ($raw===false) throw new \DomainException('Malformed CSV columns');
                $hours=trim((string)$raw['claimed_hours']);
                if (!preg_match('/^(?:0|[1-9]\d{0,1})(?:[.,]\d{1,2})?$/D',$hours)) {
                    throw new \DomainException('Invalid claimed hours row '.$lineNumber);
                }
                $number=(float)str_replace(',','.',$hours);
                $seconds=(int)round($number*3600);
                if ($seconds < 1 || $seconds > 86400) throw new \DomainException('Invalid hours range row '.$lineNumber);
                $date=(string)$raw['work_date'];
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)
                    || !checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))) {
                    throw new \DomainException('Invalid work date row '.$lineNumber);
                }
                foreach (['project_id','person_id'] as $key) {
                    if (!preg_match('/^[1-9]\d*$/D',(string)$raw[$key])) {
                        throw new \DomainException('Unresolved identity row '.$lineNumber);
                    }
                }
                $supplier=trim((string)$raw['supplier_reference']);
                if ($supplier==='' || strlen($supplier)>128) throw new \DomainException('Invalid supplier row '.$lineNumber);
                $rows[]=[
                    'source_line_reference'=>'csv-row-'.$lineNumber,
                    'source_line_sha256'=>hash('sha256',json_encode($raw,JSON_THROW_ON_ERROR)),
                    'project_id'=>(int)$raw['project_id'],
                    'person_id'=>(int)$raw['person_id'],
                    'work_date'=>$date,
                    'claimed_seconds'=>$seconds,
                    'supplier_reference'=>$supplier,
                    'requires_review'=>true,
                ];
                if (count($rows)>5000) throw new \DomainException('Too many timesheet rows');
            }
            return ['source_document_sha256'=>hash('sha256',$csv),'rows'=>$rows,'requires_review'=>true];
        } finally {
            fclose($handle);
        }
    }
}
