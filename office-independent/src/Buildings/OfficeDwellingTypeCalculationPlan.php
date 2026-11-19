<?php
declare(strict_types=1);

namespace Brebo\Office\Buildings;

use InvalidArgumentException;

/**
 * Builds address-level calculation proposals from a single dwelling-type
 * calculation. No pricing, persistence, approval or baseline mutation.
 * An override is explicit and never mutates the shared type template.
 */
final class OfficeDwellingTypeCalculationPlan {
  public static function expand(array $typeLines,array $addresses,array $overrides=[]): array {
    $templates=[];
    foreach ($typeLines as $line) {
      $typeId=(int)($line['dwelling_type_id']??0);
      $key=trim((string)($line['line_key']??''));
      $quantity=self::quantity($line['quantity']??null);
      if ($typeId<1 || $key==='' || isset($templates[$typeId][$key])) {
        throw new InvalidArgumentException('invalid_or_duplicate_type_line');
      }
      $templates[$typeId][$key]=[
        'line_key'=>$key,'component_code'=>(string)($line['component_code']??''),
        'description'=>(string)($line['description']??''),
        'quantity'=>$quantity,'unit'=>(string)($line['unit']??''),
        'recipe_id'=>$line['recipe_id']??null,
      ];
    }
    $changes=[];
    foreach ($overrides as $change) {
      $addressId=(int)($change['address_id']??0);
      $key=trim((string)($change['line_key']??''));
      $action=(string)($change['action']??'');
      if ($addressId<1 || $key==='' || !in_array($action,['add','replace','exclude'],true)
        || isset($changes[$addressId][$key])) {
        throw new InvalidArgumentException('invalid_or_duplicate_override');
      }
      $changes[$addressId][$key]=$change;
    }
    $result=[];
    $seen=[];
    foreach ($addresses as $address) {
      $addressId=(int)($address['id']??0);
      $typeId=(int)($address['dwelling_type_id']??0);
      if ($addressId<1 || $typeId<1 || isset($seen[$addressId]) || !isset($templates[$typeId])) {
        throw new InvalidArgumentException('address_without_known_type');
      }
      $seen[$addressId]=true;
      $lines=$templates[$typeId];
      foreach ($changes[$addressId]??[] as $key=>$change) {
        $action=$change['action'];
        if ($action==='add' && isset($lines[$key])) throw new InvalidArgumentException('override_add_existing_line');
        if ($action!=='add' && !isset($lines[$key])) throw new InvalidArgumentException('override_missing_base_line');
        if ($action==='exclude') {
          unset($lines[$key]);
          continue;
        }
        $base=$lines[$key]??[
          'line_key'=>$key,'component_code'=>'','description'=>'','quantity'=>0,
          'unit'=>'','recipe_id'=>null,
        ];
        $allowed=['component_code','description','quantity','unit','recipe_id'];
        foreach ($allowed as $field) {
          if (array_key_exists($field,$change)) {
            $base[$field]=$field==='quantity'?self::quantity($change[$field]):$change[$field];
          }
        }
        $lines[$key]=$base;
        $lines[$key]['source']='address_override';
      }
      foreach ($lines as $key=>$line) {
        $line['address_id']=$addressId;
        $line['dwelling_type_id']=$typeId;
        $line['source']=$line['source']??'dwelling_type';
        $result[]=$line;
      }
    }
    foreach ($changes as $addressId=>$_) {
      if (!isset($seen[$addressId])) throw new InvalidArgumentException('override_unknown_address');
    }
    return $result;
  }

  private static function quantity(mixed $value): float {
    if (!is_numeric($value) || !is_finite((float)$value) || (float)$value<0) {
      throw new InvalidArgumentException('invalid_quantity');
    }
    return (float)$value;
  }
}
