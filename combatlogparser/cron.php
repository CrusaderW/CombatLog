<?php
require_once 'functions.php';

$conn= pgConnect();
$cnst= pg_select($conn, 'const_values', array('id'=>1));
if($cnst[0]['cron_go']==1){
    pg_update($conn, 'const_values',array('is_updating'=>1) , array('id'=>1));
    pg_update($conn, 'const_values',array('cron_go'=>0) , array('id'=>1));
    
    
    
    
    pg_update($conn, 'const_values',array('is_updating'=>0) , array('id'=>1));
}


pgClose($conn);

