<?php 
  //incluidas aqui y en index.php para persistencia de SESSION
  ini_set('display_errors', 0);
  ini_set('max_execution_time', 20000); //milisegundos

  include_once('lib/constants.php');
  include_once('lib/connections.php');
  include_once('lib/functions.php');

  $hoy =date('Ymd');  ##$ahora =date('Ymd_His');
  $hace3dias=date('Ymd' , strtotime('-3 day', strtotime($hoy)) ) ;
  $nh=1;  // horas atras que comienza el intervalo de 1hora
  $prefile="terminal_hist_";

  $ruta="HOME_medea";  
  $filename_csv=$prefile .$hoy .".csv";
  $cnx=conecta_BDmaestra ();
  if (readConfigDB($cnx, $array_cfg, $array_stat))  {
    $dir_out=$array_cfg['HOME_medea']."/" .$array_cfg['path_INF_files'];
    $filename_full=$dir_out. "/" .$filename_csv;

    //componer consulta
    $tabla_his="terminal_history TH";
    $tabla_plans="bss_plan BP";
    $tabla_motiv="terminal_history_motive TM";
    $tabla_slast="terminal_sla_status ST";
    $tablas="$tabla_his , $tabla_motiv , $tabla_plans";   #"$tabla_his , $tabla_slast , $tabla_motiv , $tabla_plans";

    $campos_idterm="isp_id, sit_id, fus_service_id";
    $campos_fecha ="insertion_date,last_update_date,reset_day";
    #$campos_estado="ST.literal as SLA_status,TM.literal as motive";
    $campos_cont="upload_data_real,total_data_real, upload_data_effective,total_data_effective, fz_upload_gap,fz_total_gap, gift_consumption" ;  # "upload_data_real_residual,total_data_real_residual, upload_data_effective_residual,total_data_effective_residual,"
    $campos="$campos_idterm , $campos_fecha , $campos_cont";
    $campos_ord="isp_id, insertion_date";

    $tipo_his=" TM.literal='HOURLY_BACKUP'";
    $joins=" TM.id=TH.motive  AND TH.bss_plan = BP.bss_plan_id";   ##" TM.id=TH.motive  AND TH.bss_plan = BP.bss_plan_id  AND TH.sla_status_id = ST.id";
    $intervalo=" insertion_date >= SYSDATE() - INTERVAL " .$nh . " HOUR AND insertion_date <  SYSDATE() - INTERVAL " .($nh-1) . " HOUR";
    
    $query="SELECT $campos FROM $tablas WHERE $tipo_his AND $joins AND ($intervalo) ORDER BY $campos_ord "; 
    $result= mysqli_query($cnx, $query);
    $nrowsel= $result ? mysqli_num_rows($result) : 0;
    $nrow_ok= 0;
##echo "-----------\n $query \n nfilas:$nrowsel\n";
    if ($result !== false && $nrowsel > 0) {
        $fp = fopen($filename_full, 'w'); 
        $header_ok=fwrite($fp, $campos. PHP_EOL);

echo "\n abierto fichero:$filename_full - header_ok: $header_ok \n ";
        //BUCLE FILAS
        for ($i=0; $i < $nrowsel; $i++)  {
            $row = mysqli_fetch_array($result, MYSQL_ASSOC);
            $filaok=fputcsv($fp, $row);
            if ($filaok)  {
                $nrow_ok++;
            }
        }
        fclose ($fp);
    }
echo "-----------\n nfilas OK:$nrow_ok\n";
  }
  else  {
    echo "Error de conexión a BD de MEDEA";
  }

?>
