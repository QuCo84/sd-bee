<?php
/* *******************************************************************************************
 *  udgraphic.php
 *
 *    Handle UniversalDoc Model-View server side for Graphic elements
 *   
 *
 *   23/12/2019 QUCO - création from universaldoc.php as breakdown/factorising
 */ 
if ( !class_exists( 'UDelement')) require_once( __DIR__."/../../tests/testenv.php");    

class UDgraphic extends UDelement
 {
    protected $caption;
    protected $elementName;    
    // private $content;
    private $saveable;
    private $JSONcontent;
    private $textContent; // for CSV data
    private $drawing;
    private $MIMEtype = "text/json";
    
    function __construct( $datarow)
    {
        parent::__construct( $datarow);
        $this->caption = $datarow['_caption'];
        $this->elementName = $datarow['_elementName'];        
        $this->drawing =  $datarow['_cleanContent']; 
        $this->saveable = $datarow['_saveable'];
        $this->JSONcontent = $datarow['_JSONcontent'];          
        if ( $this->JSONcontent && $this->content[0] == '{') { $this->MIMEtype = "text/json";}
        elseif ( $this->JSONcontent) { $this->MIMEtype = "text/mixed";}
        else
        {
            // No JSON - shouldn't happen
            LF_debug( 'Graphic element with no JSON', "udgraphic.php", 5);
        }
        $this->requireModules( ['modules/editors/udedraw.js']);       
    } // UDgraphic->construct()
    
    function renderCanvas()
    {
        $r = ""; 
        if ( !$this->elementName) return $r;
        $containerStyle = ( $this->style) ? $this->style : "graphicStyle1";
        $containerStyle  .= " graphic";
        // Graphic edit zone
        $r .= "<div id=\"{$this->elementName}editZone\" class=\"editZone\" ud_type=\"graphic\" ude_bind=\"{$this->elementName}\" ude_autosave=\"Off\">";
        // Styles edit zone
        $r .= "<div id=\"{$this->elementName}editZone_styles\">";   
        $r .= "</div>";
        // Canvas container DIV
        $r .= "<div id=\"{$this->elementName}editZone_canvases\" class=\"$containerStyle\">";
        // Background CANVAS (contains graphic objects behind current one) // width=\"736\" height=\"600\"
        $r .= "<canvas width=\"736\" height =\"600\" class=\"{$this->style} canvas\" style=\"z-index:1;\"> </canvas>";
        // Work CANVAS (contains current graphic object)
        $r .= "<canvas width=\"736\" height=\"600\" class=\"{$this->style} canvas\" style=\"z-index:2;\"> </canvas>";
        // Guide CANVAS
        $r .= "<canvas width=\"736\" height=\"600\" class{$this->style} canvas\" style=\"z-index:3;\"> </canvas>";
        // Foreground CANVAS (contains graphic objects in front of current one)
        $r .= "<canvas width=\"736\" height=\"600\" class=\"{$this->style} canvas\" style=\"z-index:4;\">Your browser does not support the canvas element.</canvas>";
        $r .= "</div>";
        $r .= "</div>";
        return $r;
    } // UDgraphic->renderCanvas()
    
    function renderStyleZone()
    {
        $r = "";
        $r .= "<div id=\"{$this->elementName}styleZone\" ude_autosave=\"off\">";
        $r .= "</div>";
        return $r;
    }
    
    function renderAsHTMLandJS( $active=true)
    {
        $r = $h = $js = "";
        // Generate visible HTML
        $r .= "<div ";      
        // Add generic attributes
        $r .= " ".$this->getHTMLattributes();
        // Add specif attributes
        $r .= " ud_type=\"graphic\" ud_mime=\"{$this->MIMEtype}\""; // ude_autosave=\"Off\" UD_hidden=\"{$this->elementName}\"
        $r .= ">";
        if ( $this->MIMEtype == "text/json") {
            $content = JSON_encode( $this->JSONcontent);
            $r .= "<div id=\"{$this->JSONcontent[ 'meta']['name']}_object\" class=\"object hidden\">$content</div>";
            $js .=  "ud.initialiseElement('{$this->name}');\n"; 
       } elseif ( $this->MIMEtype == "text/mixed") {
            $r .= "<span class=\"caption\">$this->caption</span>";
            $r .= "<input type=\"button\" value=\"Save\" onclick=\"window.ud.ude.setChanged( document.getElementById( '".$this->elementName."editZone'), 1);\" />";
            // Generate hidden HTML (data) -- this is 
            // 2DO use $h on next 2 lines when universaldoc.js and AJAX_fetch is ready for 2 streams to avoid line jumps when editing caption
            $r .= "<div id=\"$this->elementName\" class=\"object graphicObject hidden\" ud_type=\"graphic\" ude_autosave=\"Off\" ";
            $r .= "ude_editzone=\"{$this->elementName}editZone\"";
            $r .= ">{$this->drawing}</div>"; 
            // $r .= "</div>";
            //$r .= $this->renderStyleZone();        
            $r .= $this->renderCanvas(); // Empty canvases ! will be filled by JS
       }
        $r .= "</div>";        
        // Generate javascript
        // $js .= "ud.updateGraphic('{$this->elementName}editZone');\n"; 
        // $js .= "ud.initialiseElement('{$this->name}');\n"; 
        return [ "content"=>$r, "hidden"=>$h, "program"=>$js];
    } // UDgraphic->renderAsHTMLandJS()
     
 } // PHP class UDgraphic
 
 // Auto-test
if ( isset( $argv[0]) && strpos( $argv[0], "udgraphic.php") !== false)
{
    // CLI launched for tests
    echo "Syntax OK\n";
    /*
    // Create an UD
    require_once( __DIR__."/../../tests/testenv.php");
    require_once(  __DIR__.'/../../ud-view-model/ud.php');     
    $data = [ 'nname'=>"B01000000001000000M", "stype"=>12, "tcontent"=>"<table id=\"myTable\"><thead><tr><th>col1</th><th>col2</th></tr></thead><body><tr><td>data1</td><td>data2</td></tr></tbody></table>"];
    $table = new UDtable( $data);
    echo $table->renderAsHTMLandJS()['content'];
    */
    echo "Test completed";    
}