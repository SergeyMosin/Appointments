<?php

style('appointments', 'form');
/** @noinspection PhpUndefinedVariableInspection */
echo $_['appt_inline_style'];

?>

<div class="srgdev-ncfp-wrap">
    <div class="srgdev-appt-info-cont">
        <div>
            We apologize for the inconvenience, as this page is temporarily unavailable.
        </div>
        <div>
            For immediate inquiries or booking requests, please contact us directly at <?php
            echo p($_['appt_email']) ?>
            <?php
            echo p($_['appt_phone'] ? ' or call ' . $_['appt_phone'] : '') ?>
        </div>
    </div>
</div>
