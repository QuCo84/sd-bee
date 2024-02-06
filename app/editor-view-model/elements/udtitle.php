<?php

// udtitle.php  PHP class UDtitle
class UDtitle extends UDelement
{

   public  $title;
   private $subTitle;
    
   function __construct( $datarow)
   {
       parent::__construct( $datarow);
       $this->title =  val( $datarow, '_title');
       $this->subTitle = val( $datarow, '_subTitle');
       if ( $this->pager) $this->pager->manageOutline( "Add", $datarow);        
   } // UDtitle construct
   
   function renderAsHTMLandJS( $active=true)
   {
      $r = "";
      $h = $this->type-UD_chapter+1;
      if ( !strpos( $this->style, 'title is')) $this->style .= " title is-".$h;
      $r .= "<h$h";        
      // Add generic attributes
      $r .= " ".$this->getHTMLattributes( $active);
      $r .= ">";
      $r .= $this->content;  
      $r .= "</h$h>";
      return [ 'content'=>$r, 'program'=>""];
   } // UDtitle->renderAsHTMLandJS()

} // PHP class UDtitle