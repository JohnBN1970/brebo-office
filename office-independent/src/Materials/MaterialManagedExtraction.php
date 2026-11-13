<?php
declare(strict_types=1);
namespace Brebo\Office\Materials;

/** Uses the existing Office-managed extraction contract; no browser credentials or fallback service. */
final class MaterialManagedExtraction
{
    public static function fromEnvironment():?\Closure
    {
        $endpoint=trim((string)(getenv('BREBO_DOCUMENT_EXTRACTION_ENDPOINT')?:''));
        $token=trim((string)(getenv('DOCUMENT_EXTRACTION_TOKEN')?:''));
        if($endpoint===''||$token==='')return null;
        if(!str_starts_with($endpoint,'https://')||!function_exists('curl_init'))throw new \RuntimeException('Documentherkenning is niet correct geconfigureerd.');
        return static function(string $bytes,string $mime,string $filename)use($endpoint,$token):array{
            $curl=curl_init($endpoint);if($curl===false)return ['status'=>'provider_unavailable'];
            $body='';
            try{
                curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>['document'=>new \CURLStringFile($bytes,$filename,$mime)],
                    CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: application/json','X-BREBO-Extraction-Contract: v1'],
                    CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>30,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
                    CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk)use(&$body):int{if(strlen($body)+strlen($chunk)>8000000)return 0;$body.=$chunk;return strlen($chunk);}]);
                if(curl_exec($curl)===false||(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE)!==200)return ['status'=>'provider_unavailable'];
                $result=json_decode($body,true,32,JSON_THROW_ON_ERROR);
                if(!is_array($result)||!is_string($result['text']??null))return ['status'=>'invalid_provider_response'];
                return ['status'=>$result['status']??'extracted','text'=>$result['text'],'extractor'=>'office_managed'];
            }catch(\Throwable){return ['status'=>'provider_unavailable'];}finally{curl_close($curl);}
        };
    }
}
