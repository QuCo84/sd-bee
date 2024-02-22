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
function UDUTILITY_listContainersAsThumbnails( $dataOrDataset, $params=[ 'sort'=>'dmodified', 'ascOrDesc'=>true, 'auto-archive'=>false]) {
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
    if ( val( $params, 'sort')) $dataset->sort( $params[ 'sort'], val( $params, 'ascOrDesc')); 
    // Loop through provided elements
    global $UDUTILITY_imageGenCount;
    $UDUTILITY_imageGenCount = 3;
    $typeCounts = [ 0, 0, 0, 0];
    // Archiving variables
    $archiving = ( isset( $params[ 'auto-archive'])) ? $params[ 'auto-archive'] : false;
    if ( $archiving) {
        $archiveDays = 90;
        $archiveMinDocs = 5;
        $archiveName = "Archive-{$params[ 'collection']}-".LF_env( 'user').LF_env('userId')."-".date('Ymd-hi');
        $archive = [];
        $now = time();        
    }
    $docsRemaining = $dataset->size;
    while ( !$dataset->eof()) {
        $elementData = $dataset->next();
        $docsRemaining--;
        UD_utilities::analyseContent( $elementData, $captionIndexes);
        $type = (int) val( $elementData, 'stype');
        $typeCounts[ $type]++;
        $elementData[ 'nname'] = str_replace( ' ', '_', val( $elementData, 'nname'));
        if ( $type == UD_document || $type == UD_model || $type == UD_docThumb) {
            if ( $elementData[ 'nname'][0] == 'S') continue; // !!! IMPORTANT do this first so no archiving on S elements
            // Automatic archiving
            // 2DO test !model and nb of remaining tasks 
            if ( 
                $archiving 
                && ( $docsRemaining >= $archiveMinDocs || count( $archive))
                && $type == UD_document && $elementData[ 'nname'][0] == 'A'
                && ($archiveDays * 86400 + LF_timestamp( (int) val( $elementData, 'dcreated'))) < $now
            ) {
                // Old task so mark for archiving
                $archive[] = $elementData;
                if ( count( $archive) == 1) {
                    // Replace with archive to be created
                    $elementData[ 'stype'] = UD_dirThumb; // UD_archiveThumb
                    $elementData[ '_link'] = 'UniversalDocElement--'.implode('-', LF_stringToOid( val( $elementData, 'oid'))).'-21/AJAX_listContainers/';
                    $elementData[ 'nlabel'] = '{!Ouvrir!}';
                    $elementData['_title'] .= " Archive";
                    //$elementData[ 'oid'] = "";
                    $element = new UDdirectory( $elementData);
                    $w = $element->renderAsHTMLandJS();
                    $r .= val( $w, 'content');
                } 
                continue;
            }
            // Document thumbnail with link to new window for showing document
            $elementData[ 'stype'] = ( isset( $params[ 'doc-type'])) ? $params[ 'doc-type'] : UD_docThumb;
            // 2DO use params[ linkModel] to determine what link should look like, for displayPage( 'blog_xxxx')
            if ( isset( $params[ 'click-model']) && $params[ 'click-model']) {
                $elementData[ '_onclick'] = LF_substitute( $params[ 'click-model'], $elementData);
            }
            if ( !val( $elementData, '_link')) $elementData[ '_link'] = "/webdesk/" . L_oidChildren( val( $elementData, 'oid')).'--{OIDPARAM}/show/';
            $elementData[ 'nlabel'] = '{!Ouvrir!}';
            // Get an image for this element
            $elementData[ '_image'] = UDUTILITY_getDocImage( $elementData);
            //$elementData[ 'oid'] = "";
            // textra val( $this->extra, 'system'); ['thumbnail'] = UDUTILITY_getDocImage
            switch( val( $elementData, 'stype')) {
                case UD_docThumb  : $element = new UDdocument( $elementData); break;
                case UD_articleThumb : $element = new UDarticle( $elementData); break;
            }
            $w = $element->renderAsHTMLandJS();
            $r .= val( $w, 'content');
        } elseif ( $type == UD_directory || $type == UD_dirThumb) {
            // Directory thumbnail with link to update view with directorie's contents 
            $elementData[ 'stype'] = UD_dirThumb;
            $sort = "";
            if ( stripos( $elementData['nlabel'], 'models') !== false || stripos( $elementData['tcontent'], 'models') !== false) 
                 $sort = "?s=nlabel&o=0";
            if ( !val( $elementData, '_link')) 
                $elementData[ '_link'] = 'UniversalDocElement--'.implode('-', LF_stringToOid( val( $elementData, 'oid')))."-21/AJAX_listContainers/{$sort}";
            $elementData[ 'nlabel'] = '{!Ouvrir!}';
            //$elementData[ 'oid'] = "";
            $element = new UDdirectory( $elementData);
            $w = $element->renderAsHTMLandJS();
            $r .= val( $w, 'content');
        } 
    }
    if ( $archiving && count( $archive)) {
        global $ACCESS;
        if ( $ACCESS) {
            // OS version
            $ACCESS->archive( $archive, val( $params, 'collection'));
        } else {
            // SOILinks version
            include_once( __DIR__."/../ud-view-model/udfile.php"); 
            $r .= UDFILE::archive( $archive, val( $params, 'collection'));
        }
    }
    
    if ( val( $params, 'wrEnable')) {
        // Add a new task, process or process model
        $title = '{!Nouvelle tâche!} {!ou!} {!groupe de tâches!}';
        $subTitle = '{!Ajouter!} {!une nouvelle tâche!} {!ou!} {!un groupe de tâches!} ici en cliquant sur Ajouter!}';
        if ( $typeCounts[3] > $typeCounts[ 2]) {
            // Model
            $title = '{!Nouveau modèle!} {!ou!} {!processus!}';
            $subTitle = '{!Ajouter!} {!un nouveau model!} {!ou!} {!processus!} ici en cliquant sur Ajouter!}';
        }
        // Document thumbnail with link to new window for showing document
        $newTaskData[ 'nname'] = 'New task';
        $newTaskData[ 'nlabel'] = '{!Ajouter!}';
        $newTaskData[ 'stype'] = ( isset( $params[ 'doc-type'])) ? $params[ 'doc-type'] : UD_docThumb;        
        $newTaskData[ 'tcontent'] = '{!Nouvelle tâche!} {!ou!} {!groupe de tâches!}';
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
    $breadcrumbs = '<div id="DIRS_Breadcrumbs" style="width:100%;height:1.5em;">';
    $breadcrumbs .= '<nav class="breadcrumb has-arrow-separator" aria-label="breadcrumbs" style="float:left; margin:0;">';
    $breadcrumbs .= '<ul style="margin:0;padding:0">';
    $lastName = end(array_keys($pathWithLinks));
    foreach( $pathWithLinks as $name => $link) {
        $selected = ( $name == $lastName) ? 'class="is-active"' : '';       
        $breadcrumbs .= "<li $selected><a href=\"javascript:\" onclick=\"{$link}\">{$name}</a></li>";
    }
    $breadcrumbs .= '</nav>';    
    $breadcrumbs .= '<nav class="breadcrumb" aria-label="breadcrumbs" style="float:right;color:rgb( 50,50,50);">';
    $breadcrumbs .= '<a href="javascript:" onclick="$$$.showOneOfClass(\'Manage\',1);$$$.buildManagePart();">';
    $breadcrumbs .= '{!Manage!}</a></nav></div>';
    return $breadcrumbs;
}

/**
 * Prepare JS so client updates the doc info resource
 * @param mixed $elementData Data record of the container
 */
function UDUTILITY_updateDocInfo( $elementData) {
    $js = "";
    $title = val( $nodeData, '_title');
    $subtitle =  val( $nodeData, '_subtitle');
    $oid = LF_stringToOid( val( $elementData, 'oid'));
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
    $id = val( $docData, 'id');    
    $thumbnail = ( TEST_ENVIRONMENT) ? "../" : "";
    $thumbnail .= "tmp/udthumbnail{$id}.jpg";
    // Return thumnail image if it exists
    if ( file_exists( $thumbnail)) return "/".$thumbnail;
    // Generate thumbnail image
    $type = (int) val( $docData, 'stype');
    if ( $type == UD_directory || $type == UD_dirThumb) {
        // Directory contents
    } elseif ( $type == UD_model || $type == UD_modelThumb) {
        // Display models public view
    } elseif ( in_array( $type, [ UD_document, UD_docThumb, UD_articleThumb])) {
        // Document contents
        // Look at extra paramaters in docData
        $params = val( $docData, '_extra/system');
        if ( $params && isset( $params[ 'thumbnail-image'])) return $params['thumbnail-image'];
        return "";     
        $oid = LF_oidToString( LF_stringToOid( val( $docData, 'oid')))."--UD|1|NO|OIDLENGTH|CD|5"; //.LF_env( 'OIDPARAM');
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

function UDUTILITY_addClip( $name, $type, $content) {
    global $ACCESS, $LD;
    if ( $ACCESS) $ACCESS->addClip( $name, $type, $content);
    elseif ( $LF)  {
        $w = LF_fetchNode( 'SimpleArticle--5--nname|clipboard*');
        $clipboardHome = $w[1][ 'oid'];
        $clip = [
            'name' => $name,
            'ttext' => $content
        ];
        LF_createNode( $clipboardHome, "SimpleArticle", [ [ 'nname', 'ttext'], $clip]);
    }
}

/**
 * OLD still needed ?
*/


/**
 * Build a JSON coded table from standard data array DEPRECATED
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
    $name = val( $system, 'name');
    $cssClass = val( $system, 'cssClass');
    $sourceURL = val( $system, 'source'); 
    $version = val( $system, 'version');    
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
        $tableJSON = val( $elementData, 'tcontent');
    }
    return $tableJSON;

} // buildJSONtableFromData

/**
 * DEPRECATE with new connector setup or at least have a param for the zone name
 */
function new_buildJSONtableFromData( $data, $system) {
    $table = [];
    // Get columns
    $cols = $data[0];    
    // Get system variables
    $name = val( $system, 'name');
    $cssClass = val( $system, 'cssClass');
    $sourceURL = val( $system, 'source'); 
    $version = val( $system, 'version'); 
    $caption = val( $system, 'caption');
    $offset = val( $system, 'offset');
    $modelRow = val( $system, 'modelRow');
    $linksDB = val( $system, 'linksDB');
    if ( !$offset) $offset = 0;
    // Add system variables
    $date = date( DateTimeInterface::ISO8601);
    $table[ 'meta'] = [ 'type'=>'table', 'name'=>$name, 'zone'=>$name."editZone"];         
    if ( val( $system, 'zone')) { $table['meta']['zone'] = val( $system, 'zone');}
    if ( $offset || true) { $table[ 'meta'][ 'offset'] = $offset;}
    if ( $caption) $table[ 'meta'] = array_merge( $table[ 'meta'], ['caption'=>$caption, 'captionPosition'=>"top"]);
    // Use jsonTable
    $tableData = [ 'tag'=>"jsontable", 'class'=>$cssClass, 'sourceURL'=>$sourceURL, 'updateMinutes'=>1440, '_lastUpdated'=>$date];
    if ( val( $system, 'datasrc')) { $tableData['datasrc'] = val( $system, 'datasrc');}
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

function SDBEE_translate( $text, $lang, $targetLang) {
    $req = [
        "action" => "translate",
        "source" => strToLower( $lang),
        "target" =>  strToLower( $targetLang),
        "text" => $text
    ];
    require_once( __DIR__."/../services/translation/udstranslation.php");
    $translateService = new UDS_translation();
    $rep = $translateService->call( $req);
    if ( !val( $rep, 'success')) return "";
    else return val( $rep, 'data/translation');
}

function SDBEE_exportUDasJSON( $oid, $ud=null) {
    function L_getNodeForExport( $node) {
        // Node's depth
        global $EXPORTUD_topDepth;
        $depth = (int) LF_count( LF_stringToOid( val( $node, 'oid')))/2;
        if ( !$EXPORTUD_topDepth) $EXPORTUD_topDepth = $depth;
        $depth -= $EXPORTUD_topDepth;
        // Permissions
        $access = 0;
        $w = LF_stringToOidParams( val( $node, 'oid'));
        if ( $w) $access = (int) $w[0]['AL'];
        // tcontent can be JSON
        $tcontent = LF_preDisplay( 't', val( $node, 'tcontent'));
        if ( ( $JSONcontent = JSON_decode( $tcontent, true))) $content = $JSONcontent;
        else $content = $tcontent;
        // Get language (avoid null)
        $lang = ( val( $node, 'nlanguage')) ? $node[ 'nlanguage'] : "";
        // Extract useful parameters from textra
        $params = JSON_decode( LF_preDisplay( 't', val( $node, 'textra')), true);
        $exportParams = [];
        if ( $params) {
          if (val( $params, 'height')) {
              $exportParams[ 'height'] = val( $params, 'height');
              $exportParams[ 'width'] = val( $params, 'width');
              if ( val( $params, 'offsetLeft')) $exportParams[ 'offsetLeft'] = val( $params, 'offsetLeft');
              if ( val( $params, 'offsetTop')) $exportParams[ 'offsetTop'] = val( $params, 'offsetTop');
              if ( val( $params, 'marginTop')) $exportParams[ 'marginTop'] = val( $params, 'marginTop');
              if ( val( $params, 'marginBottom')) $exportParams[ 'marginBottom'] = val( $params, 'marginBottom');
          } 	
          $system = val( $params, 'system');
          if ( $system) {
            foreach ( $system as $key=>$value) {
               if ( !in_array( $key, ["botlog"])) {
                 $exportParams[ 'system'][ $key] = $value;
                 // $exportParams[ $key] = $value;
               }
            }  
          }
        }  
        // Dates
        $d = DateTime::createFromFormat( 'd/m/Y H:i:s', LF_date( (int) val( $node, 'dcreated')));
        if ( !$d) $d = DateTime::createFromFormat( 'd/m/Y H:i', LF_date( (int) val( $node, 'dcreated')));
        if ($d) $dcreated = $d->getTimestamp(); else $dcreated = LF_date( (int) val( $node, 'dcreated'));
        $d = DateTime::createFromFormat( 'd/m/Y H:i:s', LF_date( (int) val( $node, 'dmodified')));
        if ( !$d) $d = DateTime::createFromFormat( 'd/m/Y H:i', LF_date( (int) val( $node, 'dmodified')));
        if ($d) $dmodified = $d->getTimestamp(); else $dmodified = LF_date( (int) val( $node, 'dmodified'));
        // Compile content
        $exportNode = [
         //'nname' => $node[ 'nname'],
         'depth' => $depth,
         'permissions' => $access,
         'nlabel' => LF_preDisplay( 'n', val( $node, 'nlabel')),
         'stype' => $node[ 'stype'],
         'nstyle' => LF_preDisplay( 'n',val( $node, 'nstyle')),
         'tcontent' => $content,
         'thtml' => "",
         'nlanguage' => $lang,
         'textra' => $exportParams,
         // 'iaccessRequest' => $node[ 'iaccessRequest'],
         'dcreated' => $dcreated, //strtotime( LF_date( (int) val( $node, 'dcreated'))),    '
         'dmodified' => $dmodified, // strtotime( LF_date( (int) val( $node, 'dmodified')))
         //'tcreated' => LF_date( (int) val( $node, 'dcreated')). ' from '.$node[ 'dcreated'],
         //'tmodified' => LF_date( (int) val( $node, 'dmodified'))
       ];
       return $exportNode;
    }
    // Main
    $content = [];
    // Detect Task(Doc) or Collection(Dir)
    if ( (int)  val( $node, 'stype') == UD_directory) {
       // Directory
       $childrenOid =  LF_mergeOID( LF_stringToOid( val( $node, 'oid')), "--21");
       $children = LF_fetchNode( $childrenOid, "* tlabel"); 
       echo $childrenOid.' ';
       for ( $childi=1; $childi < LF_count( $children); $childi++) {
           // Export each document in directory using recursive call
           echo "export {$children[ $childi][ 'nname']}<br>\n";
           exportUDasJSON( $children[ $childi][ 'oid']);
       }
       return;  
    } 
    // Document
    // Look at standard depth
    $oid = LF_substitute(LF_mergeShortOID( $oid, ""), [ 'OIDPARAM' => LF_env( 'OIDPARAM')]);
    // Build named array from sorted data  
    $dataset = UD_utilities::buildSortedAndFilteredDataset( $oid);
    while ( !$dataset->eof()) { 
      if ( !($elementData = $dataset->next())) continue;
      if ( !val( $elementData, 'nname')) continue;
      $content[ $elementData[ 'nname']] = L_getNodeForExport( $elementData);
    } 
    // Compilation cache
    if ( $ud) {
        // Save with compiler cache
        $extendedContent = [
            'content' => $content,
            'model' => "",
            'html' => $ud->content,
            'program' => $ud->program,
            'css' => $ud->style,
            'hidden' => $ud->hidden,
            'modifiedResources' => UD_getModifiedResources( false),
            'requiredModules'=>$ud->requiredModules,
            'pageHeight' => ( $ud->pager->docPageHeight)? $modelUD->pager->docPageHeight : "",
            'defaultPart' => ( $ud->displayPart) ? $modelUD->displayPart : "",
            'validDate' => time() + 4 * 60 * 60,                    
            'date' => LF_date(), //hmm
            'dependencies' => $ud->loadedModels // UD_getFetchedResources()
        ];        
    } else {
        // Save with no compiler cache
        $extendedContent = [
        'content' => $content,
        'model' => "",
        'html' => "",
        'css' => ""
        ];
    }
    // Convert to JSON
    $json = JSON_encode( $extendedContent, JSON_PRETTY_PRINT); 
    // Write to downloadable file
    $tok = "ymbNpnZm8"; // Get user's public token LF_getToken();
    $name = val( $node, 'nname');  
   // FILE_write( 'download', $tok."_".$name.".json", -1, $json);
    FILE_write( 'download', $name.".json", -1, $json);
    // FILE_write( $cacheDir, $cachedModelFilename, -1, JSON_encode( $save, JSON_PRETTY_PRINT));
    return $json;    
}

/**
 * Read a value from array avoiding warnings or errors
 */
function val( $container, $key, $default=null) {
    /*
    * idea $default="__UNSET__" to delete value unset( val => val,,__UNSET__)
    */
    if ( isset( $_REQUEST[ 'debug'])) {
        // Temp debug code
        global $DEBUG_startTime;
        $maxTime = 15;
        if ( !$DEBUG_startTime) $DEBUG_startTime = time();
        elseif ( ( time() - $DEBUG_startTime) > $maxTime) {
            $d = debug_backtrace();
            $t = "Stash<br>\n";
            for ( $di=0; $di < count( $d); $di++) { 
                $t .= " {$d[ $di][ 'file']} {$d[ $di][ 'line']}<br>\n";
            }
            echo $t;
            die( "$maxTime secs timeup");
        }
    }
    if ( is_string( $key)) $keyParts = explode( '/', $key);
    else $keyParts = [ $key];
    switch ( count( $keyParts)) {
        case 1: if ( isset( $container[ $key])) return $container[ $key]; break;
        case 2 : 
            $w = val( $container, $keyParts[0]);
            if ( $w && isset( $w[ $keyParts[1]])) 
                return $w[ $keyParts[1]];
            break;
        case 3 :
            $w = val( $container, $keyParts[0].'/'.$keyParts[1]);
            if ( $w && isset( $w[ $keyParts[2]])) 
                return $w[ $keyParts[2]];
            break;
        default : die( "error in udutilityfunctions/val : too many steps in key");
    }
    return $default;
}
function isVal( $value) { return ( $value);}

if ( isset( $argv) && strpos( $argv[0], "udutilityfunctions.php") !== false)
{    
    // Launched with php.ini so run auto-test
    echo "Syntax: OK\n";
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