<?php
/** @noinspection PhpUndefinedVariableInspection */
$apiBase = \OC::$server->getURLGenerator()->linkToRoute('appointments.admin.get_brands');
$saveUrl = \OC::$server->getURLGenerator()->linkToRoute('appointments.admin.save_brand');
$deleteUrl = \OC::$server->getURLGenerator()->linkToRoute('appointments.admin.delete_brand');
?>
<div id="appt-branding-admin" style="max-width:900px;padding:1em 0">
    <h2 style="font-size:1.3em;font-weight:600;margin-bottom:1em"><?php p($l->t('Appointments — Brand Settings')); ?></h2>

    <div id="appt-brand-list"></div>

    <button id="appt-add-brand-btn" class="button" style="margin-top:1em">
        <?php p($l->t('Add Brand')); ?>
    </button>

    <template id="appt-brand-tpl">
        <div class="appt-brand-card" style="border:1px solid var(--color-border);border-radius:8px;padding:1.2em;margin-bottom:1.2em;background:var(--color-main-background)">
            <input type="hidden" class="appt-brand-id">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.8em 1.2em;margin-bottom:.8em">
                <label style="display:flex;flex-direction:column;gap:.25em;font-size:.9em">
                    <?php p($l->t('Brand Name')); ?> *
                    <input type="text" class="appt-f-name" placeholder="<?php p($l->t('e.g. Acme Corp')); ?>"
                           style="width:100%;padding:.4em .6em;border:1px solid var(--color-border);border-radius:4px;background:var(--color-main-background);color:var(--color-main-text)">
                </label>
                <label style="display:flex;flex-direction:column;gap:.25em;font-size:.9em">
                    <?php p($l->t('Primary Color')); ?>
                    <input type="color" class="appt-f-color"
                           style="width:100%;height:2.2em;padding:.1em .2em;border:1px solid var(--color-border);border-radius:4px;background:var(--color-main-background);cursor:pointer">
                </label>
                <label style="display:flex;flex-direction:column;gap:.25em;font-size:.9em">
                    <?php p($l->t('Logo URL')); ?>
                    <input type="url" class="appt-f-logo" placeholder="https://..."
                           style="width:100%;padding:.4em .6em;border:1px solid var(--color-border);border-radius:4px;background:var(--color-main-background);color:var(--color-main-text)">
                </label>
                <label style="display:flex;flex-direction:column;gap:.25em;font-size:.9em">
                    <?php p($l->t('Favicon URL')); ?>
                    <input type="url" class="appt-f-favicon" placeholder="https://..."
                           style="width:100%;padding:.4em .6em;border:1px solid var(--color-border);border-radius:4px;background:var(--color-main-background);color:var(--color-main-text)">
                </label>
                <label style="display:flex;flex-direction:column;gap:.25em;font-size:.9em;grid-column:1/-1">
                    <?php p($l->t('Background Image URL')); ?>
                    <input type="url" class="appt-f-bg" placeholder="https://..."
                           style="width:100%;padding:.4em .6em;border:1px solid var(--color-border);border-radius:4px;background:var(--color-main-background);color:var(--color-main-text)">
                </label>
                <label style="display:flex;flex-direction:column;gap:.25em;font-size:.9em;grid-column:1/-1">
                    <?php p($l->t('Custom Email HTML Template')); ?>
                    <span style="font-size:.85em;color:var(--color-text-maxcontrast)">
                        <?php p($l->t('Available placeholders: {attendee_name}, {org_name}, {date_time}, {cancel_url}, {confirm_url}')); ?>
                    </span>
                    <textarea class="appt-f-email" rows="8" placeholder="<p>Dear {attendee_name},...</p>"
                              style="width:100%;padding:.4em .6em;border:1px solid var(--color-border);border-radius:4px;font-family:monospace;font-size:.85em;background:var(--color-main-background);color:var(--color-main-text);resize:vertical"></textarea>
                </label>
            </div>
            <div style="display:flex;gap:.6em">
                <button class="appt-save-btn button primary" style="min-width:80px"><?php p($l->t('Save')); ?></button>
                <button class="appt-delete-btn button" style="color:var(--color-error)"><?php p($l->t('Delete')); ?></button>
                <span class="appt-brand-status" style="align-self:center;font-size:.85em;color:var(--color-success)"></span>
            </div>
        </div>
    </template>
</div>

<style>
#appt-branding-admin .button{cursor:pointer;padding:.4em 1em;border-radius:4px;border:1px solid var(--color-border)}
#appt-branding-admin .button.primary{background:var(--color-primary);color:var(--color-primary-text);border-color:var(--color-primary)}
</style>

<script>
(function () {
    const listEl = document.getElementById('appt-brand-list')
    const tpl = document.getElementById('appt-brand-tpl')
    const addBtn = document.getElementById('appt-add-brand-btn')
    const apiBase = <?php echo json_encode($apiBase); ?>
    const saveUrl = <?php echo json_encode($saveUrl); ?>
    const deleteUrl = <?php echo json_encode($deleteUrl); ?>

    function renderCard(brand) {
        const node = tpl.content.cloneNode(true)
        const card = node.querySelector('.appt-brand-card')
        card.querySelector('.appt-brand-id').value = brand.id || ''
        card.querySelector('.appt-f-name').value = brand.name || ''
        card.querySelector('.appt-f-color').value = brand.primaryColor || '#0082c9'
        card.querySelector('.appt-f-logo').value = brand.logoUrl || ''
        card.querySelector('.appt-f-favicon').value = brand.faviconUrl || ''
        card.querySelector('.appt-f-bg').value = brand.bgImage || ''
        card.querySelector('.appt-f-email').value = brand.emailTemplate || ''

        card.querySelector('.appt-save-btn').addEventListener('click', () => saveBrand(card))
        card.querySelector('.appt-delete-btn').addEventListener('click', () => deleteBrand(card))
        listEl.appendChild(node)
    }

    function getToken() {
        return document.cookie.split('; ').find(r => r.startsWith('nc_token='))?.split('=')[1]
            || document.querySelector('head[data-requesttoken]')?.dataset?.requesttoken
            || document.getElementById('initial-state-core-config') && JSON.parse(atob(document.getElementById('initial-state-core-config').innerText)).requesttoken
            || ''
    }

    async function saveBrand(card) {
        const statusEl = card.querySelector('.appt-brand-status')
        const body = new FormData()
        body.set('id', card.querySelector('.appt-brand-id').value)
        body.set('name', card.querySelector('.appt-f-name').value)
        body.set('primaryColor', card.querySelector('.appt-f-color').value)
        body.set('logoUrl', card.querySelector('.appt-f-logo').value)
        body.set('faviconUrl', card.querySelector('.appt-f-favicon').value)
        body.set('bgImage', card.querySelector('.appt-f-bg').value)
        body.set('emailTemplate', card.querySelector('.appt-f-email').value)

        const res = await fetch(saveUrl, {
            method: 'POST',
            headers: {'requesttoken': OC.requestToken},
            body
        })
        const data = await res.json()
        if (res.ok) {
            card.querySelector('.appt-brand-id').value = data.id
            statusEl.textContent = '✓ Saved'
            setTimeout(() => statusEl.textContent = '', 2500)
        } else {
            statusEl.style.color = 'var(--color-error)'
            statusEl.textContent = data.error || 'Error'
        }
    }

    async function deleteBrand(card) {
        const id = card.querySelector('.appt-brand-id').value
        if (!id || !confirm('Delete this brand?')) return
        const body = new FormData()
        body.set('id', id)
        const res = await fetch(deleteUrl, {
            method: 'POST',
            headers: {'requesttoken': OC.requestToken},
            body
        })
        if (res.ok) {
            card.remove()
        }
    }

    async function loadBrands() {
        const res = await fetch(apiBase)
        if (!res.ok) return
        const brands = await res.json()
        brands.forEach(renderCard)
    }

    addBtn.addEventListener('click', () => renderCard({}))

    loadBrands()
})()
</script>
