<?php
/** 
 * uddocservice.php
 *
 *   Methods to extract data from UD documents
 *
 */

require_once(__DIR__."/../udservices.php"); 
 
class UDS_resource extends UD_service {

    function __construct() {
        
    }
    
    // Central function for all actions
    function call( $data)
    {
        $action = val( $data, 'action');
        $r = "";
        switch ( $action) {
            case "getInfo" : {
                $path = val( $data, 'path');
                $r = [
                    'exists'=>file_exists( 'upload/smartdoc/media/'.$path)
                ];
                $this->lastResponseRaw = JSON_encode( $r);
                $this->lastResponse = val( $r, 'exists');
                $this->creditsConsumed = 0;
                $this->creditComment = "Free service";
                $this->cacheable=true;
                return true;
                // $r = JSON_encode( $r);
            } break;
            case "getModelsByTag" : {
                return $this->getModelsByTag( $data);
            } break;
            case "get" :
                $content = UD_fetchResource( $data[ 'path'], $filename, $ext, $data[ 'block'], val( $data, 'block_id'));
                if ( $content) {
                    $this->lastResponseRaw = [ 'content'=>$content, 'filename'=>$filename, 'ext'=>$ext];
                    $this->lastReponse = $filename." retrieved";
                    $this->creditsConsumed = 0;
                    $this->creditComment = "Free service";
                    $this->cacheable=true;
                    $r = true;
                } else {
                    $this->lastResponse = "{$data[ 'path']} not found";
                    $r = false;
                }
                break;
        }        
        return $r;
    }

    /**
     * Get an array of models by tag
     */
    function getModelsByTag( $request) {
        $r = [ 'app-model' =>[], 'app'=>[], 'process'=>[]];
       
        $models = $this->getModelsInfo( $request[ 'dir'], $request[ 'click-model']);
        for ( $modeli = 0; $modeli < count( $models); $modeli++) {
            $model =  val( $models, $modeli);
            $tag = val( $model, 'params/tag');
            if ( $tag) $r[ $tag][] = $model;
        }
        $this->lastResponseRaw =[ "list"=>$r];
        $this->lastResponse = count( $r) . "models found";
        $this->creditsConsumed = 0;
        $this->creditComment = "Free service";
        $this->cacheable=true;
        // return JSON_encode( [ "list"=>$r]); // 2DO Temp until AJAX8service & service aligned
        return true;
    }

    function getModelsInfo( $dir="models", $clickModel = "displayPage( 'model_{id}')") {
        global $PUBLIC, $LF, $LFF;
        $r = [];
        if ( $PUBLIC) {
            // 2DO !tested, move to sd-bee OS
            $modelNames = $PUBLIC->getList( 'models');            
            for ( $namei = 0; $namei < count( $modelNames); $namei++) {
                $modelName =  val( $modelNames, $namei);
                $modelContent = $PUBLIC->read( 'models', $modelName);
                $modelData = JSON_decode( $modelContent, true);
                /*
                //2DO Align with OS db model
                */
                if ( $json) $r[] = $modelData[ 'content'][ $modelName];
            }
        } elseif ( $LF || $LFF) {
            // SOILinks version
            // Testing
            // 2DO oid should be a paramater
            $oid = "UniversalDocElement--21-44-21--UD|1";
            $cols = "";
            $data = ($LFF) ? $LFF->fetchNode( $oid, $cols) : LF_fetchNode( $oid, $cols);   
            for ( $datai=1; $datai < count( $data); $datai++) {
                $d =  val( $data, $datai);
                /*
                // 2DO aligne with OS model
                name text NOT NULL,
                label text NOT NULL,
                type int(5),
                model text DEFAULT NULL,
                description text DEFAULT NULL,
                params text DEFAULT NULL,
                prefix text DEFAULT NULL,
                created int(11) DEFAULT NULL,
                updated int(11) DEFAULT NULL,
                state text DEFAULT NULL,
                progress int(5),
                deadline int(11)
                // How toad din JS
                <div id="" class="model-thumb">
                    <h2>A4 text</h2>
                    <img src="/tmp/W231H130_N053b3h20_A4testEmpty.png">
                    <p>This model defines a general purpose A4 textual document with tools for clipboarding, stylizing and highlighting.  #model-app</p>
                    <span class="button" onclick="API.switchView( 'connect');" debug="UniversalDocElement--21-44-21-28--UD|1-CD|3.">Learn more</span>
                </div>

                { tag:"div", class:"model-thumb", value:{
                    title:{ tag:"h2", value:"{$model[ 'label']}"},
                    image:{ tag:"img", src="{$model[ 'params'][ 'thumbnail-image']}},
                    descr:{ tag:"p", value:"{$descr}"},
                    button:{ tag:"span", class:"button", onclick:$onclick}
                }}
                */
                // Check for app parameters
                $params = JSON_decode( $d[ 'textra'], true)[ 'system'];
                $tag = val( $params, 'tag');
                if ( !$params || !$tag) continue;
                // Prepare  OS-compatible data with thumbnail JSON100
                $label = LF_preDisplay( 'n', val( $d, 'nlabel'));
                $spans = HTML_getContentsByTag( LF_preDisplay( 't', val( $d, 'tcontent')), 'span');
                $descr = ( count( $spans) > 1) ? $spans[ 1] : "";
                $onclick = LF_substitute( $clickModel, $d);
                $thumbnail = [
                    "tag" => "div", "class" => "model-thumb", "value"=>[
                        "title"=>[ "tag"=>"h2", "value"=> $label],
                        "image"=>[ "tag"=>"img", "src"=>$params[ 'thumbnail-image']],
                        "descr"=>[ "tag"=>"p", "value"=>$descr],
                        "button"=>[ "tag"=>"span", "class"=>"button", "onclick"=>$onclick, "value"=>"Learn more"]
                    ]
                ];
                $model = [
                    "name" => $d[ 'nname'],
                    "label" => LF_preDisplay( 'n', val( $d, 'nlabel')),
                    "stype" => (int) $d[ 'stype'],
                    "model" => $d[ 'nstyle'],
                    "description" => $descr,
                    "params" => $params,
                    "prefix" => "",                    
                    "created" => LF_timestamp( val( $d, 'dcreated')),
                    "modified" => LF_timestamp( val( $d, 'dmodified')),
                    "state" => "model",
                    "progress" => 0,
                    "deadline" => 0,
                    "thumbnail" => $thumbnail
                ];
                $r[] = $model;
            }
        }
        return $r;
    }

} // PHP class UDS_resource

// Auto-test
if ( isset( $argv) && strpos( $argv[0], "udsresourceservice.php") !== false)
{
    function nextTest() {
        global $TEST_NO, $LF, $LFF;        
        switch ( $TEST_NO) {
            case 1 : // Login
                $r = $LFF->openSession( "retr1", "LudoKov!tZ", 98);
                // echo strlen( $r).substr( $r, 23000, 500);
                if (  strlen( $r) > 1000 && stripos( $r, "HomeDir")) echo "Login test : OK\n";
                else echo "Login test: KO $r\n";
                break;
            case 2 :
                $params = [
                    'action'=>"getInfo",
                    'path' => "demointro.webm",
                ];
                $service = new UDS_resource();
                $r = $service->call( $params);
                var_dump( $r);
                break;
            case 3 :
                $params = [
                    'action'=>"getModelsByTag",
                ];
                $service = new UDS_resource();
                $r = $service->call( $params);
                var_dump( $r);
                break;

        }
        $TEST_NO++;
    }    
    // CLI launched for tests
    echo "Syntax OK\n";
    // Create an UD
    require_once( __DIR__."/../../tests/testenv.php");
    require_once( __DIR__."/../../ud-view-model/ud.php");
    require_once( __DIR__."/../../ud-utilities/udutilities.php");
    require_once( __DIR__."/../../tests/testsoilapi.php");
    $LFF = new Test_dataModel();
    // require_once( __DIR__."/../ud-view-model/ud.php");    
    $TEST_NO = 1;
    while( $TEST_NO < 4) { sleep(1); nextTest();}    
    echo "Test completed\n";      
} 

?>