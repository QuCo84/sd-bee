<?php
/**
 * sdbee-marketplace.php - Endpoint to obtain an HTML display of available models
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

function SDBEE_endpoint_marketplace( $request, $output=false) {
    /*
    // 2DO look for favoriteApps in $request & put these first
    */
    global $PUBLIC, $STORAGE, $ACCESS;
    $r = '<div class="marketplace">';
    $defaultTemplateImage = "https://www.sd-bee.com/upload/N0r0D0N30_defaultTemplateImage.png";
    $thumbnailTemplate = '<div id="" class="model-thumb"><h2>{%title}</h2><img src="{%image}" />';
    $thumbnailTemplate .= '<p>{%descr}<p>';
    $thumbnailTemplate .= '<a href="javascript:" class="mainOption" onclick="{%click1};">{!Utiliser !}{%title}</a>';
    /* secondary options
     <br><a href="javascript:" class="secondaryOption" onclick="API.setModel('A4 text');" contenteditable="false">Créer un processus basé sur cette app</a>
     <a href="javascript:" class="secondaryOption" onclick="API.copyModel('A4 text');" contenteditable="false">Copier</a>
    */
    $thumbnailTemplate .= '</div>';
    $publicModels = $PUBLIC->getList( 'models');
    //var_dump( $publicModels);
    $ignore = [];
    /* // Local models
    $userModelsInfo =  $ACCESS->getCollectionContents( 'Models');
    for ( $modeli=0; $modeli < count( $userModelsInfo); $modeli++) {
        $modelInfo = val( $userModels, $modeli);
        $modelName = val( $modelInfo, 'name');
        $model = new SDBEE_doc( $modelName);        
        if ( false && $model) {
            // Grab the description and image from the model itself
            $descrEl = $model->readElement( 'BUU0000010000');
            $descr = val( $descrEl, 'tcontent');
            $imgEl = $model->readElement( 'BUU0000010000');
            $image = "";
        }
        $data = [
            'title' => $model->label,
            'descr' => $model->description,
            'image' => ($image) ? $image : $defaultTemplateImage,
            'click1' => "$$$.setModel('{$model->name}');",
        ];
        if ( val( $model->params, 'replaceInMarket')) {
            $ignore[] = val( $model->params, 'replaceInMarket');            
        }
        $thumbnail = LF_substitute( $thumbnailTemplate, $data);
        $r .= $thumbnail;
        if ( $output) echo $thumbnail; 
    }*/
    // PUblic models
    for ( $modeli=0; $modeli < count( $publicModels); $modeli++) {
        $modelName = val( $publicModels, $modeli);
        if ( in_array( $modelName, $ignore)) continue;
        if ( !$PUBLIC->exists( "models", $modelName)) {
            echo "model not found $modelName"; 
            die(); //continue;
        }
        if ( true) {
            $model = new SDBEE_doc( str_replace( '.json', '', $modelName), 'models', $PUBLIC);        
            if ( $model) {
                // Grab the description and image from the model itself
                $lang = LF_env( 'lang');
                $title = $model->readElementContentByLabel( 'public-title-'.$lang);
                $descr = $model->readElementContentByLabel( 'public-descr-'.$lang);
                $image = HTML_getFirstImage( $model->readElementContentByLabel( 'public-poster-'.$lang));
            }
        }
        $data = [
            '%title' => ( $title) ? $title : $model->label,
            '%descr' => ( $descr) ? $descr : $model->description,
            '%image' => ( $image) ? $image : $defaultTemplateImage,
            '%click1' => "$$$.setModel('{$model->name}');", // "sd-bee.com:{$model->name}"

        ];
        //var_dump( $thumbnailTemplate, $data); die();
        $thumbnail = LF_substitute( $thumbnailTemplate, $data);
        $r .= $thumbnail;
        if ( $output) echo $thumbnail; 
    }
    $r .= '</div>';    
    return $r;
    
}
/*
require __DIR__.'/../../../vendor/autoload.php';
    use Google\Cloud\Storage\StorageClient;
if ( !isset( $PUBLIC)) {
    
    include __DIR__."/../sdbee-storage.php";
    $p = JSON_decode( '{"storageService" : "gs",
"keyFile" : "require-version/local-config/gctest211130-567804cfadc6.json", 
"bucket" : "gcstest211130", 
"top-dir" : "", 
"prefix" : ""}', true);
    $PUBLIC = SDBEE_getStorage( $p);
}*/
// SDBEE_endpoint_marketplace( $request);