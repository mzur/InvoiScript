<?php

namespace Mzur\InvoiScript;

use setasign\Fpdi\Fpdi;

class Invoice extends Fpdi
{
   /**
    * The array of string translations for each language.
    *
    * @var array
    */
   protected static $translations;

   /**
    * The invoice content.
    *
    * @var array
    */
   protected $content;

   /**
    * The array of item entries.
    *
    * @var array
    */
   protected $entries;

   /**
    * Variables that can be substituted in the rendered beforeInfo and afterInfo.
    *
    * @var array
    */
   protected $variables;

   /**
    * Number of pages of the PDF template.
    *
    * @var int
    */
   protected $templatePages;

   /**
    * Language code.
    *
    * @var string
    */
   protected $lang;

   /**
    * The page layout.
    *
    * @var array
    */
   protected $layout;

   /**
    * Get a string translation.
    *
    * @param string $lang Language code.
    * @param string $key Translation key.
    *
    * @return string
    */
   protected static function translate($lang, $key)
   {
      if (!isset(static::$translations)) {
         static::$translations = require(__DIR__.'/lang.php');
      }

      $translation = static::$translations[$lang] ?? static::$translations['en'];

      return $translation[$key] ?? $key;
   }

   /**
    * Create a new instance.
    *
    * @param array $content Array containing 'title', 'cientAddress', 'entries', and optional 'beforeInfo' and 'afterInfo'.
    */
   public function __construct($content)
   {
      parent::__construct();
      $this->content = $content;
      $this->entries = $this->content['entries'] ?? [];
      if (empty($this->entries)) {
         throw new Exception("No item entries for the invoice.");
      }
      $this->setLayout();
      $this->setVariables();
      $this->setAutoPageBreak(false);
      $this->templatePages = 0;
      $this->aliasNbPages('{pages}');
   }

   /**
    * Set the template to use.
    *
    * @param string $path Path to the PDF template. The last page of the template will be
    * used for all pages of the invoice that have a higher page number. Else each
    * template page will be used for each invoice page.
    */
   public function setTemplate($path)
   {
      $this->templatePages = $this->setSourceFile($path);
   }

   /**
    * Set the language to use.
    *
    * @param string $code Language code.
    */
   public function setLanguage($code)
   {
      $this->lang = $code;
   }

   /**
    * Set custom layout variables.
    *
    * @param array $layout
    */
   public function setLayout($layout = [])
   {
      $this->layout = $this->getLayout($layout);
      $this->setFont($this->layout['font'], '', $this->layout['fontSize']);
      $this->setMargins($this->layout['pagePaddingLeft'], $this->layout['pagePaddingTop']);
   }

   /**
    * Set custom content variables.
    *
    * @param array $variables
    */
   public function setVariables($variables = [])
   {
      $this->variables = $this->getVariables($variables);
   }

   /**
    * Generate the invoice PDF.
    *
    * @param string $path
    */
   public function generate($path)
   {
      $this->newPage();
      $this->makeLetterhead();
      $this->makeTitle();
      $this->makeBeforeInfo();
      $this->makeEntries();
      $this->makeAfterInfo();
      $this->output('F', $path, true);
   }

   /**
    * Render the letterhead.
    */
   protected function makeLetterhead()
   {
      $address = $this->content['clientAddress'];
      $this->setMargins($this->layout['addressPaddingLeft'], $this->layout['pagePaddingTop']);
      $this->setY($this->layout['addressMarginTop']);

      foreach ($address as $line) {
         $this->cell(0, $this->layout['contentCellHeight'], utf8_decode($line), 0, 1);
      }

      $this->setMargins($this->layout['pagePaddingLeft'], $this->layout['pagePaddingTop']);
   }

   /**
    * Render the invoice title.
    */
   protected function makeTitle()
   {
      $this->setY($this->layout['titleMarginTop']);
      $this->setFont('', 'B', $this->layout['titleFontSize']);
      $this->write($this->layout['titleCellHeight'], $this->content['title']);
      $this->setFont('', '', $this->layout['fontSize']);
   }

   /**
    * Render the text before the item table.
    */
   protected function makeBeforeInfo()
   {
      $this->setY($this->layout['contentMarginTopP1']);
      $this->renderInfo($this->content['beforeInfo'] ?? []);
   }

   /**
    * Render the item table.
    */
   protected function makeEntries()
   {
      $this->setY($this->getY() + $this->layout['entriesPaddingTop']);
      $widths = $this->layout['entriesColumnWidths'];
      $alignment = $this->layout['entriesColumnAlignment'];

      $makeHeader = function ($widths, $alignment) {
         $header = [
            $this->t('quantity'),
            $this->t('item'),
            $this->t('price'),
            $this->t('total'),
         ];
         $this->setFont('', 'b');
         foreach ($widths as $index => $width) {
            $this->cell($width, $this->layout['titleCellHeight'], utf8_decode($header[$index]), 'BT', 0, $alignment[$index]);
         }
         $this->setFont('', '');
         $this->ln();
      };

      $makeHeader($widths, $alignment);
      $currentY = 0;
      $nextY = $this->getY();

      foreach ($this->entries as $entry) {
         $this->setY($nextY);
         if ($this->needsNewPage()) {
            $this->newPage();
            $makeHeader($widths, $alignment);
            $nextY = $this->getY();
         }
         $currentY = $nextY;
         $this->cell($widths[0], $this->layout['contentCellHeight'], $this->numberFormat($entry['quantity']), '', 0, $alignment[0]);
         $this->multiCell($widths[1], $this->layout['contentCellHeight'], utf8_decode($entry['description']), 0, $alignment[1]);
         $nextY = max($nextY, $this->getY());
         $this->setXY($this->layout['pagePaddingLeft'] + $widths[0] + $widths[1], $currentY);
         $this->cell($widths[2], $this->layout['contentCellHeight'], $this->numberFormat($entry['price']), '', 0, $alignment[2]);
         $total = $entry['quantity'] * $entry['price'];
         $this->cell($widths[3], $this->layout['contentCellHeight'], $this->numberFormat($total), '', 1, $alignment[3]);
      }

      $this->setY($nextY);
      $this->setFont('', 'b');
      $this->cell($widths[0], $this->layout['contentCellHeight'], '', 'BT');
      $this->cell($widths[1], $this->layout['contentCellHeight'], '', 'BT');
      $this->cell($widths[2], $this->layout['contentCellHeight'], '', 'BT');
      $this->cell($widths[3], $this->layout['contentCellHeight'], $this->variables['total'], 'BT', 0, $alignment[3]);
      $this->setFont('', '');
   }

   /**
    * Render the text after the item table.
    */
   protected function makeAfterInfo()
   {
      $this->setY($this->getY() + $this->layout['entriesPaddingBottom']);
      $this->renderInfo($this->content['afterInfo'] ?? []);
   }

   protected function renderInfo($lines)
   {
      foreach ($lines as $line) {
         $this->write($this->layout['contentCellHeight'], $line);
         $this->ln();
         if ($this->needsNewPage()) {
            $this->newPage();
         }
      }
   }

   /**
    * Write flowing text. Supports HTML-like tags for <b></b> (bold), <i></i> (italic), <u></u> (underline) as well as variable substitutions (e.g. '{page}').
    *
    * @param int $h Line height.
    * @param string $txt Text
    * @param string $link URL or identifier returned by AddLink().
    */
   public function write($h, $txt, $link = '')
   {
      $txt = str_replace("\n",' ',$txt);
      $segments = preg_split('/(<\/?[uib]>)/', $txt, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
      foreach ($segments as $segment) {
         if ($segment === '<b>') {
            $this->setFont('', 'b');
         } elseif ($segment === '<i>') {
            $this->setFont('', 'i');
         } elseif ($segment === '<u>') {
            $this->setFont('', 'u');
         } elseif (strpos($segment, '</') === 0) {
            $this->setFont('', '');
         } else {
            foreach ($this->variables as $key => $value) {
               $segment = preg_replace("/\{{$key}\}/", $value, $segment);
            }
            parent::write($h, utf8_decode($segment), $link);
         }
      }
   }

   /**
    * Calculate the total price of all invoice items.
    *
    * @return float
    */
   protected function getTotal()
   {
      $total = 0;
      foreach ($this->entries as $entry) {
         $total += $entry['price'] * $entry['quantity'];
      }

      return $total;
   }

   /**
    * Add a new page.
    */
   protected function newPage()
   {
      $this->addPage();
      $this->variables['page'] = $this->pageNo();
      if ($this->templatePages > 0) {
         $templateIndex = $this->importPage(min($this->pageNo(), $this->templatePages));
         $this->useImportedPage($templateIndex);
      }

      $this->setXY($this->layout['pageNoX'], $this->layout['pageNoY']);
      $this->write(5, $this->t('page'));
      $this->setY($this->layout['contentMarginTopPX']);
   }

   /**
    * Determine if a new page is needed.
    *
    * @return bool
    */
   protected function needsNewPage()
   {
      return $this->getY() >= $this->layout['pageMaxY'];
   }

   /**
    * Get a translated string.
    *
    * @param string $key Translation key.
    *
    * @return string
    */
   protected function t($key)
   {
      return static::translate($this->lang, $key);
   }

   /**
    * Get the page layout array.
    *
    * @param array $override User-defined overrides.
    *
    * @return array
    */
   protected function getLayout($override = [])
   {
      return array_merge([
         // Y coordinate to start the address text.
         'addressMarginTop' => 52.5,
         // X coordinate to start the address text.
         'addressPaddingLeft' => 30,
         // Height of a regular text line.
         'contentCellHeight' => 5,
         // Y coordinate to start the content of the first page.
         'contentMarginTopP1' => 105,
         // Y coordinate to start the content of the pages following the first.
         'contentMarginTopPX' => 45,
         // Text alignment of the invoice entries columns.
         'entriesColumnAlignment' => ['R', 'L', 'R', 'R'],
         // Width of the invoice entries columns.
         'entriesColumnWidths' => [25, 105, 20, 25],
         // Space after the entry table.
         'entriesPaddingBottom' => 15,
         // Space before the entry table.
         'entriesPaddingTop' => 10,
         // Font to use for all text.
         'font' => 'arial',
         // Font size of regular text.
         'fontSize' => 12,
         // Y coordinate to initiate a pagebreak.
         'pageMaxY' => 260,
         // X coordinate of the page number text.
         'pageNoX' => 15,
         // Y coordinate of the page number text.
         'pageNoY' => 277,
         // Left and right padding of the page.
         'pagePaddingLeft' => 15,
         // Top and bottom padding of the page.
         'pagePaddingTop' => 12.5,
         // Height of a title text line.
         'titleCellHeight' => 6,
         // Font size of title text.
         'titleFontSize' => 15,
         // Y coordinate to start the invoice title on the first page.
         'titleMarginTop' => 85,
      ], $override);
   }

   /**
    * Get the page variables.
    *
    * @param array $variables User-defined variables.
    *
    * @return array
    */
   protected function getVariables($variables = [])
   {
      return array_merge($variables, [
         'total' => $this->numberFormat($this->getTotal()),
         'page' => $this->pageNo(),
      ]);
   }

   /**
    * Format a number string.
    *
    * @param int|float $number
    *
    * @return string
    */
   protected function numberFormat($number)
   {
      return number_format($number, 2, $this->t('decimalSeparator'), $this->t('thousandsSeparator'));
   }
}
