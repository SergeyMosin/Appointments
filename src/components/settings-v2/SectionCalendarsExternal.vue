<script setup>
import {useSettingsStore} from "../../stores/settings";
import ComboSelect from "./ComboSelect.vue";

const props = defineProps({
	calendarOptions: {
		required: true
	}
})
const settingsStore = useSettingsStore()
</script>

<template>
	<div>
		<ComboSelect
				prop-name="nrSrcCalId"
				:options="calendarOptions"
				:store="settingsStore"
				:label="t('appointments', 'Source Calendar (Free Slots)')"
				:emit-input="true"
				v-on="$listeners">
			<template #help>
				{{ t('appointments', 'Any event with "Show As" (a.k.a. "Time As" a.k.a. "Free/Busy" a.k.a. "Time Transparency") set to "Free" will be available for booking in the public form. Most recurrence rules are supported.') }}
				<br>
				{{ t('appointments', "If an event's title/summary starts with an '_' character, the title will be displayed next to or below the time in the form. For example, '_Language Lessons' will be displayed as 'Language Lessons'.") }}
			</template>
		</ComboSelect>

		<ComboSelect
				prop-name="nrDstCalId"
				:options="calendarOptions"
				:store="settingsStore"
				:label="t('appointments', 'Destination Calendar (Booked)')"
				:emit-input="true"
				v-on="$listeners">
			<template #help>
				{{ t('appointments', 'Booked appointments will be placed here. In addition to booked appointments, any events in this calendar marked as "Busy" will prevent conflicting timeslots in the "Source Calendar" from appearing in the public form.') }}
			</template>
		</ComboSelect>

	</div>
</template>

<style scoped>
</style>