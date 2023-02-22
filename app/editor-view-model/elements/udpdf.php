<?php
// PHP class UDpdf
class UDpdf extends UDelement
{
   private $pdfurl;
   private $pdfuri;    
   private $height;
   
   function __construct( $datarow)
   {
       parent::__construct( $datarow);
       $this->pdfuri = $this->content;
       $this->pdfurl = LF_env( 'FTPbase')."/".$this->pdfuri;
       // 2DO get / initialise height
       $this->height = 790;
   } // UDpdf construct
   
   function renderAsHTMLandJS( $active=true)
   {
      $r = $js = "";
      $h = $this->type-UD_part+1;
      global $LF_env;
      // $fullFilename = "https://www.rfolks.com/upload/trb01/".LF_substitute( $this->pdfurl, $LF_env);
      $fullFileName = LF_substitute( $this->pdfurl, $LF_env);
      $r .= "<object id=\"{$this->name}\" type=\"application/pdf\"";
      $r .= " data=\"{$fullFileName}\"";
      $r .= " width=\"600px\"  height=\"790px\">";
      $r .= "</object>";
      return [ 'content'=>$r, 'program'=>$js];
   } // UDpdf->renderAsHTMLandJS()
}