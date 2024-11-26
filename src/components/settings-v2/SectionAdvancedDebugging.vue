<script setup>
import {useSettingsStore} from "../../stores/settings";
import PsSelect from "../PsSelect.vue";
import LabelAccordion from "../LabelAccordion.vue";
import {computed, ref, inject} from "vue";
import {
	NcButton,
	NcTextField
} from "@nextcloud/vue";
import IconDownload from "vue-material-design-icons/Download.vue";
import DebugDataModal from "../modals/DebugDataModal.vue";
import ComboSelect from "./ComboSelect.vue";

const settingsStore = useSettingsStore()

const debugRequest = ref({
	action: '',
	data: {}
})

const debugModeOptions = [
	{value: 0, label: t('appointments', 'Debugging Off')},
	{value: 1, label: t('appointments', 'Log remote blockers')},
	{value: 2, label: t('appointments', 'Log template durations')},
]

const pageId = inject("pageId", '')

const handleAction = (action, data) => {
	debugRequest.value.action = action
	debugRequest.value.data = data
}
const handleCloseModal = function () {
	debugRequest.value.action = ''
	debugRequest.value.data = {}
}

const calendarOptions = computed(() => {
	return settingsStore.calendars.reduce((list, cal) => {
		list.push({
			label: cal.name,
			value: cal.id
		})
		return list
	}, [])
})

const remoteCalendarOptions = computed(() => {
	return settingsStore.calendars.reduce((list, cal) => {
		if (cal.isSubscription) {
			list.push({
				label: cal.name,
				value: cal.id
			})
		}
		return list
	}, [])
})

const eventUid = ref('')
const sendUid = () => {
	handleAction('uid_info', {p: pageId, uid: eventUid.value})
}

</script>


<template>
	<div style="margin-top: 1.25em">
		<NcButton
				class="ps-vert-spacing"
				@click="handleAction('settings_dump',{p:pageId})"
				:aria-label="t('appointments', 'Settings Dump')">
			<template #icon>
				<IconDownload :size="20"/>
			</template>
			{{ t('appointments', 'Settings Dump') }}
		</NcButton>

		<LabelAccordion :label="t('appointments', 'Get raw calendar data')"/>
		<PsSelect
				class="ps-vert-spacing"
				:placeholder-label="t('appointments', 'Calendar Required')"
				:selected-value="-1"
				:options="calendarOptions"
				@input="(evt)=>{handleAction('get_raw',{p:pageId,cal_id:evt.value})}"/>

		<LabelAccordion :label="t('appointments', 'Sync remote calendar now')"/>
		<PsSelect
				class="ps-vert-spacing"
				:placeholder-label="t('appointments', 'Calendar Required')"
				:selected-value="-1"
				:options="remoteCalendarOptions"
				:noDrop="remoteCalendarOptions.length===0"
				@input="(evt)=>{handleAction('sync',{p:pageId,cal_id:evt.value})}"/>
		<ComboSelect
				prop-name="debugging_mode"
				class="ps-vert-spacing"
				:label="t('appointments', 'Debugging Mode')"
				:placeholder-label="t('appointments', 'Debugging Mode')"
				:default-value=0
				:options="debugModeOptions"
				:store="settingsStore"/>
		<DebugDataModal
				v-if="debugRequest.action!==''"
				size="large"
				:request="debugRequest"
				@close-modal="handleCloseModal"/>
		<NcTextField
				label="Get by Event UID"
				trailingButtonIcon="arrowRight"
				:showTrailingButton="true"
				trailingButtonLabel="Send"
				@trailing-button-click="sendUid"
				v-model="eventUid"/>
	</div>
</template>

<style scoped>

</style>