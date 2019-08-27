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
$conn= pgConnect();
$grav = get_gravatar(strtolower(trim($user->data()->email)));
$errors=[];
$successes=[];
$userId = $user->data()->id;
$view='';
if (isset($_POST['view'])){$view=$_POST['view'];}
//Forms posted
switch($view){
    case 'combatLogAnalysis':
        if(!empty($_POST)) {
            $token = $_POST['csrf'];
            global $db;
            if(!Token::check($token)){
                                        include($abs_us_root.$us_url_root.'usersc/scripts/token_error.php');
            }else {
                if($_FILES['combatlog']["tmp_name"]!=''){
                    if ($file = fopen($_FILES["combatlog"]["tmp_name"], "rb")) {
                        # verify is a combat log
                        $check = fgets($file);
                        if(!substr($check, 25, 4)=='INFO'){
                            $errors[]='Not a Combat Log File';
                        }else{
                            $combat_log = file($_FILES["combatlog"]["tmp_name"]);
                            $counter=0;$added=0;$duplicates=0;
                            $unknown_skills=array();
                            $_SESSION['combatLogData']=array();
                            pgLoadCombatLogDBCompareData($userId,substr($combat_log[0], 0, 10).' '. substr($combat_log[0], 11, 12), substr($combat_log[count($combat_log)-1], 0, 10).' '. substr($combat_log[count($combat_log)-1], 11, 12));
                            foreach ($combat_log as $v) {
                                $date_time= substr($v, 0, 10).' '. substr($v, 11, 12);
                                #find the hit/healed
                                $skill_action_pos=strpos($v,'healed');
                                $skill_action='healed';
                                if($skill_action_pos==NULL){
                                    $skill_action_pos=strpos($v,'hit');
                                    $skill_action='hit';
                                }
                                #find the skill
                                $i=3;
                                $found=false;
                                $skill=array();
                                while($i<30){
                                    $try_this= substr($v, $skill_action_pos-1-$i,$i);

                                    if (pgCheckValueInField('skills',$try_this, 'name')>0){
                                        $skill[]=$try_this;
                                        $found=true;
                                    }
                                    $i++;
                                }
                                if(!$found){
                                    $skill_by= substr($v, strpos($v, '[')+1, strpos($v, $try_this)-strpos($v, '[')-1);
                                    $skill_name='?: '.substr($try_this, 10);
                                    $unknown_skills[]=$v.'{{'.$skill_name;
                                }else{
                                    $skill_name=$skill[count($skill)-1];
                                    $skill_by= substr($v, strpos($v, '[')+1, strpos($v,$skill_name)-strpos($v, '[')-2);
                                    
                                    pg_update($conn, 'skills', array('last_seen'=>$date_time), array('name'=>$skill_name));
                                }
                                $skill_target='';
                                $skill_amount='';
                                if ($skill_action=='hit'){
                                    $skill_target= substr($v, $skill_action_pos+4, strpos($v, ' for ')-$skill_action_pos-4);
                                    $skill_amount= trim(substr($v, strpos($v,' for ')+5, strpos($v, ' damage')-strpos($v,' for ')-5));
                                }elseif($skill_action='healed'){
                                    $skill_target=substr($v, $skill_action_pos+7, strpos($v, ' for ')-$skill_action_pos-7);
                                    $skill_amount= trim(substr($v, strpos($v,' for ')+5, strpos($v, ' hit ')-strpos($v,' for ')-5));
                                }



                                if ($skill_by=='Your'){
                                    $skill_by=$user->data()->username;
                                }
                                if ($skill_target=='You'){
                                    $skill_target=$user->data()->username;
                                }
                                if(strpos($v,'(Critical)')){
                                    $skill_critical=1;
                                }else{
                                    $skill_critical=0;
                                }
                                #next line is for debugging purposes when combat log output will change.
                                //echo '<hr>_'.$date_time.'_'.$skill_by.'_'.$skill_name.'_'.$skill_action.'_'.$skill_target.'_'.$skill_amount.'_'.$skill_critical.'_<br>';
                                #check if skill is known
                                if ($found AND strpos){

                                    $rec=array( 'user_id'=>$user->data()->id,
                                                'date_time'=>$date_time,
                                                'skill_by'=>$skill_by,
                                                'skill_id'=> pgCheckValueInField('skills',$skill_name, 'name'),
                                                'skill_action'=>$skill_action,
                                                'skill_target'=>$skill_target,
                                                'skill_amount'=>$skill_amount,
                                                'skill_critical'=>$skill_critical
                                            );
                                    
                                    
                                    if (!in_multiarray($rec, $_SESSION['combatLogDBCompareData'])){ //verify if the log line exists already
                                        $added++;
                                        $rec['inDb']=0;
                                        $_SESSION['combatLogData'][]=$rec;
                                    }else{
                                        $rec['inDb']=1;
                                        $_SESSION['combatLogData'][]=$rec;
                                        $duplicates++;
                                    }
                                }
                                $counter++;
                            }
                            $successes[]="$counter combat log lines processed.";
                            $successes[]=$duplicates." Duplicates.";
                            $successes[]="$added possible new combat lines.";
                            $successes[]="--------------------------------";
                            $successes[]="<b>N.B.:</b>";
                            $successes[]="1. The following is an estimation of different fights. If they are the same fight just choose the same Location and they will be considered together.";
                            $successes[]="2. The time is UTC/GMT.";
                            $successes[]="--------------------------------";
                            $successes[]="<b>To continue please click the <i>Fight #Nr</i> you would like to add!</b>";
                            foreach (array_unique($unknown_skills) as $v2) {
                                $err.=$v2.'<br>';
                            }
                            if(count(array_unique($unknown_skills))>0){
                                $errors[]='You can ignore the following part or help out by reporting skill names that you recognize.';
                                $errors[]=count(array_unique($unknown_skills)).' <b>Unknown skills:</b><br> '.$err; 
                            }
                        }
                        fclose($file);
                    }
                    fightsAndPlayers();
                   
                }else{
                    $errors[]='No File Selected!'; 
                }    
            }   
        }
        break;
        
    case 'fightAnalysis':
        
        break;
    
    case 'submitFight':
       
        if(!($_POST['poi']==0 AND $_POST['new_poi']=='')AND!($_POST['map']==0 AND $_POST['new_map']=='')AND!($_POST['campaign']==0 AND $_POST['new_campaign']=='')){
            //participants to exclude
            $excludes=array();
            foreach ($_SESSION['players'][$_POST['fightNr']] as $k=> $p){
                if($_POST['player'.$k]==$k+1){
                    $excludes[]=$p;
                }
            }
            //array to insert
            $count=0;
            $logToInsert=array();
            foreach ($_SESSION['combatLogData'] as $k=>$l){
                if(!in_array($l['skill_by'],$excludes) AND !in_array($l['skill_target'],$excludes) AND $l['date_time']>=$_SESSION['fight'][$_POST['fightNr']]['start'] AND $l['date_time']<=$_SESSION['fight'][$_POST['fightNr']]['end']){
                    $rec=$l;
                    unset($rec['inDb']); //remove from the add array
                    if ($_SESSION['combatLogData'][$k]['inDb']==0){ //verify if the log line exists already
                        $rec['poi_id']=$_POST['poi'];
                        $logToInsert[]=$rec;
                        $count++;
                        $_SESSION['combatLogData'][$k]['inDb']=1;
                    }
                }
            }
            //print_r($logToInsert);
            pgInsert('combatlog', $logToInsert);
            $successes[]="$count lines have been added.";
            $txt='';
            if(count($excludes)>0){
                foreach($excludes as $t){
                    $txt.=$t.'; ';
                }
                $successes[]='<b>Lines containing the following Entries have been excluded:</b> '. $txt;
            }
            

            //suggestions
            if(strlen($_POST['new_poi'])>0){
                if($_POST['map']>0){
                    $map= pgGetValueInField1ByField2('maps', 'name', 'id', $_POST['map']);
                }else{
                    $map=$_POST['new_map'];
                }
                if($_POST['campaign']>0){
                    $campaign= pgGetValueInField1ByField2('campaigns', 'name', 'id', $_POST['campaign']);
                }else{
                    $campaign=$_POST['new_campaign'];
                }
                $rec=array(
                    'user_id' => $userId,
                    'campaign_name' => $campaign,
                    'map_name' => $map,
                    'poi_name' => $_POST['new_poi'],
                    'combat_log_start' => $_SESSION['fight'][$_POST['fightNr']]['start'],
                    'combat_log_end' => $_SESSION['fight'][$_POST['fightNr']]['end']
                );
                pgInsert('location_suggestions', [$rec]);
            }
            
            
        }else{
            $errors[]='Not a valid Location!';
        }
        $view='combatLogAnalysis';
        break;
}

?>
            <div class="row">
                <div class="col-sm-12 col-md-2">
                    <p><img src="<?=$grav; ?>" class="img-thumbnail" alt="Generic placeholder thumbnail"></p>
                </div>
                <div class="col-sm-12 col-md-10">
                    <h1><?php 
                    switch ($view){
                        case '':
                            echo 'Combat Log';
                            break;
                        case 'combatLogAnalysis':
                            echo 'Combat Log Analysis';
                            break;
                        case 'fightAnalysis':
                            echo 'Fight Data';
                            break;
                        
                    }
                    ?></h1>
                    
                    <?php 
                        if(!$successes=='') {?><div class="alert alert-success"><?=display_successes($successes);?></div><?php }
                        if(!$errors=='') {?><div class="alert alert-danger"><?=display_errors($errors);?></div><?php } 
                    includeHook($hooks,'body');
                    switch ($view){
                        case '':
                            
                            ?>

                            <form enctype="multipart/form-data" name='combat_log_form' action='<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>' method='post'>

                                <div class="form-group">
                                    <label><? echo 'Large Files will take longer so be patient'?></label>
                                    <input class='form-control' type='file' name='combatlog' value='' autocomplete="off" />

                                </div>
                                <?php includeHook($hooks,'form');?>
                                <input type="hidden" name="csrf" value="<?=Token::generate();?>" />
                                <input type="hidden" name="view" value="combatLogAnalysis" />

                                <p><input class='btn btn-primary' type='submit' value='<?echo "Go"?>' class='submit' /></p>
                            </form>
                            <?php
                            break;
                        
                        case 'combatLogAnalysis':
                            for($i=1;$i<=count($_SESSION['fight']);$i++){
                                //determine the lines added already
                                $alreadyAdded=0; $available=0;
                                foreach($_SESSION['combatLogData'] as $v){
                                    if ($v['date_time']>=$_SESSION['fight'][$i]['start'] AND $v['date_time']<=$_SESSION['fight'][$i]['end']){ 
                                        if ($v['inDb']==1){ 
                                            $alreadyAdded++;
                                        }else{
                                            $available++;
                                        }
                                    }
                                }
                                if($available>0){
                                ?>
                                 <div class="row" style="background-color: #f2f2f2;">
                                     <form enctype="multipart/form-data" name='fight_form<?php echo$i;?>' action='<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>' method='post'>
                                         <?php includeHook($hooks,'form');?>

                                         <input type="hidden" name="view" value="fightAnalysis" />
                                         <input type="hidden" name="fightNr" value="<?php echo $i;?>" />
                                         <input class='btn btn-primary' type='submit' value='<?php echo "Fight #$i";?>' class='submit' />
                                         <?php echo ' <b>Duration: </b>'.round(abs(strtotime($_SESSION['fight'][$i]['end']) - strtotime($_SESSION['fight'][$i]['start'])) / 60,0)." min </h3> <b>Size:</b>".$_SESSION['fight'][$i]['numberOfLines']." lines <b>Lines Already Added:</b> $alreadyAdded <b>Available Lines:</b> $available";?>
                                     </form>
                                 <?php
                                 echo "<br><b>From: </b>".$_SESSION['fight'][$i]['start'].' <b>To: </b>'.$_SESSION['fight'][$i]['end'].' <b> Number of participants: </b>'.count($_SESSION['players'][$i]).'<br>';
                                 echo '<b>Participants: </b>';
                                 foreach ($_SESSION['players'][$i] as $k=>$p) {
                                     echo '<font size="2" color="#999999">'.$p.';</font> ';
                                                                     }
                                 echo '</div><hr>';
                                }
                            }
                            
                            break;
                            
                        case 'fightAnalysis':
                            $fStart=$_SESSION['fight'][$_POST['fightNr']]['start'];
                            $fEnd=$_SESSION['fight'][$_POST['fightNr']]['end'];
                            echo "<br><b>From: </b>".$_SESSION['fight'][$_POST['fightNr']]['start'].' <b>To: </b>'.$_SESSION['fight'][$_POST['fightNr']]['end'].' <b> Number of participants: </b>'.count($_SESSION['players'][$_POST['fightNr']]).'<hr>';
                            
                            //determine pre-existing fights around the same time
                            $logQ = pg_query_params("SELECT DISTINCT poi_id FROM combatlog WHERE date_time BETWEEN $1 AND $2",array($fStart,$fEnd));
                            $prevFights= pg_fetch_all($logQ);
                            $pois=array();
                            if($prevFights!=FALSE){
                                $prevFights= array_values(array_msort($prevFights, array('date_time'=>SORT_ASC)));
                                $pois= array_values(array_unique(array_column($prevFights,'poi_id')));
                            }
                            ?>
                            <form enctype="multipart/form-data" name='player_form' action='<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>' method='post'>
                                <input type="hidden" name="fightNr" value="<?php echo $_POST['fightNr'];?>" />
                                <div class="row" style="background-color: #f2f2f2;">
                                    <center>
                                    <h3> WHERE DID THE FIGHT TAKE PLACE</h3>
                                    </center>
                                    <?php
                                    if(count($pois)>0){
                                        ?>
                                        <div style="background-color: #ecf9ec;">
                                            <br>
                                            <font size="3" color="#133913">Previously reported <b>Locations</b> with fights around the same time:</font><br>
                                        <?php
                                        $c=1;
                                            foreach ($pois as $k=>$p) {
                                                $mapId= pgGetValueInField1ByField2('pois', 'map_id', id, $p);
                                                $campaignId= pgGetValueInField1ByField2('maps', 'campaign_id', id, $mapId);
                                                echo $c.'. '.pgGetValueInField1ByField2('campaigns', 'name', 'id', $campaignId).
                                                        ' -> '.pgGetValueInField1ByField2('maps', 'name', 'id', $mapId).
                                                        ' -> '.pgGetValueInField1ByField2('pois', 'name', 'id', $p). '<br>';
                                            }
                                            ?>
                                            <br>
                                        </div>
                                    <?php
                                    }
                                    ?>
                                    <div class="col-sm-12 col-md-4" style="background-color: #e6e6e6;">
                                        <center>
                                        <font size="3"><b>Select Campaign</b></font><br>
                                        <?php echo buildSelectFromArrayColumns('campaign', '0', 'Campaign (New)...', $_SESSION['campaignsData'],'id','name','applyCampaignFilter()')?>
                                        <hr><input type="text" size="35" id="new_campaign" name="new_campaign" placeholder="New Campaign Suggestion">
                                        </center>
                                    </div>
                                    <div class="col-sm-12 col-md-4" style="background-color: #d9d9d9;">
                                        <center>
                                        <font size="3"><b>Select Map</b></font><br>
                                        <?php echo buildSelectFromArrayColumns('map', '', '', $_SESSION['mapsData'],'id','name','applyPoiFilter()')?>
                                        <hr><input type="text" size="35" id="new_map" name="new_map" placeholder="New Map Suggestion">
                                        </center>
                                    </div>
                                    <div class="col-sm-12 col-md-4" style="background-color: #e6e6e6;">
                                        <center>
                                        <font size="3"><b>Select Location</b></font><br>
                                        <?php echo buildSelectFromArrayColumns('poi', '', '', $_SESSION['poisData'],'id','name','applyPoiDisable()')?>
                                        <hr><input type="text" size="35" id="new_poi" name="new_poi" placeholder="New Location Suggestion">
                                        </center>
                                    </div>  
                                    
                                    <center>
                                        <p><font color="#999999">If the name of the Campaign, Map or Location is not listed please consider using the suggestion fields.
                                        </font></p>
                                        <p><font color="#999999">If New Campaign or New Map or New Location are suggested the fight will be recorded but unavailable until the location Suggestion is processed.
                                        Once the suggestions will be processed, within 24h usually, if the data provided is real then it will be added to your <b>Desired Location</b>.
                                        </font></p>
                                    </center>
                                    
                                </div>
                                
                                <hr>
                                <div class="row" style="background-color: #f2f2f2;">
                                    <center>
                                    <h3> SELECT THE ENTRIES TO IGNORE</h3>
                                    
                                    <p><i><font color="#999999">Due to combat log errors there are erroneous entries. Please select to ignore the ones that look like errors.</font></i></p>
                                    </center>
                                    <?php includeHook($hooks,'form');

                                        foreach ($_SESSION['players'][$_POST['fightNr']] as $k=>$p){
                                            ?>
                                            <div class="col-sm-12 col-md-3">
                                            <input type="checkbox" name="player<?php echo $k?>" value="<?php echo $k+1?>"> <?php echo $p?><br>
                                            </div>
                                            <?php
                                        }
                                        ?>
                                </div>
                                        
                                        <input type="hidden" name="view" value="submitFight" />





                                        <hr>
                                        <center>
                                        <input class='btn btn-primary' type='submit' value='Insert Fight' class='submit' />
                                        </center>
                            </form>
                    
                    
                    
                    <script>
                        var mapsData = <?php echo json_encode($_SESSION['mapsData']);?>;
                        var poisData = <?php echo json_encode($_SESSION['poisData']);?>;
                        
                        applyCampaignFilter();
                        function removeOptions(selectbox)
                            {
                                var i;
                                for(i = selectbox.options.length - 1 ; i >= 0 ; i--)
                                {
                                    selectbox.remove(i);
                                }
                            }
                        function applyCampaignFilter(){
                            applyMapFilter();
                            applyPoiFilter();
                            if (document.getElementById("campaign").value >0){
                                document.getElementById("new_campaign").setAttribute("disabled","disabled");
                                document.getElementById("new_campaign").value='';
                            }else{
                                document.getElementById("new_campaign").removeAttribute("disabled");
                            }
                        }    
                        
                        function applyMapFilter() {
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
                        }
                        
                        function applyPoiFilter() {
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
                            
                            //enebaled/disabled new_field
                            if (document.getElementById("map").value >0){
                                document.getElementById("new_map").setAttribute("disabled","disabled");
                                document.getElementById("new_map").value='';
                            }else{
                                document.getElementById("new_map").removeAttribute("disabled");
                            }
                        }
                        function applyPoiDisable(){
                            if (document.getElementById("poi").value >0){
                                document.getElementById("new_poi").setAttribute("disabled","disabled");
                                document.getElementById("new_poi").value='';
                            }else{
                                document.getElementById("new_poi").removeAttribute("disabled");
                            }
                        }

                    </script>
                      <?php
                            break;
                            
                        case 'submitFight':
                            $excludes=array();
                            foreach ($_SESSION['players'][$_POST['fightNr']] as $k=> $p){
                                if($_POST['player'.$k]==$k+1){
                                    $excludes[]=$p;
                                }
                            }
                            
                            foreach ($_SESSION['combatLogData'] as $l){
                                if(!in_array($l['skill_by'],$excludes) AND !in_array($l['skill_target'],$excludes) AND $l['date_time']>=$_SESSION['fight'][$_POST['fightNr']]['start'] AND $l['date_time']<=$_SESSION['fight'][$_POST['fightNr']]['end']){
                                    $rec=$l;$rec['poi_id']=111;
                                    $db->insert('kds_cclp_combat_logs',$rec);
                                }
                            }  
                            break;
                        }
                    if(isset($user->data()->oauth_provider) && $user->data()->oauth_provider != null){
                        echo lang("ERR_GOOG");
                    }
										includeHook($hooks,'bottom');
                    ?>
                
                </div>
            </div>

<!-- footers -->

<?php require_once $abs_us_root . $us_url_root . 'users/includes/page_footer.php'; ?>

<!-- Place any per-page javascript here -->
<script type="text/javascript">
		
</script>

<?php pgClose($conn); require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
