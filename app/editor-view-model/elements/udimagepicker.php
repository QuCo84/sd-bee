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
        $r = $js = "";
        // Read component's parameters in textra
        $filter = val( $this->extra, 'system/filter');
        if ( !$filter) $filter = $this->extractTags( $this->label);
        elseif ( is_array( $filter)) $filter = implode( ',', $filter);
        $selection = val( $this->extra, 'system/selection');
        if ( !$selection) $selection = $this->fetchImages( $filter);
        $content = $this->content;        
        // Generate HTML
        $r .= "<div ";          
        $r .= $this->getHTMLattributes( $active);
        $r .= " data-ud-filter=\"{$filter}\" data-ud-selection=\"{$selection}\"";
        $r .= ">";
        if ( $this->label && strpos( $content, '<span class="caption"') === false) $r .= "<span class=\"caption\">{$this->label}</span>";
        $js .= "API.initialiseElement( '{$this->name}');\n";
        // Dropzone function
        $newPasteOid = 'SimpleArticle--5-278-5-0';
        if ( !strpos( $content, 'Dropzone')) {
            // Modify content to have drop zone over image
            $content = <<< EOT
<div style="position:relative;height:340px;">
    <div style="position:absolute; top:0; left:0; z-index:1"> 
        $content
    </div>
    <div id="{$this->name}-dropzone" class="Dropzone" ud_type="Dropzone" style="position:absolute; top:0; left:0; z-index:10; height:340px; background-color:unset;">           
        <form id="{$this->name}-drop-form" method="post" enctype="multipart/form-data" accept-charset="UTF-8" name="INPUT_LINKS_script" action="" class="dropzone" style="width:100%;height:340px;">
            <input type ="hidden" name="form" value="INPUT_dropImage" />  
            <input type ="hidden" name="input_oid" value="$newPasteOid" />       
            <input type ="hidden" name="nname" value="{$this->name} test" />               
            <input type ="hidden" name="domainAndPath" value="{$this->name}" /> 
        </form>
    </div>
</div>
EOT;
        // Activate dropzone 
        // 'https://unpkg.com/dropzone@5/dist/min/dropzone.min.js'
        $js .= <<< EOT
API.initialiseElement( '{$this->name}');
{
    let dropFormId = "{$this->name}-drop-form";
    let dropForm = API.dom.element( dropFormId);
    if ( dropForm && typeof Dropzone == "undefined") {
        let src = "/upload/smartdoc/vendor/dropzone.css";
        let styleTag = document.createElement( 'style');
        styleTag.id = "dropzone_css";                
        styleTag.onerror = function() { debug( {level:2}, src, ' is not available'); }
        styleTag.onload = function() { debug( {level:2}, src, "loaded"); }
        styleTag.src = src;  
    }
    require( ['vendor/dropzone/dropzone'], function()  {
        let dropForm = API.dom.element( dropFormId);
        if ( dropForm && typeof dropForm.dropzone == "undefined") {
            let myDropzone = new Dropzone(
                "#"+dropFormId, 
                { 
                    url: "/webdesk//AJAX_clipboardTool/e|paste/", 
                    paramName: "gimage", 
                    dictDefaultMessage: 'Glisser vos fichiers images ici',
                    //dictDefaultMessage: "Â Â Â Â Â <br />Â Â <br />Â "
                }
            );
            myDropzone.on( "complete", function( file) {
                // Update image
                let picker = $$$.dom.element( '{$this->name}');
                if ( picker) {
                    let img = $$$.dom.element( 'img', picker); 
                    if ( img) {
                        // Set existing image's src attribute
                        let p = $$$.getUDparam( 'image-source');
                        if ( p) img.src = 'https://' + p + '/' + file;  
                    }
                }
            }); 
        }
    });   // end of require
}
// end of activate dropzone
EOT;
        }

        // Add image with or without link
        $r .= $content;
        $r .= "</div>";
        LF_debug( "Para length: ".$this->name.' '.strlen( $r), "UD element", 8);       
        return ["content"=>$r, "program"=>$js]; //"API.initialiseElement( '{$this->name}');\n");
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
            // IDEA src => tags
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
            $char = val( $str, $chari);
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

if ( isset( $argv) && strpos( $argv[0], "udimagepicker.php") !== false)
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