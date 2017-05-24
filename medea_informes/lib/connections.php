<?php
include('constants.php');
error_reporting(E_ALL ^ (E_NOTICE | E_WARNING) );

function conecta_BDmaestra ()
{
    global $BDmaestra;
    global $usuBDmaestra;
    global $passBDmaestra;
    global $esquema_MEDEA;

    $cnxx=mysqli_connect ("localhost", $usuBDmaestra, $passBDmaestra, $BDmaestra);
    if ($cnxx)  {
        //mysqli_select_db ($cnxx, $BDmaestra);
        mysqli_query($cnxx, "SET NAMES 'UTF8'");
    }
    else  {
        printf("<br><br>Fallo en la conexion a la Base de Datos interna:<br><br>Informacion adicional:<br>%s<br>",$BDmaestra, mysqli_connect_error() );
    }
    return $cnxx;
}


function conecta_BDgenerica ($servidor, $dbase, $usuario, $passwd, & $error=null )
{
   /*$servidor se indica en formato maquina[:puerto] (se añade puerto si es distinto del estandar 3306) */
    try  {
        $cnxx=mysqli_connect ($servidor, $usuario, $passwd, $dbase);
        if (!$cnxx) {
            $error=sprintf("<br><br>Fallo en la conexion al servidor '%s':<br><br>Informacion adicional:<br>%s<br>",$servidor, mysqli_connect_error());
        }
        else  {  //intenta conectar a base de datos (si no se paso vacia)
            if ($dbase !== null )  { 
                mysqli_select_db ($cnxx,$dbase);
                mysqli_query($cnxx,"SET NAMES 'UTF8'");
            }
        }
        return $cnxx;
    }
    catch  (Exception $e) {
        echo "<br> conecta_BDgenerica-Excepcion: ",  $e->getMessage(), "<\br>";
    }
}


function readConfigDB($DBcnx, & $array_cfg, & $array_status)
{    //CONSTANTES IMPORTADAS
    global $tabla_FDcfg, $tabla_FDest;

    $array_cfg=array();
    $array_status=array();
    $nombrevar="";

    $query = "SELECT varname,value FROM " .$tabla_FDcfg ;
    $result= mysqli_query($DBcnx, $query);
    $nrowcfg= $result ? mysqli_num_rows($result) : 0;
    for ($i=0; $i < $nrowcfg; $i++)  {
        $row = mysqli_fetch_row($result);
        $array_cfg[$row[0]]= $row[1];
    }  //end for

    $query = "SELECT id,literal FROM " .$tabla_FDest ;
    $result= mysqli_query($DBcnx, $query);
    $nrowstat= $result ? mysqli_num_rows($result) : 0;
    for ($i=0; $i < $nrowstat; $i++)  {
        $row = mysqli_fetch_row($result);
        $array_status[$row[1]]= $row[0];
    }  //end for
    mysqli_free_result($result);

    return $nrowcfg + $nrowstat;
}

?>
