<script setup>
import {
	NcModal,
} from "@nextcloud/vue";
import {reactive, onMounted} from "vue";
import SettingsService2 from "../../services/SettingsService2";
import {useSettingsStore, readOnlyProps} from "../../stores/settings";

const emit = defineEmits(['close-modal'])
const props = defineProps({
	request: {
		type: Object,
		required: true
	},
})

const state = reactive({
	loading: false,
	error: '',
	response: ''
})

onMounted(() => {
	state.loading = true
	const settingsService = new SettingsService2()
	settingsService.getDebugData(props.request.action, props.request.data).then(res => {
		if (res === null) {
			state.error = t('appointments', 'error: cannot load debug data')
		} else {
			const settingsStore = useSettingsStore()
			state.response = res.data + (props.request.action === 'settings_dump'
					? (
							JSON.stringify(settingsStore.settings, null, 2) + '\r\n' +
							JSON.stringify(readOnlyProps, null, 2) + '\r\n' +
							JSON.stringify(settingsStore.calendars, null, 2)
					)
					: '')
		}
		state.loading = false
	})

})

const handleUpdateShow = (evt) => {
	if (evt === false) {
		emit('close-modal')
	}
}


</script>

<template>
	<NcModal
			style="z-index: 10002"
			size="large"
			setReturnFocus="false"
			@update:show="handleUpdateShow">
		<div class="srgdev-appt-modal-wrap">
			<div v-if="state.loading">
				{{ t('appointments', "Loading...") }}
			</div>
			<div v-else-if="state.error!==''">
				{{ state.error }}
			</div>
			<pre v-else style="font-size:90%;padding: 0 1em;text-align: left;"><code v-html="state.response"/></pre>
		</div>
	</NcModal>
</template>

<style scoped>
</style>