<?php
declare(strict_types=1);
namespace Brebo\Office\Documents;

/** Internal worker primitive. Key supplied by authorized external key provider. */
final class OfficeDossierArchiveCipher
{
    public static function transform(string $source, string $directory, string $key, bool $decrypt, int $maxBytes): string
    {
        if (strlen($key)!==SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES || $maxBytes<1 || !is_file($source) || is_link($source)) throw new \InvalidArgumentException('Invalid cipher input');
        $dir=realpath($directory);
        $web=realpath($_SERVER['DOCUMENT_ROOT'] ?? '/nonexistent');
        if ($dir===false || !is_dir($dir) || is_link($directory) || (fileperms($dir)&0077)!==0 || ($web!==false && ($dir===$web || str_starts_with($dir,rtrim($web,'/').'/')))) throw new \RuntimeException('Private staging required');
        $target=tempnam($dir,'cipher-');
        if ($target===false) throw new \RuntimeException('Staging failed');
        chmod($target,0600);
        $in=null; $out=null; $success=false;
        try {
            $in=fopen($source,'rb'); $out=fopen($target,'wb');
            if (!$in || !$out) throw new \RuntimeException('Open failed');
            $total=0;
            if (!$decrypt) {
                [$state,$header]=sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
                self::write($out,$header);
                while (true) {
                    $chunk=fread($in,65536);
                    if ($chunk===false) throw new \RuntimeException('Read failed');
                    $total+=strlen($chunk);
                    if ($total>$maxBytes) throw new \RuntimeException('Archive limit exceeded');
                    $final=feof($in);
                    if ($chunk==='' && !$final) throw new \RuntimeException('Read stalled');
                    $cipher=sodium_crypto_secretstream_xchacha20poly1305_push($state,$chunk,'',$final?SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL:SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE);
                    self::write($out,pack('N',strlen($cipher)).$cipher);
                    if ($final) break;
                }
            } else {
                $state=sodium_crypto_secretstream_xchacha20poly1305_init_pull(self::read($in,SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES),$key);
                while (true) {
                    $length=unpack('N',self::read($in,4))[1];
                    $overhead=SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;
                    if ($length<$overhead || $length>65536+$overhead) throw new \RuntimeException('Invalid frame');
                    $result=sodium_crypto_secretstream_xchacha20poly1305_pull($state,self::read($in,$length));
                    if ($result===false) throw new \RuntimeException('Authentication failed');
                    [$plain,$tag]=$result;
                    $total+=strlen($plain);
                    if ($total>$maxBytes) throw new \RuntimeException('Archive limit exceeded');
                    self::write($out,$plain);
                    if ($tag===SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                        if (fread($in,1)!=='') throw new \RuntimeException('Trailing ciphertext');
                        break;
                    }
                    if ($tag!==SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE) throw new \RuntimeException('Invalid tag');
                }
            }
            if (!fflush($out)) throw new \RuntimeException('Flush failed');
            $success=true;
            return $target;
        } finally {
            if (is_resource($in)) fclose($in);
            if (is_resource($out)) fclose($out);
            if (!$success) @unlink($target);
        }
    }

    private static function read($stream,int $length): string
    {
        $data='';
        while (strlen($data)<$length) {
            $part=fread($stream,$length-strlen($data));
            if ($part===false || $part==='') throw new \RuntimeException('Truncated archive');
            $data.=$part;
        }
        return $data;
    }

    private static function write($stream,string $data): void
    {
        if (fwrite($stream,$data)!==strlen($data)) throw new \RuntimeException('Write failed');
    }
}
