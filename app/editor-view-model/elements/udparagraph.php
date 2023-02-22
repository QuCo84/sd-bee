<?php
/* *******************************************************************************************
 *  udparagraph.php
 *
 *    Handle UniversalDoc paragraph elements (UD_para) 
 *    Model-View server side for List elements
 *
 */

class UDpara extends UDelement
{     
   // Return array with content( HTML) and Javascript 
   function renderAsHTMLandJS( $active=true)
   {
       $r = "";
       $r .= "<p "; // id=\"{$this->name}\"";
       //if ( $this->style) $r .= " class=\"$style\"";          
       $r .= $this->getHTMLattributes( $active);
       $r .= ">";
       // Make should only inline elements placed in <p> element
       $content = HTML_stripTags($this->content, $this->inline);
       // Test disactivating clicks here then move up to element
       if ( 
           $this->mode.$this->docType == "edit3" 
           || ( $this->mode == "edit2" && strpos( $r, 'data-ude-edit="on"'))  
       ) {
           $content = HTML_disactivateClicks( $content);
       } else { 
           // In display mode, make sur user-defined buttons are clickable
           $content = str_replace( '_onclick', 'onclick', $content);
       }    
       $content = $this->renameAttr( $content);
       $r .= $content;
       $r .= "</p>";
       LF_debug( "Para length: ".$this->name.' '.strlen( $r), "UD element", 8);       
       return ["content"=>$r, "program"=>""];;
   } // UDpara->renderAsHTMLandJS()
} // UDpara PHP class
