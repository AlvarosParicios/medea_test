#!/bin/sh

php_exec=/usr/bin/php

dir_sh=`pwd`
database="medea"
user="medea"
pass="my3dEa_o2I6i"
home_medea=`mysql -u${user} -p${pass} -D${database} -s -N -e "select value from file_downloader_config where varname='HOME_medea' " `
#home_medea="/home/medea"

subdir="medea_informes"
if [ $# -eq 1 ]
then
    proceso="$1"
else
    proceso="${subdir}"
fi

dir_fd=${home_medea}/${subdir}/
fd_exec=`ps -ef | grep php | grep -c "${proceso}" `
if [ $fd_exec -gt 0 ]
then
   echo "SALIENDO: hay otra instancia de ${proceso} en ejecucion"
else
   cd ${dir_fd}/
   $php_exec  ./${proceso}.php
fi


