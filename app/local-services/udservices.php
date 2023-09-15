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
/*
 *
 * OS/cloud version
 * 2DO ::: Throttle activate / disactivate
 */

class UD_services {
    
    private $params = null;
    private $cache = [];
    private $throttle = null;

    function __construct( $params = null) {
        $this->params = $params;
        if ( !isset( $params[ 'throttle']) || $params[ 'throttle'] == "on") {
            require_once( "udservicethrottle.php");
            $this->throttle = new UD_serviceThrottle();
        }
    }

    function _identified() { return ( !LF_env( 'is_Anonymous'));} // 2DO ENviromental 
    function _decodeRequestString( $requestString) { return JSON_decode( urldecode( $requestString), true);}

    function do( $serviceRequest) {
        // Error if anonymous
        if ( !$this->_identified()) {
            // Prepare error response
            $jsonResponse = [
                'success'=>False, 
                'message'=> "Unidentified",
                'data' => "" 
            ];             
        } else $jsonResponse = $this->_doRequest( $serviceRequest);
        // $LF->out( JSON_encode( $jsonResponse));
        return $jsonResponse;
    }

    function _doRequest( $serviceRequest) {
        // Prepare response
        $jsonResponse = []; 
        $error = false;
        // Get generic parameters from request  
        $token = ( isset( $serviceRequest['accountOrToken'])) ? $serviceRequest['accountOrToken'] : '';
        $serviceName = strToLower( $serviceRequest['service']);
        $action = $serviceRequest['action'];
        if ( !in_array( $serviceName, [ 'doc', 'resource', 'tasks'])) {
            // For 3rd party services, check throttle & parameters
            // Check service throttle     
            if ( $this->throttle && !( $throttleId = $this->throttle->isAvailable( $serviceName))) {
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
            if ( ($cache = $this->_cache( $serviceRequest))) {       
                /*if ( $throttleId) {
                    $this->throttle->consume( $throttleId, 1, "counting cache value for tests");
                }*/
                return $cache;
            }
            /*
            // Force error
            $jsonResponse = [ 
                'success'=>False, 
                'message'=> "Generic services not available",
                'data'=>['no data']
            ];
            $response = "<span class=\"error\">Failure on $serviceName $action <a>more</a></span><span>$result</span>"; 
            return $jsonResponse;   
            */
            // Fill request with parameters defined by user or token
            //if ( !$this->_getParamsFromUserConfig( $serviceRequest)) return $this->_error( "202 {!No paramaters for service!} $serviceName");
        
            // Get parameters for services that rely on 3rd party        
            if ( !$this->_getParamsFromUserConfig( $serviceRequest)) {
                return $this->_error( "202 {!No parameters for service!} $serviceName");
            };
            $provider = $serviceRequest[ 'provider'];
            $providerLC = strtoLower( $provider);
            $params = $serviceRequest[ $providerLC];
            //if ( !$provider) $provider = $service:
            // if ( !$params || !$provider) return $this->_error( "202 {!No parameters or syntax error!}");
        }  // 3rd party throttle & params processing     
        // Load service
        // - find module and class name
        switch ( $serviceName) {
            case "email" :
                $modPath = __DIR__."/{$serviceName}/uds{$providerLC}.php";
                $serviceClass = "UDS_{$provider}";
                break;
            case "doc" : case "document" :
            case "task" :
            case "resource" :
                $modPath = __DIR__."/{$serviceName}/uds{$serviceName}service.php";
                $serviceClass = "UDS_{$serviceName}";
                break;
            case "images" :
            case "translation" :
                $modPath = __DIR__."/{$serviceName}/uds{$serviceName}.php";
                $serviceClass = "UDS_{$serviceName}";
                break;
            case "keywords" : // 2DO rename class UDS_keywords and file udskeywords
                $modPath = __DIR__."/NLP/uds{$serviceName}service.php";
                $serviceClass = ($providerLC && $providerLC != "default") ? "UDS_{$providerLC}_{$serviceName}" : "UDS_{$serviceName}";
                break;
            case "textgen" :
                $modPath = ($providerLC && $providerLC != "default") ?
                    __DIR__."/NLP/uds{$providerLC}{$serviceName}.php" :
                    __DIR__."/NLP/uds{$serviceName}.php";
                $serviceClass = ($provider && $provider != "default") ? 
                    "UDS_{$provider}_{$serviceName}" : 
                    "UDS_{$serviceName}";
                break;
            default :
                $modPath = ($providerLC && $providerLC != "default") ?
                    __DIR__."/{$serviceName}/uds{$providerLC}{$serviceName}.php" :
                    __DIR__."/{$serviceName}/uds{$serviceName}.php";
                $serviceClass = ($provider && $provider != "default") ? 
                    "UDS_{$provider}_{$serviceName}" : 
                    "UDS_{$serviceName}";
                break;
        }
        // - load
        try {
            include_once $modPath;
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
                'data'=>$result
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
                'message'=> "Failure on $serviceName $action",
                'value'=>$service->lastResponse,
                'data'=>$service->lastResponseRaw
            ];
            $response = "<span class=\"error\">Failure on $serviceName $action <a>more</a></span><span>$result</span>"; 
        }
        return $jsonResponse;
    }              
                                                          
    function _cache( $serviceRequest, $response=null) {       
        // Determine cache file
        if ( isset( $serviceRequest[ 'cacheTag'])) $tag = LF_removeAccents( $serviceRequest[ 'cacheTag']);
        else $tag = $this->_cacheTag( $serviceRequest);
        $cacheFile = "serviceCache/" . str_replace( ':', '_', $tag) . ".json";
        if ( $tag && $response) {
            $this->cache[ $tag] = $response;
            FILE_write( 'tmp', $cacheFile, -1, JSON_encode( $response));
        } elseif ( $tag && isset( $this->cache[ $tag])) {
            // DO check a validity date
            FILE_write( 'tmp', $cacheFile, -1, JSON_encode( $this->cache[ $tag]));
            return $this->cache[ $tag];
        } else {
            // Get Data from cache file            
            $cacheFileContent = FILE_read( 'tmp', $cacheFile);
            if ( $cacheFileContent) {
                $response = JSON_decode( $cacheFileContent, true); 
                if ( !isset( $response[ 'success']))          
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
        $token = $serviceRequest['token'];
        $service = strToLower( $serviceRequest['service']);
        /*        
        if ( !$serviceRequest( 'recurrent')) {
            // Look for service account to use
            // 1 - via a token
            $token = $serviceRequest[ 'token'];
            $task = $serviceRequest[ 'task'];
            if ( $token) $serviceAccount = $ACCESS->getToken[ $token.$task];
            if ( !$serviceAccount) {
                // 2 - from process (model) parameters
                $process = $serviceRequest[ 'process'];
                $params = $this->getModelParams( $process)
                $serviceAccount = $params[ 'service-accounts'][ $service];
            }
            $paramsName = ( $serviceAccount) ? $serviceAccount : $service.'service';
        }
        */
        $provider = "";
        // Get credentials to access service from config task-doc
        $user = LF_env( 'user_id');
        $user32 = substr( "00000".$user, strlen( $user.""));
        $request = [
            'service' => 'doc',
            'action' => 'getNamedContent',
            'dir' => '',
            'docOID' => LF_env( 'UD_userConfigOid'),
            'docName' => "Z00000010VKK8{$user32}_UserConfig",
            'elementName' => $service.'service' /* $paramsName */
        ];
        $response = $this->_doRequest( $request);
        if ( $response[ 'success']) {
            $paramsContent = JSON_decode( $response[ 'data'], true);
            if ( $paramsContent && is_array( $paramsContent)) $params = $paramsContent[ 'data']['value'];
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
                        $serviceRequest[ 'provider'] = $provider;
                        $serviceRequest[ $provider] = $params[ $provider]; 
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
                /* might need a fail-safe
                if ( !$provider && !isset( $serviceRequest[ '__all'])) {
                    foreach( $params as $key=>$value) $serviceRequest[ $key] = $value;
                }
                */
                return true;
            }    
        }        
        {
            // DEPRECATED
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
            if ( LF_count( $paramsData) < 2 ) $paramsData = $this->fetchNode( str_replace( "_", "%20", $paramsOid));
            if ( LF_count( $paramsData) > 1) {
                $paramsContent = LF_preDisplay( 't', $paramsData[1]['tvalues']);
                if ( $paramsContent && $paramsPath) $params = $paramsContent[ 'data']['value']; 
                else $params = $paramsContent;
                $params = JSON_decode( $params, true);
                if ( $params) {
                    foreach( $params as $key=>$value) {
                        if ( strpos( $key, "CK_") === 0) {
                            // Decode crypted key
                            //call nodejs udservicesecurty.js $value $service LF_env('user_id')
                        }
                        if ( $key == "provider") $provider = $value;
                    }
                    if ( $provider) {
                        $serviceRequest[ 'provider'] = $provider;
                        $serviceRequest[ $provider] = $params[ $provider]; // ex mailjet/..
                        return true;
                    }
                }
            }
        }
        return false;
    }

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
        if ( !$response[ 'success']) return null;
        // Extract parameters set
        $paramsContent = JSON_decode( $response[ 'data'], true);
        if ( !$paramsContent || !is_array( $paramsContent)) return null;
        return $paramsContent[ 'data']['value'];
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

    function __construct( $params=null, $throttle=null, $throttleId=0) {
        $this->params = $params;
        $this->throttle = $throttle;
        $this->throttleId = 0;
    }

    function response( $success, $response, $data) {
        $this->lastResponse = $response;
        $this->lastResponseRaw = $data;
        return $success;
    }
}

function SDBEE_serviceCall( $request) {

}
/**
 * Auto-test
 */
if ( $argv && $argv[0] && strpos( $argv[0], "udservices.php") !== false) {
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
                        'to' =>[ 'email'=> "pt95bn95@gmail.com"]
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
        if (isset( $argv[1])) $jumpToTest = $argv[1];
        print "Auto test udservices.php\n";
        function nextTest( $services) {
            global $TEST_NO, $LF, $LFF, $jumpToTest;
            switch ( $TEST_NO) {
                case 1 : // Login
                    $r = $LFF->openSession( "demo", "demo", 133);
                    // echo strlen( $r).substr( $r, 23000, 500);
                    if (  strlen( $r) > 1000 && stripos( $r, "Autotest")) echo "Login test : OK\n";
                    else echo "Login test: KO\n";
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
                        'to' =>[ 'email'=> "pt95bn95@gmail.com"]
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