<script setup>
import 'vue-slider-component/theme/default.css'
import LabelAccordion from "../LabelAccordion.vue";
import VueSlider from "vue-slider-component";
import {computed} from "vue";
import {useSettingsStore} from "../../stores/settings";
import {TS_MODE} from "../../use/constants";
import IconBuffer from "vue-material-design-icons/ArrowExpandHorizontal.vue"
import IconTimeslot from "vue-material-design-icons/TimelineClockOutline.vue"
import SectionCalendarsWeekly from "./SectionCalendarsWeekly.vue";
import SectionCalendarsSimple from "./SectionCalendarsSimple.vue";
import SectionCalendarsExternal from "./SectionCalendarsExternal.vue";
import ComboSelect from "./ComboSelect.vue";
import ComboCheckbox from "./ComboCheckbox.vue";

// TRANSLATORS "Min" is short for Minute(s)
const minute = t('appointments', 'Min')

const emit = defineEmits(['show-settings-modal', 'show-timeslot-editor'])

const settingsStore = useSettingsStore()
const settings = settingsStore.settings

const calendarOptions = computed(() => {
	return settingsStore.calendars.reduce((list, cal) => {
		if (!cal.isReadOnly && !cal.isSubscription) {
			list.push({
				label: cal.name,
				value: cal.id
			})
		}
		return list
	}, [])
})

const modeOptions = [
	{label: t('appointments', 'Weekly Template'), value: TS_MODE.TEMPLATE},
	{label: t('appointments', 'Simple'), value: TS_MODE.SIMPLE},
	{label: t('appointments', 'External'), value: TS_MODE.EXTERNAL}
]

const leadTimeOptions = [
	{label: t('appointments', 'No lead time'), value: '0'},
	{label: t('appointments', '15 minutes'), value: '15'},
	{label: t('appointments', '30 minutes'), value: '30'},
	{label: t('appointments', '1 hour'), value: '60'},
	{label: t('appointments', '2 hours'), value: '120'},
	{label: t('appointments', '4 hours'), value: '240'},
	{label: t('appointments', '8 hours'), value: '480'},
	{label: t('appointments', '12 hours'), value: '720'},
	{label: t('appointments', '1 day'), value: '1440'},
	{label: t('appointments', '2 days'), value: '2880'},
	{label: t('appointments', '4 days'), value: '5760'},
	{label: t('appointments', '1 week'), value: '10080'},
	{label: t('appointments', '2 weeks'), value: '20160'},
	{label: t('appointments', '4 weeks'), value: '40320'},
]

const cancelationOptions = [
	{label: t('appointments', 'Reset (make the timeslot available)'), value: 'reset'},
	{label: t('appointments', 'Mark the appointment as canceled'), value: 'mark'},
]

/**
 * @param {{prop:String,value:{value:String,label:String}}} data
 */
const handleSetCal = (data) => {
	const calId = data.value.value || '-1'
	settingsStore.setOne(data.prop, calId, false, (res) => {
		if (settings.tsMode === TS_MODE.TEMPLATE && res !== null) {
			let key = ''
			if (settings.tmmMoreCals.indexOf(calId) !== -1) {
				key = 'tmmMoreCals'
			} else if (settings.tmmSubscriptions.indexOf(calId) !== -1) {
				key = 'tmmSubscriptions'
			}
			if (key !== '') {
				settings[key] = settings[key].filter(item => {
					return item !== calId
				})
			}
		}
	})
}
</script>

<template>
	<div class="ps-section-wrap">
		<ComboSelect
				prop-name="tsMode"
				:default-value="TS_MODE.TEMPLATE"
				:label="t('appointments', 'Time slot mode')"
				:store="settingsStore"
				:options="modeOptions">
			<template #help>
				<strong>{{ t('appointments', 'Weekly Template') }}</strong>: {{ t('appointments', 'In this mode you can set a weekly template and it will be repeated automatically.') }}<br>
				<strong>{{ t('appointments', 'Simple') }}</strong>: {{ t('appointments', 'Use provided "Add Appointment Slots" dialog to add "available" time slots. Recurrence is not supported in this mode.') }}<br>
				<strong>{{ t('appointments', 'External') }}</strong>: {{ t('appointments', 'Use Calendar App or any other CalDAV compatible client to add "available" timeslots. Most recurrence rules are supported in this mode. Two calendars are required: a "Source Calendar" to keep track of your availability timeslots and a "Destination Calendar" for booked appointments.') }}<br>
			</template>
		</ComboSelect>

		<SectionCalendarsWeekly
				v-if="settingsStore.loading.tsMode!==true && settings.tsMode===TS_MODE.TEMPLATE"
				class="ps-vert-spacing"
				:calendar-options="calendarOptions"
				v-on="$listeners"
				@combo-select-input="handleSetCal"
				@show-editor="()=>{emit('show-timeslot-editor',null)}"/>

		<SectionCalendarsSimple
				v-else-if="settingsStore.loading.tsMode!==true && settings.tsMode===TS_MODE.SIMPLE"
				class="ps-vert-spacing"
				:calendar-options="calendarOptions"
				@combo-select-input="handleSetCal"
				@show-editor="(data)=>{emit('show-timeslot-editor', data)}"/>

		<SectionCalendarsExternal
				v-else-if="settingsStore.loading.tsMode!==true && settings.tsMode===TS_MODE.EXTERNAL"
				class="ps-vert-spacing"
				:calendar-options="calendarOptions"
				@combo-select-input="handleSetCal"/>

		<LabelAccordion
				v-if="settingsStore.loading.tsMode!==true"
				class="ps-vert-spacing"
				:label="t('appointments', 'Time Slot Settings')"
				:accordion="true">
			<template #accordionIcon>
				<IconTimeslot :size="24"/>
			</template>
			<ComboSelect
					prop-name="prepTime"
					default-value="0"
					:label="t('appointments', 'Minimum lead time')"
					:store="settingsStore"
					:options="leadTimeOptions"/>
			<ComboSelect
					prop-name="whenCanceled"
					default-value="0"
					:label="t('appointments', 'When attendee cancels')"
					:store="settingsStore"
					:options="cancelationOptions"/>
			<ComboCheckbox
					prop-name="allDayBlock"
					class="ps-vert-spacing"
					:label="t('appointments', 'Include all day events in conflict check')"
					:store="settingsStore">
			</ComboCheckbox>
		</LabelAccordion>

		<LabelAccordion
				v-if="settingsStore.loading.tsMode!==true && settings.tsMode!==TS_MODE.SIMPLE"
				class="ps-vert-spacing"
				:label="t('appointments', 'Booked appointment buffers')"
				:loading="settingsStore.loading.bufferBefore===true"
				:accordion="true">
			<template #accordionIcon>
				<IconBuffer :size="24"/>
			</template>
			<template #helpPopover>
				{{ t('appointments', 'It is possible to block off a period of time before and after a booked (and pending) appointment. This could be useful when some preparation/travel time is required before, or cleanup/cool-off time needs to be blocked off after an appointment.') }}
			</template>
			<LabelAccordion
					style="margin-top: .5em"
					:label="t('appointments', 'Before')"
					for="ps-buffer-before"/>
			<vue-slider
					:min="0"
					:max="120"
					:interval="5"
					:lazy="true"
					:disabled="settingsStore.loading.bufferBefore===true"
					tooltip="always"
					tooltipPlacement="right"
					:tooltip-formatter="'{value} '+minute"
					id="ps-buffer-before"
					class="ps-slider"
					:value="settings.bufferBefore"
					@change="(value)=>{settingsStore.setOne('bufferBefore',value)}"/>
			<LabelAccordion
					style="margin-top: .5em"
					:label="t('appointments', 'After')"
					for="ps-buffer-after"/>
			<vue-slider
					:min="0"
					:max="120"
					:interval="5"
					:lazy="true"
					:disabled="settingsStore.loading.bufferBefore===true"
					tooltip="always"
					tooltipPlacement="right"
					:tooltip-formatter="'{value} '+minute"
					id="ps-buffer-after"
					class="ps-slider"
					:value="settings.bufferBefore"
					@change="(value)=>{settingsStore.setOne('bufferBefore',value)}"/>
		</LabelAccordion>
	</div>
</template>

<style scoped>
</style>
