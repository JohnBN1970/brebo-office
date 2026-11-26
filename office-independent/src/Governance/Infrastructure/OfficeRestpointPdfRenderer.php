<?php
declare(strict_types=1);
namespace Brebo\Office\Governance\Infrastructure;

final class OfficeRestpointPdfRenderer
{
    /** Caller must authorize the immutable document before passing its private path. */
    public function render(string $path,int $page): string
    {
        if ($page<1 || $page>1000 || !is_file($path) || !is_readable($path)
            || (new \finfo(FILEINFO_MIME_TYPE))->file($path)!=='application/pdf') {
            throw new \InvalidArgumentException('Ongeldige PDF-pagina.');
        }
        if (!is_executable('/usr/bin/pdftoppm') || !is_executable('/usr/bin/timeout') || !is_executable('/usr/bin/prlimit')) {
            throw new \RuntimeException('PDF-renderdienst niet beschikbaar.');
        }
        $dir=sys_get_temp_dir().'/office-pdf-'.bin2hex(random_bytes(16));
        if (!mkdir($dir,0700)) throw new \RuntimeException('Tijdelijke opslag niet beschikbaar.');
        try {
            $target=$dir.'/page';
            $command=['/usr/bin/timeout','--kill-after=2s','15s','/usr/bin/prlimit','--as=536870912','--fsize=16777216','--cpu=12','--','/usr/bin/pdftoppm','-f',(string)$page,'-l',(string)$page,'-singlefile','-scale-to','2400','-png',$path,$target];
            $process=proc_open($command,[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes,$dir);
            if (!is_resource($process)) throw new \RuntimeException('PDF-renderdienst niet beschikbaar.');
            $status=proc_close($process);
            $output=$target.'.png';
            if ($status!==0 || !is_file($output) || filesize($output)>16777216) throw new \RuntimeException('PDF-pagina niet beschikbaar.');
            $info=@getimagesize($output);
            if (!$info || $info['mime']!=='image/png' || max($info[0],$info[1])>2400) throw new \RuntimeException('Ongeldige paginaweergave.');
            $bytes=file_get_contents($output);
            if ($bytes===false) throw new \RuntimeException('Pagina niet leesbaar.');
            return $bytes;
        } finally {
            foreach (glob($dir.'/*')?:[] as $file) @unlink($file);
            @rmdir($dir);
        }
    }
}
