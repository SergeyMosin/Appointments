<?php

script('appointments', 'cncf');
style('appointments', 'form');
/** @noinspection PhpUndefinedVariableInspection */
echo $_['appt_inline_style'];
?>

<div class="srgdev-ncfp-wrap">
    <div class="srgdev-appt-info-cont">
        <h1><?php
            p($l->t("An error has occurred")); ?></h1>
        <?php
        if (!empty($_['appt_e_rc']) && $_['appt_e_rc'] === '1') {
            echo $l->t('Please %1$stry again%2$s and select a different date.', ['<a class="srgdev-appt-err-a" href="form">', '</a>']);
        } elseif (!empty($_['appt_e_ne'])) {
            echo '<p>';
            // TRANSLATORS This is followed by an email address
            p($l->t("Please contact us directly at"));
            echo ' <a class="srgdev-appt-err-a" href="mailto:' . $_['appt_e_ne'] . '">' . $_['appt_e_ne'] . '</a></p>';
        } else {
            if (!empty($_['input_err'])) {
                echo '<div>' . p($_['input_err']) . '</div>';
            }
            echo "<p>" . p($l->t("Please try again later")) . "</p>";
        }
        ?>
        <p><?php
            p($l->t("Thank you")); ?></p>
    </div>
</div>
