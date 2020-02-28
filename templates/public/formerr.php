<?php
style('appointments', 'form');
?>

<div class="srgdev-ncfp-wrap">
    <div class="srgdev-appt-info-cont">
        <h1><?php p($l->t("An Error Has Occurred"));?></h1>
        <?php
        /** @noinspection PhpUndefinedVariableInspection */
        if(!empty($_['appt_e_rc']) && $_['appt_e_rc']==='1'){
            echo $l->t('Please %1$stry again%2$s and select a different date.', ['<a class="srgdev-appt-err-a" href="form">', '</a>']);
        }elseif (!empty($_['appt_e_ne'])) {
            echo '<p>';
            // TRANSLATORS This is folowed by an email address
            p($l->t("Please contact us directly at"));
            echo ' <a class="srgdev-appt-err-a" href="mailto:'.$_['appt_e_ne'].'">'.$_['appt_e_ne'].'</a></p>';
        }else{
            echo "<p>".p($l->t("Please try again later"))."</p>";
        }
        ?>
        <p><?php p($l->t("Thank you"));?></p>
    </div>
</div>
