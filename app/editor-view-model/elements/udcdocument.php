<?php
require_once( __DIR__."/udconnector.php");
/**
 * Universal Document element that extracts data from another document
 * and presents it as a table.
 * <p> Planned evolution : list of element ids and extraction within a saveable element </p>
 *
 */
 class UDconnector_document extends UDconnector {
    
    private $targetDocOID="";
    private $targetElementId="";
    
    function __construct( $datarow) {
        $this->subType = "document";
        $defaultParameters = [
            'ready' => "no",
            'docOID' => "UniversalDocElement--21-...",
            'elementId' => "service",          
        ];
        $this->defaultParameters = $defaultParameters;
        parent::__construct( $datarow);
        $params = $this->JSONparameters;
        if ( $params && $params[ 'ready'] == "yes" ) {
            // Get useful info
            $this->targetDocOID = $params[ 'docOID'];         
            $this->targetElementId = $params[ 'elementId'];
        }  
    }

    function renderAsHTMLandJS( $active=true) {
        $r = $js = "";
        // Send as DIV
        $r .= "<div ";      
        // Add generic attributes
        $r .= " ".$this->getHTMLattributes();
        // Add specif attributes
        $r .= " ud_type=\"connector\"";
        if ( $this->JSON) {
            // 100% JSON format
            $name = val( $this->JSON, 'meta/name');
            $holder = $name."_object";
            $r .= "ud_mime=\"text/json\">";
            $content = JSON_encode( $this->JSON);
            $r .= "<div id=\"{$holder}\" class=\"object connectorObject hidden\">{$content}".HTML_closeDiv;
            if ( $this->html) {
                $r .= "<div id=\"{$name}editZone\" class=\"editZone\" ude_bind=\"{$holder}\">{$this->html}</div>"; 
                $js .= "API.updateTable( '{$name}');";
            }
        } else { 
            // DEPRECATED MIXED FORMAT
            $r .= " ud_mime=\"text/mixed\">";
            if ( $this->subType) { $r .= " ud_subtype=\"{$this->subType}\"";}
            $r .= ">";            
            // Caption in SPAN
            $r .= "<span class=\"caption\">{$this->caption}</span>";
            // JSON parameters DIV (hidden)
            $r .= "<div id=\"{$this->elementName}_parameters\" class=\"textObject hidden\" ud_type=\"textObject\" >";
            $r .= htmlentities( JSON_encode( $this->JSONparameters));
            $r .= "</div>";
            // JSON data table DIV (hidden)
            $r .= "<div id=\"{$this->elementName}_data\" class=\"tableObject hidden\" ud_type=\"jsonObject\">";
            if ( $this->JSONdata && LF_count( $this->JSONdata)) {
                // Existing data
                $r .= str_replace( ['<br>'], ['\n'], JSON_encode( $this->JSONdata));
            } elseif ( $this->targetDocOID && $this->targetElementId) {
                // Get data
                $data = $this->getData( $this->targetDocOID, $this->targetElementId);
                $tableName = $this->elementName."_data";
                $jsonTable = new_buildJSONtableFromData( $data, [ 'name'=>$tableName, 'cssClass'=>"dataset", 'source'=>$this->docOid]);            
                $r .= $jsonTable;
                $this->update = true;
            }
        }
        $r .= "</div>";        
        // Close connector's DIV
        $r .= "</div>";
        // Prepare Javascript
        if ( $this->update) { $js .= "setTimeout( function() { window.ud.viewEvent( 'change', '{$this->name}');}, 3000);";} 
        $js .= "window.ud.initialiseElement('{$this->name}');\n";             
        
       return [ "content"=>$r, "hidden"=>"", "program"=>$js];    
    }
    
   /**
    * Retrieve 1 or more elements from 1 or more other documents as a table
    * @param string $docOid OID of document to open
    * @param string $elementId Path to element to retrieve. Later CSV list.
    * @return string HTML of element
    *
    *  Find files : Directory name/OID, model, depth    OR docOID
    *  Find elements : view/viewType, tag/dbtype, class,
    *  Results : chapter, section, sub section, oid, permissions, text, html, value,    
    */    
    function getData( $docOid, $elementId, $filters = []) {
        $table = null;
        // Get DM
        $dm = $this->ud->dataModel;
        $fetchOid = $docOid."-21--".LF_env( 'OIDPARAM');
        if ( !$dm) { $data = LF_fetchNode( $fetchOid); } else { $data = $dm->fetchNode( $fetchOid);}
        if ( LF_count( $data) < 2) { return $table;}
        // BuildSortedDataset
        $dataset = UD_utilities::buildSortedAndFilteredDataset( $docOid, $data, $dm);  
        // Lookup element
        // 2DO split into ID and sub-element path
        $elementData = $dataset->lookup( $elementId);
        if ( LF_count( $elementData) < 2) { return $table;}
        // DocOid / Text / Full
        $table = array( array( "OID", "value", "text", "HTML"));
        $html = LF_preDisplay( 't', $elementData[1]['tcontent']);
        $text = HTML_stripTags( $html);
        $value = (float) $text;
        $table[] = array( 'OID'=>$docOid, 'value'=>$value, 'text'=>$text, 'html'=>$html);
        /*
        for ($resi=0; $resi < LF_count( $results); $resi++) {
            $row = [];
            foreach ( $cols as $target=>$source) {
                if ( $source[0) == "=") {
                    // 2DO formulae
                } else $row[ $target] = $source;
            }
            $table[] = $row;         
        }                
        */
        return $table;
    } // UD_connector_document->getData();
    
    function addDataFromElements( &$table, $doc, $elementId) {
        // BuildSortedDataset
        $dataset = UD_utilities::buildSortedAndFilteredDataset( "", $doc);        
        // Lookup element
        // 2DO split into ID and sub-element path
        $elementData = $dataset->lookup( $elementId);
        if ( LF_count( $elementData) < 2) { return $table;}
        // DocOid / Text / Full
        $table = array( array( "OID", "value", "text", "HTML"));   
        $docOid = $elementData[1]['oid'];
        $html = LF_preDisplay( 't', $elementData[1]['tcontent']);
        $text = HTML_stripTags( $html);
        $value = (float) $text;
        $table[] = array( 'OID'=>$docOid, 'value'=>$value, 'text'=>$text, 'html'=>$html);
        return $table;
    } // UD_connector_document->addDataFromElements();
    
    // 2DO Finish
    function addDataFromMatchingElements( &$table, $doc, $views, $labels, $classes, $cols) {
        // Get list of ud_types
        
        // BuildSortedDataset
        $dataset = UD_utilities::buildSortedAndFilteredDataset( "", $doc);        
        // Loop through elements for match
        $cChapter="";
        $cSection="";
        $cSubsection="";
            // Keep track of titles
            if ( $el[ 'stype'] == UD_chapter) $cChapter = $el[ 'tcontent']; 

        // 2DO split into ID and sub-element path
        $elementData = $dataset->lookup( $elementId);
        if ( LF_count( $elementData) < 2) { return $table;}
        // DocOid / Text / Full
        $table = array( array( "OID", "value", "text", "HTML"));
        $docOid = $elementData[1]['oid'];
        $html = LF_preDisplay( 't', $elementData[1]['tcontent']);
        $text = HTML_stripTags( $html);
        $value = (float) $text;
        // Build row
            // For each column
            
        $table[] = array( 'OID'=>$docOid, 'value'=>$value, 'text'=>$text, 'html'=>$html);
        return $table;
    } // UD_connector_document->addDataFromElements();
    
    
} // PHP class UDconnector_document

global $UD_justLoadedClass;
$UD_justLoadedClass = "UDconnector_document";  

if ( isset( $argv) && strpos( $argv[0], "udcdocument.php") !== false)
{    
    // Launched with php.ini so run auto-test
    echo "Syntaxe OK\n";
    echo "Test completed\n";
} // end of auto-test