<script setup>
import IconCalendarAlert from "vue-material-design-icons/CalendarAlert.vue"
import LabelAccordion from "../LabelAccordion.vue"
import {NcButton} from "@nextcloud/vue"
import NcLoadingIcon from "@nextcloud/vue/dist/Components/NcLoadingIcon.js"
import IconPencil from "vue-material-design-icons/Pencil.vue"
import {useSettingsStore} from "../../stores/settings"
import {computed, inject, ref} from "vue"
import {MODAL} from "../../use/constants"
import ComboSelect from "./ComboSelect.vue"
import {getTimezone} from "../../use/utils"
import {showError} from "@nextcloud/dialogs";

const emit = defineEmits(['show-editor', 'show-settings-modal'])

const props = defineProps({
	calendarOptions: {
		required: true
	}
})

const settingsStore = useSettingsStore()
const settings = settingsStore.settings

const pageId = inject("pageId", '')

const conflictCals = computed(() => {
	return settingsStore.calendars.filter(cal => {
		if (settings.tsMode !== '2') {
			return false
		} else {
			return cal.id !== settings.tmmDstCalId
		}
	})
})

const conflictCalsModel = computed({
	get() {
		return conflictCals.value.filter(cal => {
			return settings.tmmMoreCals.indexOf(cal.id) !== -1
					|| settings.tmmSubscriptions.indexOf(cal.id) !== -1
		})
	},
	set(cals) {
		const tmmCals = []
		const tmmSubs = []
		cals.forEach(cal => {
			if (cal.isSubscription) {
				tmmSubs.push(cal.id)
			} else {
				tmmCals.push(cal.id)
			}
		})

		const callback = (res) => {
			if (res.status === 202) {
				emit('show-settings-modal', {
					type: MODAL.CONTRIBUTION,
					message: res.data.message
				})
			}
		}
		if (tmmCals.length !== settings.tmmMoreCals.length) {
			settingsStore.setOne('tmmMoreCals', tmmCals, false, callback)
		} else {
			settingsStore.setOne('tmmSubscriptions', tmmSubs, false, callback)
		}
	}
})
const conflictCalsLoading = computed(() => {
	return settingsStore.loading['tmmMoreCals'] === true
			|| settingsStore.loading['tmmSubscriptions'] === true
})

const templateInfoLoading = ref(false)
const handleEditTemplate = () => {
	if (settings.tmmDstCalId === '-1') {
		return
	}
	// we need to sync template_info before we can edit template_data
	templateInfoLoading.value = true
	getTimezone(pageId, settings.tmmDstCalId).then(d => {
		if (d === null) {
			showError(t('appointments', "Can't load time zones"))
			templateInfoLoading.value = false
		} else if (d.name !== settings.template_info.tzName) {
			// we need to re-sync
			settingsStore.setOne('template_info', {
				tzName: d.name,
				tzData: d.data
			}, false, (res) => {
				templateInfoLoading.value = false
				if (res === null) {
					showError(t('appointments', "Can't sync time zones"))
				} else {
					emit('show-editor')
				}
			})
		} else {
			templateInfoLoading.value = false
			emit('show-editor')
		}
	})
}
</script>

<template>
	<div>
		<ComboSelect
				prop-name="tmmDstCalId"
				:options="calendarOptions"
				:store="settingsStore"
				:label="t('appointments','Destination calendar (Booked)')"
				:emit-input="true"
				v-on="$listeners">
			<template #help>{{ t('appointments', 'Booked/pending appointments will be placed into this calendar.') }}</template>
		</ComboSelect>
		<NcButton
				class="ps-vert-spacing"
				:disabled="settings.tmmDstCalId==='-1' || templateInfoLoading===true"
				@click="handleEditTemplate"
				:aria-label="t('appointments', 'Edit Template')">
			<template #icon>
				<IconPencil v-if="templateInfoLoading===false" :size="20"/>
				<NcLoadingIcon v-else :size="20"/>
			</template>
			{{ t('appointments', 'Edit Template') }}
		</NcButton>
		<LabelAccordion
				class="ps-vert-spacing"
				:label="t('appointments', 'Check for conflicts in…')"
				:loading="conflictCalsLoading"
				:accordion="true">
			<template #accordionIcon>
				<IconCalendarAlert :size="24"/>
			</template>
			<template #helpPopover>
				{{ t('appointments', 'These calendars will be checked for conflicting events in addition to the Destination Calendar.') }}
			</template>
			<div v-for="cal in conflictCals" :key="cal.id">
				<input
						type="checkbox"
						:value="cal"
						:disabled="conflictCalsLoading"
						v-model="conflictCalsModel"
						:id="'srgdev-appt_tmm_more_'+cal.id"
						class="checkbox ps-checkbox"/>
				<label class="srgdev-appt-sb-label-inline"
							 :class="{'ps-label-subscription':cal.isSubscription}"
							 :for="'srgdev-appt_tmm_more_'+cal.id">
					{{ cal.name }}
				</label>
			</div>
		</LabelAccordion>
	</div>
</template>

<style scoped>

</style>