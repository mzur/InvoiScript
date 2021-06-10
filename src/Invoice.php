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
         $this->cell(0, $this->layout['contentCellHeight'], $line, 0, 1);
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
         $this->cell($widths[0], $this->layout['contentCellHeight'], number_format($entry['quantity'], 2), '', 0, $alignment[0]);
         $this->multiCell($widths[1], $this->layout['contentCellHeight'], utf8_decode($entry['description']), 0, $alignment[1]);
         $nextY = max($nextY, $this->getY());
         $this->setXY($this->layout['pagePaddingLeft'] + $widths[0] + $widths[1], $currentY);
         $this->cell($widths[2], $this->layout['contentCellHeight'], number_format($entry['price'], 2), '', 0, $alignment[2]);
         $total = $entry['quantity'] * $entry['price'];
         $this->cell($widths[3], $this->layout['contentCellHeight'], number_format($total, 2), '', 1, $alignment[3]);
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
         'addressMarginTop' => 52.5,
         'addressPaddingLeft' => 30,
         'contentCellHeight' => 5,
         'contentMarginTopP1' => 105,
         'contentMarginTopPX' => 45,
         'entriesColumnAlignment' => ['R', 'L', 'R', 'R'],
         'entriesColumnWidths' => [25, 105, 20, 25],
         'entriesPaddingBottom' => 15,
         'entriesPaddingTop' => 10,
         'font' => 'arial',
         'fontSize' => 12,
         'pageMaxY' => 260,
         'pageNoX' => 15,
         'pageNoY' => 277,
         'pagePaddingLeft' => 15,
         'pagePaddingTop' => 12.5,
         'titleCellHeight' => 6,
         'titleFontSize' => 15,
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
         'total' => number_format($this->getTotal(), 2),
         'page' => $this->pageNo(),
      ]);
   }
}
