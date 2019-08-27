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

//Forms posted
if(!empty($_POST)) {
    $token = $_POST['csrf'];
    global $db;
    if(!Token::check($token)){
				include($abs_us_root.$us_url_root.'usersc/scripts/token_error.php');
    }else {
            
    }   
}
?>
            <div class="row">
                <div class="col-sm-12 col-md-10">
                    <h1><?echo 'View';?></h1>
                    
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
                    $logQ = $db->query("SELECT * FROM kds_cclp_combat_logs",[]);
                    $data=$logQ->results();
                    $data= json_decode(json_encode($data),true);
                    $data= array_msort($data, array('date_time'=>SORT_ASC, 'skill_by'=>SORT_ASC));
                    switch ($_GET['view']){
                        case '':
                            $dates=array();
                            foreach($data as $l){
                                $dates[]=substr($l['date_time'],0,10);
                            }
                            $dates= array_unique($dates);

                            foreach($dates as $d){
                                $players=array();
                                foreach ($data as $l) {
                                    if(substr($l['date_time'],0,10)==$d){
                                        $players[]=$l['skill_by'];
                                        $players[]=$l['skill_target'];
                                    }
                                }
                                $players= array_unique($players);
                                asort($players);
                                echo "<a href='combatlogview.php?view=day&day=$d'>".$d.'</a> '.count($players) .' players <br>';

                                foreach($players as $p){
                                    echo $p.', ';
                                }

                                echo '<hr>';
                            }
                            break;
                        case ('day'):
                            $dayData=array();
                            foreach($data as $l){
                                if (substr($l['date_time'], 0, 10)==$_GET['day']){
                                    $dayData[]=$l;
                                }
                            }
                           
                            break;
                        }
                        
                        
                    
                ?>
            </div>

<!-- footers -->

<?php require_once $abs_us_root . $us_url_root . 'users/includes/page_footer.php'; ?>

<!-- Place any per-page javascript here -->
<script type="text/javascript">
		
</script>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
