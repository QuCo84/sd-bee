<?php
/**
 * sdbee-marketplace.php 
 * SD bee endpoint to display available models
 */

function SDBEE_endpoint_marketplace( $request, $output=false) {
    global $PUBLIC, $STORAGE, $ACCESS;
    $r = '<div class="marketplace">';
    $defaultTemplateImage = "https://www.sd-bee.com/upload/N0r0D0N30_defaultTemplateImage.png";
    $thumbnailTemplate = '<div id="" class="model-thumb"><h2>{%title}</h2><img src="{%image}" />';
    $thumbnailTemplate .= '<p>{%descr}<p>';
    $thumbnailTemplate .= '<a href="javascript:" class="mainOption" onclick="{%click1};">{!Utiliser !}{%title}</a>';
    $thumbnailTemplate .= '</div>';
    $publicModels = $PUBLIC->getList( 'models');
    //var_dump( $publicModels);
    $ignore = [];
    /* // Local models
    $userModelsInfo =  $ACCESS->getCollectionContents( 'Models');
    for ( $modeli=0; $modeli < count( $userModelsInfo); $modeli++) {
        $modelInfo = $userModels[ $modeli];
        $modelName = $modelInfo[ 'name'];
        $model = new SDBEE_doc( $modelName);        
        if ( false && $model) {
            // Grab the description and image from the model itself
            $descrEl = $model->readElement( 'BUU0000010000');
            $descr = $descrEl[ 'tcontent'];
            $imgEl = $model->readElement( 'BUU0000010000');
            $image = "";
        }
        $data = [
            'title' => $model->label,
            'descr' => $model->description,
            'image' => ($image) ? $image : $defaultTemplateImage,
            'click1' => "$$$.setModel('{$model->name}');",
        ];
        if ( $model->params[ 'replaceInMarket']) {
            $ignore[] = $model->params[ 'replaceInMarket'];            
        }
        $thumbnail = LF_substitute( $thumbnailTemplate, $data);
        $r .= $thumbnail;
        if ( $output) echo $thumbnail; 
    }*/
    // PUblic models
    for ( $modeli=0; $modeli < count( $publicModels); $modeli++) {
        $modelName = $publicModels[ $modeli];
        if ( in_array( $modelName, $ignore)) continue;
        if ( !$PUBLIC->exists( "models", $modelName)) {
            echo "don't exists $modelName"; 
            die(); //continue;
        }
        $model = new SDBEE_doc( str_replace( '.json', '', $modelName), 'models', $PUBLIC);        
        if ( false && $model) {
            // Grab the description and image from the model itself
            $descrEl = $model->readElement( 'BUU0000010000');
            $descr = $descrEl[ 'tcontent'];
            $imgEl = $model->readElement( 'BUU0000010000');
            $image = "";
        }
        $data = [
            '%title' => $model->label,
            '%descr' => $model->description,
            '%image' => ($image) ? $image : $defaultTemplateImage,
            '%click1' => "$$$.setModel('{$model->name}');",

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