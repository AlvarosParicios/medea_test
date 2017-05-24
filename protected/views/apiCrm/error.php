<?php $this->pageTitle=Yii::app()->name . ' - Error'; ?>

<div class="error-page container" id="page">

    <h1 class="error-code">Error <?php echo $code?></h1>
    <h2><?php echo CHtml::encode($message)?></h2>



    <?php if ($code == '400' || $code == '404'){?>

        <hr/>

        <div class="error-causes">
            <h3>Possible causes:</h3>
            <ul class="error-list">
                <li><b>Baptist explanation: </b>There must be sin in your life. Everyone else opened it fine.</li>
                <li><b>Presbyterian explanation: </b>It's not God's will for you to open this link.</li>
                <li><b>Word of Faith explanation: </b> You lack the faith to open this link. Your negative words have prevented you from realizing this link's fulfillment.</li>
                <li><b>Charismatic explanation: </b> Thou art loosed! Be commanded to OPEN!</li>
                <li><b>Unitarian explanation: </b> All links are equal, so if this link doesn't work for you, feel free to experiment with other links that might bring you joy and fulfillment.</li>
                <li><b>Buddhist explanation: </b>   ...   </li>
                <li><b>Christian Science explanation: </b> There really is no link.</li>
                <li><b>Atheist explanation: </b> The only reason you think this link exists is because you needed to invent it.</li>
                <li><b>Freudian explanation: </b> And what did you feel when the link would not open?</li>
            </ul>
        </div>

    <?php } else if ($code == '500') {?>

        <hr/>

        <div class="error-causes">
            <h3>Oops, sorry, apparently something went wrong.</h3><br/>
            <h4>A team of highly trained monkeys has been dispatched to fix this issue. If they ask, show them this: </h4>
            <br/>
            <pre style="font-size: 18px;">

                1200 0000 1600 0000 1a00 0000 1e00 0000
                2200 0000 2600 0000 2a00 0000 2e00 0000
                3200 0000 3600 0000 3a00 0000 3e00 0000
                4200 0000 4600 0000 4a00 0000 4e00 0000
                5200 0000 5600 0000 5a00 0000 5e00 0000
                6200 0000 6600 0000 6a00 0000 6e00 0000
                7200 0000 7600 0000 7a00 0000 7e00 0000
                8200 0000 8600 0000 8a00 0000 8e00 0000
                9200 0000 9600 0000 9a00 0000 9e00 0000
                a200 0000 a600 0000 aa00 0000 ae00 0000
                b200 0000 b600 0000 ba00 0000 be00 0000
                c200 0000 c600 0000 ca00 0000 ce00 0000
                d200 0000 d600 0000 da00 0000 de00 0000
                e200 0000 e600 0000 ea00 0000 ee00 0000
                f200 0000 f600 0000 fa00 0000 fe00 0000
                0201 0000 0601 0000 0a01 0000 0e01 0000
                1201 0000 1601 0000 1a01 0000 1e01 0000
                2201 0000 2601 0000 2a01 0000 2e01 0000
                3201 0000 3601 0000 3a01 0000 3e01 0000
                4201 0000 4601 0000 4a01 0000 4e01 0000
                5201 0000 5601 0000 5a01 0000 5e01 0000
                6201 0000 6601 0000 6a01 0000 6e01 0000
                7201 0000 7601 0000 7a01 0000 7e01 0000
            </pre>
        </div>

    <?php } ?>


    <!--<div class="error">
        <pre>
            <code>
                <?php /*echo "File: " . CHtml::encode($file); */?>
                <br/>
                <?php /*echo "Line: " . CHtml::encode($line); */?>
                <br/>
                <?php /*echo "Msg: " . CHtml::encode($message); */?>
                <br/>
                <?php /*echo "Source: " . CHtml::encode($source); */?>
                <br/>
                <?php /*echo "Trace: " . CHtml::encode($trace); */?>
            </code>
        </pre>
    </div>-->
</div>

