<?php

declare(strict_types=1);

namespace Drupal\brebo_procurement\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/** Operational overview for open, delayed and received supplier orders. */
final class ProcurementOrderOverviewController extends ControllerBase {
  public function __construct(private readonly Connection $database) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'));
  }

  public function overview(): array {
    $today = date('Y-m-d');
    $query = $this->database->select('brebo_procurement_order','o');
    $query->leftJoin('brebo_procurement_request','r','r.id = o.request_id');
    $query->addField('o','id'); $query->addField('o','order_number'); $query->addField('o','supplier_name');
    $query->addField('o','status'); $query->addField('o','expected_delivery_date'); $query->addField('o','ordered_at');
    $query->addField('r','project_nid'); $query->addField('r','request_number');
    $query->orderBy('o.expected_delivery_date','ASC')->orderBy('o.id','DESC');
    $rows=[]; $counts=['late'=>0,'today'=>0,'open'=>0,'received'=>0,'exception'=>0];
    foreach ($query->execute() as $order) {
      $status=(string)$order->status; $expected=(string)($order->expected_delivery_date ?? '');
      if ($status === 'received') { $signal='Ontvangen'; $counts['received']++; }
      elseif ($status === 'receipt_exception') { $signal='Ontvangstafwijking'; $counts['exception']++; }
      elseif ($expected !== '' && $expected < $today) { $signal='TE LAAT'; $counts['late']++; }
      elseif ($expected === $today) { $signal='Vandaag'; $counts['today']++; }
      else { $signal='Open'; $counts['open']++; }
      $action = $status === 'ordered'
        ? Link::createFromRoute($this->t('Ontvangen'), 'brebo_procurement.order_receive', ['order_id'=>(int)$order->id])
        : '-';
      $rows[]=[
        'order'=>$order->order_number,'request'=>$order->request_number ?: '-', 'project'=>$order->project_nid ?: '-',
        'supplier'=>$order->supplier_name,'expected'=>$expected ?: '-', 'status'=>$status,'signal'=>$signal,'action'=>$action,
      ];
    }
    return [
      'summary'=>['#theme'=>'item_list','#title'=>$this->t('Leveringsbewaking'),'#items'=>[
        $this->t('Te laat: @n',['@n'=>$counts['late']]),$this->t('Vandaag: @n',['@n'=>$counts['today']]),$this->t('Open: @n',['@n'=>$counts['open']]),
        $this->t('Ontvangen: @n',['@n'=>$counts['received']]),$this->t('Afwijkingen: @n',['@n'=>$counts['exception']]),
      ]],
      'orders'=>['#type'=>'table','#header'=>[$this->t('Order'),$this->t('Aanvraag'),$this->t('Project'),$this->t('Leverancier'),$this->t('Verwacht'),$this->t('Status'),$this->t('Signaal'),$this->t('Actie')],'#rows'=>$rows,'#empty'=>$this->t('Nog geen bestellingen.')],
    ];
  }
}
