<?php
/* *******************************************************************************************
 *  udlist.php
 *
 *    Handle UniversalDoc Model-View server side for List elements
 *   
 *
 *
 */
if ( !class_exists( 'UDelement')) require_once( __DIR__."/../../tests/testenv.php");    
 
/**
 *  Class to handle a list element on server-side. 
 */ 
class UDlist extends UDelement
 {
    // Handled by element
    private $JSONcontent; // for tables stored as JSON data
    private $MIMEtype = "text/json";
    protected $caption;  
    protected $elementName;     
       
    //private $content;
    private $saveable;
    private $textContent; // for CSV data
    private $HTMLcontent; // for HTML lists saved as is

    // UDlist construct
    function __construct( $datarow)
    {
        if ( function_exists( "UD_alignContent")) UD_alignContent( $datarow);
        parent::__construct( $datarow);
        /*if ( $this->isJSON100) return;*/
        /* DEPRECATED Code */        
        $this->caption = $datarow['_caption'];
        $this->elementName = $datarow['_elementName'];
        $this->saveable = $datarow['_saveable'];
        $this->JSONcontent = $datarow['_JSONcontent'];
        if ( !$this->JSONcontent)
        {
            if ( $datarow['_textContent'])
            {
                // Convert text lines to JSON 
            
            }         
            elseif ( $this->content[0] == '<') // or strpos( c, "span")
            {
                /* HTML OK but may be caption+object */
                // HTML text (backward compatibility with version--4)
                $this->MIMEtype = "text/html";
                $this->HTMLcontent = $datarow['_cleanContent'];
                if ( !$this->HTMLcontent) $this->HTMLcontent = $this->content;
                $this->HTMLcontent = str_replace( ["\n"], ["<br>"], $this->HTMLcontent);
            }
            else
            {
                $this->MIMEtype = "text/text";                
                if ( strpos( $this->content, "\t") > 0) $items = explode( "\t", $this->content);
                elseif ( strpos( $this->content, ";") > 0) $items = explode( ";", $this->content);
                elseif ( strpos( $this->content, ",") > 0) $items = explode( ",", $this->content);
                else $items = ['...'];
                // Quick
                $this->HTMLcontent = "";
                $this->HTMLcontent .= '<span class="caption">List caption</span>';
                $this->HTMLcontent .= '<div id="'.$this->name.' class="listObject hidden"><ul id="'.$this->name.'list" class="listStyle1"><thead>';
                for ( $itemi=0; $itemi < LF_count( $items); $itemi++)  $this->HTMLcontent .= "<li>{$items[ $itemi]}</li>";
                $this->HTMLcontent .= '</ul></div>';        
            }    
        }
        else {
            // 2DO if JSON and Caption (_caption)  then version before -2.7 => convert mixed format to 100% JSON => content = json
            $this->MIMEtype = "text/json";
        } 
        $this->requireModules( ['modules/editors/udelist.js']);
    } // UDlist->construct()
    
    function renderAsHTMLandJS( $active=true)
    {
       /*if ( $this->isJSON100) return parent::_construct( $active);
       else {*/        
       $r = $js = "";
       $r .= "<div ";      
       // Add generic attributes
       $r .= " ".$this->getHTMLattributes();
       // Add specif attributes
       $r .= " ud_type=\"list\" ud_mime=\"{$this->MIMEtype}\"";
       // Add content with a structure that depends on MIME type
       // 2DO JSON100 and html. JSON100 add thtml       
       if ( $this->MIMEtype == "text/html")
       {
           // Content is text/html content
           // Display table directly in saveable element, and allow direct saving
           $r .= ">";
           $r .= $this->HTMLcontent;
           $r .= "</div>";
           // Generate javascript           
           // $js .= "ud.initialiseElement('{$this->name}');\n";            
       }    
       else
       {
            // Content is already setup   
            $r .= ">";
            if ( $this->content[0] == '{') {
               $name = $this->JSONcontent[ 'meta']['name'];
               $holder = $name."_object";
               $r .= "<div id=\"{$holder}\" class=\"object hidden\">{$this->content}</div>";
               if ( $this->html) 
                   $r .= "<div id=\"{$name}editZone\" class=\"editzone\" ude_bind=\"{$holder}\">{$this->html}</div>";
            } else $r .= $this->content; // 2DO Bug Names not regenerated
            /*
            // Get autosave mode from extra>system
            $autosave = "On"; //$this->extra['system']['autosave']; // 2DO extra yes
            $r .= "<span class=\"caption\">$this->caption</span>";
            if ( !$autosave || $autosave == "Off")
            {
                $r .= "<input type=\"button\" value=\"Save\" onclick=\"window.ud.ude.setChanged( document.getElementById( '".$this->elementName."editZone'), 1);\" />";
                $autosave = "Off";
            }
            else $staged = "off";  // 2DO not sent to client
            // Generate hidden HTML (data)
            $r .= "<div id=\"{$this->elementName}\" class=\"listObject\" style=\"display:none;\"";
            if ( $this->JSONcontent)
                $r .= ">".JSON_encode( $this->JSONcontent)."</div>"; 
            else
                $r .= ">{$this->content}</div>";  // if text/html  
            */            
            $r .= "</div>";
            // Generate javascript 
            if ( strpos( $this->content, "ude_formula"))
                $js .= "setTimeout( function() { /*ud.initialiseElement('{$this->name}');*/ud.ude.calc.redoDependencies();}, 2000);\n";                 
            // $js .= "ud.initialiseElement('{$this->name}');\n"; 
        }
       return [ "content"=>$r, "program"=>$js];
    } // UDlist->renderAsHTMLandJS()

    
    //TEMPORARY ! this must be done client-side for refreshing when multiple users
    function renderJSONcontentAsHTMLlist()
    {
       // Get decoded JSON expected as nested name array parts (thead/tbody) > rows > cells 
       $json = $this->JSONcontent;     
       if ( !$json) return ""; 
       // Create HTML table
       $list = "";
       $list .= "<ul id=\"{$this->elementName}editlist\" class=\"{$this->style}\" ude_bind=\"{$json->bind}\">";
       // Part loop
       foreach( $json as $key=>$element)
       { 
            if ( $key[0] == '_') continue;
            $value = $element['value'];
            $formula = $element['ude_formula'];
            $class = $element['class'];
            $list .= "<li";
            if ( $formula) $list .= " ude_formula=\"{$formula}\"";
            $list .= ">{$element['value']}</li>";
       } // end of list element loop
      $list .= "</list>";
      return $list; 
    } // UDlist->renderJSONcontentAsHTMLlist()
 
 } // PHP class UDlist

// Auto-test
if ( isset( $argv[0]) && strpos( $argv[0], "udlist.php") !== false)
{
    // CLI launched for tests
    echo "Syntax OK\n";
    // Create an UDlist element   
    require_once( __DIR__."/../../tests/testenv.php"); 
    $data = [ 'nname'=>"B01000000001000000M", "stype"=>13, "tcontent"=>"<ul id=\"myList\"><li>One</li><li>two></li></ul>"];
    UD_utilities::analyseContent( $data, $captionIndexes);
    $list = new UDlist( $data);
    echo "\nTest HTML\n";
    echo $list->renderAsHTMLandJS()['content'];
    // JSON
    $data2 = [ 'nname'=>"B01000000002000000M", "stype"=>13, "tcontent"=>'{ "1":"one","2":"two", "3":"three"}'];
    UD_utilities::analyseContent( $data2, $captionIndexes);
    $list2 = new UDlist( $data2);
    echo "\nTest JSON\n";    
    echo $list2->renderAsHTMLandJS()['content'];
    echo "\nTest completed\n";
}