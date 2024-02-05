<?php
/* *******************************************************************************************
 *  udgraph.php
 *
 *    Handle UniversalDoc Model-View server side for Graph elements
 *   
 *
 *
 */
if ( !class_exists( 'UDelement')) require_once( __DIR__."/../../tests/testenv.php");      
 
class UDchart extends UDelement
 {
    protected $caption;  
    protected $elementName;     
    //private $content;
    private $saveable;
    private $JSONcontent; // for lists stored as JSON data
    private $textContent; // for CSV data
    private $HTMLcontent; // for HTML lists saved as is
    private $MIMEtype = "text/json";

    // UDlist construct
    function __construct( $datarow)
    {
        parent::__construct( $datarow);
        $this->caption = val( $datarow, '_caption');
        $this->elementName = val( $datarow, '_elementName');
        $this->saveable = val( $datarow, '_saveable');
        $this->JSONcontent = val( $datarow, '_JSONcontent');
        if ( $this->JSONcontent && $this->content[0] == '{') { $this->MIMEtype = "text/json";}
        elseif ( $this->JSONcontent) { $this->MIMEtype = "text/mixed";}
        else
        {
            // No JSON - shouldn't happen
            LF_debug( 'Chart element with no JSON', "ucchart.php", 5);
        }
        $this->requireModules( ['modules/udchart/udechart.js', 'modules/editors/udetable.js']);
    } // UDlist->construct()
    
    function renderAsHTMLandJS( $active=true)
    {
       $r = $h = $js = "";
       $r .= "<div ";      
       // Add generic attributes
       $r .= " ".$this->getHTMLattributes();
       // Add specif attributes
       $r .= " ud_type=\"chart\" ud_mime=\"{$this->MIMEtype}\">";
       // Add content with a structure that depends on MIME type
       if ( $this->MIMEtype == "text/json") {
            $content = JSON_encode( $this->JSONcontent);
            $r .= "<div id=\"{$this->JSONcontent[ 'meta']['name']}_object\" class=\"object hidden\">$content</div>";
            $js .=  "ud.initialiseElement('{$this->name}');\n"; 
       } elseif ( $this->MIMEtype == "text/mixed") {
            // Mixed content (old)
            // Get autosave mode from extra>system
            $autosave = "Off"; //val( $this->extra, 'system/autosave');
            $r .= "<span class=\"caption\">$this->caption</span>";
            // if ( !$autosave || $autosave == "Off")
            {
                // $r .= "<input type=\"button\" class=\"hidden\" value=\"Save\" onclick=\"window.ud.ude.setChanged( document.getElementById( '".$this->elementName."editZone'), 1);\" />";
                $configIcon = '/'.LF_env( 'WEBDESK_images')['Config_s'];
                $onclick = "window.ud.ude.changeClass( 'hidden', '{$this->elementName}editZone', '');";
                $r .= "<span class=\"rightButton\"><a href=\"javascript:\" onclick=\"{$onclick}\"><img src=\"{$configIcon}\"></a></span>";
                //$r .= "<input type=\"button\" value=\"Configure\" onclick=\"window.ud.ude.changeClass( 'hidden', '{$this->elementName}editZone', '');\" />";
                $autosave = "Off";
            }
            // else $staged = "off";
            // Generate hidden HTML (data)
            $r .= "<div id=\"{$this->elementName}\" class=\"chartObject\" ude_editZone=\"{$this->elementName}viewZone\" style=\"display:none;\"";
            $r .= ">".JSON_encode( $this->JSONcontent)."</div>"; 
            // Put waiting GIF which can then be deleted after init            
            // Generate javascript            
            $js .= "setTimeout( function() { ud.initialiseElement('{$this->name}');}, 2000);\n"; 
        }
        // Close saveable div
        $r .= "</div>";        
        return [ "content"=>$r, "hidden"=>$h, "program"=>$js];
    } // UDchart->renderAsHTMLandJS()
 
 } // PHP class UDchart

// Auto-test
if ( isset( $argv) && strpos( $argv[0], "udchart.php") !== false)
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
