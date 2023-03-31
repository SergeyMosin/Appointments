<script setup>
import {useSettingsStore} from "../../stores/settings";
import ComboCheckbox from "./ComboCheckbox.vue";
import LabelAccordion from "../LabelAccordion.vue";
import ComboInput from "./ComboInput.vue";

const settingsStore = useSettingsStore()
const settings = settingsStore.settings

</script>

<template>
	<div class="ps-section-wrap">
		<ComboCheckbox
				class="ps-vert-spacing"
				prop-name="icsFile"
				:label="t('appointments', 'Attach .ics file to confirm/cancel emails')"
				:store="settingsStore">
			<template #help>
				{{ t('appointments', 'An .ics attachment will automatically be generated for notification emails, allowing better integration with email/calendar apps that support it.') }}
			</template>
		</ComboCheckbox>
		<LabelAccordion
				:label="t('appointments','Email Attendee when the appointment is:')">
			<template #helpPopover>
				{{ t('appointments', 'Attendees will be notified via email when their upcoming appointments are updated or deleted in the calendar app or through some other external mechanism. Only changes to Date/Time, Status, or Location will trigger the "Modified" notification.') }}
			</template>
		</LabelAccordion>
		<div class="srgdev-appt-sb-indent">
			<ComboCheckbox
					prop-name="attMod"
					:label="t('appointments', 'Modified (Time, Status, Location)')"
					:store="settingsStore"/>
			<ComboCheckbox
					prop-name="attDel"
					:label="t('appointments', 'Deleted')"
					:store="settingsStore"/>
		</div>
		<LabelAccordion
				:label="t('appointments','Email Me when an appointment is:')">
			<template #helpPopover>
				{{ t('appointments', 'A notification email will be sent to you when an appointment is booked via the public page or when an upcoming appointment is confirmed or canceled through the email links.') }}
			</template>
		</LabelAccordion>
		<div class="srgdev-appt-sb-indent">
			<ComboCheckbox
					prop-name="meReq"
					:label="t('appointments', 'Requested')"
					:store="settingsStore"/>
			<ComboCheckbox
					prop-name="meConfirm"
					:label="t('appointments', 'Confirmed')"
					:store="settingsStore"/>
			<ComboCheckbox
					prop-name="meCancel"
					:label="t('appointments', 'Canceled')"
					:store="settingsStore"/>
		</div>
		<ComboCheckbox
				class="ps-vert-spacing"
				prop-name="skipEVS"
				:label="t('appointments', 'Skip email validation step')"
				:store="settingsStore">
			<template #help>
				{{ t('appointments', 'When this option is selected, the "... action needed" validation email will NOT be sent to the attendee. Instead, the "... Appointment is confirmed" message will be sent right away, and the "All done" page will be shown when the form is submitted.') }}
			</template>
		</ComboCheckbox>

		<ComboInput
				v-if="settings.skipEVS===false"
				type="textarea"
				prop-name="vldNote"
				:label="t('appointments', 'Additional VALIDATION email text:')"
				:store="settingsStore"/>
		<ComboInput
				type="textarea"
				prop-name="cnfNote"
				:label="t('appointments', 'Additional CONFIRMATION email text:')"
				:store="settingsStore"/>
		<ComboInput
				type="textarea"
				prop-name="icsNote"
				:label="t('appointments', 'Additional ICS file description:')"
				:store="settingsStore">
			<template #help>
				{{ t('appointments', "This text will be appended to the end of the event's 'DESCRIPTION' property. Do NOT use HTML here, as most apps do NOT render it.") }}
			</template>
		</ComboInput>
	</div>
</template>

<style scoped>

</style>