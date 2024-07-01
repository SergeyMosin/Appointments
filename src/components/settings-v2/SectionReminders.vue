<script setup>
import {NcNoteCard} from "@nextcloud/vue";
import {useSettingsStore, readOnlyProps} from "../../stores/settings";
import ComboInput from "./ComboInput.vue";
import ComboCheckbox from "./ComboCheckbox.vue";
import ComboSelect from "./ComboSelect.vue";
import LabelAccordion from "../LabelAccordion.vue";
import {MODAL} from "../../use/constants";

const emit = defineEmits(['show-settings-modal'])

const settingsStore = useSettingsStore()
const settings = settingsStore.settings

const reminderOptions = [
	{value: "0", label: t('appointments', 'Not set')},
	{value: "3600", label: t('appointments', '1 hour')},
	{value: "7200", label: t('appointments', '2 hours')},
	{value: "14400", label: t('appointments', '4 hours')},
	{value: "28800", label: t('appointments', '8 hours')},
	{value: "86400", label: t('appointments', '24 hours')},
]

if (settingsStore.k === true) {
	reminderOptions.push(
			{value: "172800", label: t('appointments', '2 days')},
			{value: "259200", label: t('appointments', '3 days')},
			{value: "345600", label: t('appointments', '4 days')},
			{value: "432000", label: t('appointments', '5 days')},
			{value: "518400", label: t('appointments', '6 days')},
			{value: "604800", label: t('appointments', '7 days')},
	)
}

const handleInterceptor = (data) => {
	if (settingsStore.k === true) {
		return false
	}
	emit('show-settings-modal', {
		type: MODAL.CONTRIBUTION,
		message: t('appointments', "Multiple Reminders")
	})
	return true
}

</script>

<template>
	<div class="ps-section-wrap">
		<NcNoteCard
				v-if="readOnlyProps.bjm!=='cron' && readOnlyProps.bjm!=='webcron'"
				:heading="t('appointments', 'Warning')"
				type="warning">
			You are using <strong>AJAX</strong> scheduling method, which
			is the least reliable. Please consider <strong>Webcron</strong> or
			<strong>Cron</strong> scheduling methods. More information is available in
			<a style="text-decoration: underline" href="https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/background_jobs_configuration.html#parameters" target="_blank">Admin Manual: Cron jobs</a> section.
		</NcNoteCard>
		<NcNoteCard
				v-if="readOnlyProps.cliUrl===''"
				:heading="t('appointments', 'Warning')"
				type="warning">
			The <strong>overwrite.cli.url</strong> parameter is set to an invalid URL,
			<strong>action links</strong> will not be included in the reminder emails. More information is available in
			<a style="text-decoration: underline" href="https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/config_sample_php_parameters.html#proxy-configurations"
				 target="_blank">Admin Manual</a>.
		</NcNoteCard>

		<div v-for="i in [0,1,2]" :key="i">
			<ComboSelect
					:label="(i+1) + '. ' + t('appointments', 'Time before appointment')"
					:prop-name="'reminders_data_'+i+'_seconds'"
					default-value="0"
					:placeholder="t('appointments', 'Not set')"
					:options="reminderOptions"
					:click-interceptor="i===0?undefined:handleInterceptor"
					:store="settingsStore"/>
			<ComboCheckbox
					class="reminder-action-checkbox"
					:label="t('appointments', 'Add action links')"
					:prop-name="'reminders_data_'+i+'_actions'"
					:click-interceptor="i===0?undefined:handleInterceptor"
					:store="settingsStore"/>
		</div>
		<ComboInput
				prop-name="reminders_moreText"
				type="textarea"
				:label="t('appointments', 'Additional reminder email text')"
				:store="settingsStore"/>

		<LabelAccordion
				style="font-style: italic"
				:label="t('appointments', 'Default Cron/Email Language: {langCode}', {langCode: readOnlyProps.defaultLang})">
			<template #helpPopover>
				This language is used for all system notification including Reminders.
			</template>
		</LabelAccordion>
	</div>
</template>

<style scoped>
.reminder-action-checkbox {
	margin-top: -1em;
	margin-bottom: .75em;
	margin-left: .125em;
}
</style>