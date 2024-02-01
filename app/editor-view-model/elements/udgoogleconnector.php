<?php
/**
 *
 * Google cloud client
 * Enables several UD elements to use a single API client 
 * 
 */
require __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/udconnector.php';
  
class UD_googleConnector extends UDConnector {    
// could include Drive stuff Google_Service_Drive::DRIVE_METADATA_READONLY    
    static private $client = null;
    static private $scopes = [];
    
    function __construct( &$datarow, $scope)  {
        parent::__construct( $datarow);
        self::$scopes[] = $scope;
    }
    
    function getClient() {
        if ( self::$client) { return self::$client;}
        // Setup file paths and redirect URI
        // if ( !file_exists) extract from config file        
        $userId = LF_env( 'user_id');
        $tokenFile = "googlecalendartoken{$userId}.json";
        $tokenFull = "tmp/{$tokenFile}";
        $cache = LF_env( 'cache');
        $credentialsFull = ( TEST_ENVIRONMENT || $cache > 10) ? "core/googlecredentials.json" : "core/sdbee_googlecredentials.json";
        $checkJSON = file_get_contents( $credentialsFull);
        if ( ! JSON_decode( $checkJSON)) {
            echo "Bad JSON $checkJSON";
            die();
        }
        $appName = ( TEST_ENVIRONMENT || $cache > 10) ? 'rSmartDoc' : 'SD bee';
        $redirect = $this->makeAbsoluteURL( "webdesk//AJAX_oauth"); // Add OID or env to indicate Google
        $service = self::$scopes;
        // Setup Google API parameters

        // Build ENViromental variable for oauth callback service
        $googleOAUTH = [ 
            'tokenFile'=>$tokenFile, 
            'callerURI'=>$_SERVER['REQUEST_URI'], 
            'credentials'=>$credentialsFull,
            'appName' =>$appName,
            'service' => $service,
            'redirect'=>$redirect
        ]; 
        LF_env( 'GoogleOAUTH', $googleOAUTH);        
        
        
        // Setup client
        $client = new Google_Client();
        $client->setApplicationName( $appName);
        $client->setScopes( self::$scopes);
        $client->setAuthConfig( $credentialsFull);
        //$client->setAccessType('offline');
        $client->setPrompt('select_account consent');
        $client->setRedirectUri( $redirect);

        // Load previously authorized token from a file, if it exists.
        // The file token.json stores the user's access and refresh tokens, and is
        // created automatically when the authorization flow completes for the first
        // time.
         if (file_exists($tokenFull)) { // 2DO use FILE_
            $accessToken = json_decode(file_get_contents( $tokenFull), true);
            $client->setAccessToken( $accessToken);
        }

        // If there is no previous token or it's expired.
        if ($client->isAccessTokenExpired()) 
        {
            // Refresh the token if possible, else fetch a new one.
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken( $client->getRefreshToken());
            } else {
                // Request authorization from the user.
                $authURL = $client->createAuthUrl();
                global $LF;
                $LF->out( "<meta http-equiv=\"refresh\" content=\"0; URL={$authURL}\">", 'head');
                // $client->setAccessType("offline"); 2TRY
                return null;               
             }
        }
         
        self::$client = $client;
        return $client;    
    
    }
    
    
    function drive() {
        $client = $this->getClient();
        $service = new Google_Service_Drive($client);

        // Print the names and IDs for up to 10 files.
        $optParams = array(
          'pageSize' => 10,
          'fields' => 'nextPageToken, files(id, name)'
        );
        $results = $service->files->listFiles($optParams);
        print "Files:\n";
        foreach ($results->getFiles() as $file) {
            printf("%s (%s)\n", $file->getName(), $file->getId());
        }
    }
    
} // UD_googleClient()

global $UD_justLoadedClass;
$UD_justLoadedClass = "UD_googleConnector";   
 
if ( isset( $argv[0]) && strpos( $argv[0], "udgoogleconnector.php") !== false)
{    
    // Launched with php.ini so run auto-test
    echo "Syntaxe OK\n";
    include_once __DIR__.'/../../tests/testenv.php';
    echo "Test completed\n";
} // end of auto-test