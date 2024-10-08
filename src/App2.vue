<script setup>
import {reactive} from "vue";
import {
	NcContent,
	NcAppContent,
	NcLoadingIcon
} from '@nextcloud/vue'
import Navigation2 from "./components/Navigation2.vue";
import Settings from "./components/settings-v2/Settings.vue";
import TimeSlotEditor from "./components/views/TimeSlotEditor.vue";
import SettingsInfoModal from "./components/modals/SettingsInfoModal.vue";
import {CONTEXT, MODAL} from "./use/constants";
import {usePagesStore} from "./stores/pages";

const pagesStore = usePagesStore();

const state = reactive({
	settingsPageId: '',
	context: CONTEXT.PREVIEW,
	contextData: null,
	modalData: {
		type: MODAL.NONE,
		message: ''
	}
})

const handleShowSettingsInfoModal = (data) => {
	state.modalData.type = data.type;
	state.modalData.message = data.message
}

const handleCloseSettings = () => {
	if (state.settingsPageId !== '') {
		showPage(state.settingsPageId)
	}
	state.settingsPageId = '';
}

/**
 * @param {string} pageId
 * @param {boolean} [reload=true]
 */
const showPage = (pageId, reload = true) => {
	state.context = CONTEXT.PREVIEW
	if (pageId === '-1') {
		state.contextData = null
	} else {

		if (reload === false && state.contextData && state.contextData.pageUrl
				&& state.contextData.pageUrl.includes('p=' + pageId + '&')) {
			return
		}

		if (pageId[0] === 'p') {
			state.contextData = {
				pageLoading: true,
				pageUrl: 'form?p=' + pageId + '&v=' + Date.now(),
				...pagesStore.getPageById(pageId)
			}
		} else {
			state.contextData = {
				pageLoading: true,
				pageUrl: 'dir?p=' + pageId + '&v=' + Date.now(),
				...pagesStore.dirPage
			}
		}
	}
}

/** @param {string} pageId */
const showSettings = (pageId) => {
	state.settingsPageId = pageId
}

let lastPageId = '-1'
const showTimeSlotEditor = (data) => {
	lastPageId = state.settingsPageId || '-1'
	state.settingsPageId = ''
	state.context = CONTEXT.EDITOR
	state.contextData = data
}

const handleHideTimeslotEditor = () => {
	showPage(lastPageId || '-1')
}

</script>

<template>
	<NcContent app-name="appointments">
		<Navigation2
				@show-page="showPage"
				@show-settings-modal="handleShowSettingsInfoModal"
				@show-settings="showSettings"/>
		<NcAppContent style="position: relative">
			<div style="height: 100%" v-if="state.context===CONTEXT.PREVIEW">
				<div v-if="state.contextData===null" class="no-page-selected">
					{{ t('appointments', 'Please select or create a page') }}
				</div>
				<template v-else>
					<div class="srgdev-appt-main-info">
						<NcLoadingIcon
								v-if="state.contextData.pageLoading"
								class="page-loading-spinner"
								:size="18"/>
						<span>{{ state.contextData.label + ' ' + t('appointments', 'Preview') }}</span>
						<div
								class="page-disabled-preview-tag"
								v-if="state.contextData.enabled === false"> {{ t('appointments', 'Page Is Not Enabled') }}
						</div>
					</div>
					<div class="srgdev-appt-main-frame-cont">
						<iframe
								class="srgdev-appt-main-frame"
								@load="state.contextData.pageLoading=false"
								ref="pubPageRef"
								:src="state.contextData.pageUrl"></iframe>
					</div>
				</template>
			</div>
			<TimeSlotEditor
					v-if="state.context===CONTEXT.EDITOR"
					:data="state.contextData"
					@hide-timeslot-editor="handleHideTimeslotEditor"
			/>
		</NcAppContent>
		<Settings
				style="z-index: 9998"
				v-if="!!state.settingsPageId"
				:page-id="state.settingsPageId"
				@close-settings="handleCloseSettings"
				@show-settings-modal="handleShowSettingsInfoModal"
				@show-timeslot-editor="showTimeSlotEditor"/>
		<SettingsInfoModal
				v-if="state.modalData.type!==MODAL.NONE"
				:data="state.modalData"
				@hide-settings-modal="()=>{state.modalData.type=MODAL.NONE}"/>
	</NcContent>
</template>

<style scoped>
.no-page-selected {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	font-size: 250%;
	opacity: .5;
	font-weight: bold;
	width: 100%;
	height: 100%;
}

.page-loading-spinner {
	display: inline-flex !important;
	vertical-align: middle;
	margin-right: .25em;
	margin-left: -0.75em;
}
.page-disabled-preview-tag{
	color: darkred;
	font-weight: bold;
}
</style>