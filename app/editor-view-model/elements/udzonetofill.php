<?php
/* *******************************************************************************************
 *  udzonetofill.php
 *
 *    Model-view server-side UniversalDoc HTML zone filled by app elements (UD_zoneToFill) 
 *    The UDzoneToFill class handles HTML elements that are filled by the application and can be edited
 *
 */
 // PHP class UDnonEditable
 class UDzoneToFill extends UDelement
 {
    private $url;
    public $title;
    function __construct( $datarow)
    {
        parent::__construct( $datarow);
        $this->title = $datarow[ '_title'];
        $lines = explode( "\n", $datarow[ 'tcontent']);
        $content = "";
        for ( $i=0; $i < LF_count( $lines); $i++) $content .= trim( str_replace( [ "&quo"."te;"], [ "'"], $lines[ $i]));
        $this->content = $content;
        // $this->content = str_replace( [ "\n", "&quo"."te"], [ "", "'"], $datarow[ 'tcontent']);
    } // UDnonEditable construct
    
    function renderAsHTMLandJS( $active=true)
    {
        $r = "";
        if ( $this->extra[ 'system']['onlyprod'] == "yes" && LF_env( 'cache') > 10 && $this->mode != "edit") {
           return [ 'content'=>$r, 'program'=>""];
        }
        $h = $this->type-UD_part+1;
        // 2DO if url grab and display
        $r .= "<div "; //id=\"{$this->name}\" class=\"zone\" ude_input=\"none\">"; 
        $r .= $this->getHTMLattributes();
        if ( $this->type == UD_filledZone) { $r .= " ude_input=\"none\"";}
        $r .= ">";
        $r .= $this->content;      
        $r .= "</div>";
        // $r .= ".."; // handle for insertign after 2DO TEMP
        return [ 'content'=>$r, 'program'=>""];
    } // UDexternal->renderAsHTMLandJS()
 
 } // PHP class UDzoneToFill



// Deprecated
// PHP class UDnonEditable
class UDnonEditable extends UDelement
{
   private $url;
   public $title;
   function __construct( $datarow)
   {
       parent::__construct( $datarow);
       $this->title = $datarow['_title'];
   } // UDnonEditable construct
   
   function renderAsHTMLandJS( $active=true)
   {
      $r = "";
      $h = $this->type-UD_part+1;
      $r .= "<div "; // id=\"{$this->name}\" contenteditable=\"false\">";  
      $r .= $this->getHTMLattributes();       
      // 2DO make SAFE variable SERVER (UD_utilities)
      if ( $this->content && strpos( $this->content, ' ') === false)
           $r .= file_get_contents( "http://dev.rfolks.com/".$this->content);  
      $r .= "</div>";
      return [ 'content'=>$r, 'program'=>""];
   } // UDnonEditable->renderAsHTMLandJS()

} // PHP class UDnonEditable