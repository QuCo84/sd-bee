<?php
/* *******************************************************************************************
 *  udbreak.php
 *
 *    Model-view server-side UniversalDoc break elements (UD_view, UD_zone, UD_page) 
 *
 */
class UDbreak extends UDelement
{
   private static $breakOrder = [ UD_part, UD_pageBreak, UD_subPart, UD_lineBreak];
   private static $openTags = [ UD_part=>"",  UD_pageBreak=>"", UD_subPart=>"", UD_lineBreak =>""];
   private static $openTagNames = [ UD_part=>"",  UD_pageBreak=>"", UD_subPart=>"", UD_lineBreak =>""];
   private $tagList = [UD_part =>"div.part", UD_pageBreak=>"div.page", UD_subPart=>"div.zone", UD_pageBreak=>"div.page", UD_lineBreak=>"br"];     
   private $pageNo;
   private $topPage;
   private $pageHeight;
   private $comments = false;
   public  $title;
   
   // UDbreak element construct
   function __construct( $datarow=null)
   {
       if ( $datarow == null)
       {
           $this->tag = "CLOSEALL";  
           return;
       }    
       parent::__construct( $datarow);
       $this->tag = $this->tagList[ $this->type];
       // Move to getHTMLattributes
       if ( ( $p1 = strpos( $this->tag, ".")))
       {
           // Obsolete $this->style .= ' '.substr( $this->tag, $p1+1);
           $this->tag = substr( $this->tag, 0, $p1);
       }
       if ( $this->type == UD_page) {
           $this->pageNo = $datarow['_pageNo'];
           $this->pageHeight = $datarow['_pageHeight'];
           $this->topPage = $datarow['_topPage'];
       }
       if (!$this->name ) $this->name ="Break_{$this->tag}_{$this->pageNo}";
       if ( $datarow[ '_forced']) $this->name ="Forced_".$this->name;
       $this->title = ( $datarow['_title']) ? $datarow['_title'] : $datarow[ 'nlabel'];
       if ( $this->ud && $this->type == UD_view && ( $this->mode == "edit" || !$this->lang || $this->lang == LF_env( 'lang')))
       {
           // Manage outline for Views (parts)
           if( $this->mode != "model" && $this->ud) $this->ud->loadSystemParameters( $datarow);
           $this->pager->manageOutline( "Add", $datarow);
           if ( $this->ud) $this->ud->loadSystemParameters( $datarow); // !!! remove ud
           $this->pager->managePages( $datarow); //, $this->ud);
           // Set style class according to Block no
           /*
           if ( $partdId > A0 && < AA) $style = "System Styles"
           $this->class = $style.' '.$this->class;
           */
       }
   }  // UDbreak->construct()  

   // Render UDbreak element
   function renderAsHTMLandJS( $active = true)
   {
      $r = "";
      $system = $this->extra[ 'system'];
      if ( $this->tag == "br") $r .= "<br>";
      elseif ( $this->tag == "CLOSEALL")
      {           
           if ( self::$openTags[ UD_subPart]) {
               $r .= "</".self::$openTags[ UD_subPart].">";
               if ( $this->comments) $r .= "<!-- close subpart -->";  
           }
           if ( self::$openTags[ UD_pageBreak]) {
              $r .= "</".self::$openTags[ UD_pageBreak].">";
              if ( $this->comments) $r .= "<!-- close page -->";
           }
           if ( self::$openTags[ UD_part]) {
              $r .= "</".self::$openTags[ UD_part].">";
              if ( $this->comments) $r .= "<!-- close part -->"; 
           }
          self::$openTags[ UD_pageBreak] = self::$openTags[ UD_subPart] = self::$openTags[ UD_part] = "";         
      }
      elseif ( in_array( $this->type, self::$breakOrder))
      {
           // Generate closing tag of previous break(s)
           // Look at break type in inverse order of hierachie
           $breakIndex = array_search( $this->type, self::$breakOrder);
           for( $i=2; $i>=$breakIndex; $i--)
           {
               $breakType = self::$breakOrder[ $i];
               // Part break must close sub-part and page break first etc 
               if ( self::$openTags[ $breakType])
               {
                   $r .= "</".self::$openTags[ $breakType].">";
                   if ( $this->comments) $r .= "<!-- close ".self::$openTagNames[ $this->type]." -->";
                   self::$openTags[ $breakType] = "";         
               }   
           }
           // 
           if ($this->name != UD_viewZoneClose)
           {
               // Store current type as open
               self::$openTags[ $this->type] = $this->tag;
               self::$openTagNames[ $this->type] = $this->name;
               // Manage visibility 
               $display = ( $this->mode == "none") ? false : true;
               // Generate opening tag
               if ( $this->comments) $r .= "<!-- {$this->tagList[ $breakType]}{$this->name}-->";
               $r .= "<{$this->tag} "; //  id=\"{$this->name}\""; 
               $r .= $this->getHTMLattributes( $active);
               // if ( $this->title == "Manage") $r .= ' contenteditable="true"';
               $r .= " ud_fields=\"stype nstyle\"";
               if ( isset( $this->pageNo)) 
                   $r.= " ude_pageno=\"{$this->pageNo}\" ud_pageTop=\"{$this->topPage}\" ud_pageHeight=\"{$this->pageHeight}\"";

               //$class = "";
               // if ( $this->style) $class .= $this->style;
               // if ( !$display) $class .= " hidden"; 
               // if ( $class) $r .= " class=\"".trim($class)."\"";
               $r .= ">";
               // Add caption to zones
               if ( $this->type == UD_zone && $this->mode == "edit" && $this->docType == "model") {
                   $r .= "<span class=\"caption\" style=\"width:100%;\">{$this->title}</span>";
               }
               // Terminate botlog here before it s rendered
               if ( $this->type == UD_view && $this->title == "botlog") UD_utilities::terminateAppPage( $this->ud);
           }    
      }   
      //return [ 'content'=>"", 'program'=>""];
      return [ 'content'=>$r, 'program'=>""];
       
   } // Udbreak->renderAsHTMLandJS
   
} // PHP class UDbreak