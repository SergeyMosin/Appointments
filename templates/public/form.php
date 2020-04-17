<?php
script('appointments', 'form');
style('appointments', 'form');
?>

<div class="srgdev-ncfp-wrap">
    <?php
    $disabled="";
    /** @noinspection PhpUndefinedVariableInspection */
//    echo "AS: ".$_['appt_state'];
    if($_['appt_state']!=='2'){
        $disabled='disabled ';
    }
    print '<form autocomplete="off" method="POST" class="srgdev-ncfp-form" '.$disabled.' id="srgdev-ncfp_frm" data-pps="'.$_['appt_pps'].'">
        <h2 class="srgdev-ncfp-form-org">'.htmlentities($_['appt_org_name']).'</h2>
        <div class="srgdev-ncfp-form-addr">'.$_['appt_org_addr'].'</div>
        <h3 class="srgdev-ncfp-form-header">'.
            ($_['appt_state']==='2'
                ?$_['appt_form_title']
                :$l->t('No appointments available')).'</h3>'
    ?>  <div class="srgdev-ncfp-form-main-cont">
        <label class="srgdev-ncfp-form-label"><?php p($l->t("Date & Time:")) ?></label>
        <div id="srgdev-ncfp_sel_cont">
            <input <?php echo $disabled ?>readonly placeholder="<?php p($l->t("Select Date & Time")); ?>" name="dtstr" class="srgdev-ncfp-form-input" id="srgdev-ncfp_sel-dummy">
            <select name="adatetime" style="display:none;" id="srgdev-ncfp_sel-hidden"
                <?php /** @noinspection PhpUndefinedVariableInspection */
                print_unescaped(' data-state="'.$_['appt_state'].'">'.$_['appt_sel_opts']); ?>
            </select>
        </div>
        <label class="srgdev-ncfp-form-label"><?php p($l->t("Name:"))?></label>
        <input name="name" <?php echo $disabled ?>placeholder="<?php p($l->t("Enter name")); ?>" id="srgdev-ncfp_fname" class="srgdev-ncfp-form-input" type="text">
        <label class="srgdev-ncfp-form-label"><?php p($l->t("Email:"));?></label>
        <input name="email" <?php echo $disabled ?>placeholder="<?php p($l->t("Enter email")); ?>" id="srgdev-ncfp_femail" class="srgdev-ncfp-form-input" type="email">
        <label class="srgdev-ncfp-form-label"><?php p($l->t("Phone:")); ?></label>
        <input name="phone" <?php echo $disabled ?>placeholder="<?php p($l->t("Enter phone number")); ?>" id="srgdev-ncfp_fphone" class="srgdev-ncfp-form-input" type="tel"><?php
        if(!empty($_['appt_gdpr'])){
            echo '<div class="srgdev-ncfp-chb-cont"><input class="checkbox" type="checkbox" id="appt_gdpr_id"/>'.(strpos($_['appt_gdpr'],'appt_gdpr_id')===false?'<label for="appt_gdpr_id">'.$_['appt_gdpr'].'</label>':$_['appt_gdpr']).'</div>';
        }
        ?>
        <button id="srgdev-ncfp_fbtn" <?php echo $disabled ?>class="primary srgdev-ncfp-form-btn"><?php
            // TRANSLATORS This is the text for the "Book Now" button, on the appointment form.
            p($l->t("Book now"));
            ?></button>
    </div>
    </form>

</div>
