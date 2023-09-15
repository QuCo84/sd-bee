<?php
/* ===========================================================================
 *  udconstants.php
 *
 *  Constants for code clarity and parameters. Loaded by ud.php
 */ 

// call with UD_utilities::copyModelIntoUD( "mymodel", "mydoc");
/**
 * Universal Doc constants
 *
 * @package VIEW-MODEL
 */
 // DEPRECATED 2022-05-13 VERSION provided by APP 
 /*
define( 'VERSION', "-v-0-1-7");
define( 'VERSION_DEV', "-v-0-1");
*/
// VENDOR_DIR & VENDOR_AUTOLOAD for 3rd party modules
if ( !defined( 'VENDOR_DIR')) {
    
    if ( $argv[0]) {
        // TESTING
        define( 'VENDOR_DIR', __DIR__."/../../vendor/");      
    } else define( 'VENDOR_DIR', __DIR__."/../vendor/");
    define( 'VENDOR_AUTOLOAD',VENDOR_DIR."autoload.php");
}

define ( "UD_soilink_service", "localhost_webdesk");

// UniversalDocElement types (stype field)
// Containers
define( "UD_directory",             1);                 // a directory element, fills elementNames for breadcrumb function
define( "UD_document",              2);                 // detemines model and autoload, fills header banner
define( "UD_model",                 3);                 // determines pageHeight, defaultPart fils header banner if mode edit
// Document views
define( "UD_part",                  4);                 // div.part
define( "UD_view",                  4);                 // new name for part
define( "UD_subPart",               5);                 // div.subpart
define( "UD_zone",                  5);                 // new name for subpart
// Titles
define( "UD_chapter",               6);                 // h1
define( "UD_section",               7);                 // h2
define( "UD_subsection",            8);                 // h3
define( "UD_subsubsection",         9);                 // h4
define( "UD_paraTitle",            91);                 // h5
define( "UD_subParaTitle",         92);                 // h6
// Basic text elements
define( "UD_paragraph",            10);
define( "UD_subParagraph",         11);
define( "UD_table",                12);
define( "UD_list",                 13);
define( "UD_graphic",              14);
define( "UD_text",                 15);
// Text elements with special use
define( "UD_commands",             16);                 // Server-side instructions16
define( "UD_css",                  17);                 // CSS styles
define( "UD_js",                   18);                 // Javascript
define( "UD_json",                 19);                 // JSON-coded parameters
define( "UD_apiCalls",             20);                 // API calls (obsolete, use JS API.fct ..)
define( "UD_resource",             29);                 // JSON-coded data to modify resources
// Charts
define( "UD_chart",                21);
// Players
define( "UD_audio",                22);
define( "UD_video",                23);
// Layout and native HTML
define( "UD_nonEditable",          30);
define( "UD_zoneToFill",           31);
define( "UD_filledZone",           32);
define( "UD_HTML",                 60);                  // editable HTML bloc
define( "UD_emailTemplate2Col",    61);
define( "UD_HTML_FIN",             69);
// Readers
define( "UD_pdf",                  40);
// Breaks
define( "UD_partBreak",            50);
define( "UD_page",                 51);
define( "UD_pageBreak",            51);
define( "UD_lineBreak",            52);
// Connectors
define( "UD_connector",                80);
define( "UD_connector_csv",            81);
define( "UD_connector_siteExtract",    82);
define( "UD_connector_googleDrive",    83);
define( "UD_connector_dataGloop",      84);
define( "UD_connector_googleCalendar", 85);
define( "UD_connector_dropZone",       86);
define( "UD_connector_service",        87);
define( "UD_connector_document",       88);
define( "UDC_googleSheet",             89);
define( "UDC_googleDoc",               90);
define( "UDC_googleSlides",            91);
define( "UDC_facebook",                92);
define( "UD_connector_end",            99);
// System
define ( "UD_undefined",           100);
define ( "UD_dirThumb",            121);
define ( "UD_docThumb",            122);
define ( "UD_modelThumb",          123);
define ( "UD_closeView",           124);
define ( "UD_closeZone",           125);
define ( "UD_articleThumb",        126);
// UI
define ( "UD_imagePicker",         201);    // uses ud_filter
define ( "UD_datePicker",          202);     
define ( "UD_stateProgress",       203);
define ( "UD_expandableList",      204);




/*
define( "UD_directory",      1); // fills elementNames for breadcrumb function
define( "UD_document",       2); // detemines model and autoload, fills header banner
define( "UD_model",          3); // determines pageHeight, defaultPart fils header banner if mode edit

define( "UD_part",          11); // div.part
define( "UD_subPart",       12); // div.subpart
 
define( "UD_chapter",       21); // h1
define( "UD_section",       22); // h2
define( "UD_subsection",    23); // h3
define( "UD_subsubsection", 24); // h4
define( "UD_paraHeader",    25); // h5
define( "UD_subParaHeader", 26); // h6

define( "UD_paragraph",     31); // p
define( "UD_subParagraph",  32); // p.subpara

define( "UD_table",         41); // div.table
define( "UD_list",          42); // div.list 2DO
define( "UD_graphic",       43); // div.graphic
define( "UD_text",          44); // div.text

define( "UD_commands",      51); // div.text ud_type="server" if model or autload fill onload execute
define( "UD_css",           52); // div.text ud_type="style" if model or autload fill style
define( "UD_js",            53); // div.text ud_type="js" if model or autload fill onload
define( "UD_json",          54); // div.text ud_type="json"
define( "UD_apiCalls",      55); // div.text ud_type="apiCalls" if model or autload fill onload with newUDapiRequest()

define( "UD_nonEditable",   61); // div contenteditable="false"

define( "UD_partBreak",     71); // div.part
define( "UD_pageBreak",     72); // div.page
define( "UD_lineBreak",     73); // br
*/
/*
// Remove "" from 
define( "ud_type", "data_ud_type" );

cont ud_bind "data_ud_bind";

*/
define ( 'UD_minIdStep', 300);                           // Minimum difference between sequential block ids
// UniversalDocElement parameters (textra field)
define ( 'UDP_system',              "system"        ); // contains paramaters used
define ( 'UDP_system_pageHeight',   "pageHeight"    );
define ( 'UDP_system_userFilter',    "userFilter"    ); // List user id's than will see elementNames


// Special values
define ( "DummyText", "...");
// SOILinks Parameters
define ( "UserDepthToDocModels", 1);
define ( "UserDepthToUserModels", 1);
define ( "ChildDepthOnDocs", 5);

// Well-known names
define( 'Marketplace', "Marketplace_");

// Well-known directories, documents and models
define( "WellKnownDocs", [ 
    "Models" => "A000000003LQ170000M_ModelsFR",
    "Models_EN"=>"ASS0000000003_Models", 
    "Models_FR"=>"A000000003LQ170000M_ModelsFR",
    "Marketplace_EN"=>"A000000003B1D90000M_ModelMarke", 
    "Marketplace_FR"=>"A000000003B2D90000M_ModelMarke",
    "Wastebin"=>"Z0100000010000000M_wastebin",
    "Relog_EN"=>"A0000001BNA3B0000M_Relog",
    "Relog_FR"=>"A0000001BNA3B0000M_Relog",
    "emptyDoc" => "A000000003FRAQ0000M_dummy"
]);

// Well known elements
define ( "WellKnownElements", [
    "emailservice"=>"B60000001009C0000M",
    "botlog" => "BVU00000002200000M",
    "docNameHolder" => "BVU000000081000035_texts",
    "UD_docParams" => "UD_docParams",
]);
// Well-known class maps
define ( "WellKnownClassMaps", [
    "basicText1" => [         
        "p"=>[ 
            "normal", "emphasized", "quoted", "question", "break",
            "span.field"=>[ "name", "tel", "email", "address"],
            "span.button"=>["button", "leftButton", "rightButton"], 
            "span.link"=>["link"], 
            "span.style"=>[ "emphasized", "quote", "unemphasize"],
            "span"=>[ "emphasized", "quote", "unemphasize"]
        ], 
        "h1"=>["chapter"], "h2"=>["section"], "h3"=>["sub-section"], 
        "div.table"=>[ "tableStyle1", "input"], 
        "div.list" => [ 
            "normal", "listStyle1", "input", 
            "li" => [
                "normal", "rightAnwser",
                "span.field"=>[ "image", "name", "tel", "email", "address"],
                "span.button"=>["button", "leftButton", "rightButton"], 
                "span.link"=>["link"], 
                "span.style"=>[ "emphasized", "quote", "unemphasize"],
            ],
            "span.field"=>[ "name", "tel", "email", "address"],
            "span.button"=>["button", "leftButton", "rightButton"], 
            "span.link"=>["link"], 
            "span.style"=>[ "emphasized", "quote", "unemphasize"],
            "span"=>[ "emphasized", "quote", "unemphasize"]
        ],
        /*
        "li" => [
            "normal", "rightAnwser",
            "span.field"=>[ "image", "name", "tel", "email", "address"],
            "span.button"=>["button", "leftButton", "rightButton"], 
            "span.link"=>["link"], 
            "span.style"=>[ "emphasized", "quote", "unemphasize"],
        ],*/
        "div.graphic"=>[ 
            "normal",
            "span.drawstyle"=>[ "color0", "color1", "color2"]
        ], 
        "div.chart"=>[], 
        "div.video"=>[ "collapsable", "autoplay"]        
    ],
]);
// 2DO seperate spans
define ( "UD_standardClassMap", [
        "div.part.unconfigured" => [ "classes"=>[ 'LAY_thirds'], "elements"=>WellKnownClassMaps[ 'basicText1'], "spans"=>[]],
        "div.part.doc" => [ "classes"=>[ 'LAY_thirds'], "elements"=>WellKnownClassMaps[ 'basicText1'], "spans"=>[]],
        "div.part.app" => [ "classes"=>[], "elements"=>WellKnownClassMaps[ 'basicText1']],
        "div.part.model" => [ "classes"=>[], "elements"=>WellKnownClassMaps[ 'basicText1']],
        "div.part.language" => [ "classes"=>[], "elements"=>WellKnownClassMaps[ 'basicText1']],
        "div.part.data" => [ 
            "classes"=>[], 
            "elements"=> [
                "p"=>[], "h2"=>[], "div.server"=>[], "div.json"=>[], "div.connector.csv"=>[], "div.connector.site"=>[], "div.connector.googleCalendar"=>[],
                "div.connector.service"=>[], "div.connector.document"=>[], "div.connector.dataGloop"=>[], 
                "div.connector.googleSheet"=>[], "div.connector.googleDoc"=>[]
            ]
        ],
        "div.part.clipboard" => [ "classes"=>[], "elements"=>["div.clip"]],
        "div.part.system" => [ "classes"=>[], "elements"=>[ "div.css"=>[], "div.js"=>["textContent"], "div.json"=>["classMap"],]],
        "div.part.page" => [ 
            "classes"=>[], 
            "elements"=>[ 
                "p"=>[], "h2"=>[],
                "div.css"=>[], 
                "div.json"=>[]
            ]
        ], 
        "div.part.middleware" => [ 
            "classes"=>[], 
            "elements"=>[  "div.css"=>[], "div.js"=>[""], "div.json"=>["classMap"], "p"=>[], "h2"=>[]]
        ],
        "div.part.style" => [ 
            "classes"=>[], 
            "elements"=> [ "div.json"=>["classMap"], "div.css"=>[""], "div.js"=>[""], "div.html"=>[], "p"=>[], "h2"=>[]]
        ],
        "div.part.program" => [ 
            "classes"=>[], 
            "elements"=>[ "div.js"=>[""], "p"=>[], "h2"=>[]]
        ],
        "div.part.public" => [ "classes"=>[], "elements"=>WellKnownClassMaps[ 'basicText1']],
        "div.part.manage"=> [ "classes"=>[], "elements"=>WellKnownClassMaps[ 'basicText1']],
]);
// Define classes to add to different type
define ( "UD_typeToClassMap", [ 
        UD_part=>"part", UD_subPart=>"zone", UD_zoneToFill=>"zoneToFill",  UD_filledZone=>"filledZone", UD_pageBreak=>"page",
        UD_table=>"table", UD_list=>"list", UD_graphic=>"graphic",
        UD_commands => "commands linetext", UD_css => "css linetext", UD_js => "js linetext", UD_json => "json linetext", UD_apiCalls => 'api linetext', UD_resource => 'resource linetext',
        UD_HTML=>"html", UD_emailTemplate2Col=>"html",
        UD_connector=>"connector", UD_connector_siteExtract=>"connector site",
        UD_connector_csv=>"connector csv", UD_connector_googleDrive=>"connector drive",
        UD_connector_dataGloop=>"connector gloop", UD_connector_googleCalendar=>"connector agenda",
        UD_connector_dropZone=>"connector dropzone", UD_connector_service=>"connector service",
        UD_connector_document=>"connector doc", UDC_googleSheet=>"connector Google sheet"
]);
// Element classes
define ( "WellKnownElementClasses", [
        UD_directory => "UDdirectory", 
        UD_document => "UDdocument", 
        UD_model =>"UDdocument",
        // Document views
        UD_part=>"UDbreak",
        UD_view=>"UDbreak",
        UD_subPart=>"UDbreak",
        UD_zone=>"UDbreak",
        // Titles
        UD_chapter=> "UDtitle",
        UD_section=>"UDtitle",
        UD_subsection=>"UDtitle",
        UD_subsubsection=>"UDtitle",
        UD_paraTitle=>"UDtitle",
        UD_subParaTitle=>"UDtitle",
        // Basic text elements
        UD_paragraph=>"UDpara",
        UD_subParagraph=>"UDpara", 
        UD_table=>"UDtable",
        UD_list=>"UDlist",
        UD_graphic=>"UDgraphic",
        UD_text=>"UDtext",
        // Interpretated text elements
        UD_commands=>"UDcommands",
        UD_css=>"UDstyle",
        UD_js=>"UDjs",
        UD_json=>"UDjson",
        UD_apiCalls=>"UDapiCalls",
        UD_resource=>"UDresource",
        // Charts
        UD_chart=>"UDchart", 
        // Players
        // Layout and native HTML
        UD_nonEditable=>"UDnonEditable",
        UD_zoneToFill=>"UDzoneToFill",
        UD_filledZone=>"UDzoneToFill",
        UD_HTML=>"UDhtml",
        UD_emailTemplate2Col=>"UDhtml",
        UD_HTML_FIN=>"UDhtml",
        // Readers
        UD_pdf=>"UDpdf",
        UD_audio=>"UDaudio",
        UD_video=>"UDvideo",       
        // Breaks
        UD_partBreak=>"UDbreak",
        UD_page=>"UDbreak",
        UD_lineBreak=>"UDbreak",
        // Connectors
        UD_connector=>"UDconnector", 
        UD_connector_csv=>"UDconnector_csv",
        UD_connector_siteExtract=>"UDconnector_siteExtract",
        UD_connector_googleDrive=>"",
        UD_connector_dataGloop=>"",
        UD_connector_googleCalendar=>"../modules/connectors/udcgooglecalendar.php",
        UD_connector_dropZone=>"../modules/connectors/udcdropzone.php",    
        UD_connector_service=>"../modules/connectors/udcservice.php",    
        UD_connector_document=>"../modules/connectors/udcdocument.php",    
        UDC_googleSheet => "../modules/connectors/udcgooglesheet.php",  
        UDC_googleDoc => "../modules/connectors/udcgoogledoc.php", 
        UDC_facebook => "../modules/connectors/udcfacebook.php",
        // System
        UD_undefined=>"UDpara",   
        UD_imagePicker=>"../modules/pickers/udimagepicker.php", 
        UD_closeView=>"UDbreak",
        UD_closeZone=>"UDbreak",
        UD_dirThumb=>"UDdirectory",
        UD_docThumb=>"UDdocument",
        UD_modelThumb=>"UDdocument",
        UD_articleThumb=>"UDarticle"
]);

// HTML element attributes
define ( "data_ud_fields", "_ud_fields"); // maybe DOMATTR_ud_fields
define ( "UD_appAttributes", [ 
    'data-ud-iheight', 'data-ud-pageHeight', 'data-ud-offset', 'data-ud-cursor', 'data-ud-debug', 'data-ud-refresh', 'data-ud-mode',
    'data-ud-dupdated', 'data-ud-dchanged', 'data-ud-oid', 'data-ud-oidchildren', 'data-ud-dsent', 'data-ud-fields', 'data-ud-saveId',
    'data-ud-type', 'data-ud-subtype', 'data-ud-mime', 'data-ud-key', 'data-ud-extra', 'data-ud-hidden',
    'data-ud-onupdate', 'data-ud-onevent', 'data-ud-prepost', 'data-ud-model',
    'data-ud-content', 'data-ud-display', 'data-ud-follow', 'data-ud-dbaction',
    'data-ud-defaultPart', 'data-ud-quotes', 'data-ud-selection', 'data-ud-filter', 'data-ud-saveid',
    'data-ude-datasrc', 'data-ude-formula', 'data-ude-bind', 'data-ude-edit', 'data-ude-menu', 'data-ude-input',
    'data-ude-stage', 'data-ude-mode', 'data-ude-autosave', 'data-ude-source', 'data-ude-onclick', 'data-ude-noedit',
    'data-ude-place', 'data-ude-onclickFormula', 'data-ude-updateaction', 'data-ude-validate',
    'data-ude-editzone', 'data-ude-autoplay', 'data-ude-onvalid', 'data-ude-oninvalid', 
    'data-ude-accept', 'data-ude-pageno', 'data-ude-check', 'data-ude-input', 'data-ude-form', 'data-ude-ui',
    'data-udc-step', 'data-udc-scenario', 
    'data-udapi-quotes', 'data-udapi-callbackid', 'data-udapi-value1',
    'data-cb-type', 'data-cb-tags',
    'data-rb-time', 'data-rb-action',
    '_editable', '_dataset', '_add', 'target_id', '_onclick'
]);
define ( "UD_legacyAppAttributes", [
    'ud_iheight', 'ud_pageHeight', 'ud_offset', 'ud_cursor', 'ud-debug', 'ud_refresh', 'ud_mode',
    'ud_dupdated', 'ud_dchanged', 'ud_oid', 'ud_oidchildren', 'ud_dsent', 'ud_fields', 'ud_saveId',
    'ud_type', 'ud_subtype', 'ud_mime', 'ud_key', 'ud_extra', 'ud_hidden',
    'ud_onupdate', 'ud_onevent', 'ud_prepost', 'ud_model',
    'ud_content', 'ud_display', 'ud_follow', 'ud_dbaction', 
    'ud_defaultPart', 'ud_quotes', 'ud_selection', 'ud_filter', 'ud_saveid',
    'ude_datasrc', 'ude_formula', 'ude_bind', 'ude_edit', 'ude_menu', 'ude_edit',
    'ude_stage', 'ude_mode', 'ude_autosave', 'ude_source', 'ude_onclick', 'ude_noedit', 
    'ude_place', 'ude_onclickFormula', 'ude_updateaction', 'ude_validate',
    'ude_editZone', 'ude_autoplay', 'ude_onvalid', 'ude_oninvalid', 
    'ude_accept', 'ude_pageno', 'ude_check', 'ude_input', 'ude_form', 'ude_ui',
    'udc_step', 'udc_scenario',
    'udapi_quotes', 'udapi_callbackid', 'udapi_value1',
    'cb_type','cb_tags',
    'RB_time', 'RB_action',
    '_editable', '_datadest', '_add', 'target_id', '_onclick'
]);

// Data fields
define( "DATA_caption",          "_caption");
define( "DATA_elementName",       "_elementName");
define( "DATA_cleanContent",     "_cleanContent");
//2DO texra textcontent

// Well-known HTML sequences
define( "HTML_closeDiv", "</div>" );

define( "EXTAG_textEd", [
    'div.linetext', 'div.server', 'div.css', 'div.json', 
    'div.js', 'div.apiCalls', 'div.zoneToFill'
]);

// Well-known resource categories based on extension
/* NOT USED YET Needed for organising resources*/
define( "UD_wellKnownResourceCategories", [
    "css" => "style", "sass"=> "style",
    "js" => "program",
    "html" => "content"
]);

// Other constants
define( 'UD_viewZoneClose', "__JUST_CLOSE__");
// !!! Must be synchronised with requireconfig.js and the list of modules automatically loaded
define ( "UD_standardModules", [ 'modules/editors/udetext', 'modules/tools/zone']);

// ENV usable in models and which will be substituted on instantation
if ( $argv[0] && strpos( $argv[0], "udconstants.php") !== false)
{    
    // Launched with php.ini so run auto-test
    echo "Syntaxe OK\n";
    echo "Test completed\n";
} // end of auto-test


?>
