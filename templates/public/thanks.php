<?php /** @noinspection PhpUndefinedVariableInspection */
script('appointments', 'cncf');
style('appointments', 'form');
?>

<div class="srgdev-ncfp-wrap">
    <div class="srgdev-appt-info-cont">
        <h1><?php p($_['appt_c_head']) ?></h1>
        <p><?php p($_['appt_c_msg']) ?></p>
        <?php if (isset($_['appt_action_url'])) { ?>
            <button id="srgdev-appt-cncf_action_btn"
                    data-appt-action-url="<?php echo $_['appt_action_url'] ?>"
                    class="primary srgdev-ncfp-form-btn"
                    type="button"><span><?php p($_['appt_action_url_text']) ?></span><span id="srgdev-ncfp_fbtn-spinner"></span></button>
        <?php } else { ?>
            <p><?php p($l->t("Thank you")); ?></p>
        <?php } ?>
    </div>
</div>
