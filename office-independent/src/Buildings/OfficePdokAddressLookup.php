<?php
declare(strict_types=1);
namespace Brebo\Office\Buildings;
use Closure;
use DomainException;
use RuntimeException;
require_once __DIR__.'/OfficePdokAddress.php';

final class OfficePdokAddressLookup {
    private const BASE = 'https://api.pdok.nl/bzk/locatieserver/search/v3_1/free';
    public function __construct(private ?Closure $transport = null) {}
    public function search(string $query): array {
        $query = trim($query);
        if (strlen($query)<3 || strlen($query)>160) throw new DomainException('Invalid address query');
        return $this->fetch(['q'=>$query,'rows'=>10,'fq'=>['bron:BAG','type:adres']]);
    }
    public function verify(string $bagId): array {
        if (!preg_match('/^[0-9]{16}$/D',$bagId)) throw new DomainException('Invalid BAG identity');
        $rows=$this->fetch(['q'=>'*:*','rows'=>2,'fq'=>['bron:BAG','type:adres','nummeraanduiding_id:'.$bagId]]);
        if (count($rows)!==1 || $rows[0]['bag_id']!==$bagId) throw new DomainException('BAG address not uniquely verified');
        return $rows[0];
    }
    private function fetch(array $params): array {
        $parts=[];
        foreach ($params as $key=>$values) {
            foreach ((array)$values as $value) $parts[]=rawurlencode($key).'='.rawurlencode((string)$value);
        }
        $url=self::BASE.'?'.implode('&',$parts);
        $raw=$this->transport!==null ? ($this->transport)($url) : @file_get_contents($url,false,stream_context_create(['http'=>['timeout'=>5,'follow_location'=>0,'header'=>"Accept: application/json\r\n"]]),0,1048576);
        if (!is_string($raw)) throw new RuntimeException('PDOK unavailable');
        $json=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if (!is_array($json['response']['docs']??null)) throw new RuntimeException('Invalid PDOK response');
        $out=[];
        foreach ($json['response']['docs'] as $doc) {
            if (!is_array($doc)) continue;
            try { $out[]=OfficePdokAddress::fromDocument($doc); }
            catch (DomainException $e) { continue; }
        }
        return $out;
    }
}
