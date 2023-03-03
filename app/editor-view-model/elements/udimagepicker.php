<?php
/** 
* udimagepicker.php -- server-side imagepicker component
*
*
*/
if ( !class_exists( 'UDelement')) require_once( __DIR__."/../../tests/testenv.php");    

 class UDimagePicker extends UDelement  {     
    function __construct( $datarow)
    {
        parent::__construct( $datarow); 
        $this->requireModules( [ 
            'modules/pickers/udeimagepicker.js',
            'modules/editors/udetable.js', 
            'modules/connectors/udeconnector.js',
            'modules/connectors/udcsiteextract.js'
        ]);          
    } // UDimagePicker construct
    
    // Return array with content( HTML) and Javascript 
    function renderAsHTMLandJS( $active=true)
    {
        $r = "";
        // Read component's parameters in textra
        $filter = $this->extra[ 'system']['filter'];
        if ( !$filter) $filter = $this->extractTags( $this->label);
        elseif ( is_array( $filter)) $filter = implode( ',', $filter);
        $selection = $this->extra[ 'system'][ 'selection'];
        if ( !$selection) $selection = $this->fetchImages( $filter);
        $content = $this->content;        
        // Generate HTML
        $r .= "<div ";          
        $r .= $this->getHTMLattributes( $active);
        $r .= " data-ud-filter=\"{$filter}\" data-ud-selection=\"{$selection}\"";
        $r .= ">";
        if ( $this->label && !strpos( $content, '<span class="caption"')) $r .= "<span class=\"caption\">{$this->label}</span>";
        /*
        // Add image name edit zone
        $title = ($this->title) ? $this->title : "Caption";
        if ( $title && $this->mode == "edit") {
            $r .= "<span class=\"caption\">{$title}</span>";
        }
        // Add link edit zone 
        $links = HTML_getLinks( $this->content);
        if ( $links && $this->mode == "edit") {
            $r .= "<span class=\"caption\">{$links[0]}</span>";
        }
        */
        // Add image with or without link
        $r .= $content;
        $r .= "</div>";
        LF_debug( "Para length: ".$this->name.' '.strlen( $r), "UD element", 8);       
        return ["content"=>$r, "program"=>"API.initialiseElement( '{$this->name}');\n"];;
    } // UDimagePicker->renderAsHTMLandJS()
    
    function fetchImages( $filter) {
        $csv = "";
        if ( is_array( $filter)) $filter = implode( ',', $filter);
        $useFilter = strtoupper( $filter); 
        // Loop through available images 
        //$imagesData = LF_fetchNode( "Media--14--CD|5");        
        // 2DO use a clipboarder.php ou gallery.php getClips
        $imagesData = LF_fetchNode( "SimpleArticle--5--nname|Clipboard*|CD|5");      
        for ( $imagei=1; $imagei < LF_count( $imagesData); $imagei++) {
            // Check access
            // Check image
            $image = $imagesData[ $imagei][ 'gimage'];
            // Get tags
            //$tags = LF_subString( $imagesData[ $imagei][ 'ttext'], 'ud_tag="', '"');      
            $tags = HTML_getContentsByTag( $imagesData[ $imagei][ 'ttext'], 'div');
            $tags = ( $tags) ? explode( ',', strtoupper( $tags[0])) : $this->extractTags( $image, false); 
            $add = !( $filter);
            // Check tags vs filter
                 
            if ( $filter && $tags) {               
                foreach ($tags as $index=>$tag) {
                    if ( strlen( $tag) < 3) continue;
                    if ( $tag && strpos( $useFilter, strToUpper($tag)) !== false) $add = true;
                }
            } 
            // Add to csv
            if ($image && $add) $csv .= '/'.$image.',';
        }
        // If too many return function
        // Return csv
        return substr( $csv, 0, -1);
    } // UDimagePicker->fetchImages()

   /**
    * Return default tags generated from string
    */
    function extractTags( $str, $csv = true) {
        $r = [];
        if ( !$str) return ($csv) ? "" : [];
        $sep = " _-/.";
        // Remove accents
        $str = LF_removeAccents( $str);
        // Loop through string charcater by character
        $token = "";
        $lastCharIsUpper = false;
        for ( $chari=0; $chari < strlen( $str); $chari++) {
            $char = $str[ $chari];
            $charIsUpper = ( strtoupper( $char) == $char);
            // New token of case change or seperators
            if ( ($charIsUpper && !$lastCharIsUpper) || strpos($sep, $char) !== false ) {
                // 2DO filter or translate  useless tokens
                if ( $token) $r[] = $token;
                $token = ( strpos( $sep, $char) === false ) ? $char : "";
            } else $token .= $char;
            $lastCharIsUpper = $charIsUpper;
        }
        if ( $token) $r[] = $token;
        // Return CSV
        if ($csv) return implode( ",", $r); else return $r;
    } 
    
 } // UDimagePicker PHP class
 
global $UD_justLoadedClass;
$UD_justLoadedClass = "UDimagePicker";   

if ( $argv[0] && strpos( $argv[0], "udimagepicker.php") !== false)
{    
    // Launched with php.ini so run auto-test
    echo "Syntaxe udimagepicker.php OK\n";
    require_once( __DIR__."/../../tests/testenv.php");
    global $UD;
    $UD = new UniversalDoc( ['mode'=>"edit"]);
    $datarow = [ 'nname'=>"B010000020000000M", 'stype'=>UD_imagePicker, 'tcontent'=>""];
    $imagePicker = new UDimagePicker( $datarow);
   // echo $imagePicker->renderAsHTMLandJS( true)[ 'content']."\n";
    // Tag extraction
    $test = "Tag extraction on name";
    $tags = explode( ",", $imagePicker->extractTags( "MutuelleSenior"));
    if ( LF_count( $tags) == 2) echo "$test : OK\n"; else echo "$test : KO ".LF_count( $tags)." instead of 2\n";
    $test = "Tag extraction on file name";
    $tags = $imagePicker->extractTags( "upload/O0X0e1400_bannière-club-retraite-newslette.jpg", false);
    if ( LF_count( $tags) == 8) echo "$test : OK\n"; else echo "$test : KO ".LF_count( $tags)." instead of 8\n";
    global $debugTxt;
    echo $debugTxt;
    echo "Test completed\n";
} // end of auto-test