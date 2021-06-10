<?php

use Mzur\InvoiScript\Invoice;

require_once(__DIR__.'/../vendor/autoload.php');
require_once(__DIR__.'/../src/Invoice.php');

$content = [
   'title' => 'Invoice No. 1',
   'beforeInfo' => [
      '<b>Date:</b>',
      'June 10, 2021',
   ],
   'afterInfo' => [
      'All prices in EUR.',
      '',
      'This invoice is due on <b>June 20, 2021</b>.',
   ],
   'clientAddress' => [
      'Jane Doe',
      'Example Street 42',
      '1337 Demo City',
   ],
   'entries' => [
      [
         'description' => 'Hot air',
         'quantity' => 11,
         'price' => 8,
      ],
      [
         'description' => 'Something cool',
         'quantity' => 5,
         'price' => 20,
      ],
   ],
];

$pdf = new Invoice($content);
$pdf->generate('example.pdf');
