<?php
declare(strict_types=1);
namespace Brebo\Office\Calculation;

use DomainException;

/**
 * Calculatiecorrecties bovenop nettohoeveelheden uit Gebouwen.
 * Wijzigt nooit de gebouwbron; bedragen zijn exclusief btw.
 */
final class OfficeCalcQuantityAdjustments
{
    public static function material(string $netQuantity, string $lossPercent, string $unitPrice): array
    {
        self::decimal($netQuantity, 6, 4);
        self::decimal($lossPercent, 3, 2);
        self::decimal($unitPrice, 8, 4);
        if (bccomp($lossPercent, '100', 2) > 0) {
            throw new DomainException('Materiaalverlies boven 100 procent.');
        }
        $factor=bcadd('1', bcdiv($lossPercent, '100', 6), 6);
        $gross=bcmul($netQuantity, $factor, 4);
        return [
            'net_quantity'=>$netQuantity,
            'loss_percent'=>$lossPercent,
            'gross_quantity'=>$gross,
            'extra_quantity'=>bcsub($gross, $netQuantity, 4),
            'material_cost'=>bcmul($gross, $unitPrice, 4),
        ];
    }

    public static function labor(string $baseHours, string $extraHours, string $hourlyRate): array
    {
        self::decimal($baseHours, 6, 4);
        self::decimal($extraHours, 6, 4);
        self::decimal($hourlyRate, 8, 4);
        $total=bcadd($baseHours, $extraHours, 4);
        return [
            'base_hours'=>$baseHours,
            'extra_hours'=>$extraHours,
            'total_hours'=>$total,
            'labor_cost'=>bcmul($total, $hourlyRate, 4),
        ];
    }

    /**
     * Reconstruct an editable adjustment from a canonical line snapshot.
     * Legacy rows without metadata remain unadjusted.
     */
    public static function snapshot(string $effectiveQuantity, ?string $netQuantity, ?string $lossPercent, ?string $extraHours, string $costType): array
    {
        self::decimal($effectiveQuantity, 6, 4);
        if($netQuantity===null){
            if($lossPercent!==null||$extraHours!==null)throw new DomainException('Correctie zonder nettohoeveelheid.');
            return ['net_quantity'=>$effectiveQuantity,'effective_quantity'=>$effectiveQuantity,'material_loss_percent'=>null,'extra_labor_hours'=>null];
        }
        self::decimal($netQuantity, 6, 4);
        if($lossPercent!==null&&$extraHours!==null)throw new DomainException('Conflicterende correcties.');
        if($lossPercent!==null){
            if($costType!=='material')throw new DomainException('Materiaalverlies alleen voor materiaal.');
            $calculated=self::material($netQuantity,$lossPercent,'0');
            if(bccomp($calculated['gross_quantity'],$effectiveQuantity,4)!==0)throw new DomainException('Afwijkende materiaalhoeveelheid.');
        }elseif($extraHours!==null){
            if($costType!=='labor')throw new DomainException('Extra uren alleen voor arbeid.');
            $calculated=self::labor($netQuantity,$extraHours,'0');
            if(bccomp($calculated['total_hours'],$effectiveQuantity,4)!==0)throw new DomainException('Afwijkende arbeidsuren.');
        }elseif(bccomp($netQuantity,$effectiveQuantity,4)!==0){
            throw new DomainException('Afwijkende nettohoeveelheid.');
        }
        return ['net_quantity'=>$netQuantity,'effective_quantity'=>$effectiveQuantity,'material_loss_percent'=>$lossPercent,'extra_labor_hours'=>$extraHours];
    }

    private static function decimal(string $value, int $integerDigits, int $fractionDigits): void
    {
        if (!preg_match('/^[0-9]{1,'.$integerDigits.'}(?:\\.[0-9]{1,'.$fractionDigits.'})?$/D', $value)) {
            throw new DomainException('Ongeldige calculatiewaarde.');
        }
    }
}
