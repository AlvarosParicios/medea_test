<?php 
  //incluidas aqui y en index.php para persistencia de SESSION
  ini_set('display_errors', 0);
  ini_set('max_execution_time', 10000); //milisegundos

  include_once('lib/constants.php');
  include_once('lib/connections.php');
  include_once('lib/functions.php');

  $hoy =date('Ymd');
  $ayer=date('Ymd' , strtotime('-1 day', strtotime($hoy)) ) ;
  $hace3dias=date('Ymd' , strtotime('-3 day', strtotime($hoy)) ) ;
  $hace5dias=date('Ymd' , strtotime('-5 day', strtotime($hoy)) ) ;
  $prefile="/fich_hub_";

  $cnx=conecta_BDmaestra ();
  if (readConfigDB($cnx, $array_cfg, $array_stat))  {
    $dir_in=$array_cfg['HOME_medea']."/" .$array_cfg['path_IN_files']; //$dir_in="/home/medea/files/IN" ;
    $dir_bck=$array_cfg['HOME_medea']."/" .$array_cfg['path_BCK_files']; //$dir_bck="/home/medea/files/backup" ;
    $fich_in="ACC-PT*" . $hace3dias . "*.csv" ;
  //$dir_out="/home/medea/files/OUT" ;
  //$fich_out="ACC-PT*" . $hace5dias . "*.csv" ;
    $cmd1="cd " .$dir_in . " ; tar cvf " .$dir_bck . $prefile .$hace3dias . ".tar " .$fich_in . " ; gzip " .$dir_bck . $prefile .$hace3dias . ".tar ; rm " .$fich_in . ";" ;
    echo $cmd1 ."\n" ;

    shell_exec("$cmd1  2>&1 ");
    //shell_exec("$cmd2  2>&1 ");  //dir_out no se usa
  }
  else  {
    echo "Error de conexión a BD de MEDEA";
  }

?>
