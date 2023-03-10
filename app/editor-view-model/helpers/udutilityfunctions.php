<?php
/* ===========================================================================
 *  udutilityfunctions.php
 *
 *  Set of PHP functions for presenting UD data
 *
 *  listContainersAsThumbnails() - returns container elements in dataset as thumbnail elements
 *
 */ 
function L_oidChildren( $oidstr) {
   $oid = LF_stringToOid( $oidstr);
   $oid[] = 21;
   return "UniversalDocElement--".implode( '-', $oid);
} 

/**
 * Build HTML containing thumbnail divs for each container in a dateset
 * @param mixed $dataOrDataset Standard data array or dataset
 * @param array $parameters Named list of systeme parameters
 * @return string THe JSON string representing the table
 */
function UDUTILITY_listContainersAsThumbnails( $dataOrDataset, $parameters=[ 'sort'=>'dmodified', 'ascOrDesc'=>true]) {
    // 2DO use maxNb & offset
    // Make dataset of data
    if ( is_Object( $dataOrDataset) /*&& get_class( $dataOrDataset) == "Dataset"*/) {
        // Already a dataset
        $dataset = $dataOrDataset;
    } elseif ( is_array( $dataOrDataset)) {
        // Make dataset
        $dataset = new Dataset();
        $dataset->load( $dataOrDataset);
    }
    if ( !$dataset) return "";
    $r = "";
    if ( isset( $params[ 'sort'])) $dataset->sort( $params[ 'sort'], $params[ 'ascOrDesc']); 
    // Loop through provided elements
    global $UDUTILITY_imageGenCount;
    $UDUTILITY_imageGenCount = 3;
    $typeCounts = [ 0, 0, 0, 0];
    while ( !$dataset->eof()) {
        $elementData = $dataset->next();
        UD_utilities::analyseContent( $elementData, $captionIndexes);
        $type = (int) $elementData[ 'stype'];
        $typeCounts[ $type]++;
        $elementData[ 'nname'] = str_replace( ' ', '_', $elementData[ 'nname']);
        if ( $type == UD_document || $type == UD_model || $type == UD_docThumb) {
            // Document thumbnail with link to new window for showing document
            $elementData[ 'stype'] = UD_docThumb;
            if ( !isset( $elementData[ '_link'])) $elementData[ '_link'] = "/webdesk/" . L_oidChildren( $elementData[ 'oid']).'--{OIDPARAM}/show/';
            $elementData[ 'nlabel'] = '{!Ouvrir!}';
            // #2224002 not ready $elementData[ '_image'] = UDUTILITY_getDocImage( $elementData);
            //$elementData[ 'oid'] = "";
            // textra $this->extra[ 'system']; ['thumbnail'] = UDUTILITY_getDocImage
            $element = new UDdocument( $elementData);     
            $w = $element->renderAsHTMLandJS();
            $r .= $w[ 'content'];
        } elseif ( $type == UD_directory || $type == UD_dirThumb) {
            // Directory thumbnail with link to update view with directorie's contents 
            $elementData[ 'stype'] = UD_dirThumb;
            if ( !isset( $elementData[ '_link'])) $elementData[ '_link'] = 'UniversalDocElement--'.implode('-', LF_stringToOid( $elementData[ 'oid'])).'-21/AJAX_listContainers/';
            $elementData[ 'nlabel'] = '{!Ouvrir!}';
            //$elementData[ 'oid'] = "";
            $element = new UDdirectory( $elementData);
            $w = $element->renderAsHTMLandJS();
            $r .= $w[ 'content'];
        } 
    }
    if ( $parameters[ 'wrEnable']) {
        // Add a new task, process or process model
        $title = '{!Nouvelle t??che!} {!ou!} {!groupe de t??ches!}';
        $subTitle = '{!Ajouter!} {!une nouvelle t??che!} {!ou!} {!un groupe de t??ches!} ici en cliquant sur Ajouter!}';
        if ( $typeCounts[3] > $typeCounts[ 2]) {
            // Model
            $title = '{!Nouveau mod??le!} {!ou!} {!processus!}';
            $subTitle = '{!Ajouter!} {!un nouveau model!} {!ou!} {!processus!} ici en cliquant sur Ajouter!}';
        }
        // Document thumbnail with link to new window for showing document
        $newTaskData[ 'nname'] = 'New task';
        $newTaskData[ 'nlabel'] = '{!Ajouter!}';
        $newTaskData[ 'stype'] = UD_docThumb;        
        $newTaskData[ 'tcontent'] = '{!Nouvelle t??che!} {!ou!} {!groupe de t??ches!}';
        $newTaskData[ '_title'] = $title;
        $newTaskData[ '_subTitle'] = $subTitle;
        $newTaskData[ '_onclick'] = 'API.createDocument();';
        $newTaskData[ '_noPlanning'] = true;
        $newTaskData[ '_deleteStyle'] = 'hidden';
        $newTask = new UDdocument( $newTaskData);     
        $w = $newTask->renderAsHTMLandJS();
        $r = $w[ 'content'].$r;
    }
    // Return HTML with thumnail elements
    return $r;

} // UDUTILITY_listContainersAsThumbnails()

function UDUTILITY_breadcrumbs( $pathWithLinks) {
    $breadcrumbs = '<div id="DIRS_Breadcrumbs"><nav class="breadcrumb has-arrow-separator" aria-label="breadcrumbs">';
    $breadcrumbs .= '<ul>';
    $lastName = end(array_keys($pathWithLinks));
    foreach( $pathWithLinks as $name => $link) {
        $selected = ( $name == $lastName) ? 'class="is-active"' : '';       
        $breadcrumbs .= "<li $selected><a href=\"javascript:\" onclick=\"{$link}\">{$name}</a></li>";
    }
    $breadcrumbs .= '</nav></div>';
    return $breadcrumbs;
    /*
    $docOID =  LF_env( 'UD_docOID'); // set in UD_utilities::buildSortedAndFilteredDataset();
    if ( LF_env( 'reconnect')) {
        $docOID = "";
        LF_env( 'reconnect', '');
        LF_env( 'UD_docOID', '');
    }
    $viewId = "API.dom.getView( 'DIRS_Breadcrumbs').id";
    if ( $docOID) {
        // Start from a specifc OID
        $link = $docOID;
        $link .= "/AJAX_listContainers/";             
        $onclick = "window.ud.udajax.updateZone( '{$link}', $viewId);";
        $breadcrumbs .= "<li><a href=\"javascript:\" onclick=\"{$onclick}\">Top</a></li>";
    } else {
        // Start from top
        $icon = '<span class="icon is-small"><i class="fa fa-home" aria-hidden="true"></i></span>';
        $breadcrumbs .= "<li><a href=\"/webdesk/?TOP=1\">{$icon}Top</a></li>";
    }
    $startBreadcrumbs = LF_count( LF_stringToOid( $docOID)) - 1;
    //if ( $startBreadcrumbs < 2) $breadcrumbs .= " <a href=\"/webdesk/\">Top</a>";
    if ( LF_count( $oid) >= 2) {
        $oidSoFar = [];
        if ( LF_count( $oid) % 2) array_pop( $oid);
        $home = false;
        for ( $pathi=0; $pathi < LF_count( $path); $pathi +=2) {
            $oidSoFar[] = $oid[ $oidi];
            $oidSoFar[] = $oid[ $oidi + 1];
            if ( $oid[ $oidi] != 21) continue;
            $nodeInfo = $access->getDocInfo( $path[ $pathi]);
            $nodeData = LF_fetchNode( LF_oidToString(  $oidSoFar));
            $nodeData = $nodeData[ 1];
            UD_utilities::analyseContent( $nodeData);
            $nodeData[ 'stype'] = UD_dirThumb;  
            $node = new UDdirectory( $nodeData); 
            $name = $node->title;
            $link = "";      
            if ( $oidi >= $startBreadcrumbs) {
            if ( $home) {
                $home = false;
                $onclick = "API.reloadView( {$viewId});";
            } else { 
                $link .= "UniversalDocElement--".implode( "-", LF_stringToOid( $nodeData[ 'oid']))."-21";
                $link .= "/AJAX_listContainers/";             
                $onclick = "window.ud.udajax.updateZone( '{$link}', $viewId);";
            }
            $breadcrumbs .= " > ";
            $breadcrumbs .= "<a href=\"javascript::\" onclick=\"{$onclick}\">{$name}</a>";
            $selected = ( $oidi >= ( LF_count( $oid) - 2)) ? 'class="is-active"' : '';       
            $breadcrumbsBulma .= "<li $selected><a href=\"javascript:\" onclick=\"{$onclick}\">{$name}</a></li>";
            }        	
        }
     }
     $breadcrumbsBulma .= '</nav>';
     $controls = "";
     if ( false) {   
            // Add controls on last step   
            / * bin on each element   	
            $recycleBin = LF_env( 'LINKS_wasteBinOID'); 
            $recycleClick = "window.ud.udajax.updateZone('{$recycleBin}-21--{OIDPARAM}/AJAX_modelShow/','document');";
            $controls .= ' <span data-ud-type="button" class="recycleButton" onclick="'.$recycleClick.'" style="width:100%;"><img id="UD_wasteBin" src="/upload/N313S2W1m_wastebin.png" style="float:right; margin-left:20px;" alt="Recycler"></span>';
            * /
            $controls .= '<span data-ud-type="button" class="addButton" onclick="API.createDocument();" style="width:100%;"><img src="/upload/XM1MpN9nO_addfile.png" style="float:right;"></span>';      	
            $controls .= "<br><a href=\"javascript::\" onclick=\"$$$.showOneOfClass('Manage',1);$$$.buildManagePart();\">{!Manage!}</a>";
            $newWindowFormula = "checkboxTag( 1, 'UD_openNewWindow', 'dir','Open files in new window');";
            $newWindowChange = "if (this.checked) this.value='yes'; else this.value='no';window.ud.ude.updateTable('dir');";
            $controls  .= '<span data-ud-type="field" data-ude-formula="'.$newWindowFormula.'" id="udcalc8"><input id="UD_openNewWindow" type="checkbox" onchange="'.$newWindowChange.'" checked="" value="yes">Open files in new window</span>';
            $breadcrumbs .= $controls;
            $breadcrumbsBulma .= $controls;
     } 
     $breadcrumbs .= "</div>";
     $breadcrumbsBulma .= "</div>";    
     */
}

/**
 * Prepare JS so client updates the doc info resource
 * @param mixed $elementData Data record of the container
 */
function UDUTILITY_updateDocInfo( $elementData) {
    $js = "";
    $title = $nodeData[ '_title'];
    $subtitle =  $nodeData[ '_subtitle'];
    $oid = LF_stringToOid( $elementData[ 'oid']);
    $docOID = "UniversalDocElement--".implode( '-', $oid);
    $oidNew = $docOID."-0";
    $oidChildren = $docOID."-21";
    // 2DO complete = docModel = Basic Dir, docFull
    $js .= "$$$.dom.element( 'UD_docTitle').textContent = '{$title}';\n";
    $js .= "$$$.dom.element( 'UD_docSubtitle').textContent = '{$subtitle}';\n";
    $js .= "$$$.dom.element( 'UD_oidNew').textContent = '{$oidNew}';\n";
    $js .= "$$$.dom.attr( 'document', 'ud_oid','{$docOID}');\n";
    $js .= "$$$.dom.attr( 'document', 'ud_oidchildren','{$oidChildren}');\n";
    $LF->onload( $js);
}

/**
 * Get 1st or best image found in UD element or its children
 * Cache info 
 */ 
function UDUTILITY_getDocImage( $docData, $width=280, $height=120) {
    $id = $docData[ 'id'];    
    $thumbnail = ( TEST_ENVIRONMENT) ? "../" : "";
    $thumbnail .= "tmp/udthumbnail{$id}.jpg";
    // Return thumnail image if it exists
    if ( file_exists( $thumbnail)) return "/".$thumbnail;
    // Generate thumbnail image
    $type = (int) $docData[ 'stype'];
    if ( $type == UD_directory || $type == UD_dirThumb) {
        // Directory contents
    } elseif ( $type == UD_model || $type == UD_modelThumb) {
        // Display models public view
    } elseif ( $type == UD_document || $type == UD_docThumb) {
        // Document contents
        $oid = LF_oidToString( LF_stringToOid( $docData[ 'oid']))."--UD|1|NO|OIDLENGTH|CD|5"; //.LF_env( 'OIDPARAM');
        $uri = "webdesk/{$oid}/show"; //VIEWAS%7CPRINT/";
        //$url = "https://www.sd-bee.com/webdesk/{$oid}/show/";
        $options = "--enable-local-file-access --images";
        $program = ( TEST_ENVIRONMENT) ? '"C:\Program Files\wkhtmltopdf\bin\wkhtmltoimage.exe"' : "wkhtmltoimage";
        if ( TEST_ENVIRONMENT) {
            global $LFF;
            $page = $LFF->fetchURI( $uri); //, "NONE=NO"); //VIEWAS=PRINT");
        } else {            
            LF_env( 'VIEWAS', "PRINT");
            $page = process_request( $oid, "show", UD_soilink_service);
            LF_env( 'VIEWAS', "");
        }
        if ( $page) {
            $workFileName = ( TEST_ENVIRONMENT) ? "../" : "";
            $workFilename .= "tmp/work{$id}.html";
            if ( TEST_ENVIRONMENT) $workFilename = "../".$workFilename;
            file_put_contents( $workFilename, $page);
            $convert = "{$program} {$options} {$workFilename} {$thumbnail}";
            echo $convert."\n";
            exec( $convert, $response);
            //unlink( $workFilename);
            if ( file_exists( $thumbnail)) {
                $thumbnail = "/".FILE_getImageFile( $thumbnail, $width, $height);
                return $thumbnail;
            }
            var_dump( $response);
        }
        return "";
    }
    return "";
} // UDUTILITY_getDocImage()

/**
 * Build a JSON coded table from standard data array
 * @param array $data Standard data array 
 * @param array $system Named list of systeme parameters
 * @return string THe JSON string representing the table
 */
function buildJSONtableFromData( $data, $system)
{
    $table = [];
    // Get columns
    $cols = $data[0];    
    // Get system variables
    $name = $system[ 'name'];
    $cssClass = $system[ 'cssClass'];
    $sourceURL = $system[ 'source']; 
    $version = $system[ 'version'];    
    // Add system variables
    $date = date( DateTimeInterface::ISO8601);
    $table['_table'] = ['_id'=>$name, '_classList'=>$cssClass, '_sourceURL'=>$sourceURL, '_updateMinutes'=>1440, '_lastUpdated'=>$date];
    // Add header
    $row = [];
    for ( $j=0; $j<LF_count( $cols); $j++) $row[ $cols[ $j]] = [ 'value'=>$cols[$j], 'tag'=>"th"];
    $table[ 'thead'][] = $row;
    $row = [ '_class'=>"rowModel"];
    for ( $j=0; $j<LF_count( $cols); $j++) $row[ $cols[ $j]] = [ 'value'=>""];
    $table[ 'thead'][] = $row;
    $table[ 'tbody'] = [];
    // Add body
    for ( $i=1; $i<LF_count( $data); $i++)
    {
        $row = [];            
        for ( $j=0; $j<LF_count( $cols); $j++)  $row[ $cols[$j]] = [ 'value'=>$data[$i][$cols[$j]]];
        $table['tbody'][] = $row;
    }
    // Encode as JSON 
    $tableJSON = JSON_encode( $table);
    // Align to requested version
    if ( $version == "-2.7")  // Use date ?
    {
        include_once( __DIR__."/../modules/data-versions/uddataversion--2.6.php");
        $elementData= [ 'tcontent'=>"<span class=\"caption\">Caption<span class=\"objectName\">{$name}</span></span><div>{$tableJSON}</div>"];
        UD_alignContent( $elementData);
        $tableJSON = $elementData['tcontent'];
    }
    return $tableJSON;

} // buildJSONtableFromData

function new_buildJSONtableFromData( $data, $system) {
    $table = [];
    // Get columns
    $cols = $data[0];    
    // Get system variables
    $name = $system[ 'name'];
    $cssClass = $system[ 'cssClass'];
    $sourceURL = $system[ 'source']; 
    $version = $system[ 'version']; 
    $caption = $system[ 'caption'];
    $offset = $system[ 'offset'];
    $modelRow = $system[ 'modelRow'];
    $linksDB = $system[ 'linksDB'];
    if ( !$offset) $offset = 0;
    // Add system variables
    $date = date( DateTimeInterface::ISO8601);
    $table[ 'meta'] = [ 'type'=>'table', 'name'=>$name, 'zone'=>$name."editZone"];         
    if ( isset( $system[ 'zone'])) { $table['meta']['zone'] = $system[ 'zone'];}
    if ( $offset || true) { $table[ 'meta'][ 'offset'] = $offset;}
    if ( $caption) $table[ 'meta'] = array_merge( $table[ 'meta'], ['caption'=>$caption, 'captionPosition'=>"top"]);
    // Use jsonTable
    $tableData = [ 'tag'=>"jsontable", 'class'=>$cssClass, 'sourceURL'=>$sourceURL, 'updateMinutes'=>1440, '_lastUpdated'=>$date];
    if ( isset( $system[ 'datasrc'])) { $tableData['datasrc'] = $system[ 'datasrc'];}
    $tableValue = [];
    if ( $modelRow) { $tableValue['model'] = $modelRow;}
    for ( $i=1; $i<LF_count( $data); $i++) {
        $row = [];            
        for ( $j=0; $j<LF_count( $cols); $j++) {
            $col = $cols[ $j];
            $row[ $col] = ( $linksDB) ? LF_preDisplay( $col[ 0], $data[ $i][ $col]) : $data[ $i][ $col];
            if ( $col == "id") { $row[ 'oid'] = $data[$i][ 'oid'];} 
        }
        $tableValue[ 'row '.( $i + $offset)] = $row;
    }
    $tableData[ 'value'] = $tableValue;    
    $table[ 'data'] = $tableData;
    // Encode as JSON     
    $tableJSON = JSON_encode( $table);
    // Patch 12/04/2022
    $tableJSON = str_replace( '\ufeff', '', $tableJSON);
    return $tableJSON;    
} // new_buildJSONtableFromData()

if ( $argv[0] && strpos( $argv[0], "udutilityfunctions.php") !== false)
{    
    // Launched with php.ini so run auto-test
    // Create test environment
    require_once( __DIR__."/../tests/testenv.php");
    require_once( __DIR__."/../ud-view-model/ud.php");
    require_once( __DIR__."/../tests/testsoilapi.php");
    global $LFF;
    $LFF = new Test_dataModel();
    LF_env( 'cache', 5);
    // global $UD;
    // $UD = new UniversalDoc( ['mode'=>"edit"]);
    // Test
    echo "udutilityfunctions.php auto-test program\n";    
    {
        // Test data to JSON table
        $test = "data toJSON table conversion";
        $data = [[ "imyCol1", "tmyCol2"], [ 'imyCol1'=>25, 'tmyCol2'=>"abc&quote;efg"]];
        $params = [ 
            'name'=> 'mytarget','cssClass'=>"dataset",'source'=>"an-oid--21-5895", 
            'zone' =>'mytarget'."_dataeditZone", 'linksDB' =>true,
            'version'=>"-2.7"
        ];         
        $json = new_buildJSONtableFromData( $data, $params);
        if ( strpos( $json, "abc'efg") !== false) echo "Test $test: OK\n"; else   echo "Test $test: KO $json\n";
    }
    {
        // Test list containers as thumbnails
        $test = "listContainersAsThumbnails()";
        $data = [
            [ "id", "nname", "stype", "tcontent", "textra"], 
            [ 'id'=>857, 'oid'=>"UniversalDocElement--21-2588", 'nname'=>"Dir 1", 'stype'=>UD_directory, 'tcontent'=>"Just a title", 'textra'=>""]];
        $r = UDUTILITY_listContainersAsThumbnails( $data);
        if ( strpos( $r, "Just a title") !== false) echo "Test $test: OK\n"; else   echo "Test $test: KO $r\n";
    }
    {
        // Test thumbnail image generation
        $test = "UDUTILITY_getDocImage";
        $r = $LFF->openSession( "demo", "demo", 133);
        sleep(1);
        $docOid = 'UniversalDocElement--21-725';
        $docData = $LFF->fetchNode( $docOid);
        $thumb = UDUTILITY_getDocImage( $docData[ 1]);
        var_dump( $thumb);
        if ( $thumb) echo "Test $test: OK $thumb\n"; else   echo "Test $test: KO ".print_r( $docData, true)."\n";
    }
    global $debugTxt;
    echo "Program's trace :\n{$debugTxt}\n";
    $check = crc32( $debugTxt);
    echo "Program's trace checksum:$check\n";      
    echo "Test completed\n";
    exit(0);
} // end of auto-test