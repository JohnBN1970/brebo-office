<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Process/OfficeProcessFormView.php';
use Brebo\Office\Process\OfficeProcessFormView as V;
$list=V::catalog(['FR-201']);
if (!str_contains($list,'FR-201') || str_contains($list,'FR-300')) throw new RuntimeException('Catalog permission filtering failed');
$form=['id'=>42,'document_code'=>'FR-201','status'=>'concept','revision'=>2,'data'=>['scope'=>'<script>alert(1)</script>']];
$html=V::instance($form,['scope'=>'Scope <test>'],'csrf-token',true);
if (!str_contains($html,'&lt;script&gt;') || str_contains($html,'<script>') || !str_contains($html,'expected_revision') || !str_contains($html,'csrf-token')) throw new RuntimeException('Unsafe or incomplete editable form');
$form['status']='akkoord';
$readonly=V::instance($form,['scope'=>'Scope'],'csrf-token',true);
if (str_contains($readonly,'<form') || str_contains($readonly,'<textarea')) throw new RuntimeException('Approved form editable');
echo "Process form shared view contracts OK\n";
