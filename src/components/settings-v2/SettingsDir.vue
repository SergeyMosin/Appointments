<script setup>

import {useSettingsStore, LOADING_DIR} from "../../stores/settings";
import {
	NcActionButton,
	NcActions, NcAppSettingsSection,
	NcButton,
	NcLoadingIcon
} from "@nextcloud/vue";
import IconPencil from "vue-material-design-icons/Pencil.vue";
import IconPlus from "vue-material-design-icons/Plus.vue";
import IconDelete from "vue-material-design-icons/Delete.vue";
import DirItemEditorModal from "../modals/DirItemEditorModal.vue";
import {reactive, nextTick} from "vue";
import {INDEX} from "../../use/constants";
import ComboInput from "./ComboInput.vue";
import ComboCheckbox from "./ComboCheckbox.vue";

const settingsStore = useSettingsStore()

const state = reactive({
	editorIndex: INDEX.NONE,
	loadingIndex: INDEX.NONE,
})

const addDirItem = () => {
	state.editorIndex = INDEX.NEW
}

const editDirItem = (index) => {
	nextTick(() => {
		state.editorIndex = index
	})
}

const deleteDirItem = (deleteIndex) => {
	state.loadingIndex = deleteIndex

	// we are using clone/oldValue because we want the spinner while deleting
	const clone = settingsStore.dirSettings.dirItems.map(item => ({...item}))
	settingsStore.setOldValue('dirItems', clone)

	const data = settingsStore.dirSettings.dirItems.filter((_, index) => index !== deleteIndex)

	settingsStore.setOne('dirItems', data, false, (res) => {
		if (res !== null) {
			settingsStore.dirSettings.dirItems = data
		}
		state.loadingIndex = INDEX.NONE
	})
}
</script>

<template>
	<div>
		<NcAppSettingsSection
				id="dir-items"
				:name="t('appointments','Items/Links')">
			<div class="ps-section-wrap">
				<div v-if="settingsStore.dirSettings.dirItems.length===0" class="dir-items-empty">
					{{ t('appointments', 'no items found') }}
				</div>
				<div v-for="(item,index) in settingsStore.dirSettings.dirItems" class="srgdev-appt-dir-pl">
					<NcActions
							:disabled="settingsStore.loading[LOADING_DIR]===true"
							menuAlign="right"
							style="position: absolute"
							class="srgdev-appt-dir-pl_actions">
						<template #icon>
							<NcLoadingIcon v-if="state.loadingIndex===index" :size="20"/>
						</template>
						<NcActionButton
								:closeAfterClick="true"
								@click="editDirItem(index)">
							<template #icon>
								<IconPencil :size="20"/>
							</template>
							{{ t('appointments', 'Edit') }}
						</NcActionButton>
						<NcActionButton
								:closeAfterClick="true"
								@click="deleteDirItem(index)">
							<template #icon>
								<IconDelete :size="20"/>
							</template>
							{{ t('appointments', 'Delete') }}
						</NcActionButton>
					</NcActions>
					<div class="srgdev-appt-dir-pl_title">{{ item.title }}</div>
					<div class="srgdev-appt-dir-pl_sub">{{ item.subTitle }}</div>
					<div class="srgdev-appt-dir-pl_url">{{ item.url }}</div>
				</div>
				<NcButton
						class="dir-add-new-button"
						:aria-label="t('appointments', 'Add New Item')"
						type="primary"
						:disabled="settingsStore.loading[LOADING_DIR]===true"
						@click="addDirItem">
					<template #icon>
						<IconPlus :size="20"/>
					</template>
					{{ t('appointments', 'Add New Item') }}
				</NcButton>
			</div>
		</NcAppSettingsSection>
		<NcAppSettingsSection
				id="dir-page-settings"
				:name="t('appointments','Page Settings')">
			<div class="ps-section-wrap">
				<ComboInput
						prop-name="pageTitle"
						:label="t('appointments', 'Page Header Title')"
						settings-type="dirSettings"
						:store="settingsStore"/>
				<ComboCheckbox
						class="ps-vert-spacing"
						prop-name="useNcTheme"
						:label="t('appointments', 'Auto Style')"
						settings-type="dirSettings"
						:store="settingsStore"/>
				<!--				<ComboCheckbox-->
				<!--						class="ps-vert-spacing"-->
				<!--						prop-name="privatePage"-->
				<!--						:label="t('appointments', 'Private (visitors must be logged-in)')"-->
				<!--						settings-type="dirSettings"-->
				<!--						:store="settingsStore"/>-->
				<ComboInput
						prop-name="pageStyle"
						type="textarea"
						:label="t('appointments', 'Style Override')"
						placeholder="&lt;style&gt;...&lt;/style&gt;"
						settings-type="dirSettings"
						:store="settingsStore"/>
			</div>
		</NcAppSettingsSection>
		<DirItemEditorModal
				v-if="state.editorIndex!==INDEX.NONE"
				:index="state.editorIndex"
				@close-modal="state.editorIndex=INDEX.NONE"/>
	</div>
</template>

<style scoped>

.dir-items-empty {
	font-size: 175%;
	text-align: center;
	padding: 1.5em 0;
	opacity: .5;
	font-weight: bold
}

.dir-add-new-button {
	/*noinspection CssInvalidPropertyValue*/
	position: -webkit-sticky;
	position: sticky;
	bottom: 0;
	margin-top: 1.75em;
	margin-bottom: .25em
}

.app-settings-section {
	margin-bottom: 40px;
}
</style>