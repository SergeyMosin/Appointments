<?php
style('appointments', 'form');
?>

<div style="padding-top: 2em" class="srgdev-ncfp-wrap">
    <?php
    /** @noinspection PhpUndefinedVariableInspection */
    echo $_['appt_inline_style'];
    foreach ($_['links'] as $link){
      echo '<a href="'.$link['url'].'" target="_blank" class="appt-dir-lnk">'.
        '<h1 class="appt-dir-lnk_h1">'.$link['title'].'</h1>'.
          (!empty($link['subTitle'])?'<h2 class="appt-dir-lnk_h2">'.$link['subTitle'].'</h2>':'').
          (!empty($link['text'])?'<p class="appt-dir-lnk_p">'.$link['text'].'</p>':'').'</a>';
    }
    ?>
</div>
