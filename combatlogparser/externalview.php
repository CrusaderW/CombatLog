<?php

require_once '../users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/template/prep.php';
require_once 'functions.php';

if (!securePage($_SERVER['PHP_SELF'])){die();}
$hooks =  getMyHooks();
$errors=[];
$successes=[];
$userId = $user->data()->id;
clearMemory();

if(!isset($_SESSION['campaignsData'])){
    pgLoadCampaignMapPoiData();
}
if (is_numeric($_GET['poi'])){
    $poi=$_GET['poi'];
    
}
if (strlen($_GET['start'])>19 AND strlen($_GET['start'])<24){
    $start=$_GET['start'];
    $day= substr($start,0,10);
}
if (strlen($_GET['end'])>19 AND strlen($_GET['end'])<24){
    $end=$_GET['end'];
}
 
$map= pgGetValueInField1ByField2('pois', 'map_id', 'id', $poi);
$campaign= pgGetValueInField1ByField2('maps', 'campaign_id', 'id', $map);
$conn= pgConnect();

$logQ = pg_query_params("SELECT * FROM combatlog WHERE (date_time BETWEEN $1 AND $2 AND poi_id=$3)",array($start,$end,$poi));
$logsData= pg_fetch_all($logQ);


//get player stats
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
            $skills[$i]['name']= pgGetValueInField1ByField2('skills', 'name', 'id', $s);
            $skills[$i]['skillAction']='hit';
            $skills[$i]['nrOfUses']=$hitCounter;
            $skills[$i]['nrOfCrits']=$hitCritCounter;
            $skills[$i]['max']=$hitMaxSkillValue;
            $skills[$i]['total']=$hitTotal;
            $i++;
        }
        if($healCounter>0){
            $skills[$i]['name']= pgGetValueInField1ByField2('skills', 'name', 'id', $s);
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
    $errors[]='No Data Available!';
    die();
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
                             foreach ($topShow as $d){
                                 echo "<tr><td>$i. ".nameFormat($d['name'],$d['dataSource']).'</td><td>'.$d['totalDamageDelivered'].'</td></tr>';
                                 $i++;
                                 if ($i>10){
                                     break;
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
                             foreach ($topShow as $d){
                                 echo "<tr><td>$i. ".nameFormat($d['name'],$d['dataSource']).'</td><td>'.$d['totalHealDelivered'].'</td></tr>';
                                 $i++;
                                 if ($i>10){
                                     break;
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
                             foreach ($topShow as $d){
                                 echo "<tr><td>$i. ".nameFormat($d['name'],$d['dataSource']).'</td><td>'.$d['totalDamageReceived'].'</td></tr>';
                                 $i++;
                                 if ($i>10){
                                     break;
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
                             foreach ($topShow as $d){
                                 echo "<tr><td>$i. ".nameFormat($d['name'],$d['dataSource']).'</td><td>'.$d['totalHealReceived'].'</td></tr>';
                                 $i++;
                                 if ($i>10){
                                     break;
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
                             foreach ($topShow as $d){
                                 echo "<tr><td>$i. ".$d['name'].'</td><td>'.$d['nrOfUses'].'</td></tr>';
                                 $i++;
                                 if ($i>10){
                                     break;
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
                             foreach ($topShow as $d){
                                 echo "<tr><td>$i. ".$d['name'].'</td><td>'.$d['nrOfCrits'].'</td></tr>';
                                 $i++;
                                 if ($i>10){
                                     break;
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
                             foreach ($topShow as $d){
                                 echo "<tr><td>$i. ".$d['name'].'</td><td>'.$d['max'].'</td></tr>';
                                 $i++;
                                 if ($i>10){
                                     break;
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
                             foreach ($topShow as $d){
                                 echo "<tr><td>$i. ".$d['name'].'</td><td>'.$d['total'].'</td></tr>';
                                 $i++;
                                 if ($i>10){
                                     break;
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
                             foreach ($topShow as $d){
                                 echo "<tr><td>$i. ".$d['name'].'</td><td>'.$d['nrOfUses'].'</td></tr>';
                                 $i++;
                                 if ($i>10){
                                     break;
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
                             foreach ($topShow as $d){
                                 echo "<tr><td>$i. ".$d['name'].'</td><td>'.$d['nrOfCrits'].'</td></tr>';
                                 $i++;
                                 if ($i>10){
                                     break;
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
                             foreach ($topShow as $d){
                                 echo "<tr><td>$i. ".$d['name'].'</td><td>'.$d['max'].'</td></tr>';
                                 $i++;
                                 if ($i>10){
                                     break;
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
                             foreach ($topShow as $d){
                                 echo "<tr><td>$i. ".$d['name'].'</td><td>'.$d['total'].'</td></tr>';
                                 $i++;
                                 if ($i>10){
                                     break;
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
                                <b>*</b><i>These combatants have Combat Logs submitted and they are far better represented then the rest.</i>
                           </div>
                  </div>
            </div>

<!-- footers -->

<?php require_once $abs_us_root . $us_url_root . 'users/includes/page_footer.php'; ?>

<!-- Place any per-page javascript here -->
<script type="text/javascript">
    
</script>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
