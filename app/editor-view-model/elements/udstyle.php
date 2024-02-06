<?php
/* *******************************************************************************************
 *  udstyle.php
 *
 *    Model-view server-side UniversalDoc style elements (UD_style) 
 *
 */
class UDstyle extends UDelement
{ 
    public  $pageHeight;
    public  $pageWidth;
    private $textContent; 
    private   $mmToPx = 3.77;  // multiply by 72/25.4
    
    // UDstyle construct just get textContext
    function __construct( $datarow, $helper=false)
    {
        parent::__construct( $datarow);
        $this->textContent =  val( $datarow, '_textContent');
        $len = LF_count( $this->textContent);
        LF_debug( "Style element with $len lines", "UDelement", 5);
        // Get page dimensions and store in element
        if ( $this->pager) {
            $this->pager->noteStyleWidthsAndHeights(implode( "\n", $this->textContent));
            /*
            $this->pageHeight = $this->getPageHeight( $this->pager->docPageHeight, $helper);
            $this->pageWidth = "";     
            // communicate page and zone dimensions to pager if not in onTheFly mode             
            $pageHeight = $this->pageHeight;
            if ( $pageHeight !== false) {
                if ( $this->lastClassHeight) {
                    $pageWidth = "";
                    $this->pager->noteStyleWidthAndHeight( $this->lastClassHeight, $pageWidth, $pageHeight);
                    LF_debug( "Noted for style {$this->lastClassHeight} {$pageWidth} {$pageHeight}", "UDelement", 5);
                } else {
                    $this->pager->docPageHeight = $pageHeight;
                }
            }
            // Extract style widths for paging zones (not 100% width)
            $this->noteStyleWidthsAndHeights( $this->pager);
            */
        }
        // Add editing zone 
        if ( !$helper && !$this->noAuxillary && ( $this->mode == "edit" || $this->extra [ 'system']['show'] == "yes")) 
            $this->ud->addElement( new UDtext( $datarow));
        
    } // UDstyle construct

    // Return array with content( HTML) and Javascript 
    function renderAsHTMLandJS( $active=true)
    {
        $content = $js = "";
        if ( is_array( $this->textContent)) $style  = implode( "\n", $this->textContent);
        else $style = "";
        // Remove "CSS" and replace nbsp with spaces
        $style = str_ireplace( [ "CSS\n", "&nbsp;", "&amp;amp;nbsp;", "&quot;"], [ "", " ", " ", '"'], htmlentities( $style));
        // $style = str_replace( "\u{c2a0}", " ", $style); // didn't work
        $style = UD_processPseudoCSSattributes( $style);
        if( LF_env('req') == "AJAX")
        {    
            //$style = str_replace( " ", "", $style); // to remove spaces
            $content = "<div id=\"{$this->name}_load\" class=\"hidden\">{$style}</div>";
            $js = "window.ud.api.run([\"loadStyle('{$this->name}_load');\"]);\n"; //$ud
            $style = "";
        }         
       return ['content'=>$content, 'program'=>$js, 'style'=>$style];
    } // UDstyle->renderAsHTMLandJS()
    
    function getPageHeight( $defaultReturn, $helper=false)
    {
        if ( $this->textContent) $style = implode( "\n", $this->textContent);
        else return $defaultReturn;
        $style = str_replace( [ "&nbsp;", "&amp;nbsp;"], [ " ", " "], $style);
        $r = $defaultReturn; 
        $height = "";
        if ( ( $p1 = strpos( $style, "div.page")))
        {
            $this->lastClassHeight = "";
            $p3 = strrpos( $style, "div.part", $p1 - strlen( $style));
            $p4 = strrpos( $style, "\n", $p1 - strlen( $style));
            if ( $p3 && $p3 > $p4 ) {
                $p4 = strpos( $style, " ", $p3);
                $p3 += strlen( "div.part.");              
                $this->lastClassHeight = substr( $style, $p3, $p4-$p3);
            }
            $w = substr( $style, $p1+8+3); //, 50);
            $p1 = strpos( $w, "height:");
            $p2 = strpos( $w, ";", $p1);
            if ( $p1 && $p2) { $height = substr( $w, $p1+8, $p2-$p1-8);}
            $margin = "";
            $p1 = strpos( $w, "padding:");
            $p2 = strpos( $w, ";", $p1);
            if ( $p1 && $p2) { $margin = substr( $w, $p1+9, $p2-$p1-9);}
            if ( $height && strpos( $height, "mm") > 0) { // mm test to eliminate auto in system
                $r = $this->dimStrToPx( $height);
                /*
                if ( $margin) {
                   $margins = explode( ' ', $margin);
                   $r -= $this->dimStrToPx( $margins[ 0]); //Top
                   $r -= $this->dimStrToPx( $margins[ 2]); // Bottom
                }
                */
                LF_debug( "Grabbing pageHeight $r from $height and $margin", "UD", 8);                
            }  
        }
        return $r;         

    } // UDstyle->getPageHeight()
    
    function dimStrToPx( $mmStr) {
        if ( strpos( $mmStr, "mm") > 0) { 
            $px = (int) (((int) substr( $mmStr, 0, -2)) * $this->mmToPx);
        } elseif ( strpos( $mmStr, "px") > 0) {
            $px = (int) substr( $mmStr, 0, -2);
        }
        return $px;
    }

    
    function noteStyleWidthsAndHeights( $pager)
    {
        if ( $this->textContent) $style = implode( "\n", $this->textContent);
        else return false;
        $style = str_replace( [ "&nbsp;", "&amp;nbsp;"], [ " ", " "], $style);        
        // Look for div.subpart
        $p1 = 0;
        $safe = 20;
        while ( ($p1 = strpos( $style, "div.zone.", $p1)) && $safe--) {
            $rule = substr( $style, $p1+strlen( "div.zone."), 200);
            $p1 += strlen( "div.zone.");
            $pw1 = strpos( $rule, ' ');
            // Get class name
            $className = substr( $rule, 0, $pw1);
            // Look for width in %
            $width = 0;
            $pw1 = strpos( $rule, "width:");
            $pw2 = strpos( $rule, ";", $pw1);
            $widthStr = substr( $rule, $pw1+strlen("width:"), $pw2-$pw1-strlen("width:"));
            if ( $widthStr[ strlen( $widthStr)-1] == "%") $width = (int) substr( $widthStr, 0, -1);
            // Look for height in pixels
            $height = 0;
            $pw1 = strpos( $rule, "height:");
            $pw2 = strpos( $rule, ";", $pw1);
            $heightStr = substr( $rule, $pw1+strlen("height:"), $pw2-$pw1-strlen("height:"));
            if ( substr( $heightStr, -2) == "px") $height = (int) substr( $heightStr, 0, -2);
            // Inform pager if either width or height found            
            if ( $width || $height) {
                $pager->noteStyleWidthAndHeight( $className, $width, $height);
                LF_debug( "Noting dims $width $height", "UD", 8);                
            }
        }
        return true;
    } // UDstyle->extractStyleWidths()
    
 } // UDstyle PHP class
