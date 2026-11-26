<?php
declare(strict_types=1);
namespace Brebo\Office\Documents;

final class OfficePdfWriter
{
    private array $objects=[1=>'<< /Type /Catalog /Pages 2 0 R >>',2=>'',3=>'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'];
    private array $pages=[];
    private string $stream='';
    private array $images=[];
    public function text(float $x,float $y,string $text,float $size=10): void
    {
        $bytes=iconv('UTF-8','Windows-1252//TRANSLIT',$text);
        if ($bytes===false) throw new \RuntimeException('PDF encoding failed.');
        $bytes=str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$bytes);
        $this->stream.=sprintf("0 g BT /F1 %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",$size,$x,842-$y,$bytes);
    }
    public function jpeg(string $bytes,float $x,float $top,float $width,float $height,array $markers=[]): void
    {
        $info=@getimagesizefromstring($bytes);
        if (!$info || $info['mime']!=='image/jpeg' || ($info['channels']??0)!==3 || strlen($bytes)>16000000 || $info[0]*$info[1]>25000000) throw new \InvalidArgumentException('Invalid RGB JPEG.');
        $scale=min($width/$info[0],$height/$info[1]);
        $w=$info[0]*$scale;$h=$info[1]*$scale;
        $id=count($this->objects)+1;$name='Im'.$id;
        $this->objects[$id]='<< /Type /XObject /Subtype /Image /Width '.$info[0].' /Height '.$info[1].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($bytes)." >>\nstream\n".$bytes."\nendstream";
        $this->images[$name]=$id;
        $this->stream.=sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n",$w,$h,$x,842-$top-$h,$name);
        foreach ($markers as $m) {
            $a=(int)$m['x_millionths'];$b=(int)$m['y_millionths'];
            if (min($a,$b)<0 || max($a,$b)>1000000) throw new \InvalidArgumentException('Invalid marker.');
            $cx=$x+$w*$a/1000000;$cy=842-$top-$h*$b/1000000;
            $color=match($m['status']){'accepted'=>'0.09 0.45 0.24','recheck'=>'0.64 0.35 0',default=>'0.71 0.14 0.09'};
            $this->stream.=sprintf("q %s rg %.2F %.2F 16 14 re f 1 g BT /F1 8 Tf 1 0 0 1 %.2F %.2F Tm (%d) Tj ET Q\n",$color,$cx-8,$cy-7,$cx-6,$cy-3,(int)$m['restpoint_id']);
        }
    }
    public function finishPage(): void
    {
        if ($this->stream==='') return;
        $id=count($this->objects)+1;$streamId=$id+1;
        $resources='';foreach($this->images as $name=>$imageId)$resources.='/'.$name.' '.$imageId.' 0 R ';
        $this->objects[$id]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> /XObject << '.$resources.' >> >> /Contents '.$streamId.' 0 R >>';
        $this->objects[$streamId]='<< /Length '.strlen($this->stream)." >>\nstream\n".$this->stream."endstream";
        $this->pages[]=$id.' 0 R';$this->stream='';$this->images=[];
    }
    public function render(): string
    {
        $this->finishPage();
        if (!$this->pages) throw new \LogicException('No PDF pages.');
        $this->objects[2]='<< /Type /Pages /Kids ['.implode(' ',$this->pages).'] /Count '.count($this->pages).' >>';
        $out="%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";$offsets=[];
        foreach($this->objects as $id=>$object){$offsets[$id]=strlen($out);$out.=$id." 0 obj\n".$object."\nendobj\n";}
        $xref=strlen($out);$count=count($this->objects)+1;
        $out.="xref\n0 ".$count."\n0000000000 65535 f \n";
        for($id=1;$id<$count;$id++)$out.=sprintf("%010d 00000 n \n",$offsets[$id]);
        return $out."trailer\n<< /Size ".$count." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }
}
