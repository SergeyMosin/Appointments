<script setup>
import {reactive, inject} from "vue";
import LabelAccordion from "../LabelAccordion.vue";
import IconCalendarAdd from "vue-material-design-icons/CalendarPlus.vue";
import {useSettingsStore} from "../../stores/settings";
import DatePicker from "vue2-datepicker";
import VueSlider from "vue-slider-component";
import {getTimezone} from "../../use/utils";
import {NcButton, NcCheckboxRadioSwitch} from "@nextcloud/vue";
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

// TODO: refactor SEF
const lang = (() => {
	let days = undefined
	let months = undefined
	const formatLocale = {
		// 1 (Mon) = default/fallback, or 0 (Sun) or 6 (Sat)
		firstDayOfWeek: window.firstDay === 0
				? 0
				: (window.firstDay === 6 ? 6 : 1)
	}
	if (window.Intl && typeof window.Intl === "object") {
		days = []
		let d = new Date(1970, 1, 1)
		let f = new Intl.DateTimeFormat([],
				{weekday: "short",})
		for (let i = 1; i < 8; i++) {
			d.setDate(i)
			days[i - 1] = f.format(d)
		}
		f = new Intl.DateTimeFormat([],
				{month: "short",})
		d.setDate(1)
		months = []
		for (let i = 0; i < 12; i++) {
			d.setMonth(i)
			months[i] = f.format(d)
		}
		formatLocale.monthsShort = months
	}
	return {days: days, formatLocale: formatLocale}
})()

const getStartOfWeek = (d) => {

	d.setHours(0, 0, 0, 0)

	// lang.formatLocale.firstDayOfWeek can be:
	//  0: Sunday
	//  1: Monday
	//  6: Saturday
	const fdw = lang.formatLocale.firstDayOfWeek

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

const datePickerPopupStyle = {
	top: "75%",
	left: "50% !important",
	transform: "translate(-50%,0)"
}

const weekFormat = {
	// Date to String
	stringify: (date, fmt) => {
		if (date) {
			const endDate = new Date(date.getTime())
			endDate.setDate(endDate.getDate() + 6);
			if (window.Intl && typeof window.Intl === "object") {
				let f = new Intl.DateTimeFormat([],
						{month: "short", day: "2-digit",})
				return f.format(date) + ' - ' + f.format(endDate)
			} else {
				return date.toLocaleDateString() + ' - ' + (endDate).toLocaleDateString()
			}
		} else return ''
	}
}
const setToStartOfWeek = () => {
	if (state.apptWeek !== null) {
		state.apptWeek = getStartOfWeek(state.apptWeek)
	}
}

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
		week: state.apptWeek.getTime(),
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
				<DatePicker
						style="width: auto; min-width: 21em;"
						:editable="false"
						:disabled-date="compNotBefore"
						:appendToBody="false"
						:popup-style="datePickerPopupStyle"
						:placeholder="t('appointments','Select Dates')"
						v-model="state.apptWeek"
						:lang="lang"
						@input="setToStartOfWeek"
						:formatter="weekFormat"
						type="week"></DatePicker>
				<div class="srgdev-appt-info-lcont srgdev-appt-tz-cont">
					{{ t('appointments', 'Time zone:') + ' ' + state.tzName }}
				</div>
				<label for="appt_dur-select" class="select-label">{{ t('appointments', 'Appointment Duration:') }}</label>
				<vue-slider
						:min="5"
						:max="120"
						:interval="5"
						tooltip="always"
						tooltipPlacement="bottom"
						:tooltip-formatter="'{value} Min'"
						id="appt_dur-select"
						class="appt-slider"
						v-model="state.apptDur"/>
				<NcButton
						@click="showSimpleEditor"
						:disabled="state.apptWeek===null"
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