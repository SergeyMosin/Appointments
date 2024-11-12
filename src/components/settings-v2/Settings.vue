<script setup>
import {
	NcAppSettingsDialog,
	NcAppSettingsSection,
	NcLoadingIcon,
	NcButton, NcTextField
} from "@nextcloud/vue"
import IconPreview from "vue-material-design-icons/MonitorEye.vue";
import SectionAdvanced from "./SectionAdvanced.vue";
import SectionBbb from "./SectionBbb.vue";
import SectionCalendars from "./SectionCalendars.vue";
import SectionContact from "./SectionContact.vue";
import SectionEmail from "./SectionEmail.vue";
import SectionPage from "./SectionPage.vue";
import SectionReminders from "./SectionReminders.vue";
import SectionTalk from "./SectionTalk.vue";
import SettingsDir from "./SettingsDir.vue";
import SettingsService2 from "../../services/SettingsService2";
import {CK} from "../../use/constants";
import {isDir} from "../../stores/settings_utils";
import {onMounted, ref, provide, readonly, computed, watch} from "vue";
import {parsePageUrls} from "../../use/utils";
import {showError} from "@nextcloud/dialogs";
import {usePagesStore} from "../../stores/pages";
import {useSettingsStore, LOADING_ALL, readOnlyProps} from "../../stores/settings";
import SectionSecurity from "./SectionSecurity.vue";

const settingsStore = useSettingsStore()

const pagesStore = usePagesStore()

const emit = defineEmits(['close-settings', 'show-timeslot-editor', 'show-settings-modal'])

const props = defineProps({
	pageId: {
		type: String,
		required: true
	}
})

const _isDir = isDir(props.pageId)

provide('pageId', props.pageId)

const label = computed(() => _isDir
		? t('appointments', 'Directory Page')
		: pagesStore.getPageById(props.pageId).label
)

onMounted(() => {
	settingsStore.getAllSettings(props.pageId).then((res) => {
		if (res === false) {
			handleUpdateOpen(false)
		}
	})
})

const handleUpdateOpen = (evt) => {
	if (evt === false) {
		settingsStore.cancelServiceRequest(CK.SETTINGS)
		emit('close-settings')
	}
}

let pageUrl = ref('')
const handleShowPreview = () => {
	pageUrl.value = 'loading'
	const settingsService = new SettingsService2()
	settingsService.getPageUrl(props.pageId).then(res => {
		if (res !== null) {
			const urls = parsePageUrls(res.data)
			if (urls !== null) {
				pageUrl.value = urls[0]
			}
		}
	})
}
watch(pageUrl, (url) => {
	if (url !== '' && url !== 'loading') {
		window.open(url, 'appointment_setting_preview').focus()
	}
})

const handleCKey = () => {
	settingsStore.setOldValue('__ckey', '')
	settingsStore.setOne('__ckey', settingsStore.settings.__ckey, false,
			(res) => {
				if (res !== null && res.status === 200) {
					alert(t('appointments', 'Thank You')
							+ "\n"
							+ t('appointments', 'Key accepted. All contributor only features are unlocked.'))
					settingsStore.settings.__ckey = ''
					settingsStore.k = true
				} else {
					showError(t('appointments', "Error: Please check key"))
				}
			}
	)
}

</script>

<template>
	<div>
		<NcAppSettingsDialog
				:name="label"
				:open="!!pageId"
				:class="{'hide-navigation':settingsStore.loading[LOADING_ALL]}"
				@update:open="handleUpdateOpen"
				:show-navigation="!settingsStore.loading[LOADING_ALL]">
			<template>
				<NcAppSettingsSection
						v-if="settingsStore.loading[LOADING_ALL]"
						id="loading"
						style="text-align: center"
						:name="t('appointments','Loading Settings')">
					<div style="margin-bottom: 2em;">
						<NcLoadingIcon :size="64"/>
					</div>
				</NcAppSettingsSection>
				<template v-if="!settingsStore.loading[LOADING_ALL]">
					<NcButton
							class="preview-button"
							:aria-label="t('appointments','Preview Changes')"
							:readonly="readonly"
							:disabled="pageUrl==='loading'"
							@click="handleShowPreview"
							type="tertiary-no-background">
						<template #icon>
							<NcLoadingIcon v-if="pageUrl==='loading'" :size="20"/>
							<IconPreview v-else :size="20"/>
						</template>
						{{ t('appointments', 'Preview Changes') }}
					</NcButton>

					<template v-if="_isDir===false">
						<NcAppSettingsSection
								id="calendars"
								:name="t('appointments','Calendars and Schedule')">
							<h4 class="ps-section-subtitle">
								{{ t('appointments', 'Manage appointments and calendar settings') }}
							</h4>
							<SectionCalendars
									@show-settings-modal="(data)=>{emit('show-settings-modal',data)}"
									@show-timeslot-editor="(data)=>{emit('show-timeslot-editor',data)}"
							/>
						</NcAppSettingsSection>

						<NcAppSettingsSection
								id="contact"
								:name="t('appointments','Contact Information')">
							<h4 class="ps-section-subtitle">
								{{ t('appointments', 'Form header and event organizer settings') }}
							</h4>
							<SectionContact/>
						</NcAppSettingsSection>

						<NcAppSettingsSection
								id="email"
								:name="t('appointments','Email and Notifications')">
							<h4 class="ps-section-subtitle">
								{{ t('appointments', 'Control when emails and notifications are sent') }}
							</h4>
							<SectionEmail/>
						</NcAppSettingsSection>

						<NcAppSettingsSection
								id="page_and_form"
								:name="t('appointments','Form Settings')">
							<h4 class="ps-section-subtitle">
								{{ t('appointments', 'Control what your visitors see') }}
							</h4>
							<SectionPage/>
						</NcAppSettingsSection>

						<NcAppSettingsSection
								id="security"
								:name="t('appointments','Security')">
							<h4 class="ps-section-subtitle">
								{{ t('appointments', 'Security related settings') }}
							</h4>
							<SectionSecurity/>
						</NcAppSettingsSection>

						<NcAppSettingsSection
								id="reminders"
								:name="t('appointments','Reminders')">
							<h4 class="ps-section-subtitle">
								{{ t('appointments', 'Send appointment reminders to attendees') }}
							</h4>
							<SectionReminders @show-settings-modal="(data)=>{emit('show-settings-modal',data)}"/>
						</NcAppSettingsSection>

						<NcAppSettingsSection
								v-if="readOnlyProps['talk_integration_disabled']===false"
								id="talk"
								:name="t('appointments','Talk Integration')">
							<h4 class="ps-section-subtitle">
								{{ t('appointments', 'Talk room settings for appointments') }}
							</h4>
							<SectionTalk @show-settings-modal="(data)=>{emit('show-settings-modal',data)}"/>
						</NcAppSettingsSection>

						<NcAppSettingsSection
								v-if="readOnlyProps['bbb_integration_disabled']===false"
								id="bbb"
								:name="t('appointments','Video Integration')">
							<h4 class="ps-section-subtitle">
								{{ t('appointments', 'Video room settings for appointments') }}
							</h4>
							<SectionBbb/>
						</NcAppSettingsSection>

						<NcAppSettingsSection
								id="advanced"
								:name="t('appointments','Advanced')">
							<h4 class="ps-section-subtitle">
								{{ t('appointments', 'Advanced settings and configurations') }}
							</h4>
							<SectionAdvanced/>
						</NcAppSettingsSection>

						<NcAppSettingsSection
								id="ckey"
								v-if="settingsStore.k ===false && settingsStore.settings.__ckey!==undefined"
								:name="t('appointments','Contributor Key')">
							<div class="ps-section-wrap">
								<NcTextField
										:label="settingsStore.loading['__ckey'] === true?t('appointments','Verifying key'):t('appointments','Enter key')"
										class="ps-text-field  ps-vert-spacing"
										@trailing-button-click="handleCKey"
										@keyup.enter="handleCKey"
										:value.sync="settingsStore.settings.__ckey"
										:disabled="settingsStore.loading['__ckey'] === true"
										:showTrailingButton="true"
										trailingButtonIcon="arrowRight"/>
							</div>
						</NcAppSettingsSection>
					</template>
					<SettingsDir v-else/>
				</template>
			</template>
		</NcAppSettingsDialog>
	</div>
</template>

<style scoped>
.dialog .preview-button {
	position: absolute;
	top: 3px;
	left: 1em;
	font-size: 84%;
	opacity: .85;
}

.dialog .preview-button:hover {
	opacity: 1
}

.dialog .preview-button >>> .button-vue__text {
	font-weight: normal;
}

/* TODO: remove when ':show-navigation' prop is fixed */
.hide-navigation >>> .app-settings__navigation {
	display: none;
}

.app-settings-section {
	margin-bottom: 64px;
}
</style>