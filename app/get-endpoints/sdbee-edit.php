<?php
/**
 * sdbee-edit?php - Endpoint on SD bee server to open an SD bee document for edition
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
function SDBEE_endpoint_edit( $request) {
        // Display a task
        $taskName = $request[ 'task'];
        /*
        if ( $request[ 'archive']) {
                include_once "sdbee_archive.php";
                $archive = new SDBEE_archive( $request[ 'archive'], $taskName);  
                $doc = $archive->open( $name),
        }
        */
        $doc = new SDBEE_doc( $taskName);
        if ( !$doc->model) {
                // No model so display market place
                $model = 'A000000003B2D90000M_ModelMarke';
                $doc2 = new SDBEE_doc( $model, 'models');
                $doc2->sendToClient();
        } else {        
                if ( $doc->state == "new") $doc->initialiseFromModel();
                $doc->sendToClient();
        }
}
SDBEE_endpoint_edit( $request);