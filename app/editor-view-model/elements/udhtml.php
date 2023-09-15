<?php
/* *******************************************************************************************
 *  udhtml.php
 *
 *    UniversalDoc Model-View server side for editable HTML elements
 *
 */
if ( !class_exists( 'UDelement')) require_once( __DIR__."/../../tests/testenv.php");       
 
/**
 * UDhtml PHP class to handle HTML elements on server-side.
 */ 
 class UDhtml extends UDelement {
    protected $caption;  
    protected $elementName;     
    //private $content;
    private $saveable;
    //private $html; 
    private $textContent;  
    private $HTMLcontent; // for HTML lists saved as is
    private $MIMEtype;
    private $JSONcontent;
    private $params;

    // UDhtml construct
    function __construct( $datarow) {
        parent::__construct( $datarow);
        $this->caption = $datarow['_caption'];
        $this->elementName = $datarow['_elementName'];
        $this->content =  $datarow['_cleanContent']; 
        $this->saveable = $datarow['_saveable']; 
        $this->JSONcontent = $datarow[ '_JSONcontent'];
        if ( $this->JSONcontent && $this->content[0] == '{') { $this->MIMEtype = "text/json";}
        else {
            $this->MIMEtype = "text/html";
            $this->textContent = $datarow['_textContent'];
            if ( $this->textContent == []) $this->textContent = [ "HTML", $this->content, "..."];        
            $this->params = $datarow['_extra']['system'];
        }
        $this->requireModules( ['modules/editors/udehtml', 'ejs']);    // , 'ejs.js'
    } // UDhtml->construct()
    
    function renderAsHTMLandJS( $active=true) {
        $r = $js = "";
        $h = $this->type-UD_part+1;
        // Re-generate HTML
        $r .= "<div ";
        // Determine type & sub type
        $udType = "html";
        $udSubType = "";
        // 2DO stock subtype as HTML comment - must be easy to change templates
        switch ( $this->type) {
            case UD_emailTemplate2Col :
                $udSubType = "emailTemplate2col";
                break;
              
        }
        // Add generic attributes
        $saveStyle = $this->style;
        $r .= " ".$this->getHTMLattributes();
        $this->style = $saveStyle;
        // Add specif attributes
        $r .= " ud_type=\"{$udType}\" ud_subtype=\"{$udSubType}\" ud_mime=\"{$this->MIMEtype}\"";
        $r .= ">";
        if ( $this->MIMEtype == "text/json") {
            // JSON100 format
            $json = $this->JSONcontent;
            $name = $json[ 'meta'][ 'name'];
            $activeZoneName = $json[ 'meta'][ 'zone'];
            $lines = $json[ 'data'][ 'edit'][ 'value'][ 'value'];
            for ( $li=0; $li < LF_count( $lines); $li++) { 
                $line = $lines[ $li];
                $line = str_replace( 
                    ['&quot;', '<', '>'], 
                    [ '\"', '&lt;', '&gt;'], 
                    $line
                );
                $mode = $this->mode.$this->docType;
                if ( $mode != "edit3") {
                    // 2DO while
                    if ( strpos( $line, '&lt;!-- UD_include src=') === 0) {
                        // HTML include instruction ONLY if !MODEL EDIT mode
                        // Get filename
                        $p1 = strlen( '&lt;!-- UD_include src=');
                        $p2 = strpos( $line, '--&gt;');
                        $filename = substr( $line, $p1, $p2 - $p1 - 1);
                        // Get contents                        
                        $includeContent = UD_fetchResource( 'resources/'.$filename, $filenameb,$extb);
                        //$includeContent = UD_getResourceFileContents( $filename);
                        // Remove script and style tags
                        // Add lines
                        $includeLines = explode( "\n", $includeContent);
                        $newLines = [];
                        for ( $nli=0; $nli < $li; $nli++) { $newLines[] = $lines[ $nli];}
                        $newLines[] = "&lt;!-- {$filename} --&gt;";
                        for ( $nli=0; $nli < LF_count( $includeLines); $nli++) { 
                            $newLines[] = str_replace(  
                                ['&quot;', '<', '>'], 
                                [ '\"', '&lt;', '&gt;'],
                                $includeLines[ $nli]
                            );
                        }                        
                        for ( $nli = $li+1; $nli < LF_count( $lines); $nli++) { $newLines[] = $lines[$nli];}
                        // Replace line with INCLUDED comment
                        $line = "&lt;!-- {$fileName} --&gt;";
                        // Skip include lines
                        $li += LF_count( $includedLines);
                        $lines = $newLines;
                        // Delete HTML cache
                        $this->html = "";
                    } else $line = str_replace( '_onclick', 'onclick', $line);
                }
                $lines[ $li] = $line;                               
            }
            $json[ 'data'][ 'edit'][ 'value'][ 'value'] = $lines;  
            $content = JSON_encode( $json);
            $holder = $json[ 'meta']['name']."_object";
            $r .= "<div id=\"{$holder}\" class=\"object hidden\">{$content}</div>";
            /* 
            * Cached HTML may contain code before substitution
            * We need to only save HTML if no error at last init
            if ( $this->html) {
                $html = str_replace( '&quote;', "'", $this->html);
                $r .= "<div id=\"{$activeZoneName}\" class=\"activeZone\" ude_bind=\"{$holder}\">{$html}</div>";
            }
            */
            // 2DO Why not t->html != implode( "\n", lines) or html built during 1st loop
            //if ( $mode == "edit3" || strlen( $this->html) <= strlen( implode( "\n", $lines))) // #2247002 - avoid initialising HTML for newsletter if its already setup
            $js = "window.ud.initialiseElement('{$this->name}');\n";
        } else { 
            // DEPRECATED COMPOSITE MIXED format
            // ?DO Why not just send element's content ?
            $r .= "<span class=\"caption\">{$this->caption}</span>";
            // $r .= "<input type=\"button\" value=\"Save\" onclick=\"new UDapiRequest('UDtext','setChanged(/".$this->elementName."editZone/, 1);', event);\"  udapi_quotes=\"//\"/>";
            // Generate hidden HTML (data)
            $content = htmlentities( $this->content);
            // Remove edit link if not editing
            if ( $this->mode != "edit2" && $this->mode != "edit3") $content = str_replace( "{edit}", "", $content);
            $r .= "<div id=\"{$this->elementName}\" class=\"htmlObject\" ud_type=\"htmlObject\" style=\"display:none;\"";
            $r .= ">{$content}</div>";  
            // Setup edition client-side
            $js = "window.ud.initialiseElement('{$this->name}');\n";                
        }
        $r .= "</div>"; // Close saveable
        return [ 'content'=>$r, 'program'=>$js];
    } // UDhtml->renderAsHTMLandJS()
 
 } // PHP class UDhtml

// Auto-test
if ( $argv[0] && strpos( $argv[0], "udhtml.php") !== false)
{
    // CLI launched for tests
    echo "Syntax OK\n";
    // Create an UD
    require_once( __DIR__."/../../tests/testenv.php");
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
    echo "Test completed";
}  
?>