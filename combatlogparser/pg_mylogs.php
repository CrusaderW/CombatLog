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
clearMemory();
$conn= pgConnect();
if(!isset($_SESSION['campaignsData'])){
    pgloadCampaignMapPoiData();
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

//Forms posted
if(!empty($_POST)) {
    $token = $_POST['csrf'];
    global $db;
    if(!Token::check($token)){
				include($abs_us_root.$us_url_root.'usersc/scripts/token_error.php');
    }else {
        switch($_POST['button']){
            case 'Delete Selected Combatants':
                for($i=1;$i<=$_POST['playerNumber'];$i++){
                    if(strlen($_POST['player'.$i])>0){
                        $name= substr($_POST['player'.$i],0, strpos($_POST['player'.$i], 'StartAndEndCheck'));
                        $start=substr($_POST['player'.$i],strpos($_POST['player'.$i], 'StartAndEndCheck')+16,23);
                        $end=substr($_POST['player'.$i],strpos($_POST['player'.$i], 'StartAndEndCheck')+39,23);
                        if(strpos($name, ']')){
                            $name=substr($name,0, strpos($name, ']')).'%';
                            pg_query_params("DELETE FROM combatlog WHERE (skill_by LIKE $1 OR skill_target LIKE $1)",array($name));
                        }else{
                            pg_query_params("DELETE FROM combatlog WHERE (date_time BETWEEN $1 AND $2 AND poi_id=$3)AND(skill_by=$4 OR skill_target=$4)",array($start,$end,$poi,$name));
                        }
                    }
                }
                $successes[]='Combatants deleted successfully!';
                break;
            case 'Delete Selected Fights':
                for($i=1;$i<=$_POST['fightNumber'];$i++){
                    if(strlen($_POST['fight'.$i])>0){
                        $start=substr($_POST['fight'.$i],0,23);
                        $end=substr($_POST['fight'.$i],23,23);
                        pg_query_params("DELETE FROM combatlog WHERE (user_id=$1 AND date_time BETWEEN $2 AND $3 AND poi_id=$4)", array($userId,$start,$end,$poi));
                    }
                }
                $successes[]='Fight(s) deleted successfully!';
                break;
            case 'Move Selected Fights':
                $found=False;
                for($i=1;$i<=$_POST['fightNumber'];$i++){
                    if(strlen($_POST['fight'.$i])>0){
                        $start=substr($_POST['fight'.$i],0,23);
                        $end=substr($_POST['fight'.$i],23,23);
                        $found=True;
                        pg_query_params("UPDATE combatlog SET poi_id=$1 WHERE (user_id=$2 AND date_time BETWEEN $3 AND $4)",array($_POST['poiMove'],$userId,$start,$end));
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
}
?>
            <div class="row">
                <div class="col-sm-12 col-md-10">
                    <h1><?echo 'My Combat Logs';?></h1>
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
                    $data = pg_select($conn,'combatlog', array('user_id'=>$userId));
                    $logsData= array_msort($data, array('date_time'=>SORT_ASC));
                    switch ($view){
                        case '':
                            $res= pg_query_params($conn,"SELECT DISTINCT DATE(date_time) FROM combatlog WHERE user_id=$1 ORDER BY DATE(date_time)", array($userId));
                            $dates= pg_fetch_all($res);
                            //pois
                            if ($dates!=FALSE){
                                foreach($dates as $vd){
                                    $d= $vd['date'];
                                    $res= pg_query_params($conn,"SELECT DISTINCT p.id poi_id, p.name poi_name, m.name map_name, c.name campaign_name FROM combatlog cl,pois p, maps m, campaigns c WHERE DATE(cl.date_time)=$1 AND cl.poi_id=p.id AND p.map_id=m.id AND m.campaign_id=c.id AND cl.user_id=$2",array($vd['date'],$userId));
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
                                                        echo "<font size='2'><a href='pg_mylogs.php?view=day&day=$d&poi=".$p['poi_id']."'>".$p['poi_name'].'</a>; </font>';
                                                    }
                                                }
                                            }
                                            echo'<hr>';
                                        }
                                        //lost POIS
                                        $res= pg_query_params($conn,"SELECT DISTINCT cl.poi_id poi_id FROM combatlog cl LEFT JOIN pois p ON cl.poi_id=p.id WHERE DATE(cl.date_time)=$1 AND p.id is Null",array($vd['date']));
                                        $lostPois= pg_fetch_all($res);
                                        
                                        if($lostPois){
                                            foreach($lostPois as $k=>$p){
                                                echo "<font size='2'><a href='pg_mylogs.php?view=day&day=$d&poi=".$p['poi_id']."'>".'Unknown Location #'.$k.'</a>; </font>';
                                                echo'<hr>';
                                            }
                                        }
                                        echo'</div>';
                                    }
                                }
                            }
                            
                            break;
                        case ('day'):
                            echo '<div class="row"><h2>';
                                $ps=pgGetValueInField1ByField2('pois', 'name', 'id', $poi);
                                if (strlen($ps)>0){
                                    echo $ps;
                                }else{
                                    echo 'Unknown Location';
                                }
                            echo '</h2> '.date('D d M Y',strtotime($day)).'</div>';
                            ?>
                            <form enctype="multipart/form-data" name='deleteFight' action='pg_mylogs.php' method='post'>
                                <input type="hidden" name="csrf" value="<?=Token::generate();?>" />
                                <input type="hidden" name="view" value="day" />
                                <input type="hidden" name="day" value="<?php echo $day;?>" />
                                <input type="hidden" name="poi" value="<?php echo $poi;?>" />
                                
                                <?php
                                $dayData=array();
                                foreach($logsData as $l){
                                    if (substr($l['date_time'], 0, 10)==$day AND $l['poi_id']==$poi){
                                        $dayData[]=$l;
                                    }
                                }
                                $dayData= array_values(array_msort($dayData, array('date_time'=>SORT_ASC)));
                                $fightsAndPlayers= getFightsAndPlayers($dayData);
                                if(count($fightsAndPlayers['fights'])==0){
                                    echo "<script>location.href='pg_mylogs.php';</script>";
                                    die();
                                }
                                $p=1;
                                foreach($fightsAndPlayers['fights'] as $k=>$f){
                                    ?>
                                    <div class="row"><h3>
                                        <input type="checkbox" name="fight<?php echo $k?>" value="<?php echo $f['start'].$f['end']?>"> <?php echo "Fight $k: ".substr($f['start'],11,8).' - '.substr($f['end'],11,8).'</h3>';?><br>
                                        <?php 
                                    $fightData=array();
                                    foreach ($dayData as $v) {
                                        if($v['date_time']>=$f['start'] AND $v['date_time']<=$f['end']){
                                            $fightData[]=$v;
                                        }
                                    }
                                    $teams=getTeams($fightData,$userId);
                                    echo '<div class="col-sm-10 col-md-3"><h3>Team Alpha</h3>';
                                    $c=1;
                                    foreach($teams['alpha'] as $v){
                                        ?>
                                        <input type="checkbox" name="player<?php echo $p?>" value="<?php echo $v.'StartAndEndCheck'.$f['start'].$f['end']?>"> <?php echo $c.'. '.$v?><br>
                                        <?php
                                        $p++;
                                        $c++;
                                    };
                                    echo'</div>';

                                    echo '<div class="col-sm-10 col-md-3"><h3>Team Bravo</h3>';
                                    $c=1;
                                    foreach($teams['bravo'] as $v){
                                        ?>
                                        <input type="checkbox" name="player<?php echo $p?>" value="<?php echo $v.'StartAndEndCheck'.$f['start'].$f['end']?>"> <?php echo $c.'. '.$v?><br>
                                        <?php
                                        $p++;
                                        $c++;
                                    };
                                    echo'</div>';
                                    echo'</div>';
                                }
                           ?>
                                <input type="hidden" name="playerNumber" value="<?php echo $p;?>" />
                                <input type="hidden" name="fightNumber" value="<?php echo count($fightsAndPlayers['fights']);?>" />
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
                                <p><input class='btn btn-primary' name='button' type='submit' value='Delete Selected Fights' class='submit' onclick="return confirm('Are you sure you want to Delete the Selected Fights?')"/>
                                <input class='btn btn-primary' name='button' type='submit' value='Delete Selected Combatants' class='submit' onclick="return confirm('Are you sure you want to Delete the Selected Combatants?')"/></p>
                            </form>
                        <?php
                            break;
                        }
                        
                        $poi
                    
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

<?php pgClose($conn); require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
