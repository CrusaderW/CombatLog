<?php

require_once '../users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/template/prep.php';
require_once 'functions.php';

if (!securePage($_SERVER['PHP_SELF'])){die();}
$hooks =  getMyHooks();
//dealing with if the user is logged in
if($user->isLoggedIn() && !checkMenu(2,$user->data()->id)){
	if (($settings->site_offline==1) && (!in_array($user->data()->id, $master_account)) && ($currentPage != 'login.php') && ($currentPage != 'maintenance.php')){
		$user->logout();
		Redirect::to($us_url_root.'users/maintenance.php');
	}
}
pgLoadCampaignMapPoiData();


$errors=[];
$successes=[];
$userId = $user->data()->id;
$suggestionsData= pgGetSuggestions();
$conn= pgConnect();
//Forms posted
if(!empty($_POST)) {
    $token = $_POST['csrf'];
    global $db;
    if(!Token::check($token)){
        include($abs_us_root.$us_url_root.'usersc/scripts/token_error.php');
    }else {
        
        //handle edits
        $edits=false;
        switch($_POST['button']){
            case 'Edit Campaign Name':
                $updateQ = pg_update($conn,'campaigns',array('name'=>$_POST['campaignName0']),array('id'=>$_POST['campaign']));
                pgLoadCampaignMapPoiData();
                $edits=true;
                break;
            case 'Edit Map Name':
                $updateQ = pg_update($conn,'maps', array('name'=>$_POST['mapName0']),array('id'=>$_POST['map']));
                pgLoadCampaignMapPoiData();
                $edits=true;
                break;
            case 'Edit Location Name':
                $updateQ =pg_update($conn,'pois', array('name'=>$_POST['poiName0']),array('id'=>$_POST['poi']));
                pgLoadCampaignMapPoiData();
                $edits=true;
                break;
        }

// handle new/old campaign,maps,pois
        
        
        if(!$edits){
            $campaignName= $_POST['campaignName'.$_POST['campaignNameCheck']];
            $mapName= $_POST['mapName'.$_POST['mapNameCheck']];
            $poiName= $_POST['poiName'.$_POST['poiNameCheck']];
            if(strlen($campaignName)>3 AND strlen($mapName)>3 AND strlen($poiName)>3){
                //campaign id
                foreach($_SESSION['campaignsData'] as $v){
                    if ($v['name']==$campaignName){
                        $campaignId= $v['id'];
                    }
                }
                if($campaignId == null){//add a new campaign
                    $recC=array(
                        'name'=>$campaignName,
                        'user_id'=>$userId,
                        'active'=>1
                    );
                    pg_insert($conn,'campaigns',$recC);
                    $successes[]="Campaign <b>$campaignName</b> added successfully.";
                    $campaignId= pgGetValueInField1ByField2('campaigns', 'id', 'name', $campaignName);
                    pgLoadCampaignMapPoiData();
                }

                //map id
                foreach($_SESSION['mapsData'] as $v){
                    if ($v['name']==$mapName AND $v['campaign_id']==$campaignId){
                        $mapId= $v['id'];
                    }
                }
                if($mapId == null){//add a new map
                    $recM=array(
                        'campaign_id'=>$campaignId,
                        'name'=>$mapName,
                        'user_id'=>$userId
                    );
                    pg_insert($conn,'maps',$recM);
                    pgLoadCampaignMapPoiData();
                    $successes[]="Map <b>$mapName</b> added successfully in campaign <b>$campaignName</b>.";
                    foreach($_SESSION['mapsData'] as $v){
                        if($v['name']==$mapName AND $v['campaign_id']==$campaignId){
                            $mapId= $v['id'];
                        }
                    }
                }

                //poi id
                foreach($_SESSION['poisData'] as $v){
                    if ($v['name']==$poiName AND $v['map_id']==$mapId){
                        $poiId= $v['id'];
                    }
                }
                if($poiId == null){//add a new map
                    $recP=array(
                        'map_id'=>$mapId,
                        'name'=>$poiName,
                        'user_id'=>$userId
                    );
                    pg_insert($conn,'pois',$recP);
                    pgLoadCampaignMapPoiData();
                    $successes[]="Location <b>$poiName</b> added successfully on map <b>$mapName</b> in campaign <b>$campaignName</b>";
                    foreach($_SESSION['poisData'] as $v){
                        if($v['name']==$poiName AND $v['map_id']==$mapId){
                            $poiId= $v['id'];
                        }
                    }
                }

                

                //handle buttons
                switch($_POST['button']){
                    case 'Delete Selected Campaign':
                        foreach($_SESSION['mapsData'] as $v){
                            if($v['campaign_id']==$campaignId){
                                pg_delete($conn, "pois", array('map_id'=>$v['id']));
                            }
                        }
                        pg_delete($conn, "maps", array('campaign_id'=>$campaignId));
                        pg_delete($conn, "campaigns", array('id'=>$campaignId));
                        pgLoadCampaignMapPoiData();
                        $successes[]="Campaign <b>$campaignName</b> has been Deleted!";
                        break;
                    case 'Delete Selected Map':
                        pg_delete($conn, "pois", array('map_id'=>$mapId));
                        pg_delete($conn, "maps", array('id'=>$mapId));
                        pgLoadCampaignMapPoiData();
                        $successes[]="Map <b>$mapName</b> in Campaign <b>$campaignName</b> has been Deleted!";
                        break;
                    case 'Delete Selected Location':
                        pg_delete($conn, "pois", array('id'=>$poiId));
                        pgLoadCampaignMapPoiData();
                        $successes[]="Location <b>$poiName</b> for Map <b>$mapName</b> in Campaign <b>$campaignName</b> has been Deleted!";
                        break;
                    case 'Delete Selected Suggestions':
                       foreach ($suggestionsData as $k => $v) {
                    
                            if($_POST['similarPoi'.($k+1)]>0){
                               pg_query_params("DELETE FROM combatlog WHERE user_id=$1 AND date_time BETWEEN $2 AND $3",array($v['user_id'], $v['combat_log_start'],$v['combat_log_end]']));
                               $successes[]='Selected suggestions Deleted!';
                               pg_delete($conn, "location_suggestions", array('id'=>$v['id']));
                            }
                        }
                        $suggestionsData= pgGetSuggestions();
                        break;
                }
                
                

                //handle suggestions
                foreach ($suggestionsData as $k => $v) {
                    if($_POST['similarPoi'.($k+1)]>0){
                       $updateQ = pg_query_params("UPDATE combatlog SET poi_id=$1 WHERE user_id=$2 AND date_time BETWEEN $3 AND $4",array($poiId, $v['user_id'], $v['combat_log_start'], $v['combat_log_end']));
                       $successes[]='Combat Log Data Updated!';
                       pg_delete($conn, 'location_suggestions', array('id'=>$v['id']));
                       $suggestionsData= pgGetSuggestions();
                    }
                }
            }else{
                $errors[]='Improper selection.Try again.';
            }
        }
    }   
}
?>
            <div class="row">
                <div class="col-sm-12 col-md-10">
                    <h1><?echo 'Campaigns, Maps & Locations';?></h1>
                    
                    <?php if(!$successes=='') {?><div class="alert alert-success"><?=display_successes($successes);?></div><?php }
                        includeHook($hooks,'body');?>
                    <?php if(!$errors=='') {?><div class="alert alert-danger"><?=display_errors($errors);?></div><?php } ?>
                    <p> Select the Campaign, Map and Location desired. Fields can be edited.</p>
                    <p> Use Existing names (first row) where applicable to avoid duplicates.</p>
                    <p><b>Delete Selected Campaign/Map/Location</b> - will delete all subsequent data but not the combat reports</p>
                    <p> <b>Save</b> - will add (if it's new) Campaign, Map, Location</p>
                    <p> <b>Select all similar + Save</b> - if selected then the related combat reports will be added to the selected location and the suggestion deleted.
                    <p> <b>Delete Selected Suggestions</b> - will delete the suggestions and the linked combat reports.</p>
                    <?php
                    if(isset($user->data()->oauth_provider) && $user->data()->oauth_provider != null){
                        echo lang("ERR_GOOG");
                    }
                        includeHook($hooks,'bottom');
                    ?>
                </div>
            </div>
            <div class="row">    
                <?php
                    
                    ?>
                    <form enctype="multipart/form-data" name='suggestions_form' action='<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>' method='post'>
                        <table class='table table-hover table-striped table-list-search display'>
                            <tr align="center">
                                <td></td>
                                <td><b>Select campaign name</b></td>
                                <td><b>Select map name</b></td>
                                <td><b>Select location name</b></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td align='center'></td>
                                <td align='center'>
                                    <input type="radio" id='campaignNameCheck' name="campaignNameCheck" value="0"><font color="black">Select</font><br>
                                    <?php echo buildSelectFromArrayColumns('campaign', 0,'Campaign (New)...', $_SESSION['campaignsData'],'id','name','applyCampaignScript()')?>
                                    <br><input type='text' size= '30' id='campaignName0' name='campaignName0' value=''/>
                                    <br><input type='submit' style="color:green; font-size: 12px" name='button' value='Edit Campaign Name' class='submit' onclick="return confirm('Are you sure you want to Edit this Campaign Name?')" />
                                    <hr><input type='submit' style="color:#cc0000; font-size: 12px" name='button' value='Delete Selected Campaign' class='submit' onclick="return confirm('Are you sure you want to Delete the Selected Campaign and asociated Maps and Locations?')" />
                                    
                                </td>
                                <td align='center'>
                                    <input type="radio" id='mapNameCheck' name="mapNameCheck" value="0"><font color="black">Select</font><br>
                                    <?php echo buildSelectFromArrayColumns('map', 0, 'Map (New)...', $_SESSION['mapsData'],'id','name','applyMapScript()')?>
                                    <br><input type='text' size= '30' id="mapName0" name='mapName0' value=''/>
                                    <br><input type='submit' style="color:green; font-size: 12px" name='button' value='Edit Map Name' class='submit' onclick="return confirm('Are you sure you want to Edit this Map Name?')" />
                                    <hr><input type='submit' style="color:#cc0000; font-size: 12px" name='button' value='Delete Selected Map' class='submit' onclick="return confirm('Are you sure you want to Delete the Selected Map and associated Locations?')" />
                                </td>
                                <td align='center'>
                                    <input type="radio" id='poiNameCheck' name="poiNameCheck" value="0"><font color="black">Select</font><br>
                                    <?php echo buildSelectFromArrayColumns('poi', 0, 'Location (New)...', $_SESSION['poisData'],'id','name','applyPoiScript()')?>
                                    <br><input type='text' size= '30' id="poiName0" name='poiName0' value=''/>
                                    <br><input type='submit' style="color:green; font-size: 12px" name='button' value='Edit Location Name' class='submit' onclick="return confirm('Are you sure you want to Edit this Location Name?')" />
                                    <hr><input type='submit' style="color:#cc0000; font-size: 12px" name='button' value='Delete Selected Location' class='submit' onclick="return confirm('Are you sure you want to Delete the Selected Location?')" />
                                </td>
                                <td></td>
                            </tr>
                            <?php
                            if (count($suggestionsData)>0){
                                ?>
                                <tr>
                                    <td width='130' align='center'><b>Select all similar</b><br> This will also delete the selected suggestions</td>
                                    <td colspan="3" align="center">
                                        <h3>Suggestions</h3>
                                    </td>
                                    <td align='center'><b>Suggested by</b></td>
                                </tr>

                                <?php includeHook($hooks,'form');

                                foreach($suggestionsData as $k=>$v){
                                    ?>
                                <tr valign="middle" height="75" align="center">
                                    <td>
                                        <input type="checkbox" name="similarPoi<?php echo $k+1?>" value="<?php echo $k+1?>"><br>
                                        <?php echo '<br><font size="1">'.$v['combat_log_start'].'<br>'.$v['combat_log_end'].'</font>';?> 
                                    </td>
                                    <td width="300" style="background: #cccccc">
                                        <input type="radio" name="campaignNameCheck" value="<?php echo $k+1?>"><font color="white">Select</font><br>
                                        <input type='text' size= '30' name='campaignName<?php echo $k+1?>' value='<?php echo $v['campaign_name']?>'/>
                                        <br>
                                    </td>
                                    <td  width="300" style="background: #8c8c8c">
                                        <input type="radio" name="mapNameCheck" value="<?php echo $k+1?>"><font color="white">Select</font><br>
                                        <input type='text' size= '30' name='mapName<?php echo $k+1?>' value='<?php echo $v['map_name']?>'/>
                                    </td>
                                    <td width="300" style="background: #cccccc">
                                        <input type="radio" name="poiNameCheck" value="<?php echo $k+1?>"><font color="white">Select</font><br>
                                        <input type='text' size= '30' name='poiName<?php echo $k+1?>' value='<?php echo $v['poi_name']?>'/>
                                    </td>
                                     <td>
                                        <?php
                                            echo getValueInField1ByField2('users', 'username', 'id', $v['user_id']);
                                        ?>
                                    </td>
                                </tr>
                                    <?php
                                    }
                            }
                            ?>
                            <tr>
                                <td align="center" colspan="5">
                                    <input type="hidden" name="csrf" value="<?=Token::generate();?>" />
                                    <input class='btn btn-primary' type='submit' name="button" value='Save' class='submit' />
                                    <?php
                                    if (count($suggestionsData)>0){
                                        ?>
                                    <input class='btn btn-primary' type='submit' name='button' value='Delete Selected Suggestions' class='submit' />
                                    <?php }?>
                                </td>
                            </tr>
                        </table>
                    </form>
                <?php
                    
                        
                        
                    
                ?>
            </div>

<!-- footers -->

<?php require_once $abs_us_root . $us_url_root . 'users/includes/page_footer.php'; ?>

<!-- Place any per-page javascript here -->
<script type="text/javascript">
		
    var mapsData = <?php echo json_encode($_SESSION['mapsData']);?>;
    var poisData = <?php echo json_encode($_SESSION['poisData']);?>;
    applyCampaignScript();
    function removeOptions(selectbox)
        {
            var i;
            for(i = selectbox.options.length - 1 ; i >= 0 ; i--)
            {
                selectbox.remove(i);
            }
        }
    function applyCampaignScript() {
        result=mapsData;

        function campaignFilter(x){
            return x['campaign_id'] == document.getElementById("campaign").value;
        }

        if (document.getElementById("campaign").value >=0){
            var result=result.filter(campaignFilter);
        }

        removeOptions(document.getElementById("map"));
        var sel = document.getElementById('map');
        var fragment = document.createDocumentFragment();
        var opt = document.createElement('option');
        opt.innerHTML = 'Map (New)...';
        opt.value = 0;
        fragment.appendChild(opt);
        result.forEach(function(nameF) {
            var opt = document.createElement('option');
            opt.innerHTML = nameF['name'];
            opt.value = nameF['id'];
            fragment.appendChild(opt);
        });
        sel.appendChild(fragment);
        document.getElementById("campaignNameCheck").checked=true;
        document.getElementById("campaignName0").value=document.getElementById("campaign").options[document.getElementById("campaign").selectedIndex].text;
        
        applyMapScript();
    }

    function applyMapScript() {
        result=poisData;

        function campaignFilter(x){
            return x['map_id'] == document.getElementById("map").value;
        }

        if (document.getElementById("map").value >= 0){
            var result=result.filter(campaignFilter);
        }

        removeOptions(document.getElementById("poi"));
        var sel = document.getElementById('poi');
        var fragment = document.createDocumentFragment();
        var opt = document.createElement('option');
        opt.innerHTML = 'Location (New)...';
        opt.value = 0;
        fragment.appendChild(opt);
        
        result.forEach(function(nameF) {
            var opt = document.createElement('option');
            opt.innerHTML = nameF['name'];
            opt.value = nameF['id'];
            fragment.appendChild(opt);
        });
        sel.appendChild(fragment);
        
        
        
        document.getElementById("mapNameCheck").checked=true;
        document.getElementById("mapName0").value=document.getElementById("map").options[document.getElementById("map").selectedIndex].text;
        applyPoiScript();
       
    }
    function applyPoiScript(){
        document.getElementById("poiNameCheck").checked=true;
        document.getElementById("poiName0").value=null;
        document.getElementById("poiName0").value=document.getElementById("poi").options[document.getElementById("poi").selectedIndex].text;
        
    }

</script>


<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
