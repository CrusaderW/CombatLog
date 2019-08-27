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


$errors=[];
$successes=[];
$userId = $user->data()->id;
if(in_array('Administrator', getUserPermissions($userId))){
    $isAdmin=True;
}else{
    $isAdmin=False;
}
clearMemory();
if(!isset($_SESSION['campaignsData'])){
    pgLoadCampaignMapPoiData();
}
$view='';$day='';$poi=0;
if(isset($_GET['view'])){
    $view=$_GET['view'];
    $day=$_GET['day'];
    $poi=$_GET['poi'];
};
if(isset($_POST['view'])){
    $view=$_POST['view'];
    $day=$_POST['day'];
    $poi=$_POST['poi'];
}
$map= pgGetValueInField1ByField2('pois', 'map_id', 'id', $poi);
$campaign= pgGetValueInField1ByField2('maps', 'campaign_id', 'id', $map);
global $db;
$conn= pgConnect();
//Forms posted
if(!empty($_POST)) {
    $token = $_POST['csrf'];
    
    if(!Token::check($token)){
				include($abs_us_root.$us_url_root.'usersc/scripts/token_error.php');
    }else {
        if($isAdmin){
            switch($_POST['button']){
                case 'Delete Selected Combatants':
                    for($i=1;$i<=$_POST['playerNumber'];$i++){
                        if(strlen($_POST['player'.$i])>0){
                            $name= substr($_POST['player'.$i],0, strpos($_POST['player'.$i], 'StartAndEndCheck'));
                            $start=substr($_POST['player'.$i],strpos($_POST['player'.$i], 'StartAndEndCheck')+16,23);
                            $end=substr($_POST['player'.$i],strpos($_POST['player'.$i], 'StartAndEndCheck')+38,23);
                            
                            if(strpos($name, ']')){
                                $name=substr($name,0, strpos($name, ']')).'%';
                                pg_query_params("DELETE FROM combatlog WHERE (skill_by LIKE $1 OR skill_target LIKE $1)",array($name));
                            }else{
                                pg_query_params("DELETE FROM combatlog WHERE (date_time BETWEEN $1 AND $2 AND poi_id=$3) AND (skill_by=$4 OR skill_target=$4)",array($start,$end,$poi,$name));
                            }
                            $successes[]="<b>$name</b> has been deleted successfully from the fight from $start to $end";
                        }
                    }
                    break;
                case 'Delete Selected Fights':
                    for($i=1;$i<=$_POST['fightNumber'];$i++){
                        if(strlen($_POST['fight'.$i])>0){
                            $start=substr($_POST['fight'.$i],0,23);
                            $end=substr($_POST['fight'.$i],24,23);
                            $q=pg_query_params("DELETE FROM combatlog WHERE (date_time BETWEEN $1 AND $2 AND poi_id=$3)",array($start,$end,$poi));
                            $successes[]='Selected Fights deleted succesfully!';
                        }
                    }
                    break;
                case 'Move Selected Fights':
                    $found=False;
                    for($i=1;$i<=$_POST['fightNumber'];$i++){
                        if(strlen($_POST['fight'.$i])>0){
                            $start=substr($_POST['fight'.$i],0,23);
                            $end=substr($_POST['fight'.$i],24,23);
                            $poi=substr($_POST['fight'.$i], strpos($_POST['fight'.$i], 'poi')+3);
                            $found=True;
                            //echo $start.'=='.$end.'=='.$poi;
                            pg_query_params("UPDATE combatlog SET poi_id=$1 WHERE (poi_id=$2 AND date_time BETWEEN $3 AND $4)",array($_POST['poiMove'],$poi,$start,$end));
                        }
                    }
                    if($found){
                        $successes[]='Fight(s) moved successfully!';
                    }else{
                        $errors[]='No fights were selected!';
                    }

                    break;
            }
        }
        switch($_POST['button']){
            case 'Show Analysis from the Selected Fights':
                $view='Analysis';
                $logsData=array();
                for($i=1;$i<=$_POST['fightNumber'];$i++){
                    if(strlen($_POST['fight'.$i])>0){
                        $start=substr($_POST['fight'.$i],0, strpos($_POST['fight'.$i],'|'));
                        $end=substr($_POST['fight'.$i],strpos($_POST['fight'.$i],'|')+1);
                        $res= pg_query_params($conn,"SELECT * FROM combatlog WHERE date_time BETWEEN $1 AND $2 AND poi_id=$3",array($start,$end,$poi));
                        $data= pg_fetch_all($res);
                        $logsData= array_merge($logsData, $data);
                    }
                }
                if(count($logsData)>0){
                    $logsData= array_values(array_msort($logsData, array('date_time'=>SORT_ASC)));
                    $teams= getTeams($logsData, $userId);
                    $playerStats=array();$i=0;
                    foreach($teams['players'] as $p){
                        $playerStats[$i]['user_id']=getValueInField1ByField2('users', 'id', 'username', $p);
                        $playerStats[$i]['name']=$p;
                        if(in_array($p, $teams['alpha'])){
                            $playerStats[$i]['team']='alpha';
                        }elseif(in_array($p, $teams['bravo'])){
                            $playerStats[$i]['team']='bravo';
                        }
                        if(in_array($playerStats[$i]['user_id'], array_column($logsData, 'user_id'))){
                            $playerStats[$i]['dataSource']='own';
                        }else{
                            $playerStats[$i]['dataSource']='others';
                        }
                        $i++;
                    }
                    foreach ($playerStats as $k=>$ps) {
                        $totalDamageDelivered=0;$totalHealDelivered=0;$nrDPSSkills=0;$nrHPSSkills=0;$nrDPSCrits=0;$nrHPSCrits=0;
                        $totalDamageReceived=0;$totalHealReceived=0;
                        $skillByPlayer=array();//skills done by the player
                        $skillToPlayer=array();//skills done to the player
                        if ($ps['dataSource']=='own'){
                            foreach($logsData as $l){
                                if($l['user_id']==$ps['user_id']){ //select only his own combat lines
                                    if($l['skill_by']==$ps['name']){//initiated by him
                                        switch($l['skill_action']){
                                            case 'hit':
                                                $nrDPSSkills++;
                                                $totalDamageDelivered+=$l['skill_amount'];
                                                if($l['skill_critical']==1){
                                                    $nrDPSCrits++;
                                                }
                                                break;
                                            case 'healed':
                                                $nrHPSSkills++;
                                                $totalHealDelivered+=$l['skill_amount'];
                                                if($l['skill_critical']==1){
                                                    $nrHPSCrits++;
                                                }
                                                break;
                                        }
                                    }
                                    if($l['skill_target']==$ps['name']){//done to him
                                        switch($l['skill_action']){
                                            case 'hit':
                                                $totalDamageReceived+=$l['skill_amount'];
                                                break;
                                            case 'healed':
                                                $totalHealReceived+=$l['skill_amount'];
                                                break;
                                        }
                                    }
                                }
                            }
                        }elseif($ps['dataSource']=='others'){

                            foreach($logsData as $l){
                                if($l['skill_by']==$ps['name']){//initiated by him
                                    switch($l['skill_action']){
                                        case 'hit':
                                            $nrDPSSkills++;
                                            $totalDamageDelivered+=$l['skill_amount'];
                                            if($l['skill_critical']==1){
                                                $nrDPSCrits++;
                                            }
                                            break;
                                        case 'healed':
                                            $nrHPSSkills++;
                                            $totalHealDelivered+=$l['skill_amount'];
                                            if($l['skill_critical']==1){
                                                $nrHPSCrits++;
                                            }
                                            break;
                                    }
                                }
                                if($l['skill_target']==$ps['name']){//done to him
                                    switch($l['skill_action']){
                                        case 'hit':
                                            $totalDamageReceived+=$l['skill_amount'];
                                            break;
                                        case 'healed':
                                            $totalHealReceived+=$l['skill_amount'];
                                            break;
                                    }
                                }
                            }
                        }
                        $playerStats[$k]['totalDamageDelivered']=$totalDamageDelivered;
                        $playerStats[$k]['totalHealDelivered']=$totalHealDelivered;
                        $playerStats[$k]['nrDPSSkills']=$nrDPSSkills;
                        $playerStats[$k]['nrDPSCrits']=$nrDPSCrits;
                        $playerStats[$k]['nrHPSSkills']=$nrHPSSkills;
                        $playerStats[$k]['nrHPSCrits']=$nrHPSCrits;
                        $playerStats[$k]['totalDamageReceived']=$totalDamageReceived;
                        $playerStats[$k]['totalHealReceived']=$totalHealReceived;
                    }
                    
                    // Getting general skill data
                    $skillIds= array_values(array_unique(array_column($logsData,'skill_id')));
                    $skills=array();
                    $i=0;
                    foreach ($skillIds as $s) {
                        $hitCounter=0;$hitCritCounter=0;$hitMaxSkillValue=0;$hitTotal=0;
                        $healCounter=0;$healCritCounter=0;$healMaxSkillValue=0;$healTotal=0;
                        foreach($logsData as $l){
                            if($l['skill_id']==$s){
                                switch($l['skill_action']){
                                    case 'hit':
                                        $hitCounter++;
                                        if($l['skill_critical']==1){
                                            $hitCritCounter++;
                                        }
                                        if($maxSkillValue<$l['skill_amount']){
                                            $hitMaxSkillValue=$l['skill_amount'];
                                        }
                                        $hitTotal+=$l['skill_amount'];
                                        break;
                                    case 'healed':
                                        $healCounter++;
                                        if($l['skill_critical']==1){
                                            $healCritCounter++;
                                        }
                                        if($maxSkillValue<$l['skill_amount']){
                                            $healMaxSkillValue=$l['skill_amount'];
                                        }
                                        $healTotal+=$l['skill_amount'];
                                        break;
                                }
                            }
                        }
                        if($hitCounter>0){
                            $skills[$i]['name']= getValueInField1ByField2('kds_cclp_skills', 'name', 'id', $s);
                            $skills[$i]['skillAction']='hit';
                            $skills[$i]['nrOfUses']=$hitCounter;
                            $skills[$i]['nrOfCrits']=$hitCritCounter;
                            $skills[$i]['max']=$hitMaxSkillValue;
                            $skills[$i]['total']=$hitTotal;
                            $i++;
                        }
                        if($healCounter>0){
                            $skills[$i]['name']= getValueInField1ByField2('kds_cclp_skills', 'name', 'id', $s);
                            $skills[$i]['skillAction']='healed';
                            $skills[$i]['nrOfUses']=$healCounter;
                            $skills[$i]['nrOfCrits']=$healCritCounter;
                            $skills[$i]['max']=$healMaxSkillValue;
                            $skills[$i]['total']=$healTotal;
                            $i++;
                        }
                    }
                    $hitSkills=array();$healSkills=array();
                    foreach ($skills as $v) {
                        switch($v['skillAction']){
                            case 'hit':
                                $hitSkills[]=$v;
                                break;
                            case 'healed':
                                $healSkills[]=$v;
                                break;
                        }
                    }
                }else{
                    $errors[]='No Fights have been Selected! Use the checkbox in front of the Fight Number to specify which data should be considered.';
                    $view='day';
                }
               break;
        }

    }
}
?>
            <div class="row">
                <div class="col-sm-12 col-md-10">
                    <?php if(!$successes=='') {?><div class="alert alert-success"><?=display_successes($successes);?></div><?php }
                        includeHook($hooks,'body');?>
                    <?php if(!$errors=='') {?><div class="alert alert-danger"><?=display_errors($errors);?></div><?php } ?>

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
                    
                   
                    switch ($view){
                        case '':
                            $showStartDate='2019-06-01'; $showEndDate='2019-06-30';
                            //days
                            $res=pg_query($conn,"SELECT DISTINCT DATE(date_time) FROM combatlog ORDER BY DATE(date_time)");
                            $dates= pg_fetch_all($res);
                            //pois
                            if ($dates!=FALSE){
                                foreach($dates as $vd){
                                    $d= $vd['date'];
                                    $res= pg_query_params($conn,"SELECT DISTINCT p.id poi_id, p.name poi_name, m.name map_name, c.name campaign_name FROM combatlog cl,pois p, maps m, campaigns c WHERE DATE(cl.date_time)=$1 AND cl.poi_id=p.id AND p.map_id=m.id AND m.campaign_id=c.id",array($vd['date']));
                                    $pois= pg_fetch_all($res);
                                    if($pois!=FALSE){
                                        echo "<div class='col-sm-11 col-md-3'><center><h3>". date("D, d M Y", strtotime($d))."</h3></center><br>";
                                        $campaigns=array_unique(array_column($pois, 'campaign_name'));
                                        asort($campaigns, SORT_STRING | SORT_FLAG_CASE | SORT_NATURAL);
                                        foreach ($campaigns as $c){
                                            echo '<font size="4">'.$c.'</font>';
                                            $maps=array();
                                            foreach ($pois as $p) {
                                                if($p['campaign_name']==$c){
                                                    $maps[]=$p['map_name'];
                                                }
                                            }
                                            $maps= array_unique($maps);
                                            asort($maps, SORT_STRING | SORT_FLAG_CASE | SORT_NATURAL);
                                            foreach ($maps as $m) {
                                                echo '<br><font size="3" color="#737373">'.$m.'</font>: ';
                                                foreach($pois as $p){
                                                    if ($p['map_name']==$m AND $p['campaign_name']==$c){
                                                        echo "<font size='2'><a href='pg_combatlogview.php?view=day&day=$d&poi=".$p['poi_id']."'>".$p['poi_name'].'</a>; </font>';
                                                    }
                                                }
                                            }
                                            echo'<hr>';
                                        }
                                    }
                                    echo'</div>';
                                }
                            }
                            break;
                        case ('day'):
                            $resP=pg_select($conn, 'pois', array('id'=>$poi));
                            
                            ?>
                            <form enctype="multipart/form-data" name='deleteFight' action='<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>' method='post'>
                                <input type="hidden" name="csrf" value="<?=Token::generate();?>" />
                                <input type="hidden" name="view" value="day" />
                                <input type="hidden" name="day" value="<?php echo $day;?>" />
                                <input type="hidden" name="poi" value="<?php echo $poi;?>" />
                                <?php
                                $res= pg_query_params($conn,"SELECT * FROM combatlog WHERE DATE(date_time)=$1 AND poi_id=$2",array($day,$poi));
                                $dayData= pg_fetch_all($res);
                                $dayData= array_values(array_msort($dayData, array('date_time'=>SORT_ASC)));
                                echo '<div class="row"><h2>'.$resP[0]['name'].'</h2> '.date('D d M Y',strtotime($day)).'<br><font size=1>['.count($dayData).'] lines processed.</font></div>';
                                $fightsAndPlayers= getFightsAndPlayers($dayData);
                                if(count($fightsAndPlayers['fights'])==0){
                                    //echo "<script>location.href='pg_combatlogview.php';</script>";
                                    //die();
                                }
                                $p=1;
                                foreach($fightsAndPlayers['fights'] as $k=>$f){
                                    $fightData=array();
                                    foreach ($dayData as $v) {
                                        if($v['date_time']>=$f['start'] AND $v['date_time']<=$f['end']){
                                            $fightData[]=$v;
                                        }
                                    }
                                    //echo 'iaia'.pgDateTimeFormat($f['start']).'|'.pgDateTimeFormat($f['end']).'poi';
                                    ?>
                                    <div class="row"><h3>
                                             
                                            <input type="checkbox" name="fight<?php echo $k?>" value="<?php echo pgDateTimeFormat($f['start']).'|'.pgDateTimeFormat($f['end']).'poi'.$poi?>"> <?php echo "<b>Fight #$k: ".substr($f['start'],11,8).' - '.substr($f['end'],11,8).'</b></h3>';?>
                                        <font color="#808080"><?php echo count($fightData);?> lines submitted by:</font><font color="#b3b3b3">
                                        <?php 
                                        foreach($f['submitters'] as $s){
                                            echo getValueInField1ByField2('users', 'username', 'id', $s).'; ';
                                        }
                                        echo '</font>';
                                    
                                    $teams=getTeams($fightData,$userId);
                                    
                                    echo '<div class="row"><h3><font color="#008000">Team Alpha</font></h3>';
                                    $c=1;
                                    echo '<div class="col-sm-10 col-md-2">';
                                    foreach($teams['alpha'] as $v){
                                        if($isAdmin){
                                            ?>
                                            <input type="checkbox" name="player<?php echo $p?>" value="<?php echo $v.'StartAndEndCheck'.$f['start'].$f['end']?>"> 
                                            <?php
                                            }
                                            echo '<font size="2">'.$c.'. '.$v.'</font><br>';
                                        $p++;
                                        if($c/1==floor($c/1)){
                                            echo'</div><div class="col-sm-10 col-md-2">';
                                        }
                                        $c++;
                                    };
                                    echo'</div></div>';

                                    echo '<div class="row"><h3><font color="#004080">Team Bravo</font></h3>';
                                    $c=1;
                                    echo '<div class="col-sm-10 col-md-2">';
                                    foreach($teams['bravo'] as $v){
                                        if($isAdmin){
                                            ?>
                                            <input type="checkbox" name="player<?php echo $p?>" value="<?php echo $v.'StartAndEndCheck'.$f['start'].$f['end']?>"> 
                                            <?php
                                            } 
                                            echo '<font size="2">'.$c.'. '.$v.'</font><br>';
                                        $p++;
                                        if($c/1==round($c/1)){
                                            echo'</div><div class="col-sm-10 col-md-2">';
                                        }
                                        $c++;
                                        
                                    };
                                    echo'</div>';
                                    echo'</div>';
                                    /*
                                    echo '<div class="row"><h4><font color="#004088">Team Unknown</font></h3>';
                                    $c=1;
                                    echo '<div class="col-sm-10 col-md-2">';
                                    foreach($teams['unknown'] as $v){
                                        if($isAdmin){
                                            ?>
                                            <input type="checkbox" name="player<?php echo $p?>" value="<?php echo $v.'StartAndEndCheck'.$f['start'].$f['end']?>"> 
                                            <?php
                                            } 
                                            echo '<font size="2">'.$c.'. '.$v.'</font><br>';
                                        $p++;
                                        if($c/1==round($c/1)){
                                            echo'</div><div class="col-sm-10 col-md-2">';
                                        }
                                        $c++;
                                        
                                    };
                                    echo'</div>';
                                    echo'</div><hr>';
                                     * 
                                     */
                                }
                                ?>
                                <input type="hidden" name="playerNumber" value="<?php echo $p;?>" />
                                <input type="hidden" name="fightNumber" value="<?php echo count($fightsAndPlayers['fights']);?>" />
                                <p><input class='btn btn-primary' name='button' type='submit' value='Show Analysis from the Selected Fights' class='submit' />
                                <?php
                                if($isAdmin){
                                ?>
                                    <table class='table table-hover table-striped table-list-search display'>
                                        <tr>
                                            <td align='center'>
                                                <?php echo buildSelectFromArrayColumns('campaign', $campaign, '', $_SESSION['campaignsData'],'id','name','applyCampaignScript()')?>
                                            </td>
                                            <td align='center'>
                                                <?php echo buildSelectFromArrayColumns('map', $map, '', $_SESSION['mapsData'],'id','name','applyMapScript()')?>
                                            </td>
                                            <td align='center'>
                                                <?php echo buildSelectFromArrayColumns('poiMove', $poi, '', $_SESSION['poisData'],'id','name')?>
                                            </td>
                                            <td align="center">
                                                <input class='btn btn-primary' name='button' type='submit' value='Move Selected Fights' class='submit' onclick="return confirm('Are you sure you want to Move the Selected Fights to the New Location?')"/>
                                            </td>
                                        </tr>
                                    </table>
                                    <input class='btn btn-primary' name='button' type='submit' value='Delete Selected Fights' class='submit' onclick="return confirm('Are you sure you want to Delete the Selected Fights?')" />
                                    <input class='btn btn-primary' name='button' type='submit' value='Delete Selected Combatants' class='submit' onclick="return confirm('Are you sure you want to Delete the Selected Combatants?')"/>
                                    <?php
                                }?>
                                    </p>
                            </form>
                            <?php
                           break;
                        
                        case 'Analysis':
                            echo '<div class="row"><h2>'.pgGetValueInField1ByField2('pois', 'name', 'id', $poi).'</h2> '.date('D d M Y',strtotime($day)).'</div>';
                            ?>
                            <div class="row"><h3>
                                <?php echo "Fight: ".substr($logsData[0]['date_time'],11,8).' - '.substr($logsData[count($logsData)-1]['date_time'],11,8).'</h3> ['.count($logsData).' Combat Log Lines Used]';?><br>
                                <div class="col-sm-12 col-md-3">
                                    <table class='table table-hover table-striped table-list-search display'>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <font size="4" color="#3973ac">Top 10 Damage Dealers</font>
                                            </td>
                                        </tr>
                                         <?php
                                         $topShow= array_msort($playerStats, array('totalDamageDelivered'=>SORT_DESC));
                                         if(count($topShow)>=10){
                                             $showNr=10;
                                         }else{
                                             $showNr=count($topShow);
                                         }
                                         $i=1;
                                        if($showNr>0){
                                            foreach ($topShow as $d){
                                                echo "<tr><td>$i. ".nameFormat($d['name'],$d['dataSource']).'</td><td>'.$d['totalDamageDelivered'].'</td></tr>';
                                                $i++;
                                                if ($i>10){
                                                    break;
                                                }
                                            }
                                        }
                                         ?>
                                    </table>
                                </div>
                                <div class="col-sm-12 col-md-3">
                                    <table class='table table-hover table-striped table-list-search display'>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <font size="4" color="#3973ac">Top 10 Healers</font>
                                            </td>
                                        </tr>
                                         <?php
                                         $topShow= array_msort($playerStats, array('totalHealDelivered'=>SORT_DESC));
                                         if(count($topShow)>=10){
                                             $showNr=10;
                                         }else{
                                             $showNr=count($topShow);
                                         }
                                         $i=1;
                                        if($showNr>0){
                                            foreach ($topShow as $d){
                                                echo "<tr><td>$i. ".nameFormat($d['name'],$d['dataSource']).'</td><td>'.$d['totalHealDelivered'].'</td></tr>';
                                                $i++;
                                                if ($i>10){
                                                    break;
                                                }
                                            }
                                        }
                                         ?>
                                    </table>
                                </div>
                                <div class="col-sm-12 col-md-3">
                                    <table class='table table-hover table-striped table-list-search display'>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <font size="4" color="#3973ac">Top 10 Most Damaged</font>
                                            </td>
                                        </tr>
                                         <?php
                                         $topShow= array_msort($playerStats, array('totalDamageReceived'=>SORT_DESC));
                                         if(count($topShow)>=10){
                                             $showNr=10;
                                         }else{
                                             $showNr=count($topShow);
                                         }
                                         $i=1;
                                        if($showNr>0){
                                            foreach ($topShow as $d){
                                                echo "<tr><td>$i. ".nameFormat($d['name'],$d['dataSource']).'</td><td>'.$d['totalDamageReceived'].'</td></tr>';
                                                $i++;
                                                if ($i>10){
                                                    break;
                                                }
                                            }
                                        }
                                         ?>
                                    </table>
                                </div>
                                <div class="col-sm-12 col-md-3">
                                    <table class='table table-hover table-striped table-list-search display'>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <font size="4" color="#3973ac">Top 10 Most Healed</font>
                                            </td>
                                        </tr>
                                         <?php
                                         $topShow= array_msort($playerStats, array('totalHealReceived'=>SORT_DESC));
                                         if(count($topShow)>=10){
                                             $showNr=10;
                                         }else{
                                             $showNr=count($topShow);
                                         }
                                         $i=1;
                                        if($showNr>0){
                                            foreach ($topShow as $d){
                                                echo "<tr><td>$i. ".nameFormat($d['name'],$d['dataSource']).'</td><td>'.$d['totalHealReceived'].'</td></tr>';
                                                $i++;
                                                if ($i>10){
                                                    break;
                                                }
                                            }
                                         }
                                         ?>
                                    </table>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12 col-md-3">
                                    <table class='table table-hover table-striped table-list-search display'>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <font size="4" color="#3973ac">Most Used Damage Skills</font>
                                            </td>
                                        </tr>
                                         <?php
                                         $topShow= array_msort($hitSkills, array('nrOfUses'=>SORT_DESC));
                                         if(count($topShow)>=10){
                                             $showNr=10;
                                         }else{
                                             $showNr=count($topShow);
                                         }
                                         $i=1;
                                        if($showNr>0){
                                            foreach ($topShow as $d){
                                                echo "<tr><td>$i. ".$d['name'].'</td><td>'.$d['nrOfUses'].'</td></tr>';
                                                $i++;
                                                if ($i>10){
                                                    break;
                                                }
                                            }
                                        }
                                         ?>
                                    </table>
                                </div>
                                <div class="col-sm-12 col-md-3">
                                    <table class='table table-hover table-striped table-list-search display'>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <font size="4" color="#3973ac">Most Criticals</font>
                                            </td>
                                        </tr>
                                         <?php
                                         $topShow= array_msort($hitSkills, array('nrOfCrits'=>SORT_DESC));
                                         if(count($topShow)>=10){
                                             $showNr=10;
                                         }else{
                                             $showNr=count($topShow);
                                         }
                                         $i=1;
                                        if($showNr>0){
                                            foreach ($topShow as $d){
                                                echo "<tr><td>$i. ".$d['name'].'</td><td>'.$d['nrOfCrits'].'</td></tr>';
                                                $i++;
                                                if ($i>10){
                                                    break;
                                                }
                                            }
                                        }
                                         ?>
                                    </table>
                                </div>
                                <div class="col-sm-12 col-md-3">
                                    <table class='table table-hover table-striped table-list-search display'>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <font size="4" color="#3973ac">Maximum Damage Per Skill</font>
                                            </td>
                                        </tr>
                                         <?php
                                         $topShow= array_msort($hitSkills, array('max'=>SORT_DESC));
                                         if(count($topShow)>=10){
                                             $showNr=10;
                                         }else{
                                             $showNr=count($topShow);
                                         }
                                         $i=1;
                                        if($showNr>0){
                                            foreach ($topShow as $d){
                                                echo "<tr><td>$i. ".$d['name'].'</td><td>'.$d['max'].'</td></tr>';
                                                $i++;
                                                if ($i>10){
                                                    break;
                                                }
                                            }
                                        }
                                         ?>
                                    </table>
                                </div>
                                <div class="col-sm-12 col-md-3">
                                    <table class='table table-hover table-striped table-list-search display'>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <font size="4" color="#3973ac">Total Damage Per Skill</font>
                                            </td>
                                        </tr>
                                         <?php
                                         $topShow= array_msort($hitSkills, array('total'=>SORT_DESC));
                                         if(count($topShow)>=10){
                                             $showNr=10;
                                         }else{
                                             $showNr=count($topShow);
                                         }
                                         $i=1;
                                        if($showNr>0){
                                            foreach ($topShow as $d){
                                                echo "<tr><td>$i. ".$d['name'].'</td><td>'.$d['total'].'</td></tr>';
                                                $i++;
                                                if ($i>10){
                                                    break;
                                                }
                                            }
                                        }
                                         ?>
                                    </table>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-12 col-md-3">
                                    <table class='table table-hover table-striped table-list-search display'>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <font size="4" color="#3973ac">Most Used Healing Skills</font>
                                            </td>
                                        </tr>
                                        <?php
                                        $topShow= array_msort($healSkills, array('nrOfUses'=>SORT_DESC));
                                        if(count($topShow)>=10){
                                            $showNr=10;
                                        }else{
                                            $showNr=count($topShow);
                                        }
                                        $i=1;
                                        if($showNr>0){
                                           foreach ($topShow as $d){
                                               echo "<tr><td>$i. ".$d['name'].'</td><td>'.$d['nrOfUses'].'</td></tr>';
                                               $i++;
                                               if ($i>10){
                                                   break;
                                               }
                                           }
                                        }
                                        ?>
                                    </table>
                                </div>
                                <div class="col-sm-12 col-md-3">
                                    <table class='table table-hover table-striped table-list-search display'>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <font size="4" color="#3973ac">Most Criticals</font>
                                            </td>
                                        </tr>
                                        <?php
                                        $topShow= array_msort($healSkills, array('nrOfCrits'=>SORT_DESC));
                                        if(count($topShow)>=10){
                                            $showNr=10;
                                        }else{
                                            $showNr=count($topShow);
                                        }
                                        $i=1;
                                        if($showNr>0){
                                            foreach ($topShow as $d){
                                                echo "<tr><td>$i. ".$d['name'].'</td><td>'.$d['nrOfCrits'].'</td></tr>';
                                                $i++;
                                                if ($i>10){
                                                    break;
                                                }
                                            }
                                        }
                                         ?>
                                    </table>
                                </div>
                                <div class="col-sm-12 col-md-3">
                                    <table class='table table-hover table-striped table-list-search display'>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <font size="4" color="#3973ac">Maximum Heal Per Skill</font>
                                            </td>
                                        </tr>
                                         <?php
                                         $topShow= array_msort($healSkills, array('max'=>SORT_DESC));
                                         if(count($topShow)>=10){
                                             $showNr=10;
                                         }else{
                                             $showNr=count($topShow);
                                         }
                                         $i=1;
                                        if($showNr>0){
                                            foreach ($topShow as $d){
                                                echo "<tr><td>$i. ".$d['name'].'</td><td>'.$d['max'].'</td></tr>';
                                                $i++;
                                                if ($i>10){
                                                    break;
                                                }
                                            }
                                        }
                                         ?>
                                    </table>
                                </div>
                                <div class="col-sm-12 col-md-3">
                                    <table class='table table-hover table-striped table-list-search display'>
                                        <tr>
                                            <td colspan="2" align="center">
                                                <font size="4" color="#3973ac">Total Heal Per Skill</font>
                                            </td>
                                        </tr>
                                         <?php
                                         $topShow= array_msort($healSkills, array('total'=>SORT_DESC));
                                         if(count($topShow)>=10){
                                             $showNr=10;
                                         }else{
                                             $showNr=count($topShow);
                                         }
                                         $i=1;
                                        if($showNr>0){
                                            foreach ($topShow as $d){
                                                echo "<tr><td>$i. ".$d['name'].'</td><td>'.$d['total'].'</td></tr>';
                                                $i++;
                                                if ($i>10){
                                                    break;
                                                }
                                            }
                                        }
                                         ?>
                                    </table>
                                </div>
                            </div>
                            <div class="row">
                               <table class='table table-hover table-striped table-list-search display'>
                                   <tr>
                                       <td colspan="5" align="center">
                                           <font size="5" color="#3973ac">Team Alpha</font>
                                       </td>
                                   </tr>
                                   <tr>
                                       <td>Name</td>
                                       <td>Total Damage Delivered /#Hits /#Criticals</td>
                                       <td>Total Healing Delivered /#Hits /#Criticals</td>
                                       <td>Total Damage Received</td>
                                       <td>Total Healing Received</td>
                                   </tr>
                                    <?php
                                    $c=1;
                                    foreach ($playerStats as $ps){
                                        if($ps['team']=='alpha'){
                                            echo '<tr>';
                                                echo '<td><font size="2">'.$c.'. '.nameFormat($ps['name'],$ps['dataSource']).'</font></td>';
                                                echo '<td><font size="2" color="#ff6666">'.$ps['totalDamageDelivered'].' / '.$ps['nrDPSSkills'].' / '.$ps['nrDPSCrits'].'</font></td>';
                                                echo '<td><font size="2" color="#53c653">'.$ps['totalHealDelivered'].' / '.$ps['nrHPSSkills'].' / '.$ps['nrHPSCrits'].'</font></td>';
                                                echo '<td><font size="2" color="#b30000">'.$ps['totalDamageReceived'].'</font></td>';
                                                echo '<td><font size="2" color="#267326">'.$ps['totalHealReceived'].'</font></td>';
                                            echo '</tr>';
                                            $c++;
                                        }
                                    }
                                    ?>
                               </table>
                               <table class='table table-hover table-striped table-list-search display'>
                                   <tr>
                                       <td colspan="5" align="center">
                                           <font size="5" color="#008000">Team Bravo</font>
                                       </td>
                                   </tr>
                                   <tr>
                                       <td>Name</td>
                                       <td>Total Damage Delivered /#Hits /#Criticals</td>
                                       <td>Total Healing Delivered /#Hits /#Criticals</td>
                                       <td>Total Damage Received</td>
                                       <td>Total Healing Received</td>
                                   </tr>
                                    <?php
                                    $c=1;
                                    foreach ($playerStats as $ps){
                                        if($ps['team']=='bravo'){
                                            echo '<tr>';
                                                echo '<td><font size="2">'.$c.'. '.nameFormat($ps['name'],$ps['dataSource']).'</font></td>';
                                                echo '<td><font size="2" color="#ff6666">'.$ps['totalDamageDelivered'].' / '.$ps['nrDPSSkills'].' / '.$ps['nrDPSCrits'].'</font></td>';
                                                echo '<td><font size="2" color="#53c653">'.$ps['totalHealDelivered'].' / '.$ps['nrHPSSkills'].' / '.$ps['nrHPSCrits'].'</font></td>';
                                                echo '<td><font size="2" color="#b30000">'.$ps['totalDamageReceived'].'</font></td>';
                                                echo '<td><font size="2" color="#267326">'.$ps['totalHealReceived'].'</font></td>';
                                            echo '</tr>';
                                            $c++;
                                        }
                                        
                                    }
                                    ?>
                               </table>
                                <b>*</b><i>These combatants have Combat Logs submitted and they are far better represented then the rest.</i><hr>
                                <?php
                                    echo 'Share link: <a href="https://crowfallcombatlogparser.kdsguild.ro/combatlogparser/externalview.php?poi='.$poi.''
                                            . '&start='.$logsData[0]['date_time'].''
                                            . '&end='.$logsData[count($logsData)-1]['date_time'].'" target="_blank">'.
                                            'https://crowfallcombatlogparser.kdsguild.ro/combatlogparser/externalview.php?poi='.$poi.''
                                            . '&start='.$logsData[0]['date_time'].''
                                            . '&end='.$logsData[count($logsData)-1]['date_time'].'</a>';
                                ?>
                           </div>
                            
                                <?php
                                
                            break;
                    }
                    
                ?>
            </div>

<!-- footers -->

<?php require_once $abs_us_root . $us_url_root . 'users/includes/page_footer.php'; ?>

<!-- Place any per-page javascript here -->
<script type="text/javascript">
    var mapsData = <?php echo json_encode($_SESSION['mapsData']);?>;
    var poisData = <?php echo json_encode($_SESSION['poisData']);?>;
    var map = <?php echo json_encode($map);?>;
    var poi = <?php echo json_encode($poi);?>;
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
        result.forEach(function(nameF) {
            var opt = document.createElement('option');
            opt.innerHTML = nameF['name'];
            opt.value = nameF['id'];
            if(nameF['id']==map){
                opt.setAttribute('selected','selected');
            }
            fragment.appendChild(opt);
        });
        sel.appendChild(fragment);
        
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

        removeOptions(document.getElementById("poiMove"));
        var sel = document.getElementById('poiMove');
        var fragment = document.createDocumentFragment();
        result.forEach(function(nameF) {
            var opt = document.createElement('option');
            opt.innerHTML = nameF['name'];
            opt.value = nameF['id'];
            if(nameF['id']==poi){
                opt.setAttribute('selected','selected');
            }
            fragment.appendChild(opt);
        });
        sel.appendChild(fragment);
    }
    	
</script>

<?php pgClose($conn);require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
