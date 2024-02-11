<?php
/**
 * sdbee-user.php - Endpoint to fetch user ùanagement interface 
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
/*
2 delete old form bits
<input type="hidden" name="input_oid" value="Links_User--2-0">
<tr>
    <input type="hidden" name="tdomain" value="localhost_webdesk">
</tr>
<tr>
    <input type="hidden" name="stype" value="1">
</tr>
*/

function SDBEE_endpoint_newUserForm() {

    $form = <<< EOT
    <form onsubmit="return window.ud.udajax.postForm( 'left-tool-zone', '/');" method="post" accept-charset="UTF-8" name="addAuser" class="form" id="addAuser" action="">
        <table class="form_table"><tbody>
            <tr class="form_titlebar"><td colspan="2">{!Add a user!}</td></tr>
            <input type="hidden" name="form" value="INPUT_addAuser">            
            <tr>
                <td class="form_fieldname">{!name!} : </td>
                <td class="form_fieldvalue"><input type="text" name="nname" id="nname" placeholder="id" size="40"></td>
            </tr>                       
            <tr>
                <td class="form_fieldname">{!password!} : </td>
                <td class="form_fieldvalue"><textarea name="tpasswd" cols="40" rows="5" style="width:100%;" placeholder="password"></textarea></td>
            </tr>
            <tr>
                <td class="form_fieldname">{!parameters!} : </td>
                <td class="form_fieldvalue">
                    <table width="100%"><tbody><tr></tr>
                        <tr>
                            <td>
                                <span class="form_compact_fieldname">JSON_params_sample</span><br>
                                <input type="text" name="nJSON_params_sample" id="nJSON_params_sample" placeholder="sample" size="40">
                            </td>
                            <td class="form_fieldname"></td>       <td class="form_fieldvalue"></td>
                        </tr>
                    </tbody></table>
                </td>
            </tr>
            <tr>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td class="form_cancelButton"></td>
                <td class="form_OKbutton"><input type="submit" value="OK"><a href=""><input type="button" name="cancel" value="{!Cancel!}" onclick=""></a></td>
            </tr>
        </tbody></table>
    </form>
EOT;

    global $DM;
    echo $DM->translate( $form);
}
global $request;
SDBEE_endpoint_newUserForm();