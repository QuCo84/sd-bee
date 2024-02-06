<?php
/**
 * udcfacebook.php
 * Facebook connector server-side
 * Enables UD elements to use a single API client 
 * 
 */
require_once __DIR__.'/udconnector.php'; 
require __DIR__.'/../../vendor/autoload.php';
use Facebook\Facebook;
//use Facebook\Api;
//use Facebook\FacebookClient.php; //FacebookRedirectLoginHelper;
//use Facebook\FacebookSDKException;
  
class UDC_facebook extends UDconnector {    

    protected $params = [];
    protected $ready = false;
    protected $app_id;
    protected $app_secret;
    protected $userToken;
    protected $account_id;
    private   $tokenFile;
    private   $pageAccessTokens=[];
    private   $error="";
    
    
    function __construct( &$datarow) {
        parent::__construct( $datarow);
        $this->subType = "facebook";
        $this->tokenFile = "facebooktoken".LF_env( 'user_id').".txt"; 
        // Check params are initialised
        $this->params = $this->JSON[ 'data'][ 'config'][ 'value'][ 'value'];
        if ( !val( $this->params, 'pageId')) {
            // Content not default so initialiseElement
            $this->params = [
               "ready" => "no",
               "pageId" => "Id of page to use",
               "format" =>"jsonTable",
               "target" => $this->elementName
            ];
            $this->JSON[ 'data'][ 'config'][ 'value'][ 'value'] = $this->params;
        } 
        $this->ready = ( strtoupper( val( $this->params, 'ready')) == "YES");
    }
    
      /**
    * Generate HTML and JS for connector
    */
    function renderAsHTMLandJS( $active=true)
    {
        $r = $js = "";
        // Update data cache if needed
        $update = false;
        $cache = val( $this->JSON, 'data/cache');
        if ( $this->ready) {
            if ( ( $connectLink = $this->checkToken())) {
                // LOGIN REQUIRED
                $cache = [ 
                    "tag"=>"div", 
                    "name"=>$this->elementName."_dataeditZone", 
                    "value"=>$connectLink
                ];
            } elseif ( !$cache || is_string( val( $cache, 'value'))) {
                // LOGGED IN BUT NO DATA - get connected and retrieve list of posts              
                try {                                
                    // Facebook connection state
                    $data = $this->getData();
                    $cache = [ 
                        "tag"=>"div", 
                        "name"=>$this->elementName."_dataeditZone", 
                        "class"=>"editZone", 
                        "value"=>$data
                    ];
                    $this->JSON[ 'data'][ 'config'][ 'class'] = "configZone hidden";
                    $update = true;
                } catch( Exception $e) {
                    $cache = [ "tag" => "div", "class" => "error", "value" => "'.$e->getMessage().'"];
                }
            } else {
                // LOGGED IN WITH DATA - process requested actions ( create, delete) and the refresh list
                $this->update();
                
            }
            $this->JSON[ 'data'][ 'cache'] = $cache;
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
        if ( $update && $this->mode == "edit") $js .= "setTimeout( function() { window.ud.viewEvent( 'change', '{$this->name}');}, 3000);";
        // No longer needed $js .= "window.ud.initialiseElement('{$this->name}');\n";             
        return [ "content"=>$r, "hidden"=>"", "program"=>$js];
    }

    function update() {
        // Get page id
        $pageId = val( $this->params, 'pageId');
        // Find data
        $data = [];
        $dataTable = $this->JSON[ 'data'][ 'cache'][ 'value'];
        if (  val( $dataTable, 'tag') == "jsontable") $data = val( $dataTable, 'value');
        // Process
        $change = false;
        for ( $rowi=0; $rowi < LF_count( $data); $rowi++) {
            $row = $data[ $rowi];
            if (  val( $row, 'action') == "create") {
                // New Post
                echo "NEW POST";
                $postId = $this->FacebookPost( $pageId, $row[ 'tmessage'], $row[ 'gimage'], val( $row, 'nlink'));
                $row[ 'id'] = $postId;
                $changed = true;
            } elseif (  val( $row, 'action') == "delete") {
                // Delete a Post
                // 2DO delete
                $changed = true;
            }            
        }
        if ( $this->error) 
            $this->JSON[ 'data'][ 'cache'][ 'value'] = [ "tag"=>"span", "class"=>"warning", 'value'=>$this->error];
        elseif ( $changed) $this->JSON[ 'data'][ 'cache'][ 'value'][ 'value'] = $this->getPosts( $pageId);
        return true;
    }
    
     function checkToken() {
        $r = [];
        // Look for existing token
        // 2DO NOT SECURE tmp has public access BETTER TO USE SESSION
        $this->userToken = FILE_read( "tmp", $this->tokenFile);
        if ( $this->userToken) {
            // VALID TOKEN -               
            $r = "";

        } else {
            // NO TOKEN - prepare OAUTH and return link to start Facebook login
            $credentialsFile = ( TEST_ENVIRONMENT || LF_env( $cache) > 10) ? "core/facebookcredentials.json" : "core/sdbee_facebookcredentials.json";
            $credentials = JSON_decode( file_get_contents( $credentialsFile), true);
            $redirect = $this->makeAbsoluteURL( "webdesk//AJAX_oauth/thirdparty|facebook");  // Add OID or env to indicate FB      
            $fb = new Facebook( $credentials);
            $helper = $fb->getRedirectLoginHelper();            
            // Build ENViromental variable for oauth callback service
            $facebookOAUTH = [ 
                'tokenFile' => $this->tokenFile, 
                'callerURI' => $_SERVER['REQUEST_URI'], 
                'credentials' => $credentialsFile,
                'appId' => $credentials[ 'app_id'],
                'app_secret' => $credentials[ 'app_secret'],
                'service' => "",
                'redirect'=>$redirect,
                'tokenFile' => $this->tokenFile
            ]; 
            LF_env( 'facebookOAUTH', $facebookOAUTH); 
            // Provide FB login link
            $scope = [ 'pages_manage_posts', 'pages_read_engagement'];
            $onclick = "window.location = '" . $helper->getLoginUrl( $redirect, $scope) . "';";
            $r = [ "tag"=>"span", "class"=>"button", "onclick"=>$onclick, "value"=>"Login with Facebook"];
        }  
        return $r;
    } // UDC_facebook->checkToken()
    
    function getData() {
        $r = [];
        // List posts
        $posts = [ "id"=>"xxx", "message"=>"my msg", "image"=>"", "link"=>"", "action"=>""];
        if ( val( $this->params, 'pageId')) $posts = $this->getPosts( val( $this->params, 'pageId'));
        if ( $this->error) $r = [ "tag"=>"span", "class"=>"warning", 'value'=>$this->error];
        else
            $r = [ 
                "tag"=>"jsontable", 
                'name'=>$this->elementName, 
                'class'=>"dataset", 
                'source'=>"Facebook", 
                'expires'=>"", 
                "value"=>$posts
            ];
        return $r;
    } // UDC_facebook->getData()
    
    function getPosts( $pageId) {
        $posts = [ [ "id"=>"model", "dcreated"=>"", "tmessage"=>"", "gimage"=>"", "nlink"=>""]];
        $token = $this->getPageAccessToken( $pageId);
        $req = [
            CURLOPT_URL => "https://graph.facebook.com/{$pageId}/feed?access_token={$token}",
            CURLOPT_HEADER => 0,
        ];
        $rep = $this->sendRequest( $req);
        if ( val( $rep, 'error') )  { 
            $this->error = val( $rep, 'error/message');
        } else {
            // Build table data from list of posts
            $w = val( $rep, 'data');
            for ( $wi=0; $wi < LF_count( $w); $wi++) {
                $posts[] = [ 
                    'id' => $w[ $wi]['id'], // could remove pageId_
                    'dcreated' => $w[ $wi]['created_time'], 
                    'tmessage' => ( $w[ $wi]['message']) ? $w[ $wi]['message'] : $w[ $wi]['story'],
                    'gimage' => ( $w[ $wi]['url']) ? $w[ $wi]['url'] : "",
                    'nlink' => ( $w[ $wi][ 'link']) ? $w[ $wi][ 'link'] : "",
                    'action' => "none"
                ];
            }
        }
        return $posts; 
    }
    
   /**
    * Post to a page on Facebook
    */    
    function FacebookPost( $pageId, $message, $image="", $link="") {
        $r = -1;
        // Get page access token
        $token = $this->getPageAccessToken( $pageId);
        if ( $token) {
            $post = [ "message" => $message];
            if ( $image) $post[ 'url'] = $image;
            if ( $link) $post[ 'link'] = $link;
            $post[ 'access_token'] = $token;
            $req = [
                CURLOPT_URL => "https://graph.facebook.com/{$pageId}/feed",
                CURLOPT_HEADER => 0,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $post
            ];
            $rep = $this->sendRequest( $req);
            var_dump( $rep);
            if ( val( $rep, 'error')) $this->error = val( $rep, 'error/message'); else $r = val( $rep, 'id');
        }
        return $r;
    } // UDC_facebook->FacebookPost()
    
    function getPageAccessToken( $pageId) {
        $token = $this->pageAccesTokens[ $pageId];
        if ( !$token) {
            $req = [
                CURLOPT_URL => "https://graph.facebook.com/{$pageId}?fields=access_token&access_token={$this->userToken}",
                CURLOPT_HEADER => 0,
            ];
            $rep = $this->sendRequest( $req);
            if ( val( $rep, 'error') ) $this->error = val( $rep, 'error/message');
            else $token = $this->pageAccesTokens[ $pageId] = val( $rep, 'access_token');
        }
        return $token;
    }
    
    function sendRequest( $request) {
        $ch = curl_init();
        $request[ CURLOPT_RETURNTRANSFER] = true;      
        curl_setopt_array( $ch, $request); 
        $response = curl_exec( $ch);
        if ( curl_error($ch)) {
            LF_debug( "FB err : ".curl_error($ch), "UDC_facebook", 8);
            $response = [ "ERROR" => true];
        } else {
            // Response data is always JSON
            $response = JSON_decode( $response, true);
            // Add Error attribute as false
            $response[ 'ERROR'] = false;
        }
        curl_close($ch);
        return $response;
    }
    
} // PHP class UDC_facebook

function FacebookLoginCallback( $params) {
    
    $credentialsFile = ( /*TEST_ENVIRONMENT ||*/ LF_env( $cache) > 10) ? "core/facebookcredentials.json" : "core/sdbee_facebookcredentials.json";
    $credentials = JSON_decode( file_get_contents( $credentialsFile), true);
    
    $fb = new Facebook( $credentials);

    $helper = $fb->getRedirectLoginHelper();

    try {
        $accessToken = $helper->getAccessToken();
        // Exchange the short-lived token for a long-lived token.
        //  $this->accessToken = $accessToken->extend();  
            /* 60 day token
            curl -i -X GET "https://graph.facebook.com/oauth/access_token?grant_type=fb_exchange_token&
  client_id=APP-ID&
  client_secret=APP-SECRET&
  fb_exchange_token=SHORT-LIVED-USER-ACCESS-TOKEN"
  */        
        FILE_write( "tmp", $params[ 'tokenFile'], -1, $accessToken);
    } catch(Facebook\Exception\ResponseException $e) {
      // When Graph returns an error
      echo 'Graph returned an error: ' . $e->getMessage();
      exit;
    } catch(Facebook\Exception\SDKException $e) {
      // When validation fails or other local issues
      echo 'Facebook SDK returned an error: ' . $e->getMessage();
      exit;
    }
}

global $UD_justLoadedClass;
$UD_justLoadedClass = "UDC_facebook";   
 
if ( isset( $argv) && strpos( $argv[0], "udcfacebook.php") !== false)
{    
    // Launched with php.ini so run auto-test
    echo "Syntaxe OK\n";
    /*
    include_once __DIR__.'/../../tests/testenv.php';
    include_once __DIR__.'/../../ud-view-model/ud.php';
    global $UD;
    $UD = new UniversalDoc( [ "mode"=>"edit", "displayPart" => "default"]);
    $jsonData = '{"meta":{"name":"Connector1","zone":"Connector1editZone","type":"connector", "subType":"GoogleSheet", caption":"Connector1","captionPosition":"top"},"data":{"button1":{"tag":"span","class":"rightButton","value":{"tag":"a","href":"javascript:","onclick":"API.showNextInList( ['."'Connector1_dataeditZone', 'Connector1_parametereditZone'".']);","value":"configurer"}},"button2":{"tag":"span","class":"rightButton","value":{"tag":"a","href":"javascript:","onclick":"API.connectorRefresh(  this);", "value":"refresh"}},"button3":{"tag":"span","class":"rightButton","value":{"tag":"a","href":"javascript:","onclick":"API.connectorUpdateServer( this);","value":"update server"}},"config":{"tag":"div","name":"Connector1_parametereditZone","type":"text","value":{"tag":"textedit","class":" json","value":{"ready":"yes","sheetId":"1lAU0A-hH51a0CYB7tQgxBwf1X2oDwPfAPKMUZTppM2M","range":"Tasks:A1H10","format":"jsonTable","target":"Connector1"}}},"cache":{}},"cache":{},"changes":{}}';
    $elementData = [ 'nname'=>"B6000010010000000M", 'stype'=>UDC_googleSheet, 'tcontent'=>$jsonData];   
    $sheet = new UD_connector_googleSheet( $elementData);
    var_dump( $sheet->renderAsHTMLandJS());
    */
    echo "Test completed\n";
} // end of auto-test