<?php

/**
 * Created by PhpStorm.
 * User: jtriana
 * Date: 20/08/2015
 * Time: 12:16 PM
 *
 *
 * Generates random consumption files for a terminal with sit and isp 1.
 *
 *
 */
class GenCommand extends CConsoleCommand
{
    public function run($args){

        echo "Starting consumtion generation..." . PHP_EOL;

        while(true){

            $file_path = __DIR__ . "/../../reportsHUB/";
            $file_name = "ACC-PT5M-1-" . date('YmdHi') . ".csv";
            $file_full_path = $file_path . $file_name;

            $file_contents = "nbr_records,ICD_version,file_tag,time_stamp,time_interval,,,,,,,,,,,,,,,
1,1,ACC,2.01509E+11,5,,,,,,,,,,,,,,,
SIT-ID,time_stamp,ForwardedVolume,ReturnVolume,ForwardedPackets,ReturnPackets,AccumulatedForwardVolume,AccumulatedReturnVolume,HighPriorityForwardedVolume,HighPriorityReturnVolume,HighPriorityForwardedPackets,HighPriorityReturnPackets,RealTimeForwardedVolume,RealTimeReturnVolume,RealTimeForwardedPackets,RealTimeReturnPackets,TotalForwardedVolume,TotalReturnVolume,TotalForwardedPackets,TotalReturnPackets
int,yyyy-MM-dd hh:mm:ss,bytes,bytes,packets,packets,bytes,bytes,bytes,bytes,packets,packets,bytes,bytes,packets,packets,bytes,bytes,packets,packets";

            $terminal = Terminal::model()->findByPk(1);
            $upload = $terminal->upload_data_real;
            $download = $terminal->total_data_real - $upload;

            $temp_upload = mt_rand(1, 1000);
            $temp_download = mt_rand(1, 1000);

            $new_upload = $upload + $temp_upload;
            $new_download = $download + $temp_download;

            $file_contents .= PHP_EOL . "1,0," . $temp_download ."," . $temp_upload . ",0,0," . $new_download . "," . $new_upload . ",0,0,0,0,0,0,0,0," . $temp_download . "," . $temp_upload . ",0,0";

            if (file_put_contents($file_full_path, $file_contents)){
                $newEntry = new FileDownloaderData();
                $newEntry->full_path_file = $file_full_path;
                $newEntry->filename = $file_name;
                $newEntry->insertion_date = date("Y-m-d H:i:s");
                $newEntry->downloaded_date = $newEntry->insertion_date;
                $newEntry->status = 1;
                $newEntry->isp_id = 1;
                $newEntry->save();

                echo PHP_EOL . "=============" . PHP_EOL;
                echo "New File: " . $file_name . PHP_EOL;
                echo "Temp Up: " . $temp_upload . PHP_EOL;
                echo "Temp Down: " . $temp_download . PHP_EOL;
                echo "Total Up: " . $new_upload. PHP_EOL;
                echo "Total Down: " . $new_download. PHP_EOL;
                echo "Total: " . ($new_download + $new_upload).  PHP_EOL;

            }

            sleep(30);
        }
    }

}