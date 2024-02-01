<?php
/** 
 * uddocservice.php -- Service to extract information for SD bee documents(tasks, models ...)
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
 *
 */
/**
 * DEVELOPER'S NOTES
 * Methods to extract data from UD documents OS version
 *
 *  2DO !!! Aligned with udservices ie return success with data in lastResponse and lastResponseRaw
 *
 */
 
require_once(__DIR__."/../udservices.php");

class UDS_doc extends UD_service {
   
    /*function __construct() {
        
    }*/
    
    // Central function for all actions
    function call( $data)
    {
        global $DM, $STORAGE;        
        $action = $data['action'];
        $r = "";
        if ( $DM && $STORAGE) {
            // OS version
            switch ( $action) {
                case "getMostRecentByName" : {
                    $model = $data[ 'model'];
                    $dir = $data[ 'dir'];
                    $elName = $data[ 'elementName'];
                    $max_dcreated = $data[ 'dcreated'];
                    // Get candidate docs
                    $candidates = [];
                    // Get collection name
                    $dirParts = explode( '-', explode( '--', $dir)[0]);
                    $collection = array_pop( $dirParts);                    
                    // Get docs in collection or diecrtly attached to user if no collection
                    if ( $collection) $docs = $ACCESS->getCollectionContents( $collection);
                    else $docs = $ACCESS->getUserContents();
                    // Keep if same model                   
                    for ( $doci=0; $doci < LF_count( $docs); $doci++) {
                        $doc = $docs[ $doci];
                        if ( $doc[ 'nmodel'] == $model) {
                            // Open docs for dates and add to doc record
                            // Add to candidates list
                            $candidates[] = $doc;  
                        }
                    }            
                    // Find OID of most recent candidate
                    $mostRecentOID = "";
                    $dcreated = 0;
                    for ( $candi=1; $candi < LF_count( $candidates); $candi++) {
                        $candidate = $candidates[ $candi];                    
                        if ( $candidate[ 'id'] != $data[ 'exclude'] && $candidate[ 'dcreated'] > $dcreated) {
                            if ( $max_dcreated && $candidate[ 'dcreated'] >= $max_dcreated) continue;
                            $dcreated = $candidate[ 'dcreated'];
                            $mostRecentOID = $candidate[ 'oid'];
                        }
                    }
                    if ( $mostRecentOID) {
                        $r = $this->getNamedContents( $mostRecentOID, $elName);
                    }
                break;}         
                case "getNamedContent" : {
                    $dir = $data[ 'dir'];
                    $docName = $data[ 'docName'];
                    $elementName = $data[ 'elementName'];
                    $targetElementName = $data[ 'targetElementName'];
                    $doc = new SDBEE_doc( $docName, $dir);
                    if ( !$doc) {
                        $this->lastResponse = "ERR: no file $docName";                        
                        return false;
                    }
                    $el = $doc->readElementByLabel( $elementName);        
                    $d = JSON_decode( $el, true)[ 'tcontent'];
                    if ( $targetElementName && is_array( $d)) {
                        $d = JSON_encode( $d);
                        $d = str_replace( $elementName, $targetElementName, $d);            
                        $d = JSON_decode( $d, true);
                    }
                    if ( is_string( $d)) $this->lastResponse = $d;
                    $this->lastResponseRaw = $d;
                    $r = true;
                break;}
                case "getInfo" : {
                    $dirOID = $data[ 'dir'];
                    $docName = $data[ 'doc'];
                    if ( !$dirOID) {
                        // Compute dir OID from dirPath                
                        $dirPath = explode( '/', $data[ 'dirPath']);                    
                        $elementName = $data[ 'elementName'];
                        $dirOID = "UniversalDocumentElement--21";
                        for ( $diri=0; $diri < LF_count( $dirPath); $diri++) {
                            $dirOID = $this->getDocumentOIDbyName( $dirOID, $dirPath[ $diri]);
                        }
                    }
                    $docOID = $this->getDocumentOIDbyName( $dirOID, $docName);
                    if ( !$docOID) { break;}
                    $docData = $this->fetchData( $docOID);
                    $extra = JSON_decode( LF_preDisplay( 't', $docData[ 1][ 'textra']), true);
                    if ( !$extra) $extra=[ 'system'=>""];
                    $r = [ 'info'=>"", 'params'=> $extra['system']];
                } break;
                case "create" : {
                    $model = $data[ 'model'];
                    $dir = $data[ 'dir'];
                    $docName = $data[ 'docName'];
                    $max_dcreated = $data[ 'dcreated'];
                    $dcreated = 0;
                    $searchOid = $dir."-21--NO|OIDLENGTH|nname|*{$docName}"; //tyle|{$model}"; 
                    $candidates = $this->fetchData( $searchOid, "id nname dmodified dcreated");
                    if ( LF_count( $candidates) < 2) {
                        // No task/doc with this model found, so create
                        // Get user's 32 base no
                        $user = base_convert( (int) LF_env( 'user_id'), 10, 32);
                        $user = substr( "00000".$user, strlen($user));
                        // Get name of model
                        $modelData= UD_UTILITIES::getModelToLoad( $model);
                        $newDoc = [
                            [ 'nname', 'nlabel', 'stype', 'nstyle', 'tcontent', 'textra'],
                            [ 
                                'nname'=> UD_UTILITIES::getContainerName().$user."_".substr( str_replace( ' ', '', $docName), 0, 9),
                                'nlabel' => $docName,
                                'stype'=> UD_document,
                                'nstyle'=>$modelData[ 'name'],
                                'tcontent'=>'<span class="title">'.$docName.'</span><span class="subtitle">create service</span>',
                                'textra' => '{ "system":{"state":"new"}}'
                            ]
                        ];
                        $newDocId = LF_createNode( $dir, "UniversalDocElement", $newDoc);
                        if ( $newDocId > 0) {
                            $newOID = $dir."-21-".$newDocId;
                            UD_UTILITIES::copyModelIntoUD( "", $newOID);
                            $r = [ 'oid'=>$newOID."-21--{OIDPARAM}"];
                        }
                        else $r = [ 'error'=>"ERR: failed to create doc {$newDocId}"];
                    } else {
                        // Return OID of first one found
                        $r = [ 'oid'=>$dir."-21-".$candidates[1][ 'id']."-21--{OIDPARAM}"];
                    }    
                    break;
                }
    
            }      
            return $r;
        }
        switch ( $action) {
            case "getMostRecentByName" : {
                $model = $data[ 'model'];
                $dir = $data[ 'dir'];
                $elName = $data[ 'elementName'];
                $max_dcreated = $data[ 'dcreated'];
                // Get candidate docs
                $candidates = [];
                global $DM, $ACCESS;
                if ( $DM) {
                    // OS version
                    // Get collection name
                    $dirParts = explode( '-', explode( '--', $dir)[0]);
                    $collection = array_pop( $dirParts);                    
                    // Get docs in collection
                    if ( $collection) $docs = $ACCESS->getCollectionContents( $collection);
                    else $docs = $ACCESS->getUserContents();
                    // Keep if same model                   
                    for ( $doci=0; $doci < LF_count( $docs); $doci++) {
                        $doc = $docs[ $doci];
                        if ( $doc[ 'nmodel'] == $model) {
                            // Open docs for dates and add to doc record
                            // Add to candidates list
                            $candidates[] = $doc;  
                        }
                    }
                } else {
                    $searchOid = $dir."-21--NO|OIDLENGTH|nstyle|{$model}"; 
                    $candidates = $this->fetchData( $searchOid, "id nname dmodified dcreated");                
                }
                // Get an element by name from most recent doc of a model in same dir                
                            
                // Find OID of most recent candidate
                $mostRecentOID = "";
                $dcreated = 0;
                for ( $candi=1; $candi < LF_count( $candidates); $candi++) {
                    $candidate = $candidates[ $candi];                    
                    if ( $candidate[ 'id'] != $data[ 'exclude'] && $candidate[ 'dcreated'] > $dcreated) {
                        if ( $max_dcreated && $candidate[ 'dcreated'] >= $max_dcreated) continue;
                        $dcreated = $candidate[ 'dcreated'];
                        $mostRecentOID = $candidate[ 'oid'];
                    }
                }
                if ( $mostRecentOID) {
                    $r = $this->getNamedContents( $mostRecentOID, $elName);
                }
            break;}
           /**
                let params = {
                    // Service parameters
                    action : "getNamedContent",
                    dir : "UniversalDocElement--21-52", // Top directory to search
                    dirDepth : 1, // Reserved for depth of earch
                    docName : "myDoc", // Document name
                    elementName : "myData", // Element name
                    // Where to store result
                    source : "value", // can take path value/jsonkey 
                    target : "myElement" // Element where to stock value
                }
                API.serviceRequest( "doc", params);
            */           
            case "getNamedContent" : {
                $dirOID = $data[ 'dir'];
                $docName = $data[ 'docName'];
                $docOID = $data[ 'docOID'];
                $elementName = $data[ 'elementName'];
                $targetElementName = $data[ 'targetElementName'];
                if ( !$docOID) $docOID = $this->getDocumentOIDbyName( $dirOID, $docName);
                if ( !$docOID) { $r = "ERR: no file $docName in $dirOID"; break;}
                $r = $this->getNamedContents( $docOID, $elementName);
                if ( $targetElementName) $r = str_replace( $elementName, $targetElementName, $r);                 
            break;}
            case "getInfo" : {
                $dirOID = $data[ 'dir'];
                $docName = $data[ 'doc'];
                if ( !$dirOID) {
                    // Compute dir OID from dirPath                
                    $dirPath = explode( '/', $data[ 'dirPath']);                    
                    $elementName = $data[ 'elementName'];
                    $dirOID = "UniversalDocumentElement--21";
                    for ( $diri=0; $diri < LF_count( $dirPath); $diri++) {
                        $dirOID = $this->getDocumentOIDbyName( $dirOID, $dirPath[ $diri]);
                    }
                }
                $docOID = $this->getDocumentOIDbyName( $dirOID, $docName);
                if ( !$docOID) { break;}
                $docData = $this->fetchData( $docOID);
                $extra = JSON_decode( LF_preDisplay( 't', $docData[ 1][ 'textra']), true);
                if ( !$extra) $extra=[ 'system'=>""];
                $r = [ 'info'=>"", 'params'=> $extra['system']];
            } break;
            case "create" : {
                $model = $data[ 'model'];
                $dir = $data[ 'dir'];
                $docName = $data[ 'docName'];
                $max_dcreated = $data[ 'dcreated'];
                $dcreated = 0;
                $searchOid = $dir."-21--NO|OIDLENGTH|nname|*{$docName}"; //tyle|{$model}"; 
                $candidates = $this->fetchData( $searchOid, "id nname dmodified dcreated");
                if ( LF_count( $candidates) < 2) {
                    // No task/doc with this model found, so create
                    // Get user's 32 base no
                    $user = base_convert( (int) LF_env( 'user_id'), 10, 32);
                    $user = substr( "00000".$user, strlen($user));
                    // Get name of model
                    $modelData= UD_UTILITIES::getModelToLoad( $model);
                    $newDoc = [
                        [ 'nname', 'nlabel', 'stype', 'nstyle', 'tcontent', 'textra'],
                        [ 
                            'nname'=> UD_UTILITIES::getContainerName().$user."_".substr( str_replace( ' ', '', $docName), 0, 9),
                            'nlabel' => $docName,
                            'stype'=> UD_document,
                            'nstyle'=>$modelData[ 'name'],
                            'tcontent'=>'<span class="title">'.$docName.'</span><span class="subtitle">create service</span>',
                            'textra' => '{ "system":{"state":"new"}}'
                        ]
                    ];
                    $newDocId = LF_createNode( $dir, "UniversalDocElement", $newDoc);
                    if ( $newDocId > 0) {
                        $newOID = $dir."-21-".$newDocId;
                        UD_UTILITIES::copyModelIntoUD( "", $newOID);
                        $r = [ 'oid'=>$newOID."-21--{OIDPARAM}"];
                    }
                    else $r = [ 'error'=>"ERR: failed to create doc {$newDocId}"];
                } else {
                    // Return OID of first one found
                    $r = [ 'oid'=>$dir."-21-".$candidates[1][ 'id']."-21--{OIDPARAM}"];
                }    
                break;
            }

        }      
        /*return */$this->response( true, $r, $r);  
        return $r; // keep this until we update AJAX_service
    } // UDS_ud->call()
    
   /** 
    * Find a document by name
    */
    function getDocumentOIDbyName( $dirOID, $docName) {    
        $r = "";
        $searchOid = $dirOID."-21--NO|OIDLENGTH|nlabel|{$docName}"; // LF_mergeShortOID( $dir, "--21--nstyle|{$model}");
        $candidates = $this->fetchData( $searchOid, "id nname dmodified dcreated");
        if ( LF_count( $candidates) == 2) {
            $oid = LF_stringToOid( $candidates[ 1][ 'oid']);
            $r = "UniversalDocElement--".implode( '-', $oid);
        } else {
            // Search all docs and analyse to get title
        }
        return $r;
    }
   /** 
    * Find most recent document of a model
    */
    function getDocumentByName( $dirOID, $name) {
        
    }

   /**
    * Get the contents of a named element in a document
    */
    function getNamedContents( $docOID, $elementName) {
        $r = "ERR: No element $elementName in $docOID";
        // Build OID to get doc contents
        $oid = "UniversalDocElement--".implode("-", LF_stringToOid( $docOID))."--NO|OIDLENGTH|CD|5";
        // Get contents and sort by nlabel ( provides HTML name attribute)
        $data = $this->fetchData( $oid);
        $dataset = UD_utilities::buildSortedAndFilteredDataset( $oid, $data); // , $LFF);
        $dataset->sort( 'nlabel');
        // Find searched element
        $elData = $dataset->lookup( $elementName);
        if ( LF_count( $elData) > 1) {
            // Element found - read contents
            $r = $elData[ 1][ 'tcontent'];
        }                    
        return $r;
    } // UDS_ud->getNamedElement()   
    
    function fetchData( $oid, $cols="") {
        global $DM;
        if ( $DM && method_exists( $DM, 'getDocAsData')) {
            // sd-bee OS version
            return $DM->getDocAsData( $DM->getNameFromOID( $oid));
        }
        global $LFF;
        if ( $LFF) return $LFF->fetchNode( $oid, $cols);
        else return LF_fetchNode( $oid, $cols);        
    }

} // UDS_ud


// Auto-test
if ( isset( $argv[0]) && strpos( $argv[0], "uddocservice.php") !== false)
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
                $test = "Test Get Most Recent By Name";
                $docOID = 'UniversalDocElement--21-788-21-6401';
                $docId = 6401;
                $docData = $LFF->fetchNode( $docOID); //, "* dcreated")
                // Use document service to get content from same element in similar doc;
                $dirOID = 'UniversalDocElement--21-788';
                $model = $docData[ 1][ 'nstyle'];
                $params = [
                    'action'=>"getMostRecentByName",
                    'model' => $model,
                    'dir' => $dirOID,
                    'exclude' => $docId,
                    'dcreated' => $docData[ 1][ 'dcreated'],
                    'elementName'=> 'History'
                ];
                $docService = new UDS_doc();
                $content = $docService->call( $params);
                $oldDecoded = JSON_decode( $content, true);
                if ( $oldDecoded && LF_count( $oldDecoded[ 'data'][ 'value'])) echo "$test: OK\n"; else echo "$test: KO\n";
                break;
        }
        $TEST_NO++;
    }    
    // CLI launched for tests
    echo "Syntax OK\n";
    // Create an UD
    require_once( __DIR__."/../../tests/testenv.php");
    require_once( __DIR__."/../../ud-view-model/ud_new.php");
    require_once( __DIR__."/../../ud-utilities/udutilities.php");
    require_once( __DIR__."/../../tests/testsoilapi.php");
    $LFF = new Test_dataModel();
    // require_once( __DIR__."/../ud-view-model/ud.php");    
    $TEST_NO = 1;
    while( $TEST_NO < 3) { sleep(1); nextTest();}
    echo "Test completed\n";      
} 

?>