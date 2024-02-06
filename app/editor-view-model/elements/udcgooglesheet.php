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
class UD_connector_googleSheet extends UD_googleConnector {
    
    private $ready = false;
    private $sheetId;
    private $sheetName;
    private $rangeStr;

    function __construct( &$datarow) {
        $scope = Google_Service_Sheets::SPREADSHEETS_READONLY; // https://www.googleapis.com/auth/spreadsheets.readonly"
        parent::__construct( $datarow, $scope);
        // Set connector's sub type       
        $this->subType = "googleSheet";
        // Extract some of the connector's parameters
        $params = $this->JSONparameters;
        if ( !val( $params, 'sheetId')) {
            // Content not default so initialiseElement
            $params = [
               "ready" => "no",
               "sheetId" => "Grab sheet's id from sheet's URL between /d/ and /edit",
               "range" => "Sheetname!A1H10 for example",
               "format" =>"jsonTable",
               "target" => $this->elementName
            ];
            $this->JSON[ 'data'][ 'config'][ 'value'][ 'value'] = $params;
        }
        $this->ready = ( strToLower( val( $params, 'ready')) == "yes") ? true : false;
        $this->sheetId = val( $params, 'sheetId');
        $this->rangeStr = val( $params, 'range');        
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
        if ( (!$cache || is_string( val( $cache, 'value'))) && $this->ready/*|| LF_date( val( $cache, 'expires')) < LF_date()*/) {
            $attr = [ 'name'=>$this->elementName, 'cssClass'=>"dataset", 'source'=>"GoogleSheetAPI", 'expires'=>""];
            try {
                // Connect to Google Sheet and retrieve data
                $data = $this->getData( $this->sheetId, $this->rangeStr);
                // Prepare data as JSON table
                $tableJSON = new_buildJSONtableFromData( $data, $attr);
                $table = JSON_decode( $tableJSON, true)[ 'data'];
                $table[ 'name'] = $this->elementName;   	   
                $cache = [ "tag"=>"div", "name"=>$this->elementName."_dataeditZone", "value"=>$table];
                $update = true;
            } catch( Exception $e) {
                $cache = '{"tag": "div", "class":"error", "value":"'.$e->getMessage().'"}';
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
        $r .= "<div id=\"{$this->JSON[ 'meta']['name']}_object\" class=\"object connectorObject hidden\">{$content}</div>";                
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
    function getData( $sheetId, $range)
    {
        //2DO make table response
        // Get the API client and construct the service object.
        $client = $this->getClient();
        if ( !$client) return [];
        $service = new Google_Service_Sheets( $client);
        // Reminder : https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit
        // sheetId = 1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms
        $response = $service->spreadsheets_values->get($sheetId, $range);
        $rows = $response->getValues();
        $cols = $rows[0];
        $table = [ $cols];
        for( $rowi = 1; $rowi < LF_count( $rows); $rowi++) {
            $row = $rows[ $rowi];
            $rowOut = [];
            for( $celli = 0; $celli < LF_count( $row); $celli++) {
                $rowOut[ $cols[ $celli]] = $row[ $celli];
            }
            $table[] = $rowOut;
        }
        return $table;
    } // UDconnector_googleCalendar->getData()
    
} // PHP class UDconnector_googleCalendar
 
global $UD_justLoadedClass;
$UD_justLoadedClass = "UD_connector_googleSheet";   
 
if ( isset( $argv) && strpos( $argv[0], "udcgooglesheet.php") !== false)
{    
    // Launched with php.ini so run auto-test
    echo "Syntaxe OK\n";
    include_once __DIR__.'/../../tests/testenv.php';
    include_once __DIR__.'/../../ud-view-model/ud.php';
    global $UD;
    $UD = new UniversalDoc( [ "mode"=>"edit", "displayPart" => "default"]);
    $jsonData = '{"meta":{"name":"Connector1","zone":"Connector1editZone","type":"connector", "subType":"GoogleSheet", caption":"Connector1","captionPosition":"top"},"data":{"button1":{"tag":"span","class":"rightButton","value":{"tag":"a","href":"javascript:","onclick":"API.showNextInList( ['."'Connector1_dataeditZone', 'Connector1_parametereditZone'".']);","value":"configurer"}},"button2":{"tag":"span","class":"rightButton","value":{"tag":"a","href":"javascript:","onclick":"API.connectorRefresh(  this);", "value":"refresh"}},"button3":{"tag":"span","class":"rightButton","value":{"tag":"a","href":"javascript:","onclick":"API.connectorUpdateServer( this);","value":"update server"}},"config":{"tag":"div","name":"Connector1_parametereditZone","type":"text","value":{"tag":"textedit","class":" json","value":{"ready":"yes","sheetId":"1lAU0A-hH51a0CYB7tQgxBwf1X2oDwPfAPKMUZTppM2M","range":"Tasks:A1H10","format":"jsonTable","target":"Connector1"}}},"cache":{}},"cache":{},"changes":{}}';
    $elementData = [ 'nname'=>"B6000010010000000M", 'stype'=>UDC_googleSheet, 'tcontent'=>$jsonData];   
    $sheet = new UD_connector_googleSheet( $elementData);
    var_dump( $sheet->renderAsHTMLandJS());
    echo "Test completed\n";
} // end of auto-test