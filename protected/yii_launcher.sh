#!/bin/sh

#php_exec=/usr/bin/php

#proceso por defecto a lanzar / monitorizar que no hay ya otro en ejecucion
#si se indica otro por parametro, usar el que se pase
proceso_defecto="execute"
if [ $# -eq 1 ]
then
    proceso="$1"
else
    proceso="${proceso_defecto}"
fi


dir_sh=`pwd`
database="medea"
user="medea"
pass="my3dEa_o2I6i"
home_medea=`mysql -u${user} -p${pass} -D${database} -s -N -e "select value from file_downloader_config where varname='HOME_medea' " `
#home_medea="/home/medea"

subdir="protected"
dir_yii=${home_medea}/${subdir}/

yii_exec=`ps -ef | grep -v grep | grep -c "yiic ${proceso}" `
if [ $yii_exec -gt 0 ]
then
   echo "SALIENDO: hay otra instancia de ${proceso} en ejecucion"
else
   cd ${dir_yii}/
   ./yiic ${proceso}
fi

