<?php

/**
 * Created by PhpStorm.
 * User: jtriana
 * Date: 20/08/2015
 * Time: 12:16 PM
 */
class ArchiveCommand extends CConsoleCommand
{
    public $verbose_mode = false;

    public function actionTerminals($motive = TerminalHistoryMotive::HOURLY_BACKUP, $terminal_medea_id = null, $sit_id = null, $terminal_isp_id = null, $comment = null, $commentAll = false){
        Archiver::archiveTerminals($motive, $terminal_medea_id, $sit_id, $terminal_isp_id, $comment, $commentAll);
    }

    public function actionQuotaExtra($terminal_medea_id = null){
        Archiver::archiveQuotaExtra($terminal_medea_id);
    }
}