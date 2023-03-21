# SOURCE OVERVIEW

The index.php file in the top dirctory sends all requests to the app/sdbee-index.php file which loads the configuration, sets data access and gets user's context before dispatching requests to files in the get-endpoints and post-endpoints directories according to the request received. Endpoints are :

<ul>
    <li>Full page endpoints (GET)</li>
        <ul>
          <li>/ built-in - Display home page if not connected or main task directory if connected</li>
          <li>/?task=name - Edit a process, app or app model</li>
        </ul>
    <li>Get AJAX calls
        <ul>
          <li>/name/AJAX_listContainers sdbee-collection List tasks in a container</li>
          <li>/name/AJAX_fetch sdbee-fetch-element Return an element</li>
          <li>/name/AJAX_getChanged sdbee-changes Return a list of elements that have been changed</li>
          <li>//marketplace sdbee-marketplace Display a selection of models with links to select</li>
        </ul>
    </li>
    <li>Post AJAX calls
        <ul>
          <li>form=INPUT_UDE_FETCH sdbee-modify-element (create, update, delete)</li>
          <li>nServiceRequest=JSON sdbee-service-gateway Handle a call to a local or remote service</li>
          <li>form=INPUT_addApage sdbee-add-doc Add a new document (task, model)</li>
          <li>form=INPUT_createUser sdbee-add-user Add a new user</li>
          <li>form=INPUT_deleteUser sdbee-delete-user Delete a user</li>
          <li>form=INPUT_deleteDoc sdbee-delete-doc Delete a doc</li>
        </ul>   
    </li>
</ul>

All endpoint scripts access data via the sdbee-storage class and the sdbee-access setup by sdbee-index.  These in turn use classes defined in the storage-connectors and access-connectors to adapt to different environments. 

The editor is setup by the editor-view-model/ud.php file which uses information from the config sub-directory, libraries from the helpers sub-directory and programs for each element type stored in the elements sub-directory

Client-side programs and resources are initially setup to come from a public CDN.

Payable services are configured to use the sd-bee.com service where an account must be created and credits bought.



[Back to Readme](readme.md)