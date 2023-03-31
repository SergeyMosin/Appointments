<script setup>
import {
	NcModal,
	NcButton,
} from "@nextcloud/vue";
import {onMounted, reactive} from "vue";
import SettingsService2 from "../../services/SettingsService2";
import {useSettingsStore} from "../../stores/settings";

const emit = defineEmits(['update:data'])

const props = defineProps({
	data: {
		type: Object,
		required: true
	},
})

const settingsStore = useSettingsStore()
const settings = settingsStore.settings

const state = reactive({
	loading: false,
	error: '',
	oldAppointmentsCount: 0,
	modalText: '',
	modalTextDim: '',
})

onMounted(() => {
	state.loading = true
	const service = new SettingsService2()
	service.getCalendarWeek(props.data.pageId, JSON.stringify(props.data.ri)).then((d) => {
		if (d === null) {
			state.error = t('appointments', 'error: cannot load calendar data');
			return null
		}

		const ua = d.data.split("|")

		state.oldAppointmentsCount = parseInt(ua[0])
		state.modalText = ""
		if (ua[0] !== "0") {

			const dt = new Date()
			dt.setTime(ua[1] * 1000)

			let dts = dt.toLocaleDateString(undefined, {
				year: 'numeric',
				month: 'long',
				day: 'numeric'
			})
			if (props.data.ri.type === "empty") {
				state.modalText = t('appointments', 'Remove empty appointment slots created before {fullDate} ?', {
					fullDate: dts
				})
			} else {
				state.modalText = t('appointments', 'Remove empty slots and booked appointments created before {fullDate} ?', {
					fullDate: dts
				})
			}
		}

		let att = ""
		if (ua[0] !== "0" && props.data.ri.type === "both"
				&& settingsStore.settings.destCalId !== undefined
				&& settingsStore.settings.destCalId !== "-1") {
			att = " [ " + t('appointments', 'two calendars affected') + " ]"
		}

		state.modalTextDim = t('appointments', 'Number of expired appointments/slots: {number}', {number: ua[0]}) + att

	}).finally(() => {
		state.loading = false
	})
})

const handleRemoveAppointments = () => {
	console.log('removeOldAppointments, props.data:', props.data)
	// TODO:....
}

// removeOldAppointments() {
//

// this.roaData.str JSON.stringify(props.data.ri)

// 	if (this.roaData.str === "" || this.roaData.pageId === undefined) {
// 		showError('Can not remove appointments: bad info')
// 	}
//
// 	if (!confirm(this.t('appointments', 'This action CANNOT be undone. Continue?'))) return;
//
// 	this.$emit('openGM', 2)
// 	this.$emit('updateGM', {
// 		generalModalLoadingTxt: this.t('appointments', 'Removing Appointment Slots') + "..."
// 	})
//
// 	const errTxt = this.t('appointments', 'Cannot delete old appointments/slots')
//
// 	const str = this.roaData.str.slice(0, -1) + ',"delete":true}';
// 	const pageId = this.roaData.pageId
//
// 	this.roaData.str = ""
// 	this.roaData.pageId = undefined
//
// 	axios.post('calgetweek', {
// 		t: str,
// 		p: pageId
// 	}).then(response => {
// 		if (response.status === 200) {
// 			const ua = response.data.split("|")
// 			if (ua[0] !== "0") {
// 				const dt = new Date()
// 				dt.setTime(ua[1] * 1000)
//
// 				let txt
// 				let dts = dt.toLocaleDateString(undefined, {
// 					year: 'numeric',
// 					month: 'long',
// 					day: 'numeric'
// 				})
//
// 				if (str.indexOf("empty") > -1) {
// 					txt = this.t('appointments', 'All empty appointment slots created before {fullDate} are removed', {
// 						fullDate: dts
// 					})
// 				} else {
// 					txt = this.t('appointments', 'All empty slots and booked appointments created before {fullDate} are removed', {
// 						fullDate: dts
// 					})
// 				}
//
// 				this.$emit('updateGM', {
// 					generalModalTxt: ["", txt]
// 				})
//
// 			} else {
// 				showError(errTxt)
// 			}
// 			this.$emit('updateGM', {
// 				generalModalLoadingTxt: ""
// 			})
// 		}
// 	}).catch(error => {
// 		this.$emit('closeGM')
// 		console.log(error)
// 		showError(errTxt)
// 	})
// },


const handleUpdateShow = (evt) => {
	if (evt === false) {
		emit('update:data', null)
	}
}

</script>

<template>
	<NcModal
			style="z-index: 10003"
			:show="props.data!==null"
			@update:show="handleUpdateShow">
		<div class="srgdev-appt-modal-wrap">
			<div v-if="state.loading">
				{{ t('appointments', "Loading...") }}
			</div>
			<div v-else-if="state.error!==''">
				{{ state.error }}
			</div>
			<template v-else>
				<div class="srgdev-appt-modal-header">
					{{ t('appointments', 'Remove Old Appointments') }}
				</div>
				<div class="srgdev-appt-modal-lbl">
					{{ state.modalText }}
					<div class="srgdev-appt-modal-lbl_dim">
						{{ state.modalTextDim }}
					</div>
				</div>
				<NcButton
						class="srgdev-appt-modal-btn"
						type="primary"
						:disabled="state.oldAppointmentsCount===0"
						@click="handleRemoveAppointments">
					{{ t('appointments', 'Remove') }}
				</NcButton>
				<NcButton
						style="margin-left: 2em"
						class="srgdev-appt-modal-btn"
						@click="()=>{handleUpdateShow(false)}"
						type="secondary">
					{{ t('appointments', 'Cancel') }}
				</NcButton>
			</template>
		</div>
	</NcModal>
</template>

<style scoped>
</style>