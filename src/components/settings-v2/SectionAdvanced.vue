<script setup>
import {useSettingsStore} from "../../stores/settings";
import LabelAccordion from "../LabelAccordion.vue";
import IconWeekly from "vue-material-design-icons/CalendarWeekOutline.vue";
import IconExternal from "vue-material-design-icons/CalendarImportOutline.vue"
import IconDebug from "vue-material-design-icons/Ladybug.vue"
import ComboInput from "./ComboInput.vue";
import ComboSelect from "./ComboSelect.vue";
import ComboCheckbox from "./ComboCheckbox.vue";
import SectionAdvancedDebugging from "./SectionAdvancedDebugging.vue";

const settingsStore = useSettingsStore()
const settings = settingsStore.settings

const syncOptions = [
	{label: t('appointments', 'System Native Sync'), value: '0'},
	{label: t('appointments', '1 Hour'), value: '60'},
	{label: t('appointments', '2 Hours'), value: '120'},
	{label: t('appointments', '4 Hours'), value: '240'},
	{label: t('appointments', '8 Hours'), value: '480'},
	{label: t('appointments', '12 Hours'), value: '720'},
	{label: t('appointments', '1 day'), value: '1440'},
]

</script>

<template>
	<div class="ps-section-wrap">

		<ComboInput
				class="ps-vert-spacing"
				prop-name="titleTemplate"
				placeholder="%I %N"
				:label="t('appointments', 'Event Title Template')"
				:store="settingsStore">
			<template #help>
				{{ t('appointments', "Following template tokens can be used to customize Appointment's Title:") }}<br>
				<strong>%I</strong> - {{ t('appointments', 'Icon') }} (✔️)<br>
				<strong>%N</strong> - {{ t('appointments', 'Attendee name') }}<br>
				<strong>%O</strong> - {{ t('appointments', 'Organization Name') }}<br>
				<strong>%P</strong> - {{ t('appointments', 'Page Tag') }}<br>
				<strong>%T</strong> - {{ t('appointments', 'Mask Token (first three letters of name + semi-random token)') }}<br>
				<strong>%E</strong> - {{ t('appointments', 'Event Preset Title') }}<br><br>
				{{ t('appointments', 'For example template like {tokens} will set new appointments title to something like John Smith (Good Org)', {tokens: '%N (%O)'}) }}
			</template>
		</ComboInput>

		<ComboInput
				prop-name="confirmedRdrUrl"
				:label="t('appointments', 'Redirect Confirmed URL')"
				:store="settingsStore">
			<template #help>
				{{ t('appointments', 'When this URL is specified, visitors will be redirected there after confirming their email address. A base64-encoded query parameter, "d=...", containing a JSON object with relevant data, will be added to the URL.') }}
			</template>
		</ComboInput>
		<div class="srgdev-appt-sb-indent">
			<ComboCheckbox
					style="margin-top: -1em"
					prop-name="confirmedRdrId"
					:disabled="settingsStore.settings.confirmedRdrUrl===''"
					:label="t('appointments', 'Generate ID')"
					:store="settingsStore"/>
			<ComboCheckbox
					class="ps-vert-spacing"
					prop-name="confirmedRdrData"
					:disabled="settingsStore.settings.confirmedRdrUrl===''"
					:label="t('appointments', 'Include Form Data')"
					:store="settingsStore"/>
		</div>

		<LabelAccordion
				class="ps-vert-spacing"
				:label="t('appointments', 'Weekly Template')"
				:accordion="true">
			<template #accordionIcon>
				<IconWeekly :size="24"/>
			</template>
			<ComboSelect
					prop-name="tmmSubscriptionsSync"
					default-value="0"
					:label="t('appointments', 'Subscriptions Sync Interval')"
					:store="settingsStore"
					:options="syncOptions">
				<template #help>
					{{ t('appointments', 'When linked (subscription) calendars are selected for conflict check, the Appointments app can pull data from remote servers before checking for scheduling conflicts. It is impractical to pull the data on every request, as this would increase processing time, especially if multiple remote calendars are selected. Your calendar system has a cache synchronization mechanism to facilitate timely updates. This option is provided just in case you feel that the data is not refreshed often enough.') }}
				</template>
			</ComboSelect>
		</LabelAccordion>

		<LabelAccordion
				class="ps-vert-spacing"
				:label="t('appointments', 'External Mode')"
				:accordion="true">
			<template #accordionIcon>
				<IconExternal :size="24"/>
			</template>
			<ComboCheckbox
					prop-name="nrPushRec"
					:label="t('appointments', 'Optimize recurrence')"
					:store="settingsStore">
				<template #help>
					{{ t('appointments', 'If recurrent events are used in the "Source Calendar" the start (DTSTART) date will be pushed forward once in a while in order to improve performance.') }}
				</template>
			</ComboCheckbox>
			<ComboCheckbox
					prop-name="nrRequireCat"
					:label="t('appointments', 'Require \'Appointment\' category')"
					:store="settingsStore">
				<template #help>
					{{ t('appointments', 'When this option is set only events with with "Category" set to "Appointment" (in English) will be considered.') }}
				</template>
			</ComboCheckbox>
		</LabelAccordion>

		<LabelAccordion
				class="ps-vert-spacing"
				:label="t('appointments', 'Debugging')"
				:accordion="true">
			<template #accordionIcon>
				<IconDebug :size="24"/>
			</template>
			<SectionAdvancedDebugging/>
		</LabelAccordion>
	</div>
</template>

<style scoped>

</style>