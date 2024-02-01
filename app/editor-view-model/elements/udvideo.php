<?php
/* *******************************************************************************************
 *  udvideo.php
 *
 *    UniversalDoc Model-View server side for video element
 *   
 *
 *
 */
if ( !class_exists( 'UDelement')) require_once( __DIR__."/../../tests/testenv.php");       

/**
 * UDvideo class - element to show a video 
 *
 */ 
class UDvideo extends UDelement
{
    private $videoSrc = "";
    private $sourceList = "";
    private $mime = "";
    private $playMode="";
    private $videoWidth=640;
    private $videoHeight=480;
    private $videoPoster = "";
    private $elementName;
    private $error = "";
    
    function __construct( $datarow)
    {
        parent::__construct( $datarow);
        $content = $datarow[ 'tcontent'];
        $jsonParams = HTML_getContentsByTag( $content, 'div')[0];
        if ( $jsonParams)
        {
            $params = JSON_decode( $jsonParams, true);
            if ( !$params)
            {
                $this->error = "Configuration erronnée";
                return;
            }
            $src = $params['src'];
            $this->sourceList = $src;
            $this->playMode = $params[ 'mode'];
            $this->videoWidth = $params[ 'width'];
            $this->videoHeight = $params[ 'height'];
            $this->videoPoster = $params[ 'poster'];
            $this->elementName = str_replace( " ", "_", HTML_getContentsByTag( $content, 'span')[0]); 
        }
        else $src = $content;
        $sources = explode( ',', $src);
        $src = $sources[ 0];
        $ext = substr( $src, -4, 4);
        switch ( $ext)
        {
            case ".mp4" :
                // Look for webm version
                // Setup conversion if required
                // Use substitue video in the meantime
                break;
            case "webm" :
                // OK Use this as source
                $this->videoSrc = $src;
                $this->mime = "video/webm";
                break;                
        }
        $this->requireModules( ['modules/players/udevideo.js']);

    }
    
    function renderAsHTMLandJS( $active=true)
    {
        $r = $js = $h = "";
        if ( $this->videoSrc && $this->mime)
        {
            $r .= "<div ";
            $r .= $this->getHTMLattributes();
            $r .= " ud_type=\"video\" style=\"text-align:center;\"";
            $r .= ">";
            if ( $this->error) $r .= $this->error;
            $r .= $this->content; // trying direct content rather thann reconstitution
            $r .= "<video";
            if ( $this->elementName)
            {
                $r .= " id=\"{$this->elementName}_viewZone\"";
                /*
                $js = "let {$this->elementName}Element = ud.dom.element( '{$this->elementName}');\n";
                $js .= "{$this->elementName}Element.addEventListener( 'ended', function ( event) {console.log( 'Video completed, event detected');},false);\n";
                */
            }    
            // 2D if width = % generate js
            /*
            if ( $this->videoWidth == 'auto')
            {
                $js .= "ud.dom.attr( {$this->elementName}Element, 'width', ud.dom.attr( {$this->elementName}Element.parentNode, 'computed_width'));";
                $js .= "ud.dom.attr( {$this->elementName}Element, 'width', ud.dom.attr( {$this->elementName}Element.parentNode, 'computed_width')*480/640);";
            }
            */
            $r .= "width=\"{$this->videoWidth}\" height=\"{$this->videoHeight}\" ";  
            // ude_bind=\"{$this->elementName}_object\"            
            if ( $this->playMode) $r.= " {$this->playMode}";
            if ( $this->videoPoster) $r .= " poster=\"{$this->videoPoster}\"";
            $r .= " ud_srclist=\"{$this->sourceList}\" ud_srcindex=\"1\"";
           // $r .= " style=\"margin-top:-50px;\"";
            $r .= ">";
            // 2DO if autoplay set src
            $r .= " <source src=\"\" type=\"{$this->mime}\">"; // {$this->videoSrc}
            $r .= "Sorry, video not supported on this browser";
            $r .= "</video>";
            $r .= "</div>";
            // Auto-play when visible
            $js .= "window.ud.initialiseElement('{$this->name}');\n";    
            // $js .= "let video = ud.dom.element( {$this->name};\n";            
        }
       return [ 'content'=>$r, 'hidden'=>$h, 'program'=>$js];
    }
} // PHP class UDvideo
 

// Auto-test
if ( isset( $argv[0]) && strpos( $argv[0], "udvideo.php") !== false)
{
    // CLI launched for tests
    echo "Syntax OK\n";
    // Create an UD
    require_once( __DIR__."/../../tests/testenv.php");
    require_once( __DIR__."/../../ud-utilities/udutilities.php");    
    /*
    $data = [ 'nname'=>"B01000000001000000M", "stype"=>13, "tcontent"=>"<ul id=\"myList\"><li>One</li><li>two></li></ul>"];
    UD_utilities::analyseContent( $data, $captionIndexes);
    $table = new UDlist( $data);
    echo "\nTest HTML\n";
    echo $table->renderAsHTMLandJS()['content'];
    // JSON
    $data2 = [ 'nname'=>"B01000000002000000M", "stype"=>13, "tcontent"=>'{ "1":"one","2":"two", "3":"three"}'];
    UD_utilities::analyseContent( $data2, $captionIndexes);
    $table2 = new UDlist( $data2);
    echo "\nTest JSON\n";    
    echo $table2->renderAsHTMLandJS()['content'];
    */
    // Add some elements
    // Render
    echo "Test completed\n";
}  
?>