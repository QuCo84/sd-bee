/**
 * UD register is a JS file that defines constants shared between the server & client side
 *  
 * !!! IMPORTANT all data must be defined in JSON format, ie keys are placed between ""
 */
const UD_register = { 
    "UD_exTagAndClassInfo" : {
        "directory" : { "db_type":1, "ud_type": "dirThumbnail", "isContainer":1},
        "document" : { "db_type":2, "ud_type": "docThumbnail", "isContainer":1},
        "model" : { "db_type":3, "ud_type": "modelThumbnail", "isContainer":1},
        "dirThumb" : { "db_type":121, "ud_type": "dirThumbnail", "isContainer":1},
        "docThumb" : { "db_type":122, "ud_type": "docThumbnail", "isContainer":1},
        "modelThumb" : { "db_type":123, "ud_type": "modelThumbnail", "isContainer":1},    
        "articleThumb" : { "db_type": 126, "ud_type": "articleThumbnail", "isContainer":1},    
        "div.part":{
            "label":"view","label_FR":"vue",
            "db_type":4,
            "defaultContent":{"para":{"tag":"p","value":"..."}},
            "displayable":1, "editable":1, "insertable":"above",
            "ud_type":"part", "isContainer": 1,
            "classesByViewType":{ 
                "default":["LAY_standard", "LAY_A4","LAY_A5", "LAY_thirds", "LAY_screen"],
                "doc":["standard", "LAY_A4"]
            },
            "subTypes": [
                "doc",
                "app", 
                "model",
                "language",
                "data",
                "clipboard",
                "system",
                "pageStyle",
                "middleware",
                "style",
                "program",
                "dir",
                "public"
            ],
            "defaultContentByClassOrViewType":{
                "_unconfigured":{
                    "title":{"tag":"h2","edit":"off", "value":"", "placeholder": "{!Configurer la vue!}"},
                    "name":{"tag":"p","placeholder":"{!Nom de la vue!}","value":""},
                    "describe":{"tag":"p","placeholder":"{!Description courte de la vue!}","value":""},
                    "type":{"tag":"p","value":"=selectorTag( api( 'getTagOrStyleInfo', 'div.part', 'subTypes'),'','{!Selectionner le type de vue!}');"},
                    "layout":{"tag":"p","value":"=selectorTag( api( 'availableLayoutsForExTag', 'div.part'),'', '{!Selectionner la disposition!}');"},
                    "validate":{"tag":"p","value":[
                        "La vue n'est crée que lors de la validation.",
                        "",
                        {"tag":"span","class":"button","onclick":"API.setupView();","value":"Valider"}
                      ]}
                },
                "LAY_thirds":{
                    "left":{ "tag":"div","type":"zone","class":"zone LAY_left2thirds","value":{
                        "tag":"p","value":"..."}
                    },
                    "right":{"tag":"div","type":"zone","class":"zone LAY_rightThird","value":{
                        "tag":"p","value":"..."}
                    }
                },
                "LAY_standard" : { "para": { "tag":"p","value":"..."}},
                "LAY_A4" : { 
                    "title": { "tag":"h2", "placeholder":"Titre", "value":"Titre"}, 
                    "para": { "tag":"p", "placeholder":"Texte", "value":"Texte"}
                },
                "LAY_A5" : { 
                    "title": { "tag":"h2", "placeholder":"Titre", "value":"Titre"}, 
                    "para": { "tag":"p", "placeholder":"Texte", "value":"Texte"}
                },
                "LAY_screen" : { 
                    "para": { "tag":"p", "placeholder":"Texte", "value":"Texte"}
                }
            }
        },
        "div.part.doc" : { 
            "label":"doc view", "label_FR": "Vue doc", 
            "description":"Document",
            "classes":["standard", "LAY_A4"],
            "defaultContent":{"para":{"tag":"p","value":"..."}},
            "blockNoMin" : 0, "blockNoMax" : 3, "nextId":"02"
        },
        "div.part.app" : { 
            "label":"app view", "label_FR": "Vue app", 
            "classes":["standard"],
            "defaultContent":{"para":{"tag":"p","value":"..."}},
            "blockNoMin" : 3, "blockNoMax" : 4, "nextId":"30"
        },
        "div.part.model" : { 
            "label":"models", "label_FR": "modèles", 
            "classes":["standard"],
            "defaultContent":{"para":{"tag":"p","value":"..."}},
            "blockNoMin" :4, "blockNoMax" : 5, "nextId":"40"
        },
        "div.part.language" : { 
            "label":"translations", "label_FR": "traductions", 
            "classes":["standard"],
            "defaultContent":{"para":{"tag":"p","value":"..."}},
            "blockNoMin" : 5, "blockNoMax" : 6, "nextId":"50"
        },
        "div.part.data" : { 
            "label":"data", "label_FR": "données",
            "description":"Data, connectors",      
            "classes":["standard"],
            "defaultContent":{"para":{"tag":"p","value":"..."}},
            "blockNoMin" : 6, "blockNoMax" : 7, "nextId":"60"
        },
        "div.part.clipboard" : { 
            "label":"clips", "label_FR": "clips", 
            "description":"Clips for clipboard initilaisation",
            "classes":["standard"],
            "defaultContent":{"para":{"tag":"p","value":"..."}},
            "blockNoMin" : 7, "blockNoMax" : 8, "nextId":"70"
        },
        "div.part.system" : { 
            "label":"system styles", "label_FR": "styles systèmes",
            "description":"System styles, commands & programs",      
            "classes":["standard"],
            "defaultContent":{"para":{"tag":"p","value":"..."}},
            "blockNoMin" : 10, "blockNoMax" : 10.31, "nextId":"A0"
        },    
        "div.part.pageStyle" : { 
            "label":"page styling", "label_FR": "styles pages",
            "description":"Paging information & styles",
            "classes":["standard"],
            "defaultContent":{"para":{"tag":"p","value":"..."}},
            "blockNoMin" : 10.31, "blockNoMax" : 11, "nextId":"AA"
        },
        "div.part.middleware" : { 
            "label":"middleware styles & programs", "label_FR": "styles et programs intermédiaires", 
            "description":"Middleware styles, commands & programs",
            "classes":["standard"],
            "defaultContent":{"para":{"tag":"p","value":"..."}},
            "blockNoMin" : 11, "blockNoMax" : 12, "nextId":"B0"
        },    
        "div.part.style" : { 
            "label":"app styles", "label_FR": "styles de l'app", 
            "description":"App styles",
            "classes":["standard"],
            "defaultContent":{"para":{"tag":"p","value":"..."}},
            "blockNoMin" : 12, "blockNoMax" : 13, "nextId":"C0"
        },
        "div.part.program" : { 
            "label":"app programs", "label_FR": "programs de l'app",
            "description":"App programs",
            "classes":["standard"],
            "defaultContent":{"para":{"tag":"p","value":"..."}},
            "blockNoMin" : 13, "blockNoMax" : 14, "nextId":"D0"
        }, 
        "div.part.dir" : { 
            "label":"app programs", "label_FR": "programs de l'app",
            "description":"App programs",
            "classes":["standard"],
            "defaultContent":{"para":{"tag":"p","value":"..."}},
            "blockNoMin" : 14, "blockNoMax" : 15, "nextId":"E0"
        }, 
        "div.part.public" : { 
            "label":"public", "label_FR": "publique",
            "description" : "Public content for models",
            "classes":["standard"],
            "defaultContent":{"para":{"tag":"p","value":"..."}},
            "blockNoMin" : 30, "blockNoMax" : 31, "nextId":"U0"
        },    
        "div.part.manage" : { 
            "label":"manage document", "label_FR": "gérer le document", 
            "classes":["standard"],
            "defaultContent":{"para":{"tag":"p","value":"..."}},
            "blockNoMin" : 31, "blockNoMax" : 32, "nextId":"V1"
        },                           
        "h1":{
            "label":"chapter","label_FR":"chapitre",
            "db_type":6, "db_id_step_factor": 14, "icon": "title.png",     
            "insertableTags":{"field":"span.field","link":"span.link","styled":"span"},
            "displayable":1,"editable":1, "editableInside":1,
            "viewTypes":["doc", "synopisis", "model", "language"],
            "classesByViewClass" : {},
            "classesByViewType":{ 
                "default":["standard"]
            },
            "defaultContent":"Chapter title","defaultContent_FR":"Titre de chapitre",
            "autoClass" : "title is-1",
            "defaultContentByClassOrViewType":{}
        },
        "h2":{
            "label":"section",
            "db_type":7, "db_id_step_factor": 10, "icon": "title.png",
            "insertableTags":{"field":"span.field","link":"span.link","styled":"span"},
            "displayable":1,"editable":1, "editableInside":1,
            "viewTypes":"all",
            "classesByViewType":{ 
                "default":["standard"]
            },
            "defaultContent":"Section title", "defaultContent_FR":"Titre de section",
            "autoClass" : "title is-2",
            "defaultContentByClassOrViewType":{}
        },
        "h3":{
            "label":"sub-section", "label_FR":"sous-section",
            "db_type":8, "db_id_step_factor": 8, "icon": "title.png",
            "insertableTags":{"field":"span.field","link":"span.link","styled":"span"},
            "displayable":1,"editable":1, "editableInside":1,
            "viewTypes":["doc", "synopisis", "model", "language"],
            "classesByViewType":{ 
                "default":["standard"]
            },
            "defaultContent":"...",
            "autoClass" : "title is-3",
            "defaultContentByClassOrViewType":{}
        },
        "p":{
            "label":"paragraph",
            "label_FR":"paragraphe",
            "db_type":10, "icon": "pilcrow.png",
            "insertableTags":{"field":"span.field","button":"span.button","link":"span.link","styled":"span", "image":"img"},
            "displayable":1, "editable":1, "editableInside":1,
            "viewTypes":"all",
            "classesByViewType":{ 
                "default":["standard", "emphasized", "quoted", "question", "break","LAY_left", "LAY_right"]
            },
            "defaultContent":"...",
            "defaultContentByClassOrViewType":{}
        },
        "p.undefined":{
            "label":"generic element", "label_FR":"élémént générique",
            "db_type":100, "db_id_step_factor": 14, 
            "insertableTags":{"field":"span.field","button":"span.button","link":"span.link","styled":"span"},
            "displayable":1,
            "editable":1,
            "viewTypes" : "all",
            "defaultContent":"...",
            "defaultContentByClassOrViewType":{}
        },
        "div.image" : {
            "label":"image", "label_FR":"image",
            "db_type":201, "icon": "image.png", "ud_type":"image", 
            "insertableTags":{},
            "displayable":1,
            "editable":1, "earlyInit":1,
            "viewTypes" : [ "doc", "model", "clipboard", "style", "public"],
            "defaultContent":"Click to select.Long click for multiple selection.",
            "defaultContentByClassOrViewType":{}
        },
        "div.table":{
            "label":"table",
            "db_type":12, "icon": "table.png",      
            "displayable":1, "editable":1, "editableInside":1, "earlyInit":1, "needsOwnInit":1,
            "ud_type":"table", "isContainer":0,
            "insertableTags":{"field":"span.field", "button":"span.button","link":"span.link","styled":"span"},
            "viewTypes":["doc", "synopisis", "data", "model", "language"],
            "classesByViewType":{ 
                "default":["standard", "tableStyle1", "input"]
            },
            "defaultContent":"Table{AutoIndex_table}",
            "defaultContentPath" : "data/value",
            "defaultContentByPath" : [{"A":"","B":"","C":""},{"A":"...","B":"...","C":"..."}],
            "defaultContentByClassOrViewType":{}

        },
        "div.list":{
            "label":"list",
            "db_type":13, "icon": "list.png",
            "ud_type":"list", "isContainer":0,        
            "insertableTags":{"field":"span.field", "button":"span.button","link":"span.link","styled":"span"},
            "displayable":1,
            "editable":1, "editableInside":1,
            "earlyInit":1,
            "viewTypes":["doc", "synopisis", "data", "model", "language"],
            "classesByViewType":{ 
                "default":["standard", "input", "listStyle1"]
            },
            "defaultContent":"List{AutoIndex_list}",
            "defaultContentByClassOrViewType":{}

        },
        "div.graphic":{
            "label":"illustration",
            "db_type":14, "icon": "draw.png",
            "ud_type":"graphic", "isContainer":0,       
            "displayable":1, "editable":1, "earlyInit":1,
            "viewTypes":["doc", "synopisis", "model", "language"],
            "classesByViewType":{ 
                "default":["standard"]
            },
            "defaultContent":"Illustration{AutoIndex_graphic}",
            "defaultContentByClassOrViewType":{}

        },
        "div.linetext":{
            "label":"text",
            "db_type":15,
            "ud_type":"linetext", "isContainer":0,
            "displayable":1, "editable":1, "useTextEditor":1,
            "viewTypes":["data", "middleware", "style", "program"],
            "classesByViewType":{ 
                "default":["standard"]
            },
            "defaultContent":"Text{AutoIndex_text}",
            "defaultContentByClassOrViewType":{}
        },
        "div.server":{
            "label":"server",
            "db_type":16,
            "ud_type":"server", "isContainer":0, "forceClasses":"linetext",        
            "displayable":1, "editable":1, "useTextEditor":1, "addTextEditor":1, 
            "viewTypes":["data", "middleware", "style", "program"],
            "classesByViewType":{ 
                "default":["standard"]
            },
            "defaultContent":"Server{AutoIndex_server}",
            "defaultContentByClassOrViewType":{}

        },
        "div.css":{
            "label":"style",
            "db_type":17, "icon": "css.png",
            "ud_type":"css", "isContainer":0, "forceClasses":"linetext",
            "displayable":1,
            "editable":1,
            "useTextEditor":1, "addTextEditor":1, 
            "viewTypes":["data", "system", "middleware", "pageStyle", "style"],
            "classesByViewType":{ 
                "default":["standard"]
            },
            "defaultContent":"Style{AutoIndex_css}",
            "defaultContentByClassOrViewType":{}
        },
        "div.js":{
            "label":"js", "icon": "js.png",
            "db_type":18, 
            "ud_type":"js", "isContainer":0, "forceClasses":"linetext",
            "displayable":1, "editable":1, "useTextEditor":1, "addTextEditor":1, 
            "viewTypes":["data", "system", "middleware", "style", "program"],
            "classesByViewType":{ 
                "default":["standard"]
            },
            "defaultContent":"JS{AutoIndex_js}",
            "defaultContentByClassOrViewType":{}
            
        },
        "div.json":{
            "label":"JSON",
            "db_type":19, "icon": "settings.png",
            "ud_type":"json", "isContainer":0, "forceClasses":"linetext",
            "displayable":1, "editable":1, "useTextEditor":1,
            "viewTypes":["data", "system", "middleware", "pageStyle", "style", "program"],
            "classesByViewType":{ 
                "default":["standard"]
            },
            "defaultContent":"JSON{AutoIndex_json}",
            "defaultContentByClassOrViewType":{}
        },
        "div.api":{
            "label":"API",
            "db_type":20,
            "ud_type":"api", "isContainer":0, "forceClasses":"linetext",
            "displayable":1, "editable":1, "useTextEditor":1, "addTextEditor":1, 
            "viewTypes":["data", "middleware", "style", "program"],
            "classesByViewType":{ 
                "default":["standard"]
            },
            "defaultContent":"Server{AutoIndex_apiCall}",
            "defaultContentByClassOrViewType":{}
        },
        "div.resource":{
            "label":"resource",
            "db_type":29, "icon": "local_library.png",
            "ud_type":"resource", "isContainer":0, "forceClasses":"linetext",
            "displayable":1, "editable":1, "useTextEditor":1, "addTextEditor":1, 
            "viewTypes":["data", "middleware", "pageStyle", "pageinfo", "style", "program"],
            "classesByViewType":{ 
                "default":["standard"]
            },
            "defaultContent":"Resource{AutoIndex_resource}",
            "defaultContentByClassOrViewType":{}

        },
        "div.chart":{
            "label":"chart", "label_FR":"graphique",
            "db_type":21, "icon": "bar_chart.png",
            "ud_type":"chart", "isContainer":0,
            "displayable":1, "editable":1,
            "viewTypes":["doc", "synopisis", "model", "language"],
            "classesByViewType":{ 
                "default":["standard"]            
            },
            "defaultContent":"Chart{Autoindex_chart}",
            "defaultContentByClassOrViewType":{}
        },
        "div.video":{
            "label":"video","label_FR":"vidéo",
            "db_type":23, "icon": "movie.png",
            "ud_type":"video", "isContainer":0,
            "displayable":1, "editable":1,
            "viewTypes":["doc", "synopisis", "model", "language"],
            "classesByViewType":{ 
                "default":["standard"]            
            },
            "defaultContent":"Video{AutoIndex_video}",
            "defaultContentByClassOrViewType":{}
        },
        "div.nonEditable":{
            "label":"non-editable zone", "label_FR":"zone non editable",
            "db_type":30,
            "ud_type":"nonEditable", "isContainer":0,        
            "displayable":1, "editable":1,
            "viewTypes":"none",
            "classesByViewType":{ 
                "default":["standard"]
            },
            "defaultContent":"HTML{AutoIndex_html}",
            "defaultContentByClassOrViewType":{}        
        },
        "div.zoneToFill":{
            "label":"filled by program","label_FR":"zone à remplir",
            "db_type":31, "icon": "robot.png",
            "ud_type":"zoneToFill", "isContainer":0,
            "displayable":1, "editable":1, 
            "viewTypes":"all",
            "classesByViewType":{ 
                "default":["standard"]
            },
            "defaultContent":"HTML{AutoIndex_html}",
            "defaultContentByClassOrViewType":{}
        },
        "div.filledZone":{
            "label":"filled zone", "label_FR":"zone remplie",
            "db_type":32, 
            "ud_type":"filledZone", "isContainer":0,
            "displayable":1, "editable":0,
            "viewTypes":"none",
            "classesByViewType":{ 
                "default":["standard"]
            },
            "defaultContent":"HTML{AutoIndex_html}",
            "defaultContentByClassOrViewType":{}
        },
        "div.html":{
            "label":"HTML", "label_FR":"HTML",
            "db_type":60, "icon": "codeblock.png",
            "ud_type":"html", "isContainer":0,        
            "displayable":1, "editable":1, "earlyInit":0, "needsOwnInit" : 1,
            "viewTypes":"all",
            "classesByViewType":{ 
                "default":["standard"]
            },
            "defaultContent":"HTML{AutoIndex_html}",
            "defaultContentByClassOrViewType":{}
        },
        "div.emailTemplate2Col":{
            "label":"HTML email template", "label_FR":"Modèle email HTML",
            "db_type":61,
            "ud_type":"html", "isContainer":0,
            "displayable":1, "editable":1, "earlyInit":1,
            "viewTypes":["model"],
            "classesByViewType":{ 
                "default":["standard"]
            },
            "defaultContent":"HTML{AutoIndex_html}",
            "defaultContentByClassOrViewType":{}
        },
        "div.connector":{
            "label":"connector","label_FR":"connecteur",
            "db_type":80, "icon": "cloud.png",
            "ud_type":"connector", "isContainer":0,       
            "displayable":1, "editable":1,
            "earlyInit":1,
            "viewTypes":["data"],
            "classesByViewType":{ 
                "default":["standard", "dataset", "spreadsheet"]
            },
            "defaultContent":"__UNDEFINED__",
            "defaultContentByClassOrViewType":{}
        },
        "div.connector.csv":{ 
            "label":"CSV",
            "db_type":81, "icon": "feed.png",
            "ud_type":"connector", "ud_subType":"csv", "isContainer":0,
            "displayable":1, "editable":1,
            "viewTypes":["data"],
            "classesByViewType":{ 
                "default":["standard", "dataset", "spreadsheet"]
            },
            "defaultContent":"Conn{AutoIndex_connector}",
            "defaultContentByClassOrViewType":{}
        },
        "div.connector.site":{
            "label":"site extract",
            "db_type":82, "icon": "scanner.png",
            "ud_type":"connector", "ud_subType":"site", "isContainer":0,
            "displayable":1, "editable":1,
            "viewTypes":["data"],
            "classesByViewType":{ 
                "default":["standard", "dataset", "spreadsheet"]
            },
            "defaultContent":"Conn{AutoIndex_connector}",
            "defaultContentByClassOrViewType":{}

        },
        "div.connector.googleDrive":{
            "label":"Google drive",
            "db_type":83,
            "ud_type":"connector", "ud_subType":"drive", "isContainer":0,
            "displayable":1, "editable":1,
            "viewTypes":["data"],
            "classesByViewType":{ 
                "default":["standard", "dataset", "spreadsheet"]
            },
            "defaultContent":"Conn{AutoIndex_connector}",
            "defaultContentByClassOrViewType":{}

        },
        "div.connector.dataGloop":{
            "label":"Data gloop",
            "db_type":84,
            "ud_type":"connector", "ud_subType":"gloop", "isContainer":0,
            "displayable":1, "editable":1,
            "viewTypes":["data"],
            "classesByViewType":{ 
                "default":["standard", "dataset", "spreadsheet"]
            },
            "defaultContent":"Conn{AutoIndex_connector}",
            "defaultContentByClassOrViewType":{}
        },
        "div.connector.googleCalendar":{
            "label":"Google calendar",
            "db_type":85,
            "ud_type":"connector", "ud_subType":"agenda", "isContainer":0,
            "displayable":1, "editable":1,
            "viewTypes":["data"],
            "classesByViewType":{ 
                "default":["standard", "dataset", "spreadsheet"]
            },
            "defaultContent":"Conn{AutoIndex_connector}",
            "defaultContentByClassOrViewType":{}      
        },
        "div.connector.dropZone":{
            "label":"Drop zone",
            "db_type":86,
            "ud_type":"connector", "ud_subType":"dropzone", "isContainer":0,
            "displayable":1, "editable":1,
            "viewTypes":["data"],
            "classesByViewType":{ 
                "default":["standard", "dataset", "spreadsheet"]
            },
            "defaultContent":"Conn{AutoIndex_connector}",
            "defaultContentByClassOrViewType":{}        
        },
        "div.connector.service":{
            "label":"SOILinks service",
            "db_type":87,
            "ud_type":"connector", "ud_subType":"service", "isContainer":0,
            "displayable":1, "editable":1,
            "viewTypes":["data"],
            "classesByViewType":{ 
                "default":["standard", "dataset", "spreadsheet"]
            },
            "defaultContent":"Conn{AutoIndex_connector}",
            "defaultContentByClassOrViewType":{}        
        },
        "div.connector.document":{
            "label":"SD bee document",
            "db_type":88,
            "ud_type":"connector", "ud_subType":"doc", "isContainer":0,
            "displayable":1, "editable":1,
            "viewTypes":["data", "model"],
            "classesByViewType":{ 
                "default":["standard", "dataset", "spreadsheet"]
            },
            "defaultContent":"Conn{AutoIndex_connector}",
            "defaultContentByClassOrViewType":{}        
        },
        "div.connector.googleSheet":{
            "label":"Google sheet",
            "db_type":89,
            "ud_type":"connector", "ud_subType":"sheet", "isContainer":0,
            "displayable":1, "editable":1,
            "viewTypes":["data"],
            "classesByViewType":{ 
                "default":["standard", "dataset", "spreadsheet"]
            },
            "defaultContent":"Conn{AutoIndex_connector}",
            "defaultContentByClassOrViewType":{}        
        },
        "div.connector.googleDoc":{
            "label":"Google doc",
            "db_type":90,
            "ud_type":"connector", "ud_subType":"doc", "isContainer":0,
            "displayable":1, "editable":1,
            "viewTypes":["data"],
            "classesByViewType":{ 
                "default":["standard", "dataset", "spreadsheet"]
            },
            "defaultContent":"Conn{AutoIndex_connector}",
            "defaultContentByClassOrViewType":{}        
        },
        "div.connector.facebook":{
            "label":"Facebook",
            "db_type":92,
            "ud_type":"connector", "ud_subType":"facebook", "isContainer":0,
            "displayable":1, "editable":1,
            "viewTypes":["data"],
            "classesByViewType":{ 
                "default":["standard", "dataset", "spreadsheet"]
            },
            "defaultContent":"Conn{AutoIndex_connector}",
            "defaultConfig" : { "ready":"no", "pageId":"Id of page to use", "format":"jsonTable", "target":"{elementName}"},
            "defaultContentByClassOrViewType":{}        
        },
        "div.zone":{
            "label":"zone",
            "db_type":5, "db_id_step_factor": 7, "icon": "zone.png",
            "displayable":1, "editable":1, "insertable":"above",
            "ud_type": "zone", "isContainer": 1,
            "viewTypes":["doc", "synopisis", "model", "language"],
            "classesByViewType":{ 
                "default":["standard", "LAY_flex", "theme1", "theme2", "theme3"]
            },
            "defaultContent":{
                "caption":{ "tag":"span", "class":"caption", "value":"Zone{AutoIndex_zone}"},
                "para":{"tag":"p","value":"..."}
            },
            "defaultContentByClassOrViewType":{}
        },
        "div.page" : {
            "label":"page break", "label_FR":"saut de page",
            "db_type": 51, "icon": "page_break.png",
            "ud_type" : "page",
            "is_container" :1,
            "displayable":1,"editable":1,
            "viewTypes":["doc", "synopisis", "model", "language"],
            "classesByViewType":{ 
                "default":["standard"]
            },
            "defaultContent":{"para":{"tag":"p","value":"..."}},
            "defaultContentByClassOrViewType":{}
        },
        "span":{
            "label":"span",
            "displayable":0, "editable":0,
            "insertable":"inside",
            "classesByViewType":{ 
                "default":["standard", "styled", "emphasized", "quoted", "centered"]
            }
        },
        "span.caption":{
            "label":"caption",
            "displayable":0, "editable":0,
            "classesByViewType":{ 
                "default":["standard"]
            }
        },
        "span.field":{
            "label":"field","label_fr":"champ",
            "displayable":0, "editable": 1, "insertable":"inside"
        },
        "span.link":{
            "label":"link","label_fr":"lien",
            "displayable":0, "editable": 1, "insertable":"inside"
        },
        "span.button":{
            "label":"button","label_fr":"bouton",
            "displayable":0, "editable": 1, "insertable":"inside"
        },
        "span.styled":{
            "label":"styled","label_fr":"stylé",
            "displayable":0, "editable": 1, "insertable":"inside",
            "classesByViewType":{ 
                "default":["emphasized", "quoted"]
            }
        },
        "span.drawstyle":{
            "label":"draw","label_fr":"dessein",
            "displayable":1, "editable": 1, 
            "classesByViewType":{ 
                "default":["color1", "color2"]
            }
        },
        "input": { "editable":1, "displayable":1},
        "img":{
            "label":"image",
            "displayable":0, "insertable":"inside"
        },
        "br":{
            "label":"line break","label_fr":"saut de ligne",
            "displayable":0,"insertable":"inside"
        },
        "div.editZone":{ "displayable":1,"editable":1},
        "div.viewZone":{ "displayable":1,"editable":1},
        "div.configZone":{ "displayable":1,"editable":1},
        "collapsable":{
            "label":"collapsable", "label_FR":"réductible"
        },
        "summary":{
            "label":"summary", "label_FR":"résumé"
        },
        "body":{
            "label_FR":"corps"
        },
        "customerCase":{
            "label":"case", "label_FR":"cas client"
        },
        "learnMore":{
            "label":"learn more",
            "label_FR":"savoir plus"
        },
        "field":{
            "label_FR":"champ"
        },
        "button":{
            "label_FR":"action"
        },
        "link":{
            "label_FR":"lien"
        },
        "li":{
            "classesByViewType":{ 
                "default":["standard"]
            },
            "insertableTags":{ "field":"span.field","link":"span.link","styled":"span"},
            "displayable":1, "editable":1, "editableInside":1
        },
        "td":{
            "insertableTags":{"field":"span.field","link":"span.link","styled":"span"},
            "displayable":1, "editable":1, "editableInside":1
        },
        "th":{
            "displayable":1, "editable":1, "editableInside":1
        },
        "LAY_left":{
            "label":"left", "label_FR":"à gauche",
            "icon":"g1I1S1Ya4_layleft.png"
        },
        "LAY_right":{
            "label":"right", "label_FR":"à droite",
            "icon":"g1I1X1S0a_layright.png"
        },
        "quoted":{ "label_FR":"citation"},
        "emphasized":{ "label_FR":"faire sortir"},
        "unemphasized":{ "label_FR":"en arrière plan"},
        "myMappedClass":{ "label_FR":"classe composé", "mapTo":"myclass, myclass2"},
        "titleButton":{ "autoAddAttributes":{ "onclick":"API.showOneOfClass( {id}, 1);"}}

    },
    "UD_wellKnown" : {
        "UD_wellKnownElements" :{
            "botlog" : "BVU00000002200000M",
            "docNameHolder" : "BVU0000000810{userId_base32}_texts",
            "UD_docParams": "UD_docParams_object",
            "UD_currentDocument" : "me"
        },
        "UD_editorTemporaryClasses" : [ "edit", "editing", "stageediting", "edcontainer", "edinside", "hidden", "menu-on"]
    },
    "UD_parameters" : {
        "service" : "webdesk",
        "buildManageOnClientSide" : true,
        "dummyText" : "...",
        "computingText" : "...",
        "languageCodes" : [ "EN","FR"],
        "newViewId" : "_NEW_VIEW_ID",
        "register" : {
            "store-labels-by-view":false
        },
        "elementUpdateAction" : "AJAX_update",
        "required_modules" : [],
        "public-resource-storage" : "https://storage.googleapis.com/sd-bee-cdn/",
        "private-resource-storage": ""
    },
    "UD_services" : {
        "TextGen" : { "path": "services/NLP", "prefix":"UDT"}
    }
};
if ( typeof process == 'object') {
    // Testing under node.js 
    module.exports = { UD_register:UD_register};
}