<?php
script('appointments', 'form');
style('appointments', 'form');
?>

<div class="srgdev-ncfp-wrap">
    <?php
    $disabled = "";
    /** @noinspection PhpUndefinedVariableInspection */
    //    echo "AS: ".$_['appt_state'];
    if ($_['appt_state'] !== '2') {
        $disabled = 'disabled ';
    }
    /** @noinspection PhpUndefinedVariableInspection */
    print $_['appt_inline_style'] . '<form autocomplete="off" method="POST" class="srgdev-ncfp-form" ' . $disabled . ' id="srgdev-ncfp_frm" data-pps="' . $_['appt_pps'] . '" novalidate data-translations="' . $_['translations'] . '" data-zones="'. $_['zones_file'] .'">
        <h2 class="srgdev-ncfp-form-org">' . htmlentities($_['appt_org_name']) . '</h2>
        <div class="srgdev-ncfp-form-addr">' . $_['appt_org_addr'] . '</div>
        <h3 class="srgdev-ncfp-form-header">' .
        ($_['appt_state'] === '2'
            ? $_['appt_form_title']
            : $l->t('No Appointments Available')) . '</h3>'
    ?>
    <div class="srgdev-ncfp-form-main-cont" id="srgdev-ncfp-main-inputs">
        <label class="srgdev-ncfp-form-label"><?php p($l->t("Date & Time")) ?></label>
        <div id="srgdev-ncfp_sel_cont">
            <input <?php echo $disabled ?>readonly placeholder="<?php p($l->t("Select Date & Time")); ?>" name="dtstr" class="srgdev-ncfp-form-input" id="srgdev-ncfp_sel-dummy">
            <input type="hidden" name="adatetime" style="display:none;" id="srgdev-ncfp_sel-hidden"
            <?php
            // TRANSLATORS Button: meaning go back to previous section. Keep short if possible, abbreviations OK
            $back = $l->t('Back');
            // TRANSLATORS Button: meaning go to next section. Keep short if possible, abbreviations OK
            $next = $l->t('Next');

            echo ' ' . $disabled . 'data-state="' . $_['appt_state'] . '" data-info="' . $_['appt_sel_opts'] . '" data-hdr="' . htmlspecialchars($l->t('Select Date and Time'), ENT_QUOTES, 'UTF-8') . '" data-tr-back="' . htmlspecialchars($back, ENT_QUOTES, 'UTF-8') . '" data-tr-next="' . htmlspecialchars($next, ENT_QUOTES, 'UTF-8') . '" data-tr-not-available="' . htmlspecialchars($l->t('No Appointments Available'), ENT_QUOTES, 'UTF-8') . '">';
            ?>
        </div>
        <?php
        // TRANSLATORS This abbreviation for word "hour(s)" to be used as "2hr 15min" to indicate duration of 2 hours and 15 minutes
        $data_h = $l->t("hr");
        // TRANSLATORS This abbreviation for word "minutes(s)" to be used as "2hr 15min" to indicate duration of 2 hours and 15 minutes
        $data_m = $l->t("min");

        $data_h_m_str = 'data-tr-hr="' . htmlspecialchars($data_h, ENT_QUOTES) . '" data-tr-mn="' . htmlspecialchars($data_m, ENT_QUOTES) . '"';
        ?>
        <div id="srgdev-ncfp_dur-cont" <?php echo $data_h_m_str; ?> style="display: none">
            <label for="srgdev-ncfp_dur-sel" class="srgdev-ncfp-form-label"><?php p($l->t('Duration')); ?></label>
            <select name="appt_dur" required id="srgdev-ncfp_dur-sel" class="srgdev-ncfp-form-input srgdev-ncfp-form-select">
                <option value="0" class="srgdev-ncfp-form-option" selected>txt</option>
            </select>
        </div>
        <?php if (isset($_['appt_tlk_type']) && empty($disabled)) echo $_['appt_tlk_type']; ?>
        <label for="srgdev-ncfp_fname" class="srgdev-ncfp-form-label"><?php p($l->t("Full Name")) ?></label>
        <input name="name" <?php echo $disabled ?>placeholder="<?php p($l->t("Enter full name")); ?>" id="srgdev-ncfp_fname" class="srgdev-ncfp-form-input" type="text">
        <label for="srgdev-ncfp_femail" class="srgdev-ncfp-form-label"><?php p($l->t("Email")); ?></label>
        <input name="email" <?php echo $disabled ?>placeholder="<?php p($l->t("Enter email")); ?>" id="srgdev-ncfp_femail" class="srgdev-ncfp-form-input" type="email">
        <?php
        if ($_['appt_hide_phone'] === false) {
            echo '<label for="srgdev-ncfp_fphone" class="srgdev-ncfp-form-label">' . htmlspecialchars($l->t("Phone"), ENT_QUOTES, 'UTF-8') . '</label><input name="phone" ' . $disabled . ' placeholder="' . htmlspecialchars($l->t("Enter phone number"), ENT_QUOTES, 'UTF-8') . '" id="srgdev-ncfp_fphone" class="srgdev-ncfp-form-input" type="tel">';
        }

        if (empty($disabled)) echo $_['more_html'];

        if (!empty($_['appt_gdpr'])) {
            echo '<div class="srgdev-ncfp-chb-cont">';
            if ($_['appt_gdpr_no_chb'] === false) {
                // show check box
                echo '<input class="checkbox" type="checkbox" id="appt_gdpr_id"/>' . (strpos($_['appt_gdpr'], 'appt_gdpr_id') === false ? '<label for="appt_gdpr_id">' . $_['appt_gdpr'] . '</label>' : $_['appt_gdpr']);
            } else {
                // no check box
                echo $_['appt_gdpr'];
            }
            echo '</div>';
        }
        if (!empty($_['hCapKey'])) {
            echo '<div class="h-captcha" data-sitekey="' . $_['hCapKey'] . '"></div>';
        }
        ?>
        <button id="srgdev-ncfp_fbtn" <?php echo $disabled ?>class="primary srgdev-ncfp-form-btn" data-tr-ses-to="<?php echo htmlspecialchars($l->t('Session Timeout. Reload.'), ENT_QUOTES, 'UTF-8'); ?>"><span>
                <?php
                // TRANSLATORS This is the text for the "Book Now" button, on the appointment form.
                echo htmlspecialchars($l->t("Book Now"), ENT_QUOTES, 'UTF-8'); ?></span><span id="srgdev-ncfp_fbtn-spinner"></span>
        </button>
    </div>
    </form>
</div>
