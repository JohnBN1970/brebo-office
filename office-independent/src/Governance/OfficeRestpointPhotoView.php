<?php
declare(strict_types=1);
namespace Brebo\Office\Governance;

/** Render only after document and project authorization. No public storage URLs. */
final class OfficeRestpointPhotoView
{
    public static function captureForm(int $restpointId,string $csrf): string
    {
        if ($restpointId<1 || $csrf==='') throw new \InvalidArgumentException('Ongeldige fotocontext.');
        $token=htmlspecialchars($csrf,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        return '<form method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="upload_restpoint_photo"><input type="hidden" name="restpoint_id" value="'.$restpointId.'"><input type="hidden" name="csrf_token" value="'.$token.'"><label>Fototype <select name="photo_role"><option value="before">Voor herstel</option><option value="after">Na herstel</option><option value="review">Hercontrole</option></select></label><label>Foto maken <input type="file" name="camera_photo" accept="image/jpeg,image/png" capture="environment"></label><label>Bestaande foto toevoegen <input type="file" name="existing_photo" accept="image/jpeg,image/png"></label><button type="submit">Foto opslaan</button></form>';
    }

    public static function render(string $imageUrl,array $markers,array $restpoints,string $csrf,bool $canWrite): string
    {
        $h=static fn(string $v):string=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        if (!str_starts_with($imageUrl,'/office-test/') || str_contains($imageUrl,'\\') || preg_match('/[\x00-\x20]/',$imageUrl)) throw new \InvalidArgumentException('Ongeldige fotobron.');
        $html='<section><h2>Fotomarkeringen</h2><div style="position:relative;display:inline-block;max-width:100%"><img alt="Opleverfoto" style="display:block;max-width:100%;height:auto;image-orientation:from-image" src="'.$h($imageUrl).'">';
        $list='<ol>';
        foreach ($markers as $m) {
            $id=(int)$m['id'];$x=(int)$m['x_millionths'];$y=(int)$m['y_millionths'];
            if (min($x,$y)<0||max($x,$y)>1000000) continue;
            $status=match($m['status']){'accepted'=>'Akkoord','recheck'=>'Hercontrole',default=>'Open / herstel'};
            $color=match($m['status']){'accepted'=>'#18743d','recheck'=>'#a45a00',default=>'#b42318'};
            $html.='<a href="#marker-'.$id.'" style="position:absolute;left:'.($x/10000).'%;top:'.($y/10000).'%;transform:translate(-50%,-50%);border:2px solid white;border-radius:50%;padding:5px;background:'.$color.';color:white" aria-label="'.$h('Restpunt '.$m['restpoint_id'].' '.$status).'">'.(int)$m['restpoint_id'].'</a>';
            $list.='<li id="marker-'.$id.'">'.$h((string)$m['title']).' — '.$h($status).'</li>';
        }
        $html.='</div>'.$list.'</ol>';
        if ($canWrite) {
            $html.='<form method="post"><input type="hidden" name="csrf_token" value="'.$h($csrf).'"><label>Restpunt <select name="restpoint_id" required>';
            foreach ($restpoints as $r) $html.='<option value="'.(int)$r['id'].'">'.$h((string)$r['title']).'</option>';
            $html.='</select></label><label>X (miljoensten) <input name="x" type="number" min="0" max="1000000" required></label><label>Y (miljoensten) <input name="y" type="number" min="0" max="1000000" required></label><button type="submit">Markering toevoegen</button></form>';
        }
        return $html.'</section>';
    }
}
