<script setup>
import {computed, reactive, inject} from "vue";
import LabelAccordion from "../LabelAccordion.vue";
import IconCalendarAdd from "vue-material-design-icons/CalendarPlus.vue";
import {useSettingsStore} from "../../stores/settings";
import VueSlider from "vue-slider-component";
import {getTimezone} from "../../use/utils";
import {NcButton, NcCheckboxRadioSwitch, NcDateTimePickerNative} from "@nextcloud/vue";
import {showError} from "@nextcloud/dialogs";
import ComboSelect from "./ComboSelect.vue";

const emit = defineEmits(['show-editor'])

const props = defineProps({
	calendarOptions: {
		required: true
	}
})

const pageId = inject("pageId", '')

const settingsStore = useSettingsStore()
const settings = settingsStore.settings

const state = reactive({
	tzLoading: false,
	error: false,
	tzName: '',
	tzData: '',
	/** @type {Date|null} */
	apptWeek: null,
	apptDur: 30,

	rsValue: "58",
	remType: "empty",
	remModalData: null
})

// add new ----------------

// 1 (Mon) = default/fallback, or 0 (Sun) or 6 (Sat)
const firstDayOfWeek = window.firstDay === 0
		? 0
		: (window.firstDay === 6 ? 6 : 1)

const getStartOfWeek = (d) => {

	d.setHours(0, 0, 0, 0)

	//  0: Sunday
	//  1: Monday
	//  6: Saturday
	const fdw = firstDayOfWeek

	//  fdw=0 : 0 1 2 3 4 5 6 | adjust: d.getDay()
	//  fdw=1 : 1 2 3 4 5 6 0 | adjust: (d.getDay() + 6) % 7
	//  fdw=6 : 6 0 1 2 3 4 5 | adjust: (d.getDay() + 1) % 7
	//  delta : 0 1 2 3 4 5 6

	const deltaDays = (d.getDay() + (7 - fdw)) % 7

	const nd = new Date(d.getTime())
	nd.setDate(nd.getDate() - deltaDays)
	return nd
}

// TODO: refactor SEF
const notBeforeDate = (() => {
	const d = getStartOfWeek(new Date())
	// because of daylight savings
	d.setHours(1, 30, 0, 0)
	return d
})()


const compNotBefore = (d) => {
	d.setHours(1, 30, 0, 0)
	return d < notBeforeDate
}

// any selected date counts for its whole week
const apptWeekStart = computed(() => state.apptWeek === null
		? null
		: getStartOfWeek(new Date(state.apptWeek.getTime())))

const weekRange = computed(() => {
	const startDate = apptWeekStart.value
	if (startDate === null) {
		return ''
	}
	const endDate = new Date(startDate.getTime())
	endDate.setDate(endDate.getDate() + 6)
	if (window.Intl && typeof window.Intl === "object") {
		const f = new Intl.DateTimeFormat([],
				{month: "short", day: "2-digit",})
		return f.format(startDate) + ' - ' + f.format(endDate)
	}
	return startDate.toLocaleDateString() + ' - ' + endDate.toLocaleDateString()
})

const weekInvalid = computed(() => state.apptWeek === null
		|| compNotBefore(new Date(state.apptWeek.getTime())))

const addAccordionOpen = () => {
	state.tzLoading = true
	state.tzName = 'UTC'
	state.tzData = 'UTC'
	getTimezone(pageId, settings.mainCalId).then(d => {
		if (d === null) {
			state.error = true
		} else {
			state.tzName = d.name
			state.tzData = d.data
		}
	}).finally(() => {
		state.tzLoading = false
	})
	state.apptDur = 30
	state.apptWeek = null
}

const showSimpleEditor = () => {

	if (settings.mainCalId === '-1') {
		showError(t('appointments', 'error: main calendar required'))
		return
	}

	const cal = settingsStore.calendars.find(item => item.id === settings.mainCalId)
	if (!cal) {
		showError(t('appointments', 'error: cannot find calendar with ID ' + settings.mainCalId))
		return;
	}

	const r = {
		tz: state.tzData,
		week: apptWeekStart.value.getTime(),
		dur: state.apptDur,
		pageId: pageId,
		calColor: cal.color,
		calName: cal.name,
	}
	emit('show-editor', r)
}

// remove old --------------------

// // TODO: refactor SEF
// const rsMarks = (() => {
// 	const options = {month: 'short', day: '2-digit'};
// 	const d = new Date()
// 	d.setTime(Date.now() - 86400000)
// 	const y = d.toLocaleString(undefined, options)
// 	d.setTime(d.getTime() - 86400000 * 6)
// 	const w = d.toLocaleString(undefined, options)
// 	return {
// 		0: '-∞',
// 		58: w,
// 		100: y,
// 	}
// })()
//
// const checkRsMin = () => {
// 	if (+state.rsValue < 58) state.rsValue = "58"
// }
//
// const removeOldAppointments = () => {
// 	state.remModalData = {
// 		ri: {
// 			type: state.remType,
// 			before: state.rsValue === "100" ? 1 : 7
// 		},
// 		pageId: pageId
// 	}
// }

</script>

<template>
	<div>
		<ComboSelect
				prop-name="mainCalId"
				:required="true"
				:options="calendarOptions"
				:store="settingsStore"
				:label="t('appointments', 'Main calendar')"
				:emit-input="true"
				v-on="$listeners">
			<template #help>
				{{ t('appointments', 'When you create new appointment slots they are placed here and are shown in your public page(s). It is recommended to use a dedicated calendar.') }}
			</template>
		</ComboSelect>
		<ComboSelect
				prop-name="destCalId"
				:required="true"
				:options="calendarOptions"
				:store="settingsStore"
				:label="t('appointments', 'Calendar for booked appointments')"
				:placeholder="t('appointments', 'Use Main calendar')"
				:emit-input="true"
				v-on="$listeners">
			<template #help>
				{{ t('appointments', 'If this calendar is different from the Main Calendar, once an appointment is booked it will be moved here.') }}
			</template>
		</ComboSelect>

		<LabelAccordion
				class="ps-vert-spacing"
				:label="t('appointments', 'Add Appointment Slots')"
				:accordion="true"
				@open-accordion="addAccordionOpen">
			<template #accordionIcon>
				<IconCalendarAdd :size="24"/>
			</template>
			<div v-if="state.tzLoading">
				{{ t('appointments', "Loading...") }}
			</div>
			<div v-else-if="state.error">
				{{ t('appointments', "Error: Can't load time zones") }}
			</div>
			<template v-else-if="state.tzName!==''">
				<LabelAccordion
						:label="t('appointments', 'Select Dates')"/>
				<NcDateTimePickerNative
						id="ps-simple-week"
						:label="t('appointments','Select Dates')"
						:hide-label="true"
						:min="notBeforeDate"
						v-model="state.apptWeek"/>
				<div class="srgdev-appt-info-lcont srgdev-appt-tz-cont" aria-live="polite">
					{{ weekRange }}
				</div>
				<div class="srgdev-appt-info-lcont srgdev-appt-tz-cont">
					{{ t('appointments', 'Time zone:') + ' ' + state.tzName }}
				</div>
				<label id="ps-appt-dur-label" class="select-label">{{ t('appointments', 'Appointment Duration:') }}</label>
				<vue-slider
						:min="5"
						:max="120"
						:interval="5"
						tooltip="always"
						tooltipPlacement="bottom"
						:tooltip-formatter="'{value} Min'"
						:dot-attrs="{'aria-labelledby': 'ps-appt-dur-label'}"
						class="appt-slider"
						v-model="state.apptDur"/>
				<NcButton
						@click="showSimpleEditor"
						:disabled="weekInvalid"
						style="margin-top: 3.5em;margin-bottom: 2em;padding-left: 3em;padding-right: 3em;"
						class="srgdev-appt-sb-genbtn"
						:aria-label="t('appointments', 'Start')">
					{{ t('appointments', 'Start') }}
				</NcButton>
			</template>
		</LabelAccordion>

		<!--		<LabelAccordion-->
		<!--				class="ps-vert-spacing"-->
		<!--				:label="t('appointments', 'Remove Old Appointments')"-->
		<!--				:accordion="true">-->
		<!--			<template #accordionIcon>-->
		<!--				<IconCalendarRemove :size="24"/>-->
		<!--			</template>-->
		<!--			<LabelAccordion-->
		<!--					:label="t('appointments', 'Scheduled before')"/>-->
		<!--			<vue-slider-->
		<!--					v-model="state.rsValue"-->
		<!--					:marks="rsMarks"-->
		<!--					:process="true"-->
		<!--					:included="true"-->
		<!--					:lazy="true"-->
		<!--					tooltip="none"-->
		<!--					@change="checkRsMin"-->
		<!--					class="appt-slider"/>-->
		<!--			<NcCheckboxRadioSwitch-->
		<!--					style="margin-top: 2.5em; margin-left: -1em"-->
		<!--					:checked.sync="state.remType"-->
		<!--					value="empty"-->
		<!--					name="remove_type"-->
		<!--					type="radio">{{ t('appointments', 'Remove empty slots only') }}-->
		<!--			</NcCheckboxRadioSwitch>-->
		<!--			<NcCheckboxRadioSwitch-->
		<!--					style="margin-left: -1em"-->
		<!--					class="ps-vert-spacing"-->
		<!--					:checked.sync="state.remType"-->
		<!--					value="both"-->
		<!--					name="remove_type"-->
		<!--					type="radio">{{ t('appointments', 'Remove empty and booked') }}-->
		<!--			</NcCheckboxRadioSwitch>-->
		<!--			<NcButton-->
		<!--					style="padding-left: 3em;padding-right: 3em;"-->
		<!--					@click="removeOldAppointments"-->
		<!--					:aria-label="t('appointments', 'Start')"-->
		<!--					class="srgdev-appt-sb-genbtn">{{ t('appointments', 'Start') }}-->
		<!--			</NcButton>-->
		<!--		</LabelAccordion>-->
		<!--		<RemoveSimpleApptsModal v-if="state.remModalData!==null" :data.sync="state.remModalData"/>-->
	</div>
</template>

<style scoped>
.srgdev-appt-tz-cont {
	color: var(--color-text-lighter);
	font-size: 85%;
	line-height: 1.1;
	margin-bottom: 1.25em;
}

</style>