<?php
/**
 * udservices.php - Central access point to services for SD bee
 * Copyright (C) 2023  Quentin CORNWELL
 *  
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */


class UD_services {
    
    private $params = null;
    private $cache = [];
    private $throttle = null;
    private $serviceRootDir;

    function __construct( $params = null) {
        $this->params = $params;
        if ( !val( $params, 'throttle') ||  val( $params, 'throttle') == "on") {
            require_once( "udservicethrottle.php");
            $this->throttle = new UD_serviceThrottle();
        }
        $this->serviceRootDir = val( $params, 'service-root-dir', __DIR__);
    }

    function _identified() { return ( !LF_env( 'is_Anonymous'));} // 2DO ENviromental 
    function _decodeRequestString( $requestString) { return JSON_decode( urldecode( $requestString), true);}

    function do( $serviceRequest) {
        /* Anonymous tested in endpoint, compatible with service gateway
        // Error if anonymous
        if ( !$this->_identified()) {
            // Prepare error response
            $jsonResponse = [
                'success'=>False, 
                'message'=> "Unidentified",
                'data' => "" 
            ];             
        } else */
        $jsonResponse = $this->_doRequest( $serviceRequest);
        // $LF->out( JSON_encode( $jsonResponse));
        return $jsonResponse;
    }

    function _doRequest( $serviceRequest) {
        // Prepare response
        $jsonResponse = []; 
        $error = false;
        // Get generic parameters from request  
        $token = ( val( $serviceRequest, 'accountOrToken')) ? $serviceRequest['accountOrToken'] : '';
        $serviceName = strToLower( val( $serviceRequest, 'service'));
        $serviceAndProvider = $serviceName . strToLower( val( $serviceRequest, 'provider'));
        $action = val( $serviceRequest, 'action');
        $params = $serviceRequest;
        if ( 
            !in_array( $serviceName, [ 'doc', 'resource', 'tasks', 'scrape'])
            && !in_array( $serviceAndProvider, [ 'imagesfileimages', 'imagesftpimages'])
        ) {
            // For 3rd party services, check throttle, cache & parameters
            // Check service throttle     
            if ( !in_array( $serviceName, [ 'email']) && $this->throttle && !( $throttleId = $this->throttle->isAvailable( $serviceName))) {
                $result = "{$this->throttle->lastError}: {$this->throttle->lastResponse}";
                $jsonResponse = [ 
                    'success'=>false, 
                    'message'=> "No credits for $serviceName $action",
                    'data'=>$result
                ];
                $response = "<span class=\"error\">No credits for $serviceName $action <a>more</a></span><span>$result</span>"; 
                return $jsonResponse;
            }
            $serviceRequest[ 'throttleId'] = $throttleId;
            // Check service cache 	
            if ( $this->throttle && ($cache = $this->_cache( $serviceRequest))) {       
                /*if ( $throttleId) {
                    $this->throttle->consume( $throttleId, 1, "counting cache value for tests");
                }*/
                return $cache;
            }
            // Get parameters for services that rely on 3rd party        
            if ( !$this->_getParamsFromUserConfig( $serviceRequest)) {
                return $this->_error( "202 {!No parameters for service!} $serviceName");
            };
            $provider = val( $serviceRequest, 'provider');
            $providerLC = strtoLower( $provider);
            $params =  val( $serviceRequest, $providerLC);
            //if ( !$provider) $provider = $service:
            // if ( !$params || !$provider) return $this->_error( "202 {!No parameters or syntax error!}");
        }  // 3rd party throttle & params processing     
        // Load service
        // - find module and class name
        switch ( $serviceName) {
            case "email" :
                $modPath = $this->serviceRootDir."/{$serviceName}/uds{$providerLC}.php";
                $serviceClass = "UDS_{$provider}";
                break;
            case "doc" : case "document" :
            case "task" :
            case "resource" :
            case "scrape" :
                $modPath = $this->serviceRootDir."/{$serviceName}/uds{$serviceName}service.php";
                $serviceClass = "UDS_{$serviceName}";
                break;
            case "images" :
            case "translation" :
                $modPath = $this->serviceRootDir."/{$serviceName}/uds{$serviceName}.php";
                $serviceClass = "UDS_{$serviceName}";
                break;
            case "keywords" : // 2DO rename class UDS_keywords and file udskeywords
                $modPath = $this->serviceRootDir."/NLP/uds{$serviceName}service.php";
                $serviceClass = ($providerLC && $providerLC != "default") ? "UDS_{$providerLC}_{$serviceName}" : "UDS_{$serviceName}";
                break;
            case "textgen" :
                $modPath = ($providerLC && $providerLC != "default") ?
                    $this->serviceRootDir."/NLP/uds{$providerLC}{$serviceName}.php" :
                    $this->serviceRootDir."/NLP/uds{$serviceName}.php";
                $serviceClass = ($provider && $provider != "default") ? 
                    "UDS_{$provider}_{$serviceName}" : 
                    "UDS_{$serviceName}";
                break;
            default :
                $modPath = ($providerLC && $providerLC != "default") ?
                    $this->serviceRootDir."/{$serviceName}/uds{$providerLC}{$serviceName}.php" :
                    $this->serviceRootDir."/{$serviceName}/uds{$serviceName}.php";
                $serviceClass = ($provider && $provider != "default") ? 
                    "UDS_{$provider}_{$serviceName}" : 
                    "UDS_{$serviceName}";
                break;
        }
        // - load
        try {
            // Look for module here and also ../../.services
            if ( file_exists( $modPath)) include_once $modPath;
            else include_once( str_replace( $this->serviceRootDir, __DIR__."/../../services", $modPath));
            $service = new $serviceClass( $params, $this->throttle, $throttleId);                       
        } catch ( Exception $e) {
            return $this->_error( "203 {!Module not available!} : {$modPath}");
        }
        // Call service 
        $service->lastRequest = $serviceRequest;
        $success = $service->call( $serviceRequest);
        $result = $service->lastResponseRaw; //JSON_encode( $service->lastResponseRaw);                    
        if ( $success) {
            $jsonResponse = [ 
                'success'=>True, 
                'message'=> "Success on $serviceName {$serviceRequest['action']}",
                'value'=>$service->lastResponse,
                'data'=>$result,
                'credits' => $service->creditsConsumed
            ];
            // if ( debugging) $jsonResponse[ 'request'] = $service->lastRequest;
            $response = "<span class=\"success\">Success on $serviceName $action</span>";
            $response .= "<span class=\"result hidden\">{$result}</span>";
            // Consume credits
            if ( $service->creditsConsumed && $throttleId) {
                $this->throttle->consume( $throttleId, $service->creditsConsumed, $service->creditComment);
            }
            // Cache response if service is cacheable
            if ( $service->cacheable) $this->_cache( $serviceRequest, $jsonResponse);
        } else {
            $jsonResponse = [ 
                'success'=>False, 
                'message'=> ( $service->lastResponse) ? $service->lastResponse : "Failure on $serviceName $action",
                'value'=>$service->lastResponse,
                'data'=>$service->lastResponseRaw
            ];
            $response = "<span class=\"error\">Failure on $serviceName $action <a>more</a></span><span>$result</span>"; 
        }
        return $jsonResponse;
    }              
                                                          
    function _cache( $serviceRequest, $response=null) {       
        // Determine cache file
        if ( val( $serviceRequest, 'cacheTag')) $tag = LF_removeAccents( val( $serviceRequest, 'cacheTag'));
        else $tag = $this->_cacheTag( $serviceRequest);
        $cacheFile = "serviceCache/" . str_replace( ':', '_', $tag) . ".json";
        if ( $tag && $response) {
            $this->cache[ $tag] = $response;
            FILE_write( 'tmp', $cacheFile, -1, JSON_encode( $response));
        } elseif ( $tag && isset( $this->cache[ $tag])) {
            // DO check a validity date
            FILE_write( 'tmp', $cacheFile, -1, JSON_encode( val( $this->cache, $tag)));
            return  val( $this->cache, $tag);
        } else {
            // Get Data from cache file            
            $cacheFileContent = FILE_read( 'tmp', $cacheFile);
            if ( $cacheFileContent) {
                $response = JSON_decode( $cacheFileContent, true);                 
                if ( val( $response, 'data/times')) $response[ 'data'][ 'times'] = [];
                if ( !val( $response, 'success'))         
                    $response = [ 
                        'success'=>true, 
                        'message'=> "Auto",
                        'data'=>$response
                    ]; 
                return $response;
            }
        }
        return null;
    }

    function _cacheTag( $serviceRequest) {
        $tag = "";
        foreach( $serviceRequest as $key=>$value) {
            if ( is_array( $value))  continue;
            $tag .= "{$key}:{$value},";
        }
        return $tag;
    }

    function _error( $error) {
        $response = "JSON ERR: {$error}";
  	    $jsonResponse = [ 'success'=>false, 'message'=>$response, 'data'=>[]];
        return $jsonResponse;
    }

    // Get parameters from User's configuration UD
    function _getParamsFromUserConfig( &$serviceRequest) {
        $token = val( $serviceRequest, 'token');
        $service = strToLower( val( $serviceRequest, 'service'));
        /*  Srvice token mgmt      
        if ( !$serviceRequest( 'recurrent')) {
            // Look for service account to use
            // 1 - via a token
            $token = val( $serviceRequest, 'token');
            $task = val( $serviceRequest, 'task');
            if ( $token) $serviceAccount = $ACCESS->getToken[ $token.$task];
            if ( !$serviceAccount) {
                // 2 - from process (model) parameters
                $process = val( $serviceRequest, 'process');
                $params = $this->getModelParams( $process)
                $serviceAccount = $params[ 'service-accounts'][ $service];
            }
            $paramsName = ( $serviceAccount) ? $serviceAccount : $service.'service';
        }
        */
        $provider = "";
        // Get credentials to access service from config task-doc
        $user = LF_env( 'user_id');
        $user = strToUpper( base_convert( $user, 10, 32));
        $user32 = substr( "00000".$user, strlen( $user.""));
        $request = [
            'service' => 'doc',
            'action' => 'getNamedContent',
            'dir' => '',
            'docOID' => LF_env( 'UD_userConfigOid'),
            'docName' => "Z00000010VKK8{$user32}_UserConfig",
            'elementName' => $service.'service' /* $paramsName */
        ];
        $serviceH = new UD_services( [ 'throttle'=>'off', 'service-root-dir'=> __DIR__ ]);
        $response = $serviceH->_doRequest( $request); //$this
        if ( val( $response, 'success')) {
            $paramsContent = $response[ 'data'];
            if ( is_string( $paramsContent)) $paramsContent = JSON_decode( $response[ 'data'], true);
            if ( $paramsContent && is_array( $paramsContent)) $params = val( $paramsContent, 'data/value');
            if ( $params) {
                foreach( $params as $key=>$value) {
                    $key = strToLower( $key);
                    if ( strpos( $key, "ck_") === 0) {
                        // Decode crypted key
                        //call nodejs udservicesecurty.js $value $service LF_env('user_id')
                    }
                    if ( $key == "enabled") $enabled = ( $value == "on");
                    elseif ( $key == "provider") {
                        $provider = strToLower( $value);
                        if ( !val( $serviceRequest, 'provider') ||  val( $serviceRequest, 'provider') == 'default') {
                            $serviceRequest[ 'provider'] = $provider;
                            $serviceRequest[ $provider] =  val( $params, $provider); 
                        }
                        if ( $params[ $provider] == 'parent') {
                            /* 2DO Upward search for parameters
                            $serviceRequest[ 'recurrent'] = true;
                            // Change user
                            // Get params $this._getParamsFromUserConfig( &$serviceRequest);
                            // Restore user
                            */
                        }
                    } elseif ( $key == "__all") {
                        $serviceRequest[ '__all'] = $value;
                    }
                }
                /*
                if ( !val(  $serviceRequest, $provider) ||  val( $serviceRequest, 'provider') == 'default') {
                    // Use default provider
                    $serviceRequest[ 'provider'] = $defaultProvider;
                }
                $provider = strToLower( val( $serviceRequest, 'provider'));
                if ( $provider && val(  $params, $provider)) $serviceRequest[ $provider] =  val( $params, $provider);
                */
                /* might need a fail-safe
                if ( !$provider && !val( $serviceRequest, '__all')) {
                    foreach( $params as $key=>$value) $serviceRequest[ $key] = $value;
                }
                */
                return true;
            }    
        }        
        {
            // DEPRECATED ALign retr1
            // 2DO use param for reading account info (recursive)
            $paramsOid = "SetOfValues--16--nname|{$service}_service";
            $paramsField = "tvalues";
            $paramsPath = "";
            /*
            // New procedure not in place yet client side
            $userConfigOid = LF_env( 'UD_userConfigOid');
            $paramsId = WellKnownElements[ "{$service}service"];
            //$udParamOid = "UniversalDocElement--21-4-21-2342-21-0-21--NO|NO-NO|NO-nname|*_services-nname|{$udParamId}";
            $paramsOid = "{$userConfigOid}-21-0-21--NO|NO-nname|*_services-nname|{$udParamId}";
            $paramsField = "tcontent";
            $paramsPath = "data/value";
            */
            $paramsData = $this->fetchNode( $paramsOid);
            if ( LF_count( $paramsData) < 2 ) $paramsData = $this->fetchNode( str_replace( "_", " ", $paramsOid));
            if ( LF_count( $paramsData) > 1) {
                $paramsContent = LF_preDisplay( 't', $paramsData[1]['tvalues']);
                if ( $paramsContent && $paramsPath) $params = val( $paramsContent, 'data/value'); 
                else $params = $paramsContent;
                $params = JSON_decode( $params, true);
                if ( $params) {
                    // Examine paramaters
                    $defaultProvider = "";
                    foreach( $params as $key=>$value) {
                        if ( strpos( $key, "CK_") === 0) {
                            // Decode crypted key
                            //call nodejs udservicesecurty.js $value $service LF_env('user_id')
                        }
                        if ( $key == "provider") $defaultProvider = $value;
                    }
                    if ( $defaultProvider 
                        && ( 
                            !val( $serviceRequest, 'provider') 
                            ||  val( $serviceRequest, 'provider') == 'default'
                            || isset( $params[ $serviceRequest[ $provider]])
                        )                      
                    ) {                        
                        if ( !val(  $serviceRequest, $provider) ||  val( $serviceRequest, 'provider') == 'default') {
                            // Use default provider
                            $serviceRequest[ 'provider'] = $defaultProvider;
                        }
                        // Add selected provider's paramaters to service request
                        $provider = val( $serviceRequest, 'provider');
                        $serviceRequest[ $provider] =  val( $params, $provider); // ex mailjet/..
                    }
                    return true;
                }
            }
        }
        return false;
    }
    
    // Not used yet
    function _getParamsFromDoc( $service) {
        // Read from doc
        $request = [
            'service' => 'doc',
            'action' => 'getNamedContent',
            'dir' => 'UniversalDocElement-',
            'docOID' => LF_env( 'UD_userConfigOid'),
            'elementName' => $service.'service'
        ];
        $response = $this->_doRequest( $request);
        if ( !val( $response, 'success')) return null;        
        // $profile = UD_utilities::getNamedElementFromUD( LF_env( 'UD_userConfigOid'), 'profile');
        // Extract parameters set
        $paramsContent = JSON_decode( $response[ 'data'], true);
        if ( !$paramsContent || !is_array( $paramsContent)) return null;
        return val( $paramsContent, 'data/value');
    }

    function fetchNode( $oid, $cols="") {
        global $LF, $LFF;
        if ( TEST_ENVIRONMENT) return $LFF->fetchNode( $oid);
        return  LF_fetchNode( $oid, $cols);
    }

} // PHP clss UD_services

/**
 * Generic service class
 * Defines generic attributes used for handling response data, throttling and caching
 */
class UD_service {
    public $lastRequest = "";
    public $lastResponse = "";
    public $lastResponseRaw = [];
    public $cacheable = false;
    public $creditsConsumed = 0;
    public $creditComment = "";
    protected $params = null;
    protected $throttle = null;
    protected $throttleId = 0;
    protected $times = [];

    function __construct( $params=null, $throttle=null, $throttleId=0) {
        $this->params = $params;
        $this->throttle = $throttle;
        $this->throttleId = 0;
        $this->times = [ 'service-start'=>time()];
    }

    function response( $success, $response, $data) {
        $this->lastResponse = $response;
        $this->lastResponseRaw = $data;
        return $success;
    }

    function error( $msg) {
        $this->lastResponse = $msg;
        $this->lastResponseRaw = $msg;
        return false;
    }
}

/*
function SDBEE_serviceCall( $request) {

}
*/
/**
 * Auto-test
 */
if ( isset( $argv) && strpos( $argv[0], "udservices.php") !== false) {
    // CLI launched for tests
    print "Syntax OK\n";  
    define( 'TEST_ENVIRONMENT', true);    
    if ( file_exists( __DIR__."/../sdbee-config.php")) {
        // OS version
        include ( __DIR__."/../sdbee-config.php");
        include ( __DIR__."/../sdbee-access.php");
        include ( __DIR__."/../editor-view-model/helpers/uddatamodel.php");
        // 2DO include a testenv.php with next line or move to datamodel
        $_SERVER[ 'REMOTE_ADDR'] = "192.168.1.1";
        global $ACCESS, $CONFIG;
        $CONFIG = SDBEE_getconfig();
        $ACCESS = new SDBEE_access( $CONFIG[ 'access-database']);
        function nextTest( $services) {
            global $TEST_NO, $ACCESS, $CONFIG;
            switch ( $TEST_NO) {
                case 1 : // Login                
                    $r = $ACCESS->login( "a", "b", [ 'a'=>"demo", 'b'=>"demo"]);
                    // echo strlen( $r).substr( $r, 23000, 500);
                    if (  $r) echo "Login test : OK\n";
                    else echo "Login test: KO\n";
                    LF_env( 'user_id', $r);                
                    break;
                case 2 :
                    $test = "Send email";
                    $html = "Hello world";
                    $serviceRequest = [
                        'service' => "email",
                        'provider' => "Mailjet",
                        'accountOrToken' => 'default', 
                        'action'=> "send",
                        'from'=> [ 'name'=>"sd bee", 'email'=>"contact@sd-bee.com"],
                        'subject'=> "Test message",
                        'body'=> $html,
                        'to' =>[ 'email'=> "pt95.bn95@gmail.com"]
                    ];
                    $r = $services->do( $serviceRequest);
                    echo "$test\n";
                    var_dump( $r);
                    break;   
                case 3 :
                    break;            
                case 4 :
                    break;
            }
            $TEST_NO++;
        }
        // Test
        print "udservices.php auto-test program\n";    
        $services = new UD_services();
        $TEST_NO = 1;
        while( $TEST_NO < 4) { sleep(1); nextTest( $services);}
        echo "Test completed\n";
    } else {
        // Legacy SOILinks version  
        // Load test environment
        require_once( __DIR__."/../tests/testenv.php");
        require_once( __DIR__."/../tests/testsoilapi.php");
        require_once( __DIR__."/../ud-view-model/udconstants.php");
        $LFF = new Test_dataModel();
        $jumpToTest = 2;
        if ( val( $argv, 1)) $jumpToTest = val( $argv, 1);
        print "Auto test udservices.php\n";
        function nextTest( $services) {
            global $TEST_NO, $LF, $LFF, $jumpToTest;
            switch ( $TEST_NO) {
                case 1 : // Login
                    $r = $LFF->openSession( "demo", "demo", 133);
                    // echo $r.strlen( $r).' '.strpos( $r, 'Autotest'); // substr( $r, 23000, 500);
                    // Dir listing only filled by ajax call
                    if (  strlen( $r) > 1000 && stripos( $r, "_dirListing")) echo "Login test : OK\n";
                    else echo "Login test: KO\n";
                    LF_env( 'user_id', 3*32+28);
                    $TEST_NO = $jumpToTest - 1;
                    echo "Jumping to $jumpToTest\n";
                    break;
                case 2 :
                    $html = "Hello world";
                    $serviceRequest = [
                        'service' => "email",
                        'provider' => "Mailjet",
                        'action'=> "send",
                        'from'=> [ 'name'=>"sd bee", 'email'=>"contact@sd-bee.com"],
                        'subject'=> "Test message",
                        'body'=> $html,
                        'to' =>[ 'email'=> "pt95.bn95@gmail.com"]
                    ];
                    //$r = $services->do( $serviceRequest);
                    //print_r( $r);
                    /*
                    if ( $rep)
                        echo "Throttle test : OK\n";
                    else {
                        echo "Throttle test: KO {$service} {$throttle->lastError}\n";
                        if ( strpos( $throttle->lastError, "not enabled")) {
                            $throttle->createLog( $service);
                            $TEST_NO--;
                        }
                        // echo $page;
                    }
                    */
                    break;            
                case 3 : // Keywords test
                    $stems = [
                        'keywords' => "coach digital webmarketing"
                    ];
                    $serviceRequest = [
                        'service' => "keywords",
                        'action' => "get",
                        'cacheTag' => "keywordsTest",
                        'stems' => $stems
                    ];
                    /*
                    $r = $services->do( $serviceRequest);
                    print_r( $r);
                    */
                    break;
                case 4 : // Textgen test
                    /*
                    $serviceRequest = [
                        'service' => "textgen",
                        'provider' => "GooseAI",
                        'action' => "complete",
                        'engine' => "gpt-neo-20b",
                        'text' => "Un site web doit être visible aux Internautes. Ca implique d'être référencé sur les moteurs de recherche.",
                        'cacheTag' => "textgenTest",
                        'lang' => 'fr'
                    ];
                    $r = $services->do( $serviceRequest);
                    print_r( $r);
                    */
                    break;
                case 5 :  // Read service parameters from user config
                    $serviceRequest = [
                        'service' => "Email",
                        'provider' => "Mailjet"
                    ];
                    $r = $services->_getParamsFromUserConfig( $serviceRequest);
                    var_dump( $r, $serviceRequest);
                    break;
            }
            $TEST_NO++;
        }
        $TEST_NO = 1;
        $services = new UD_services();
        while( $TEST_NO < 6) { sleep(1); nextTest( $services);}
        print "\nTest completed\n";
    }    
}    