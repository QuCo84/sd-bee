<?php
/**
 * ud_connector.php
 *
 * Universal Document element that extracts data from an external source
 * and presents it as a table
 *
 */

if ( !class_exists( 'UDelement')) require_once( __DIR__."/../../tests/testenv.php");      

class UDconnector extends UDelement
{
    static private $index = 1;  // for temporary names when element is created needed ?
    private $update = false;    // true if data has changed and must be saved to server
    protected $JSONparameters = null;
    protected $JSONdata = null;
    protected $JSON = null;
    protected $initCommand = "";
    protected $defaultParameters;
    protected $subType = "";
    public    $caption;
    
    function __construct( &$datarow)
    {
        parent::__construct( $datarow);
        // Get element's name and caption
        $this->elementName = val( $datarow, '_elementName');        
        $this->caption = val( $datarow, '_caption');
        // Get Parameter part
        $isJSON100 = false;
        if (  val( $datarow, '_JSONcontent')) {
            if ( val( $datarow, '_JSONcontent/meta')) {
                $this->JSON = val( $datarow, '_JSONcontent');
                $this->caption = val( $this->JSON, 'meta/name');
                $this->JSONparameters = $this->JSON[ 'data'][ 'config'][ 'value'][ 'value'];
                $isJSON100 = true;
            } else $this->JSONparameters = val( $datarow, '_JSONcontent');
        }
        if ( !$this->JSONparameters || LF_count( $this->JSONparameters) < 3) {
            // Use default parameters
            $this->JSONparameters = $this->defaultParameters;
            if ( $isJSON100) $this->JSON[ 'data'][ 'config'][ 'value'][ 'value'] = $this->JSONparameters;
        }
        // Get Data part
        if ( isset( $datarow[ '_divContent'][1])) {
            // Data present
            $this->JSONdata = JSON_decode( $datarow[ '_divContent'][1], true);
        }
        $this->requireModules( ['modules/editors/udetable.js', 'modules/connectors/udeconnector.js']);
    } // UDconnector->construct()
    
   /**
    * Generate HTML and JS for connector
    */
    function renderAsHTMLandJS( $active=true)
    {
        $r = $js = ""; 
        // Send as DIV
        $r .= "<div ";      
        // Add generic attributes
        $r .= " ".$this->getHTMLattributes();
        // Add specif attributes
        if ( $this->subType) { $r .= " ud_subtype=\"{$this->subType}\"";}
        if ( $this->JSON) {
            // 100% JSON format
            $name = val( $this->JSON, 'meta/name');
            $holder = $name."_object";
            $r .= "ud_mime=\"text/json\">";           
            $r .= "<div id=\"{$holder}\" class=\"object connectorObject hidden\">$this->content".HTML_closeDiv;
            /*if ( $this->html) {
                $r .= "<div id=\"{$name}editZone\" class=\"editZone\" ude_bind=\"{$holder}\">{$this->html}</div>"; 
                $js .= "API.updateTable( '{$name}');\n";
            }*/
        } else { 
            // Caption in SPAN
            $r .= "ud_mime=\"text/mixed\">";           
            $r .= "<span class=\"caption\">{$this->caption}</span>";
            // JSON parameters DIV (hidden)
            $r .= "<div id=\"{$this->elementName}_parameters\" class=\"textObject hidden\" ud_type=\"textObject\" >";
            $r .= htmlentities( JSON_encode( $this->JSONparameters));
            $r .= HTML_closeDiv;
            // JSON data table DIV (hidden)
            $r .= "<div id=\"{$this->elementName}_data\" class=\"tableObject hidden\" ud_type=\"jsonObject\">";
            if ( $this->JSONdata) {
                $r .= str_replace( ['<br>'], ['\n'], JSON_encode( $this->JSONdata));
            }
            elseif ( $this->initCommand)
            {
                $cmd = new UDcommands();
                global $LF_env;
                $instr = LF_substitute( $this->initCommand, $LF_env);
                $r_temp = "";            
                $cmd->serverSideInterpretator( $instr, $r_temp, $h_ignore, $js_ignore);
                $content = HTML_getContentsByTag( $r_temp, 'div')[0];
                $tableName = $this->elementName;
                $jsonTable = buildJSONtableFromData( JSON_decode( $content, true), [ 'name'=>$tableName, 'cssClass'=>"dataset", 'source'=>"{$instr}"]);            
                $r .= $jsonTable;
            }
            $r .= HTML_closeDiv;  
            // $js .= "window.ud.initialiseElement('{$this->name}');\n";             
        } 
        // Close connector's DIV
        $r .= "</div>";
        // Prepare Javascript
        if ( $this->update) { $js .= "window.ud.viewEvent( 'change', '{$this->name}');";}                   
        
       return [ "content"=>$r, "hidden"=>"", "program"=>$js];
    }
    
    function makeAbsoluteURL( $uri)
    {
        $url = "http:";
        if ( val( $_SERVER, 'HTTPS')) $url = "https:";
        $cache = LF_env( 'cache');
        $cache = 0; $url = "https:";
        $url .= (!TEST_ENVIRONMENT && $cache < 10) ? "//www.sd-bee.com/{$uri}/" :  "//".$_SERVER['HTTP_HOST'].'/'.$uri.'/';
        return $url;
    }
    
} // PHP class UDconnector
 
class UDconnector_siteExtract extends UDconnector
{
    function __construct( &$datarow)
    {
        $this->subType = "site";
        $defaultParameters = [
            'ready' => 'no',
            'service' => "sitextract",
            'action' => "",
            'site' => "http...",
            'fields' => "ntime,ntitle,ntag,nauthor,nteaser,gimage,nlink",
            'pageModels' => [
                'model1' => [
                    'ntime' => "tag .class ...",
                    'ntitle' => "",
                    'ntag' => "",
                    'nauthor' => "",
                    'nteaser' => "",
                    'gimage' => "",
                    'nlink' => ""
                ]
            ],
            'pages' => [
                'home' => [
                    'model' => "model1",
                    'uri' => ""
                ]
            ]            
        ];
        $this->defaultParameters = $defaultParameters;
        parent::__construct( $datarow);
        if ( $this->JSONparameters['site'] != "http..."  &&  val( $this->JSONparameters, 'ready') == "yes") {
            $this->initCommand = "localService( 'siteextract', '{$this->oid}', '', '{\"page\":\"home\", \"params\":\"parameters\"}');";  
        }
        $this->requireModules( [ 
            'modules/editors/udetable.js', 
            'modules/connectors/udeconnector.js',
            'modules/connectors/udcsiteextract.js'
        ]);
    }
} // PHP class UDconnector_siteExtract     
 
if ( isset( $argv) && strpos( $argv[0], "udconnector.php") !== false)
{    
    // Launched with php.ini so run auto-test
    echo "Syntaxe OK\n";
    echo "Test completed\n";
} // end of auto-test