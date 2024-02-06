<?php
require_once( __DIR__."/udgoogleconnector.php");

require __DIR__.'/../../vendor/autoload.php';

function L_makeAbsoluteURL( $uri)
{
    $url = "http:";
    if ( val( $_SERVER, 'HTTPS')) $url = "https:";
    $url .= "//".$_SERVER['HTTP_HOST'].'/'.$uri.'/';
    return $url;
}
/**
 * udcgooglecalendar.php
 *
 * Universal Document connector that extracts data from Google calendar
 * and presents it as a table
 *
 * composer require google/apiclient:^2.0
 *
 */
class UDconnector_googleCalendar extends UD_googleConnector
{
    
    function __construct( &$datarow)
    {
        $scope = Google_Service_Calendar::CALENDAR_READONLY;;
        parent::__construct( $datarow, $scope);
        // Get element's name and caption
        $this->elementName = val( $datarow, '_elementName');        
        $this->caption = val( $datarow, '_caption');
        $this->subType = "googleCalendar";
        // Get Parameter part
        if (  val( $datarow, '_JSONcontent') && val( $datarow, '_JSONcontent'))
        {
            // Existing parameters
            $this->JSONparameters = val( $datarow, '_JSONcontent');
        }
        else
        {
            // Use default parameters
           $this->JSONparameters = [
                "calendarId" => "primary",
                "startDate"=>date( DATE_RFC3339, time() - 31*24*60*60),
                "endDate" => date( DATE_RFC3339),
            ];
            /*
            $this->calendarId = 'primary';
            $this->startDate = date( DATE_RFC3339, time() - 31*24*60*60);
            $this->endDate = date( DATE_RFC3339);
            */            
        }
        $params = $this->JSONparameters;
        $this->calendarId = val( $params, 'calendarId');
        $this->startDate = val( $params, 'startDate');
        $this->endDate = val( $params, 'endDate');
        
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
        // Send as DIV
        $r .= "<div ";      
        // Add generic attributes
        $r .= " ".$this->getHTMLattributes();
        // Add specif attributes
        $r .= " ud_type=\"connector\" ud_mime=\"text/mixed\"";
        if ( $this->subType) $r .= " ud_subtype=\"{$this->subType}\"";
        $r .= ">";           
/*
        // 100% JSON
        meta, config, data, changes
        $r .= "ud_mime=\"text/json\">";           
        $r .= "<div id=\"{$this->JSON[ 'meta']['name']}_object\" class=\"object connectorObject hidden\">$this->content</div>";            

*/        
        // Caption in SPAN
        $r .= "<span class=\"caption\">{$this->caption}</span>";
        // JSON parameters DIV (hidden)
        $r .= "<div id=\"{$this->elementName}_parameters\" class=\"textObject hidden\" ud_type=\"textObject\" >";
        $r .= htmlentities( JSON_encode( $this->JSONparameters));
        $r .= "</div>";
        // JSON data table DIV (hidden)
        $r .= "<div id=\"{$this->elementName}_data\" class=\"tableObject hidden\" ud_type=\"jsonObject\">";
        if ( $this->JSONdata)
        {
            $r .= str_replace( ['<br>'], ['\n'], JSON_encode( $this->JSONdata));
        }
        elseif ( $this->mode == "edit2" && $this->calendarId && $this->startDate && $this->endDate)
        {
            $data = $this->getData( $this->calendarId, $this->startDate, $this->endDate);
            $jsonTable = new_buildJSONtableFromData( $data, [ 'name'=>$this->elementName, 'cssClass'=>"dataset", 'source'=>"GoogleCalendarAPI"]);            
            $r .= $jsonTable;
            $this->update = true;
        }
        $r .= "</div>";        
        // Close connector's DIV
        $r .= "</div>";
        // Prepare Javascript
        if ( $this->update && $this->mode == "edit2") $js .= "setTimeout( function() { window.ud.viewEvent( 'change', '{$this->name}');}, 3000);";
        $js .= "window.ud.initialiseElement('{$this->name}');\n";             
        
       return [ "content"=>$r, "hidden"=>"", "program"=>$js];
    }
    
   /**
    * Google Calendar API connection
    */
    /* defined in parent
    function getClient()
    {
        // Setup file paths and redirect URI
        // if ( !file_exists) extract from config file        
        $userId = LF_env( 'user_id');
        $tokenFile = "googlecalendartoken{$userId}.json";
        $tokenFull = "tmp/{$tokenFile}";
        $credentialsFull = "core/googlecredentials.json";
        $redirect = L_makeAbsoluteURL( "webdesk//AJAX_oauth");
        // Setup Google API parameters
        $appName = '(ou)rSmartDoc Google Calendar connector';
        $service = Google_Service_Calendar::CALENDAR_READONLY;

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
        $client->setScopes( $service);
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
                return null;               
             }
        }
    
        return $client;    
    
    } // UDconnector_googleCalendar->getClient()
    */
    
    function getData( $calendarId, $startDate, $endDate, $filter = "")
    {
        //2DO make table response
        // Get the API client and construct the service object.
        $client = $this->getClient();
        if ( !$client) return [];
        $service = new Google_Service_Calendar($client);

        // Print the next 10 events on the user's calendar.
        if ( !$calendarId) $calendarId = 'primary';
        $optParams = array(
            'maxResults' => 1000,
            'orderBy' => 'startTime',
            'singleEvents' => true,
            'timeMin' => $startDate,
            'timeMax' => $endDate
        );
        $results = $service->events->listEvents($calendarId, $optParams);
        $events = $results->getItems();

        if (empty($events)) {
           // "No events found.\n";
           $table = [];
        } 
        else 
        {
            // Events found
            $table = [ [ 'category', 'job', 'start', 'duration', 'details']];
            foreach ($events as $event) 
            {
                $start = $event->start->dateTime;
                $end = $event->end->dateTime;
                $duration = ( strtotime( $end) - strtotime( $start)) / 3600;
                $title = $event->summary;
                $titleParts = explode( ':', $title);
                $category = $titleParts[ 0];
                if ( $category == "P") $category = "Programming";
                elseif ( $category == "C") $category = "Vente";
                elseif ( $category == "A") $categpry = "Administration";
                else continue;
                $jobItems = explode( ' ', trim( $titleParts[1]));
                $job = $jobItems[0];
                array_shift( $jobItems);
                $description = str_replace( '"', '__QUOTE__', $event->description);
                $details = htmlentities( implode( ' ', $jobItems)."\n".$description);
                $row = [ 'category'=>$category, 'job'=>$job, 'start'=>$start, 'duration'=>$duration, 'details'=>$details];
                $table[] = $row;
            }            
        }    
        return $table;
    } // UDconnector_googleCalendar->getData()
    
} // PHP class UDconnector_googleCalendar
 
global $UD_justLoadedClass;
$UD_justLoadedClass = "UDconnector_googleCalendar";   
 
if ( isset( $argv) && strpos( $argv[0], "udcgooglecalendar.php") !== false)
{    
    // Launched with php.ini so run auto-test
    echo "Syntaxe OK\n";
    include_once __DIR__.'/../../tests/testenv.php';    
    $cal = new UDconnector_googleCalendar( []);
    var_dump( $cal);
    echo "Test completed\n";
} // end of auto-test