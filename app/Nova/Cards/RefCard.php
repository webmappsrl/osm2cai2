<?php

namespace App\Nova\Cards;

use Abordage\HtmlCard\HtmlCard;

class RefCard extends HtmlCard
{
   private $osmTags;

   public function __construct($osmTags)
   {
      $this->osmTags = $osmTags;
      parent::__construct();
   }


   /**
    * The width of the card (1/2, 1/3, 1/4 or full).
    */
   public $width = '1/2';

   /**
    * The height strategy of the card (fixed or dynamic).
    */
   public $height = 'fixed';

   /**
    * Align content to the center of the card.
    */
   public bool $center = true;

   /**
    * Html content
    */
   public function content(): string
   {
      if (empty($this->osmTags) || is_null($this->osmTags)) return 'No data';
      $ref = $this->osmTags['ref'] ?? '/';
      $refRei = $this->osmTags['ref:REI'] ?? '/';

      return <<<HTML
       <h1 class='text-4xl'>REF:$ref (CODICE REI: $refRei)</h1><p class='text-lg text-gray-400 text-center'>Settori: TBI</p> 
       HTML;
   }
}
