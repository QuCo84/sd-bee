<?php
/**
 * The UDpager PHP class provides server-side outline and page management.
 *
 * <p>The UDpager class is initiated by the UniversalDoc class. The method managePages() is called for each element.
 * It uses parameters in the textra field of each element to take a best guess of where page boundaries are. The module also
 * handles the documents outline (hierachy of views, zones and titles) with the manageOutline() method.</p>
 *
 * @package VIEW-MODEL
 */
class UDpager
{
    // App
    private $ud;
    // Paging
    public  $autoPageBreak = [];
    public  $views = [];
    private $enablePaging = true;    
    public  $docPageHeight = 0;    
    public  $partPageHeights = [];
    public  $pageHeight = 0; 
    private $currentPage = 0;
    public  $currentPageHeight = 9000;
    public  $currentPageOffsetTop = 0;
    private $currentPageWidth = 0;
    private $skip = false;    // 2DO rename paginate and ! logic
    private $skipZone = false;
    private $mmToPx = 3.77;  // multiply by 72/25.4
    public  $styleWidths = [];
    public  $styleHeights = [];
    // Outline
    private $currentPart = "default";
    private $currentSubPart = "default";
    public  $outline=[];
    private $outlineStack = [];
    private $typeByLevel = [];
    private $currentSubpartOIDlength = 0;
    public  $nextPartIds = [];
    // Detecting late paging paramters
    public  $latePageHeightDetected = false;
    private $viewsWithNoPaging = [];
    private $layoutsWithNoPaging = [];
    /*public  $nextPartIds = [
        "default"=>"02", 
        "doc"=>"02", "app"=>"30", "models"=>"40", "language"=>"50", "data"=>"60", "clipboard"=>"70",
        "system"=>"A0", "pageStyle"=>"AA", "middleware"=>"B0",
        "style"=>"C0", "program"=>"D0", "dir"=>"E0", "public"=>"U0"
    ];*/
   // public $elements = [];

    function __construct( $ud, $enable, $pageHeight)
    {
        $this->ud = $ud;
        // Initialise nextPartIds
        $viewTypes = UD_getDbTypeInfo( UD_part, 'subTypes');
        foreach ( $viewTypes as $viewType) {
            $viewTypeInfo = UD_getExTagAndClassInfo( "div.part.{$viewType}");
            $this->nextPartIds[ $viewType] = $viewTypeInfo[ 'nextId'];
        }
    }
   /**
    *  Insert page break if needed  
    */
    function managePages( &$elementData, $udForPageBreaks = null)
    {
        // 2DO if part then get pageHeight
        // 2DO #632 set local pageHeight if pageHeight is 0 class for page  
        // Page breaks are inserted before the next element depending on its height
        // Best logic would be to create the element, manage pages and then add element
        // so we could use a  getHeight method or public attribute
        if ( !$this->enablePaging) return null;
        $type = (int) $elementData['stype'];
        $force = false;
        $skipOnce = false;
        $pageElement = true;
        $style = $elementData['nstyle'];
        if ( in_array( $type, [ UD_page])) { $force = true; $pageElement = false;}
        elseif ( $type == UD_view) {
            // Decide if view is displayable and needs paging
            $this->skip = true;
            $partName = $elementData[ 'nlabel'] ? mb_strtoupper( $elementData[ 'nlabel']) : mb_strtoupper( $elementData[ '_title']);
            $mode = $elementData[ '_mode'];
            if ( $mode == "edit") {
                $this->skip = false;
            } elseif ( $mode == "model") {
                // No access to model unless specified in list of views to display
                if ( LF_count( $this->views)) {         
                    $this->skip = !in_array( $partName, $this->views);
                } 
            } else { // if ( $this->ud->mode == "display") {
                // Display mode - always display default,   and public mode              
                if ( !LF_count( $this->views)) {
                    $this->skip = ( strpos( $partName, mb_strtoupper( $elementData[ '_defaultPart'])) === false); 
                } else {         
                    $this->skip = !in_array( $partName, $this->views);
                }
            }
            if ( !$this->skip) {            
                // Fix page height for this view
                $this->currentPageHeight = 9000; // to force new page if paging
                if ( isset( $this->partPageHeights[ $elementData[ '_title']])) {
                    // Page height specified for this view
                    $this->pageHeight = $this->partPageHeights[ $elementData[ '_title']];
                    LF_debug( "Using pageHeight for view {$elementData[ '_title']}", "UDpager", 8);
                } elseif ( strpos( $style, "LAY_") !== false) {
                    // Get height for layout style
                    $styles = explode( ' ', $style);
                    LF_debug( "Searching pageHeight for style {$style}", "UDpager", 8);
                    for ( $stylei=0; $stylei < LF_count( $styles); $stylei++) {
                        $wstyle = $styles[ $stylei];
                        if ( isset( $this->styleHeights[ $wstyle])) { // && $this->styleHeights[ $styles[ $stylei]]) {
                            $this->pageHeight = $this->styleHeights[ $wstyle];
                            LF_debug( "Using pageHeight for style {$wstyle}", "UDpager", 8);
                            break;
                        } elseif ( strpos( $wstyle, 'LAY_') !== false) {
                            $this->layoutsWithNoPaging[] = $wstyle;
                        }
                    }                    
                } else { 
                    $this->pageHeight = $this->docPageHeight;
                    $this->viewsWithNoPaging[] = $elementData[ '_title'];
                }                
            }
            return null;
        } elseif ( $type == UD_zone && !$this->skip) { 
            // 2DO Could be all elements
            // Take into account zone widths
            $width = 100;         
            // 2DO if closeZone skipZone = false
            $this->skipZone = true;
            if ( isset( $this->styleWidths[ $style])) $width = $this->styleWidths[ $style];
            // Ignore if new currentPageWidth < 100%
            $currentWidth = $this->currentPageWidth;
            if ( $width) $this->currentPageWidth += $width;
            if ( $currentWidth) {
                if ( $this->currentPageWidth <= 100) $this->skipZone=true;                
                else {
                    $this->currentPageWidth = 0;
                    $this->skipZone = false;
                }    
            }
            // 2DO if no height in style then ignore zone for paging 
            if ( !$this->skipZone && isset( $this->styleHeights[ $style])) {
                $this->skip = true;
                $this->currentPageHeight += $this->styleHeights[ $style];
            }
            // $skipOnce = true;
            // 2DO add test skipZOne on l 139
        } elseif ( in_array( $type, $this->autoPageBreak)) { $force = true;}
        if ( !$this->pageHeight && !$force) { return null;}
        $averageMargin = 18;
        $returnElement = null;
        if ( $force || ( !$this->skip && !$skipOnce)) {
            if ( !$elementData['textra']) $elementData['textra'] = '{"height":16}';
            // Decode JSON extra data
            $extra = JSON_decode( LF_preDisplay( 't', $elementData['textra']), true);
            // 2DO if $this->reCompute page Heights or if too far away (twice last element's height then ignore) 
            $top = max( $extra['offsetTop'], $this->currentPageOffsetTop);
            $height = $extra['height'];
            $relTop = $top - $this->currentPageOffsetTop;
            $relTop2 = 0;
            $margins = $averageMargin;
            if ( isset( $extra[ 'marginTop']) && is_numeric($extra[ 'marginTop']) && (int) $extra[ 'marginBottom'] > $margins) {
                $margins = (int) $extra[ 'marginTop'];
            }
            if ( isset( $extra[ 'marginBottom']) && is_numeric($extra[ 'marginBottom']) && (int) $extra[ 'marginBottom'] > $margins) {
                   $margins = (int) $extra[ 'marginBottom'];
            }
            // 2DO use relTop only if height extravagant = too small or too high for 
            /*if ($relTop > $this->currentPageHeight && ($relTop - $this->currentPageHeight) < 300)
            {
                $this->currentPageHeight = $relTop + $height;
                $relTop2 = $top;
                $averageMargin = ($margins + $relTop - $this->currentPageHeight)/3;
            }
            else*/  $this->currentPageHeight += $height + $margins;
            $padding = 38;
            if ( $force || $this->currentPageHeight >= ( $this->pageHeight - $padding) )
            {
                // Insert new page
                // Trace
                LF_debug( "Adding element id: none name:Page{$this->currentPage} type:Page break(21) {$top} {$this->currentPageHeight}", "UD_pager", 8);                        
                // Add a page break
                if( $pageElement) {
                    $tempDatarow = [
                        'stype'=>UD_page, '_forced'=>$force, '_pageNo'=>$this->currentPage, 
                        '_pageHeight'=>$this->pageHeight, '_topPage'=>$this->currentPageOffsetTop
                    ];
                    if ( $udForPageBreaks) $udForPageBreaks->addElement( new UDbreak( $tempDatarow));
                    else $returnElement = new UDbreak( $tempDatarow); 
                }    
                $this->currentPage++;
                global $pageManager_topPage;
                $pageManager_topPage = $this->currentPageOffsetTop = $top; //$relTop2;            
                $this->currentPageHeight = $margins;
                $elementData[ '_pageNo'] = $this->currentPage;
                $elementData[ '_pageHeight'] = $this->pageHeight;
                $elementData[ '_topPage'] = $this->currentPageOffsetTop;
            } else {
                LF_debug( "Page Height with element id: {$elementData[ 'nname']} ({$height} {$margins}) is {$this->currentPageHeight}", "UD_pager", 5);
            }
        }
        return $returnElement;
    } // UniversalDoc->managePages()
    
   /**
    * Take note of style widths and heights of zones. 
    */
    function noteStyleWidthAndHeight( $style, $width="", $height="") {
        if ( $width !== "") $this->styleWidths[ $style] = $width;
        if ( $height !== "") $this->styleHeights[ $style] = $height;
        if ( in_array( $style, $this->layoutsWithNoPaging)) $this->latePageHeightDetected = true;
    } // UD_pager->noteStyleWidth()
    
    function grabDocPageHeight( $style) {
        $style = str_replace( [ "&nbsp;", "&amp;nbsp;"], [ " ", " "], $style);
        $height = "";
        $retHeight = 0;
        $viewClass = "";
        $p1 = 0;
        $safe = 10;
        while ( ( $p1 = strpos( $style, "div.page", $p1)) && $safe--)
        {
            $p3 = strrpos( $style, "div.part", $p1 - strlen( $style));
            $p4 = strrpos( $style, "\n", $p1 - strlen( $style));
            if ( $p3 && $p3 > $p4 ) {
                $p4 = strpos( $style, " ", $p3);
                $p3 += strlen( "div.part.");              
                if ( $p4 > $p3) $viewClass = substr( $style, $p3, $p4-$p3);
            }
            $p1 = $p1 + 8 + 3;
            $p2 = strpos( $style, '}', $p1);
            $w = ($p2) ? substr( $style, $p1, $p2 - $p1) : "";
            $p1b = strpos( $w, "height:");
            $p2b = strpos( $w, ";", $p1b);
            if ( $p1b && $p2b) { $height = substr( $w, $p1b+8, $p2b-$p1b-8);}
            $margin = "";
            $p1b = strpos( $w, "padding:");
            $p2b = strpos( $w, ";", $p1b);
            if ( $p1b && $p2b) { $margin = substr( $w, $p1b+9, $p2b-$p1b-9);}
            if ( $height && strpos( $height, "mm") > 0) { // mm test to eliminate auto in system
                $height = $this->dimStrToPx( $height);
                if ( $viewClass) $this->noteStyleWidthAndHeight( $viewClass, "", $height);
                // TRY 221025 - fix doc's default page height if no view class specified OR if default height no set yet
                // else  $this->docPageHeight = $height;
                if ( !$viewClass || !$this->docPageHeight)  $this->docPageHeight = $height;
                /*
                if ( $margin) {
                   $margins = explode( ' ', $margin);
                   $r -= $this->dimStrToPx( $margins[ 0]); //Top
                   $r -= $this->dimStrToPx( $margins[ 2]); // Bottom
                }
                */
                LF_debug( "UDPager grabbed doc's page height {$this->docPageHeight} from $height and $margin for $viewClass", "UD", 8);    
                if ( !$retHeight) $retHeight = $height;            
            }  
        }   
        return $retHeight;            
    } // UDpager->grabDocPageHeight()
    
    function dimStrToPx( $mmStr) {
        $px = 0;
        if ( strpos( $mmStr, "mm") > 0) { 
            $px = (int) (((int) substr( $mmStr, 0, -2)) * $this->mmToPx);
        } elseif ( strpos( $mmStr, "px") > 0) {
            $px = (int) substr( $mmStr, 0, -2);
        }
        return $px;
    }

    
    function noteStyleWidthsAndHeights( $style)
    {
        $style = str_replace( [ "&nbsp;", "&amp;nbsp;"], [ " ", " "], $style); 
        // Grab doc's page height
        $this->grabDocPageHeight( $style);
        // Save dimensions for div.zone elements
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
                $this->noteStyleWidthAndHeight( $className, $width, $height);
                LF_debug( "UDpager noting dims $width $height for $className", "UD", 8);                
            }
        }
        return true;
    } // UDpager->extractStyleWidths()
  
    
    
    
    
    
    // Manage outline centralised function
    /* Action "Add" : set currentPart and subPart so elements are added at the right place
     * Action "Render" : return DIV tag for HTML stream
     * Action "Outline" : return Outline list 
     */
    function manageOutline( $action, $data = null)
    {
        switch ( $action)
        {
            case "Add" : 
              return $this->addToOutline( $data);
              //break;
            case "Render" :
              return $this->renderOutlineAsList();
              //break;              
        }
    } // UniversalDoc->manageOutline()

    function formNb32( $nb) { $nb = base_convert($nb, 10, 32); return substr( "00".strToUpper( $nb), strlen($nb));}    

   /**
    * Store element in app's outline
    */    
    function addToOutline( $elementData)
    {   
        // Get name of part or sub-part 
        $label =  ( $elementData['_title']) ? $elementData['_title'] : $elementData['nlabel'];
        $type = (int) $elementData['stype']; 
        $includeInOutline = false;
        if ( $type == UD_view) {
            // Set current part pointer
            $this->currentPart = mb_strtoupper( $label);
            // Determine next part nb for same type
            $nb = base_convert( substr( $elementData['nname'], 1, 2), 32, 10);
            $viewTypes = UD_getDbTypeInfo( $type, 'subTypes');
            foreach ( $viewTypes as $viewType) {
                $viewTypeInfo = UD_getExTagAndClassInfo( "div.part.{$viewType}");
                if ( $nb >= $viewTypeInfo[ 'blockNoMin']*32 && $nb <=  $viewTypeInfo[ 'blockNoMax']*32-1) {
                    UD_setResource( "UD_tagAndClassInfo", "div.part.{$viewType}/nextId", $this->formNb32($nb+1));
                    $this->nextPartIds[ $viewType] = $this->formNb32($nb+1);
                    break;
                }
            }
            // Decide if view is displayable and therefore must be in outline
            $mode = $elementData[ '_mode'];
            if ( $mode == "edit") {
                $includeInOutline = true;
            } elseif ( $mode == "model") {
                // No access to model unless specified in list of views to display
                if ( LF_count( $this->views)) {         
                    $includeInOutline = in_array( strToUpper( $this->currentPart), $this->views);
                } else $includeInOutline = false;
            } elseif ( $label && $label != '...') { // if ( $this->ud->mode == "display") {
                // Display mode - always display default, 
                if ( !LF_count( $this->views)) {
                    $includeInOutline = ( $this->currentPart == $this->ud->defaultPart);
                } else {         
                    $includeInOutline = in_array( strToUpper( $this->currentPart), $this->views);
                }
            }
            // Check not already in outline
            $present = false;
            foreach ( $this->outline as $key=>$val) {
                if ( $val[ 'label'] == $label) { 
                    $present = true; 
                    break;
                }
            }
            $this->nextPartIds['default'] = $this->nextPartIds['doc'];            
        }
        // Leave here if not be included in outline
        if ( !$includeInOutline || $present) { return $this->outline;}
        
        // Add to outline        
        $level = $type - 3;       
        if ( $level > 0 && $level < 4) // 2DO Let through pther levels but skip if parent not sent otherwise ... tabs
        {    
            $blockName = LF_preDisplay( 'n', $elementData['nname']); // , 1, 12);
            // $useLevel = $pFirstT = strpos( $blockName, "T",1);
            $stackLen = LF_count( $this->outlineStack);
            while ( $stackLen && $level <= $this->outlineStack[ $stackLen - 1]['l'])
            { 
                array_pop( $this->outlineStack); 
                $stackLen--; 
            }  
            $shortOid = "UniversalDocElement--".explode( '--',  $elementData['oid'])[1];            
            array_push( $this->outlineStack, array( 'n'=>$blockName, 'l'=>$level, 'oid'=>$shortOid, 'label'=>$label, 'children'=>[]));
            $stackLen++;          
            $w = $this->outlineStack;
            switch ( $stackLen)
            {
                case 1:
                    $this->outline[$w[0]['n']] = $w[0];
                    break;
                case 2:        
                    if ( isset( $this->outline[$w[0]['n']])){               
                        $this->outline[$w[0]['n']]['children'][$w[1]['n']] = $w[1];
                    }
                    break;
                case 3:
                    if ( isset( $this->outline[$w[0]['n']])){               
                        $this->outline[$w[0]['n']]['children'][$w[1]['n']]['children'][$w[2]['n']] = $w[2];
                    }
                case 4:
                    if ( isset( $this->outline[$w[0]['n']])){               
                        $this->outline[$w[0]['n']]['children'][$w[1]['n']]['children'][$w[2]['n']]['children'][$w[3]['n']] = $w[3];
                    }
                    break;
            } 
       }
       return $this->outline;
    } // UniversalDoc->addToOutline()

    // Build a UL element with tree effects using css classes caret and nested 
    // 2DO onlick = server request
    function renderOutlineAsList( $root = null, $rootName = "")
    {
        $r = "";
        if ( !$rootName)
        {
            $r .= "<ul id=\"outline\" class=\"caret\">\n";
            $r .= "<li>Document:<ul id=\"document-outline\"><li>doc<ul>\n";
            $root = $this->outline;
        } 
/*        
        else
        {
            // DEPRECATED ?
            $blockNo = base_convert( substr( $rootName,1, 2), 32, 10);
            if ( $blockNo > 10 * 32 && $blockNo < 11 *32) $r .= "</li><li>System styles:<ul>\n";
            $r .= "<ul class=\"nested\">\n";
        }
*/        
        //$lastBlockType = "doc";
        $lastBlockLabel = "";
        $viewTypes = UD_getExTagAndClassInfo( "div.part")[ 'subTypes'];
        $lang = LF_env( 'lang');
        foreach ($root as $key=>$value)
        {
            $attr = " id=\"".$value['n']."_li\" class=\"viewoutline\" target_id=\"".$value['n']."\" ud_oid=\"".$value['oid']."\"";
            $label = $value['label'];
            $onclick = "window.ud.ude.focus('".$value['n']."');";
            if ( substr( $value['n'], 3, 10)== "0000000000" )
            {    
              $onclick = "new UDapiRequest('Outline','showOneOfClass(/{$value['n']}/,1);', event);";
              $attr .= " udapi_quotes=\"//\"";
            }  
            if ( $key == '_root' )
            { 
                if (!$rootName) $r .= "<li id=\"top\">".$label."</li>\n";
            }  
            else
            {
                $blockNo = base_convert( substr( $value[ 'n'],1, 2), 32, 10); // $rootName              
                foreach ( $viewTypes as $viewType) {
                    $viewTypeInfo = UD_getExTagAndClassInfo( "div.part.{$viewType}");
                    if ( $blockNo >= $viewTypeInfo[ 'blockNoMin']*32 && $blockNo <=  $viewTypeInfo[ 'blockNoMax']*32-1) {
                        $blockLabel = ( $lang != 'EN') ? $viewTypeInfo[ 'label_'.$lang] : $viewTypeInfo[ 'label'];
                        break;
                    }
                }
                /*                
                // Inverse table in udelement.php
                $blockType = "doc";
                if ( $blockNo >=  3 * 32 && $blockNo <  4 * 32) $blockType = "app";
                elseif ( $blockNo >=  4 * 32 && $blockNo <  5 * 32) $blockType = "model pages, chapters or sections";
                elseif ( $blockNo >=  5 * 32 && $blockNo <  6 * 32) $blockType = "translations";
                elseif ( $blockNo >=  6 * 32 && $blockNo <  7 * 32) $blockType = "data";
                elseif ( $blockNo >= 10 * 32 && $blockNo < 11 * 32) $blockType = "system styles";
                elseif ( $blockNo >= 11 * 32 && $blockNo < 12 * 32) $blockType = "intermediate model's styles and programs";
                elseif ( $blockNo >= 12 * 32 && $blockNo < 14 * 32) $blockType = "application's styles and programs";
                elseif ( $blockNo >= 30 * 32 && $nb < 31 * 32) $blockType = "public";
                elseif ( $blockNo >= 31 * 32 && $blockNo <= 32 * 32) $blockType = "manage";
                if ( $blockType != $lastBlockType) {
                    $lastBlockType = $blockType;
                    $r .= "</ul></li><li>".LINKSAPI::startTerm.$blockType.LINKSAPI::startTerm.":<ul>\n";
                }
                */
                if ( $lastBlockLabel && $blockLabel != $lastBlockLabel) {
                    $lastBlockLabel = $blockLabel;
                    $r .= "</ul></li><li>{$blockLabel}:<ul>\n";
                } else { $lastBlockLabel = $blockLabel;}
                $r .= "<li $attr onclick=\"$onclick\">{$label}</li>\n";
               // if ( LF_count( $value['children'])>0) $r .= $this->renderOutlineAsList( $value['children'], $key);
            }  
        }
        $r .= "</ul></li></ul></li></ul>\n";
        return $r;
   } // UniversalDoc->renderOutlineAsList()
   
    
} // end of PHP class UDpager

// Auto-test
if ( isset( $argv[0]) && strpos( $argv[0], "udpager.php") !== false)
{
    // CLI launched for tests
    echo "Syntax OK\n";
    echo "Test completed\n";
}
?>