<?php

\OCP\Util::addScript('appointments', 'cncf');
style('appointments', 'form');
/** @noinspection PhpUndefinedVariableInspection */
echo $_['appt_inline_style'];
?>

<div class="srgdev-ncfp-wrap">
    <div class="srgdev-appt-info-cont">
        <h1><?php
            p($_['appt_c_head']) ?>
        </h1>
        <p><?php
            p($_['appt_c_msg']) ?>
        </p>
        <?php
        echo isset($_['appt_c_more']) ? $_['appt_c_more'] : '';
        if (isset($_['appt_t1'])) { ?>
            <form id="srgdev-appt-cncf_action_frm" data-lbl="<?= $_['appt_cncf_delay'] ?>" method="post">
                <label class="srgdev-appt-cncf-label"><input name="tos" type="checkbox"/>Agree to TOS</label>
                <button id="srgdev-appt-cncf_action_btn"
                        data-t1="<?= $_['appt_t1'] ?>"
                        class="primary srgdev-ncfp-form-btn"
                        type="button"><span id="srgdev-ncfp_fbtn-text"><?php
                        p($_['appt_action_url_text']) ?></span><span id="srgdev-ncfp_fbtn-spinner"></span></button>
            </form>
            <?php
        } else { ?>
            <p><?php
                p($l->t("Thank you")); ?></p>
            <?php
        } ?>
    </div>
</div>
