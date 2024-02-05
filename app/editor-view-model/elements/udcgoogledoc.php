<?php

require_once __DIR__.'/udgoogleconnector.php';

/**
 * udcgooglecalendar.php
 *
 * Universal Document connector that extracts data from Google calendar
 * and presents it as a table
 *
 * composer require google/apiclient:^2.0
 *
 */
class UD_connector_googleDoc extends UD_googleConnector {
    
    private $ready = false;
    private $docId;
    private $docName;
    private $rangeStr;

    function __construct( &$datarow) {
        $scope = Google_Service_Docs::DOCUMENTS_READONLY;
        parent::__construct( $datarow, $scope);
        // Set connector's sub type       
        $this->subType = "googleDoc";
        // Extract some of the connector's parameters
        $params = $this->JSONparameters;
        $this->ready = $params[ 'ready'];
        $this->docId = $params[ 'docId'];
        $this->rangeStr = $params[ 'range'];
        
        // Get Data part
        if ( isset( $datarow[ '_divContent'][1]))
        {
            // Data present
            $this->JSONdata = JSON_decode( $datarow[ '_divContent'][1], true);
        }	
    } // UDconnector->construct()
    
   /**
    * Generate HTML and JS for connector
    */
    function renderAsHTMLandJS( $active=true)
    {
        $r = $js = "";
        // Update data cache if needed
        $update = false;
        $cache = val( $this->JSON, 'data/cache');
        if ( !$cache && $this->ready /*|| LF_date( $cache[ 'expires']) < LF_date()*/) {
            $attr = [ 'name'=>$this->elementName, 'cssClass'=>"dataset", 'source'=>"GoogleDocAPI", 'expires'=>""];
            try {
                $tableJSON = new_buildJSONtableFromData( $this->getData( $this->docId, $this->rangeStr), $attr);
                $table = JSON_decode( $tableJSON, true)[ 'data'];
                $table[ 'name'] = $this->elementName;   	   
                $cache = [ "tag"=>"div", "name"=>$this->elementName."_dataeditZone", "value"=>$table];
            } catch( Exception $e) {
                $cache = '{"tag": "div", "class":"error", "value":"'.$e->getMessage.'"}';
            }
            $this->JSON[ 'data'][ 'cache'] = $cache;
            //$update = true;
        }
        // Send as object DIV
        $r .= "<div ";      
        // Add generic attributes
        $r .= " ".$this->getHTMLattributes();
        // Add specif attributes
        $r .= " ud_type=\"connector\" ud_mime=\"text/json\"";
        if ( $this->subType) $r .= " ud_subtype=\"{$this->subType}\"";
        $r .= "ud_mime=\"text/json\">"; 
        // Content = connector object
        $content = JSON_encode( $this->JSON);
        $r .= "<div id=\"{$this->elementName}_object\" class=\"object connectorObject hidden\">{$content}</div>";                
        // Close connector's DIV
        $r .= "</div>";
        // Prepare Javascript
        if ( $update && $this->mode == "edit2") $js .= "setTimeout( function() { window.ud.viewEvent( 'change', '{$this->name}');}, 3000);";
        // No longer needed $js .= "window.ud.initialiseElement('{$this->name}');\n";             
        return [ "content"=>$r, "hidden"=>"", "program"=>$js];
    }
    
   /**
    * Google Sheets API connection
    */    
    function getData( $docId, $range)
    {
        //2DO make table response
        // Get the API client and construct the service object.
        $client = $this->getClient();
        if ( !$client) return [];
        $service = new Google_Service_Docs( $client);
        // Reminder : https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit
        // sheetId = 1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms
        $doc = $service->documents->get($docId);
        $body = val( $doc, 'modelData/body');
        $content = $body[ 'content'];
        $table = [[ "iindex", "stype", "tcontent"]];      
        for( $eli = 0; $eli < LF_count( $content); $eli++) {
            $el = val( $content, $eli);
            $text = "";
            if ( isset( $el[ 'paragraph'])) {
                $type = UD_paragraph;
                $subElements = val( $el, 'paragraph/elements');
                for ( $subeli = 0; $subeli < LF_count( $subElements); $subeli++) {
                    $subEl = val( $subElements, $subeli);
                    $text .= val( $subEl, 'textRun/content');
                }
            } elseif ( isset( $rowOut[ 'SectionBreak'])) {
            } elseif ( isset( $rowOut[ 'Table'])) {
            } elseif ( isset( $rowOut[ 'TableOfContents'])) {
            } elseif ( isset( $rowOut[ 'paragraph'])) {
            }
            // Remove line breaks
            $text = str_replace( [ '<br>', "\n"], ['', ''], $text);
            if ( $text) {
                $rowOut = [
                    'iindex' => $eli, //$subElements[ 0][ 'startIndex'],
                    'stype' => $type,
                    'tcontent' => $text       
                ];
            }
            $table[] = $rowOut;
        }
        return $table;
    } // UDconnector_googleCalendar->getData()
    
} // PHP class UDconnector_googleCalendar
 
global $UD_justLoadedClass;
$UD_justLoadedClass = "UD_connector_googleDoc";   
 
if ( isset( $argv) && strpos( $argv[0], "udcgoogledoc.php") !== false)
{    
    // Launched with php.ini so run auto-test
    echo "Syntaxe OK\n";
    include_once __DIR__.'/../../tests/testenv.php';
    include_once __DIR__.'/../../ud-view-model/ud.php';
    global $UD;
    $UD = new UniversalDoc( [ "mode"=>"edit", "displayPart" => "default"]);
    $jsonData = '{"meta":{"name":"Connector1","zone":"Connector1editZone","type":"connector", "subType":"GoogleSheet", caption":"Connector1","captionPosition":"top"},"data":{"button1":{"tag":"span","class":"rightButton","value":{"tag":"a","href":"javascript:","onclick":"API.showNextInList( ['."'Connector1_dataeditZone', 'Connector1_parametereditZone'".']);","value":"configurer"}},"button2":{"tag":"span","class":"rightButton","value":{"tag":"a","href":"javascript:","onclick":"API.connectorRefresh(  this);", "value":"refresh"}},"button3":{"tag":"span","class":"rightButton","value":{"tag":"a","href":"javascript:","onclick":"API.connectorUpdateServer( this);","value":"update server"}},"config":{"tag":"div","name":"Connector1_parametereditZone","type":"text","value":{"tag":"textedit","class":" json","value":{"ready":"yes","docId":"1YXzT1QlHA41rUtPiYm6lFGA1fdSdRsU_Arlbvgzx68Q","target":"Connector1"}}},"cache":{}},"cache":{},"changes":{}}';
    $elementData = [ 'nname'=>"B6000010010000000M", 'stype'=>UDC_googleDoc, 'tcontent'=>$jsonData];   
    $doc = new UD_connector_googleDoc( $elementData);
    var_dump( $doc->renderAsHTMLandJS());
    echo "Test completed\n";
} // end of auto-test