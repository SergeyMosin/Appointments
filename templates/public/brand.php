<?php /* Brand head assets for public pages of a branded page. Params: appt_brand, cspNonce. */ ?>
<?php if (!empty($_['appt_brand']['faviconUrl'])): ?>
<link rel="icon" href="<?php echo htmlspecialchars($_['appt_brand']['faviconUrl'], ENT_QUOTES); ?>">
<?php endif; ?>
<?php if (!empty($_['appt_brand']['logoUrl'])): ?>
<div class="srgdev-appt-brand-logo" style="text-align:center;padding:1em 0 .5em">
    <img src="<?php echo htmlspecialchars($_['appt_brand']['logoUrl'], ENT_QUOTES); ?>" alt="" style="max-height:60px;max-width:240px;object-fit:contain">
</div>
<?php endif; ?>
<?php if (!empty($_['appt_brand']['customCss'])): ?>
<style><?php echo $_['appt_brand']['customCss']; ?></style>
<?php endif; ?>
<?php if (!empty($_['appt_brand']['customJs'])): ?>
<script nonce="<?php p($_['cspNonce']); ?>"><?php echo $_['appt_brand']['customJs']; ?></script>
<?php endif; ?>
