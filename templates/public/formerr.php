<?php
style('appointments', 'form');
?>

<div class="srgdev-ncfp-wrap">
    <div class="srgdev-appt-info-cont">
        <h1>An Error Has Occurred</h1>
        <?php
        /** @noinspection PhpUndefinedVariableInspection */
        if($_['appt_e_rc']==='1'){
            echo 'Please <a class="srgdev-appt-err-a" href="form">try again</a> and select a different date.';
        }elseif (!empty($_['appt_e_ne'])) {
            echo '<p>Please contact us directly at <a class="srgdev-appt-err-a" href="mailto:'.$_['appt_e_ne'].'">'.$_['appt_e_ne'].'</a></p>';
        }else{
            echo "<p>Please try again later.</p>";
        }
        ?>
        <p>Thank you</p>
    </div>
</div>
