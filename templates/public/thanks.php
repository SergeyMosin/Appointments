<?php /** @noinspection PhpUndefinedVariableInspection */
\OCP\Util::addScript('appointments', 'cncf');
style('appointments', 'form');
/** @noinspection PhpUndefinedVariableInspection */
echo $_['appt_inline_style'];
?>

<div class="srgdev-ncfp-wrap">
    <div class="srgdev-appt-info-cont">
        <h1><?php p($_['appt_c_head']) ?></h1>
        <p><?php p($_['appt_c_msg']) ?></p>
        <?php echo isset($_['appt_c_more'])?$_['appt_c_more']:'' ?>
        <?php if (isset($_['appt_action_url_hash'])) { ?>
            <form id="srgdev-appt-cncf_action_frm" method="post"><button id="srgdev-appt-cncf_action_btn"
                    data-appt-action-url-hash="<?php echo $_['appt_action_url_hash'] ?>"
                    data-cncf-del="<?php echo !empty($_['appt_cncf_delay']) ? $_['appt_cncf_delay'] : '0' ?>"
                    class="primary srgdev-ncfp-form-btn"
                    type="button"><span id="srgdev-ncfp_fbtn-text"><?php p($_['appt_action_url_text']) ?></span><span id="srgdev-ncfp_fbtn-spinner"></span></button></form>
        <?php } else { ?>
            <p><?php p($l->t("Thank you")); ?></p>
        <?php } ?>
    </div>
</div>
