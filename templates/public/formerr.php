<?php

script('appointments', 'cncf');
style('appointments', 'form');
/** @noinspection PhpUndefinedVariableInspection */
echo $_['appt_inline_style'];
$slotTaken = !empty($_['appt_e_rc']) && $_['appt_e_rc'] === '1';
?>

<div class="srgdev-ncfp-wrap">
    <div class="srgdev-appt-info-cont">
        <h1><?php
            p($slotTaken ? $l->t("This time was just booked") : $l->t("An error has occurred")); ?></h1>
        <?php
        if ($slotTaken) {
            echo '<p>';
            p($l->t("Someone booked it a moment ago. Please pick another time."));
            echo '</p><p><a class="srgdev-appt-err-a srgdev-appt-err-btn button primary" href="form">';
            p($l->t("Pick another time"));
            echo '</a></p>';
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
