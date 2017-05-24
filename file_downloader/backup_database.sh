#!/bin/sh

fecha=`date +%Y%m%d_%H%M `
database="medea"
user="medea"
pass="my3dEa_o2I6i"
home_medea=`mysql -u${user} -p${pass} -D${database} -s -N -e "select value from file_downloader_config where varname='HOME_medea' " `
subdir_bck=`mysql -u${user} -p${pass} -D${database} -s -N -e "select value from file_downloader_config where varname='path_BCK_files' " `

fich_dest="${database}_dbdump_${fecha}.sql"
ruta_dest="${home_medea}/${subdir_bck}"

mysqldump --single-transaction --user=${user} --password=${pass} ${database} > ${ruta_dest}/${fich_dest}
gzip ${ruta_dest}/${fich_dest}