<?php
//PostgreSQL
function pgInsert($table,$recArray){
    $pgDb= pgConnect();
    foreach ($recArray as $v) {
        pg_insert($pgDb, $table, $v);
    }
    pgClose($pgDb);
}

function pgConnect(){
    $dbconn = pg_connect("host=localhost dbname=kdsguild_crowfallcombatlogparser user=kdsguild_PGSQLUser password=PGsql987406");
    if($dbconn){
        return $dbconn;
    }else{
        die ('Connection to PostgreSQL failed!');
    }
}
function pgClose($conn){
    if(pg_connection_status($conn)){
        pg_close($conn);
    }
}
function pgDateTimeFormat($dt){
    switch (strlen($dt)){
        case 23:
            return $dt;
            break;
        case 22:
            return $dt.'0';
            break;
        case 21:
            return $dt.'00';
            break;
        case 19:
            return $dt.'.000';
            break;
    }
}
// end PostgreSQL

function debugArray($arr){
   echo '<hr>';
    if(is_array($arr)){
        foreach($arr as $v){
            if(is_array($v)){
                print_r($v);
            }else{
                echo $v.'/n';
            }
        }
    }else{
        echo 'var_dump: ';
        var_dump($arr);
    }
    echo '<hr>';
}

function nameFormat($name,$dataSource){
    if($dataSource=='own'){
        return "<b>$name*</b>";
    }else{
        return $name;
    }
}
function clearMemory(){
    unset($_SESSION['combatLogDBCompareData']);
    unset($_SESSION['combatLogData']);
    unset($_SESSION['fight']);
    unset($_SESSION['players']);
}
function getSuggestions(){
    global $db;
    $logQ = $db->query("SELECT * FROM kds_cclp_location_suggestions",[]);
    $data=$logQ->results();
    $data= json_decode(json_encode($data),true);
    $suggestionsData= array_values(array_msort($data, array('comabt_log_start'=>SORT_ASC, 'campaign_name'=>SORT_ASC, 'map_name'=>SORT_ASC, 'poi_name'=>SORT_ASC)));
    return $suggestionsData;
}
function pgGetSuggestions(){
    $conn= pgConnect();
    $logQ = pg_query("SELECT * FROM location_suggestions");
    $data= pg_fetch_all($logQ);
    if($data!=FALSE){
        $suggestionsData= array_values(array_msort($data, array('comabt_log_start'=>SORT_ASC, 'campaign_name'=>SORT_ASC, 'map_name'=>SORT_ASC, 'poi_name'=>SORT_ASC)));
    }else{
        $suggestionsData= array();
    }
        pgClose($conn);
    return $suggestionsData;
    
}
function getUserPermissions($userId){
    global $db;
    $logQ = $db->query("SELECT t2.name FROM user_permission_matches AS t1 INNER JOIN permissions AS t2 ON t1.permission_id=t2.id WHERE t1.user_id=?",[$userId]);
    $data=$logQ->results();
    $data= json_decode(json_encode($data),true);
    $ret=array();
    foreach ($data as $v) {
        $ret[]=$v[name];
    }// returns Array ( [0] => User [1] => Administrator )
    return $ret;
}
function loadCombatLogDBCompareData($userId,$date_time){
    global $db;
    $logQ = $db->query("SELECT * FROM kds_cclp_combat_logs WHERE user_id=?",[$userId]);
    $data=$logQ->results();
    $data= json_decode(json_encode($data),true);
    $ret=array();
    if(count($data)>0){
        foreach ($data as $key => $subArr) {
            unset($subArr['id']);
            unset($subArr['poi_id']);
            if(floor(abs(strtotime($subArr['date_time'])- strtotime($date_time))/(60*60*24))==0){
                $ret[] = $subArr;  
            }
        }
    }
    $_SESSION['combatLogDBCompareData']=$ret;
}
function pgLoadCombatLogDBCompareData($userId,$start,$end){
    $conn= pgConnect();
    $start=date('Y-m-d H:i:s', strtotime($start)-60);
    $end=date('Y-m-d H:i:s', strtotime($end)+60);
    $logQ = pg_query_params("SELECT * FROM combatlog WHERE user_id=$1 AND date_time BETWEEN $2::timestamp AND $3::timestamp",array($userId,$start,$end));
    $data= pg_fetch_all($logQ);
    $ret=array();
    if($data!=FALSE){
        foreach ($data as $key => $subArr) {
            switch (strlen($subArr['date_time'])){//in postgresmsql the timestamp is not saving the 'zero' in the end Y-m-d H:i:s.xx(ZERO)
                case 22:
                    $subArr['date_time'].='0';
                    break;
                case 21:
                    $subArr['date_time'].='00';
                    break;
                case 19:
                    $subArr['date_time'].='.000';
                    break;
            }
            unset($subArr['id']);
            unset($subArr['poi_id']);
            $ret[] = $subArr;  
        }
    }
    $_SESSION['combatLogDBCompareData']=$ret;
    pgClose($conn);
}
function loadCampaignMapPoiData(){
    global $db;
    $campaignQ = $db->query("SELECT * FROM kds_cclp_campaigns WHERE active=?",[1]);
    $campaignsData=$campaignQ->results();
    $_SESSION['campaignsData']= json_decode(json_encode($campaignsData),true);
    $_SESSION['campaignsData']= array_values(array_msort($_SESSION['campaignsData'],array('name'=>SORT_ASC)));

    $mapQ = $db->query("SELECT * FROM kds_cclp_maps",[]);
    $mapsData=$campaignQ->results();
    $_SESSION['mapsData']= json_decode(json_encode($mapsData),true);
    $_SESSION['mapsData']= array_values(array_msort($_SESSION['mapsData'],array('name'=>SORT_ASC)));

    $poisQ = $db->query("SELECT * FROM kds_cclp_pois",[]);
    $poisData=$campaignQ->results();
    $_SESSION['poisData']= json_decode(json_encode($poisData),true);
    $_SESSION['poisData']= array_values(array_msort($_SESSION['poisData'],array('name'=>SORT_ASC)));
}

function pgLoadCampaignMapPoiData(){
    $conn= pgConnect();
    $campaignQ = pg_query_params("SELECT * FROM campaigns WHERE active=$1",[1]);
    $_SESSION['campaignsData']= pg_fetch_all($campaignQ);
    if($_SESSION['campaignsData']!= FALSE){
        $_SESSION['campaignsData']= array_values(array_msort($_SESSION['campaignsData'],array('name'=>SORT_ASC)));
    }else{
         $_SESSION['campaignsData']=array();
    }
    $mapQ = pg_query_params("SELECT * FROM maps",[]);
    $_SESSION['mapsData']= pg_fetch_all($mapQ);
    if($_SESSION['mapsData']!= FALSE){
        $_SESSION['mapsData']= array_values(array_msort($_SESSION['mapsData'],array('name'=>SORT_ASC)));
    }else{
        $_SESSION['mapsData']=array();
    }
    $poisQ = pg_query_params("SELECT * FROM pois",[]);
    $_SESSION['poisData']= pg_fetch_all($poisQ);
    if($_SESSION['poisData']!= FALSE){
        $_SESSION['poisData']= array_values(array_msort($_SESSION['poisData'],array('name'=>SORT_ASC)));
    }else{
        $_SESSION['poisData']=array();
    }
    pgClose($conn);
}
function getTeams($fightData,$userId){
    global $db;
    $fightData= array_values(array_msort($fightData, array('user_id'=>SORT_ASC,'date_time'=>SORT_ASC)));
    if (in_array($idToRun, array_column($fightData, 'user_id'))){
            $idToRun=$userId;
    }else{
        $idToRun=$fightData[0]['user_id'];
    }
    
    $check=0;
    $team='Alpha';
    $teamAlpha=array();$teamBravo=array();$teamUnknown=array();$teamAlphaHealers=array();$teamBravoHealers=array();
//print_r($fightData);
    while (count($fightData)>0) {
        $check++;
       // echo '<br> Pass:'.$check.' lines:'.count($fightData).'<br> IdToRun: '.$idToRun;
        $userName= getValueInField1ByField2('users', 'username', 'id', $idToRun);
       // echo ': '.$userName;
        foreach ($fightData as $k=>$v) {
           switch($team){
               case 'Alpha':
                    $teamAlpha[]=$userName;
                   
                    //print_r(array_unique($teamAlpha));
                    //echo '<br>';
                    //print_r(array_unique($teamBravo));
                    //echo '<hr>';
                   
                    if($v['user_id']==$idToRun){
                        if($v['skill_by']==$userName){ //own actions
                            switch($v['skill_action']){
                                case 'hit':
                                    if($v['skill_by']!=$v['skill_target']){
                                        $teamBravo[]=$v['skill_target'];
                                    }
                                    break;
                                case 'healed':
                                    $teamAlpha[]=$v['skill_target'];
                                    break;
                            }
                        }elseif($v['skill_target']==$userName){//other's actions
                            switch($v['skill_action']){
                                case 'hit':
                                    if($v['skill_by']!=$v['skill_target']){
                                        $teamBravo[]=$v['skill_by'];
                                    }
                                    break;
                                case 'healed':
                                    $teamAlpha[]=$v['skill_by'];
                                    if (in_array(getValueInField1ByField2('users', 'id', 'username', $v['skill_by']), array_column($fightData, 'user_id'))){
                                        $teamAlphaHealers[]=$v['skill_by'];
                                    }
                                    break;
                            }
                        }
                        unset($fightData[$k]);
                    }
                    
                    break;

                case 'Bravo':
                    
                    $teamBravo[]=$userName;
                    if($v['user_id']==$idToRun){
                        if($v['skill_by']==$userName){ //own actions
                            switch($v['skill_action']){
                                case 'hit':
                                    if($v['skill_by']!=$v['skill_target']){
                                        $teamAlpha[]=$v['skill_target'];
                                    }
                                    break;
                                case 'healed':
                                    $teamBravo[]=$v['skill_target'];
                                    break;
                            }
                        }elseif($v['skill_target']==$userName){//other's actions
                            switch($v['skill_action']){
                                case 'hit':
                                    if($v['skill_by']!=$v['skill_target']){
                                        $teamAlpha[]=$v['skill_by'];
                                    }
                                    break;
                                case 'healed':
                                    $teamBravo[]=$v['skill_by'];
                                    if (in_array(getValueInField1ByField2('users', 'id', 'username', $v['skill_by']), array_column($fightData, 'user_id'))){
                                        $teamBravoHealers[]=$v['skill_by'];
                                    }
                                    break;
                            }
                        }
                        unset($fightData[$k]);
                    }
                    break;

                case 'Unknown':
                    $found=False;
                    if($v['skill_by']==$userName){ //own actions
                            switch($v['skill_action']){
                                case 'hit':
                                    if(in_array($v['skill_target'],$teamAlpha)){
                                        $teamBravo[]=$userName;
                                        $found=True;
                                    }
                                    break;
                                case 'healed':
                                    if(in_array($v['skill_target'],$teamAlpha)){
                                        $teamAlpha[]=$userName;
                                        $found=True;
                                    }
                                    break;
                            }
                        }elseif($v['skill_target']==$userName){//other's actions
                            switch($v['skill_action']){
                                case 'hit':
                                    if(in_array($v['skill_by'],$teamAlpha)){
                                        $teamBravo[]=$userName;
                                        $found=True;
                                    }
                                    break;
                                case 'healed':
                                    if(in_array($v['skill_by'],$teamAlpha)){
                                        $teamAlpha[]=$userName;
                                        $found=True;
                                    }
                                    break;
                            }
                        }
                        if(!$found){
                            $teamUnknown[]=$userName;
                        }
                        unset($fightData[$k]);
                    break;

            }
        }


        $teamAlphaHealers= array_values(array_unique($teamAlphaHealers));
        $teamBravoHealers= array_values(array_unique($teamBravoHealers));
        /*echo '<br>AlphaH: ';
        print_r($teamAlphaHealers);
        echo '<br>BravoH: ';
        print_r($teamBravoHealers);
        echo '<br>aLPHA: ';
        print_r(array_unique($teamAlpha));
        echo '<br>Bravo: ';
        print_r(array_unique($teamBravo));
         * 
         */
        
        
        if(count($teamAlphaHealers)>0){
            foreach($teamAlphaHealers as $k=>$a){
                $id= getValueInField1ByField2('users', 'id', 'username', $a);
                if (in_array($id, array_column($fightData, 'user_id'))){
                    $idToRun=$id;$team='Alpha';
                    unset($teamAlphaHealers[$k]);
                    break;
                }else{
                    unset($teamAlphaHealers[$k]);
                }
            }
        }elseif(count($teamBravoHealers)>0){
            foreach($teamBravoHealers as $k=>$a){
                $id= getValueInField1ByField2('users', 'id', 'username', $a);
                if (in_array($id, array_column($fightData, 'user_id'))){
                    $idToRun=$id;$team='Bravo';
                    unset($teamBravoHealers[$k]);
                    break;
                }else{
                    unset($teamBravoHealers[$k]);
                }
            }
        }else{
            if(count($fightData)>0){
                $fightData= array_values($fightData);
                $userName= getValueInField1ByField2('users', 'username', 'id', $fightData[0]['user_id']);
                $idToRun=$fightData[0]['user_id'];
                if(in_array($userName, $teamAlpha)){
                    $team='Alpha';
                }elseif(in_array($userName, $teamBravo)){
                    $team='Bravo';
                }else{
                    $team='Unknown';
                }
            }
        }
            
        
      // echo '<hr>';
    }
    //verify is a player is in both teams
    $alphaCountValues= array_count_values($teamAlpha);
    $bravoCountValues= array_count_values($teamBravo);
    foreach ($teamAlpha as $a) {
        if(in_array($a, $teamBravo)){
            if($alphaCountValues[$a]>$bravoCountValues[$a]){
                $teamBravo= array_diff($teamBravo, [$a]);
            }else{
                $teamAlpha= array_diff($teamAlpha, [$a]);
            }
        }
    }
    
    $teamAlpha= array_unique($teamAlpha);asort($teamAlpha,SORT_STRING | SORT_FLAG_CASE | SORT_NATURAL);
    $teamBravo= array_unique($teamBravo);asort($teamBravo,SORT_STRING | SORT_FLAG_CASE | SORT_NATURAL);
    $teamUnknown= array_unique($teamUnknown);asort($teamUnknown,SORT_STRING | SORT_FLAG_CASE | SORT_NATURAL);
    $players=array_merge($teamAlpha,$teamBravo,$teamUnknown);asort($players,SORT_STRING | SORT_FLAG_CASE | SORT_NATURAL);
    $ret=array('alpha'=>array_values($teamAlpha), 'bravo'=>array_values($teamBravo), 'unknown'=>array_values($teamUnknown),'players'=>$players);
    return $ret;
}
function getFightsAndPlayers($combatLogData){
    if (count($combatLogData)>0){
        $fightNr=1;
        $fights=array();
        $players=array();
        $submitters=array();
        $from_time = strtotime($combatLogData[0]['date_time']);
        $fights[$fightNr]['start']=$combatLogData[0]['date_time'];
        foreach ($combatLogData as $k=>$l) {
            
            $to_time = strtotime($l['date_time']);
            $diff_time=round(abs($to_time - $from_time) / 60,0);
            if ($diff_time<5){//a break bigger than 10 min means it's a different fight
                $from_time=$to_time;
                $submitters[]=$l['user_id'];
            }else{
                $players[$fightNr]= array_unique($players[$fightNr]);
                asort($players[$fightNr],SORT_STRING | SORT_FLAG_CASE | SORT_NATURAL);
                $fights[$fightNr]['end']=$combatLogData[$k-1]['date_time'];
                $submitters= array_unique($submitters);asort($submitters,SORT_STRING | SORT_FLAG_CASE | SORT_NATURAL);
                $fights[$fightNr]['submitters']=$submitters;
                $submitters=array();
                $submitters[]=$l['user_id'];
                $fightNr++;
                $fights[$fightNr]['start']=$combatLogData[$k]['date_time'];
                $from_time=$to_time;
                
            }
            $players[$fightNr][]=$l['skill_by'];$players[$fightNr][]=$l['skill_target'];
        }
        $players[$fightNr]= array_unique($players[$fightNr]);
        asort($players[$fightNr],SORT_STRING | SORT_FLAG_CASE | SORT_NATURAL);
        $players[$fightNr]= array_values($players[$fightNr]);//reset the keys
        $fights[$fightNr]['end']=$combatLogData[$k]['date_time'];
        $submitters= array_unique($submitters);asort($submitters,SORT_STRING | SORT_FLAG_CASE | SORT_NATURAL);
        $fights[$fightNr]['submitters']=$submitters;
        $ret= array('players'=>$players,'fights'=>$fights);
        return $ret;
    }
}
function fightsAndPlayers(){
    if (count($_SESSION['combatLogData'])>0){
        $fightNr=1;
        $_SESSION['fight']=array();
        $_SESSION['players']=array();
        $from_time = strtotime($_SESSION['combatLogData'][0]['date_time']);
        $_SESSION['fight'][$fightNr]['start']=$_SESSION['combatLogData'][0]['date_time'];
        $l_counter=0;
        foreach ($_SESSION['combatLogData'] as $k=>$l) {
            $to_time = strtotime($l['date_time']);
            $diff_time=round(abs($to_time - $from_time) / 60,0);
            if ($diff_time<10){
                $from_time=$to_time;
                $l_counter++;
            }else{
                $_SESSION['players'][$fightNr]= array_unique($_SESSION['players'][$fightNr]);
                asort($_SESSION['players'][$fightNr],SORT_STRING | SORT_FLAG_CASE | SORT_NATURAL);
                $_SESSION['fight'][$fightNr]['end']=$_SESSION['combatLogData'][$k-1]['date_time'];
                $_SESSION['fight'][$fightNr]['numberOfLines']=$l_counter;
                $l_counter=1;
                $fightNr++;
                $_SESSION['fight'][$fightNr]['start']=$_SESSION['combatLogData'][$k]['date_time'];
                $from_time=$to_time;
                
            }
            $_SESSION['players'][$fightNr][]=$l['skill_by'];$_SESSION['players'][$fightNr][]=$l['skill_target'];
        }
        $_SESSION['players'][$fightNr]= array_unique($_SESSION['players'][$fightNr]);
        asort($_SESSION['players'][$fightNr],SORT_STRING | SORT_FLAG_CASE | SORT_NATURAL);
        $_SESSION['players'][$fightNr]= array_values($_SESSION['players'][$fightNr]);//reset the keys
        $_SESSION['fight'][$fightNr]['end']=$_SESSION['combatLogData'][$k]['date_time'];
        $_SESSION['fight'][$fightNr]['numberOfLines']=$l_counter;
    }
}

function array_msort($array, $cols)
{
    if(count($array)>0 AND $array!=FALSE){
        $colarr = array();
        foreach ($cols as $col => $order) {
            $colarr[$col] = array();
            foreach ($array as $k => $row) { $colarr[$col]['_'.$k] = strtolower($row[$col]); }
        }
        $eval = 'array_multisort(';
        foreach ($cols as $col => $order) {
            $eval .= '$colarr[\''.$col.'\'],'.$order.',';
        }
        $eval = substr($eval,0,-1).');';
        eval($eval);
        $ret = array();
        foreach ($colarr as $col => $arr) {
            foreach ($arr as $k => $v) {
                $k = substr($k,1);
                if (!isset($ret[$k])) $ret[$k] = $array[$k];
                $ret[$k][$col] = $array[$k][$col];
            }
        }
        return $ret;
    }

}
function in_multiarray($elem, $array)
{
    while (current($array) !== false) {
        if (current($array) == $elem) {
            return true;
        } elseif (is_array(current($array))) {
            if (in_multiarray($elem, current($array))) {
                return true;
            }
        }
        next($array);
    }
    return false;
}
function pgCheckValueInField($table,$value,$field){
    $conn= pgConnect();
    $c = pg_query_params("SELECT id FROM $table WHERE $field=$1",array($value));
    $r= pg_fetch_all($c);
    pgClose($conn);
    if($r == FALSE){
      return 0;
    }else{
        return $r[0]['id'];
    }
}
function checkValueInField($table,$value,$field){
    global $db;
    $checkQ = $db->query("SELECT * FROM $table WHERE $field = ?",[$value]);
    $checkC = $checkQ->count();
    if($checkC < 1){
      return 0;
    }else{
        $results = $checkQ->first();
        $ret=$results->id;
        return $ret;
    }
}
function getValueInField1ByField2($table,$field1,$field2Name,$field2Value){
    global $db;
    $checkQ = $db->query("SELECT * FROM $table WHERE $field2Name = ?",[$field2Value]);
    $checkC = $checkQ->count();
    if($checkC < 1){
      return 0;
    }else{
        $results = $checkQ->results();
        
        $data= json_decode(json_encode($results),true);
        return $data[0][$field1];
    }
}
function pgGetValueInField1ByField2($table,$field1,$field2Name,$field2Value){
    $conn= pgConnect();
    $checkQ = pg_query_params("SELECT $field1 FROM $table WHERE $field2Name = $1",array($field2Value));
    if($checkQ ==FALSE){
      return 0;
    }else{
        $results = pg_fetch_all($checkQ);
        return $results[0][$field1];
    }
    pgClose($conn);
}
function array_unique_by_key($array,$field)
{
    $new = array(); 
    for ($i = 0; $i<=count($array)-1; $i++) { 
        $nou=true;
        for ($j = 0; $j<=count($new)-1; $j++) { 
            if ($new[$j][$field]==$array[$i][$field]) { 
            $nou=false;
            }
        }
        if($nou){
            $new[] = $array[$i]; 
        }
    }
    return $new;
}

    function navigationTable($page, $sortBy)
{
    $table="<table border='0' align='center' cellspacing='0'><tr>".
            "<td title='Sort Ascending'><a href='".$lnk."?".$sortBy."=Asc'><img src='models/site-templates/images/arrow_down.png' height='15' width='15'></a></td>".
            "<td title='Sort Descending'><a href='".$lnk."?".$sortBy."=Desc'><img src='models/site-templates/images/arrow_up.png' height='15' width='15'></a></td>".
            "</tr></table>";
    
    return $table;
}
function yn($a) {
    if($a==0){
        return('No');
    }else{
        return ('Yes');
    };
}
function msgBox($msg) {
    echo '<script type="text/javascript">alert("' . $msg . '")</script>';
}
function buildSelectFromArrayColumns($nameOfSelect, $selected, $select0_show, $sel_array, $column_data,$column_show, $onChangeDo='')
{
    $sel='<select id='.$nameOfSelect.' name='.$nameOfSelect.' style = "font-size:120%" onchange="'.$onChangeDo.'">';
    if($selected==0 AND strlen($select0_show)>0){
        $sel=$sel."<option value='0' selected>".$select0_show."</option>";
    }
    if(strlen($selected)!=0 AND strlen($select0_show)>0 AND $selected!=0){
        $sel=$sel."<option value='0'>".$select0_show."</option>";
    }
    if(count($sel_array)>0){
        for($i=0;$i<count($sel_array);$i++)
        {
            if($selected==$sel_array[$i][$column_data]){
                $sel=$sel."<option value='".$sel_array[$i][$column_data]."' selected>".$sel_array[$i][$column_show]."</option>";
            }else{
                $sel=$sel."<option value='".$sel_array[$i][$column_data]."'>".$sel_array[$i][$column_show]."</option>";
            }
        }
    }
    $sel=$sel.'</select>';
    return $sel;
}
function buildSelectFromArray($nameOfSelect, $selected, $select0_show, $sel_array, $onChangeDo='')
{
    $sel='<select id='.$nameOfSelect.' name='.$nameOfSelect.' style = "font-size:100%" onchange="'.$onChangeDo.'">';
    if($selected==0 AND strlen($select0_show)>0){
        $sel=$sel."<option value='0' selected>".$select0_show."</option>";
    }
    if(strlen($selected)!=0 AND strlen($select0_show)>0 AND $selected!=0){
        $sel=$sel."<option value='0'>".$select0_show."</option>";
    }
    if(count($sel_array)>0){
        for($i=0;$i<count($sel_array);$i++)
        {
            if($selected==$i){
                $sel=$sel."<option value='".$i."' selected>".$sel_array[$i]."</option>";
            }else{
                $sel=$sel."<option value='".$i."'>".$sel_array[$i]."</option>";
            }
        }
    }
    $sel=$sel.'</select>';
    return $sel;
}
function buildSelectFromArrayValue($nameOfSelect, $selected, $select0_show, $sel_array, $onChangeDo='')
{
    $sel='<select id='.$nameOfSelect.' name='.$nameOfSelect.' style = "font-size:100%" onchange="'.$onChangeDo.'">';
    if($selected==0 AND strlen($select0_show)>0){
        $sel=$sel."<option value='0' selected>".$select0_show."</option>";
    }
    if(strlen($selected)!=0 AND strlen($select0_show)>0 AND $selected!=0){
        $sel=$sel."<option value='0'>".$select0_show."</option>";
    }
    if(count($sel_array)>0){
        for($i=0;$i<count($sel_array);$i++)
        {
            if($selected==$sel_array[$i]){
                $sel=$sel."<option value='".$sel_array[$i]."' selected>".$sel_array[$i]."</option>";
            }else{
                $sel=$sel."<option value='".$sel_array[$i]."'>".$sel_array[$i]."</option>";
            }
        }
    }
    $sel=$sel.'</select>';
    return $sel;
}
function valueExists($db,$name, $tbl, $cond, $idExists=0)
{
	$stmt = $db->query("SELECT id
		FROM ".$tbl."
		WHERE
		((name = $name)".$cond.")
		");
	
	$num_returns = $stmt->_count;
        $stmt->results;
	while ($stmt->fetch()){
		$row[] = array('id' => $id);
	}
	$stmt->close();
	if ($num_returns > 0)
	{
            $found=true;
            if($row[0]['id']==$idExists){
                $found=false;
            }
            return $found;
	}
	else
	{
		return false;	
	}
}


function total($array)
{
    $sum=0;
    if(count($array)>0){
        foreach ($array as $v1) {
            $sum+=$v1['value'];
        }
    }
    return $sum;
}
function datePicker($dName, $mName, $yName, $day_default, $month_default, $year_default)
{
    global $months_array;
    
$dp="<select name='$dName' id='$dName'>";
for ($i=0; $i<=31; $i++){
    if(strlen($i)==1){
        $i_show='0'.$i;
    }else{
        $i_show=$i;
    }
    if ($i_show==$day_default){
        
        $dp.="<option value='$i_show' selected>$i_show</option>";
    }else{
        $dp.="<option value='$i_show'>$i_show</option>";
    }
}
$dp.='</select>';
$dp.="<select name='$mName' id='$mName'>";
$dp.="<option value='00'>00</option>";
for ($i=1; $i<=12; $i++){
    if(strlen($i)==1){
        $i_show='0'.$i;
    }else{
        $i_show=$i;
    }
    $m_show=$months_array[$i_show];
    if ($i_show==$month_default){
        $dp.="<option value='$i_show' selected>$m_show</option>";
    }else{
        $dp.="<option value='$i_show'>$m_show</option>";
    }
}
$dp.='</select>';
$dp.="<select name='$yName' id='$yName'>";
$dp.="<option value='0000'>0000</option>";
for ($i=1940; $i<=date('Y')+10; $i++){
    if ($i==$year_default){
        $dp.="<option value='$i' selected>$i</option>";
    }else{
        $dp.="<option value='$i'>$i</option>";
    }
}
$dp.='</select>';
return $dp;
}
$months_array=array(
    '01' => 'Jan',
    '02' => 'Feb',
    '03' => 'Mar',
    '04' => 'Apr',
    '05' => 'May',
    '06' => 'Jun',
    '07' => 'Jul',
    '08' => 'Aug',
    '09' => 'Sep',
    '10' => 'Oct',
    '11' => 'Nov',
    '12' => 'Dec'
);

?>

