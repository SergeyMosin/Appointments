<script setup>
import {
	NcModal,
	NcTextField,
	NcActions,
	NcActionButton,
	NcButton,
	NcLoadingIcon
} from "@nextcloud/vue";
import IconLinkPlus from "vue-material-design-icons/LinkVariantPlus.vue";
import IconWeb from "vue-material-design-icons/Web.vue";
import IconSave from "vue-material-design-icons/ContentSaveOutline.vue";
import IconCancel from "vue-material-design-icons/Cancel.vue";
import {INDEX} from "../../use/constants";
import {usePagesStore} from "../../stores/pages";
import {useSettingsStore} from "../../stores/settings";
import {ref} from "vue";
import {parsePageUrls} from "../../use/utils";

const pagesStore = usePagesStore()
const settingsStore = useSettingsStore()

const emit = defineEmits(['close-modal'])
const props = defineProps({
	index: {
		type: Number,
		required: true,
	},
})

const item = props.index !== INDEX.NEW && settingsStore.dirSettings.dirItems[props.index] !== undefined
		? {...settingsStore.dirSettings.dirItems[props.index]}
		: {
			title: '',
			subTitle: '',
			text: '',
			url: '',
		}
const urlRef = ref(item.url)

const handleSelectPageUrl = (pageId) => {
	pagesStore.getPageUrl(pageId, 'E_URL').then(res => {
		if (res !== null) {
			const urls = parsePageUrls(res.data)
			if (urls !== null) {
				urlRef.value = urls[0]
			}
		}
	})
}

const handelSaveItem = () => {
	const cloned = settingsStore.dirSettings.dirItems.map(item => ({...item}))
	item.url = urlRef.value
	if (props.index === INDEX.NEW) {
		cloned.push(item)
	} else {
		cloned[props.index] = item
	}

	// we are using setOldValue because we want the spinner
	settingsStore.setOldValue('dirItems', settingsStore.dirSettings.dirItems)

	settingsStore.setOne('dirItems', cloned, false, (res) => {
		if (res !== null) {
			settingsStore.dirSettings.dirItems = cloned
			handleUpdateShow(false)
		}
	})
}

const handleUpdateShow = (evt) => {
	if (evt === false) {
		emit('close-modal')
	}
}

</script>

<template>
	<NcModal
			style="z-index: 10002"
			:setReturnFocus="false"
			:closeOnClickOutside="false"
			@update:show="handleUpdateShow">
		<h4 class="tao-h4">{{ t('appointments', 'Directory Item Editor') }}</h4>
		<div class="srgdev-appt-modal-wrap" style="text-align: left">
			<NcTextField
					:value.sync="item.title"
					class="dir-editor-spacer"
					:label="t('appointments', 'Title')"/>
			<NcTextField
					:value.sync="item.subTitle"
					class="dir-editor-spacer"
					:label="t('appointments', 'Subtitle')"/>
			<NcTextField
					:value.sync="item.text"
					class="dir-editor-spacer"
					:label="t('appointments', 'Text')"/>
			<div class="dir-editor-dual-flex">
				<NcTextField
						:disabled="pagesStore.loading!==''"
						:value.sync="urlRef"
						style="margin-top: 0"
						:label="t('appointments', 'URL')"/>
				<NcActions
						:disabled="pagesStore.loading!==''"
						:menu-name="t('appointments', 'Select Page')"
						:forceMenu="true">
					<template #icon>
						<NcLoadingIcon v-if="pagesStore.loading==='E_URL'" :size="20"/>
						<IconLinkPlus v-else :size="20"/>
					</template>
					<NcActionButton
							v-for="page in pagesStore.pages"
							:key="page.id"
							:closeAfterClick="true"
							@click="handleSelectPageUrl(page.id)">
						<template #icon>
							<IconWeb :size="20"/>
						</template>
						{{ page.label }}
					</NcActionButton>
				</NcActions>
			</div>
			<div class="dir-editor-dual-flex">
				<NcButton
						:disabled="pagesStore.loading!=='' || settingsStore.loading['dirItems']===true"
						class="dir-editor-btn"
						type="primary"
						@click="handelSaveItem">
					<template #icon>
						<NcLoadingIcon v-if="settingsStore.loading['dirItems']===true" :size="20"/>
						<IconSave v-else :size="20"/>
					</template>
					{{ t('appointments', 'Save') }}
				</NcButton>
				<NcButton
						class="dir-editor-btn"
						@click="handleUpdateShow(false)">
					<template #icon>
						<IconCancel :size="20"/>
					</template>
					{{ t('appointments', 'Cancel') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<style scoped>
.tao-h4 {
	font-size: 100%;
	padding: 0 0 1em 2em;
	border-bottom: 1px solid var(--color-border);
	font-weight: bold;
	margin: 1em 0;
}

.dir-editor-spacer {
	margin-bottom: 1.25em;
}

.dir-editor-dual-flex {
	display: flex;
	align-items: center;
	gap: .5em;
	margin-bottom: 1.75em;
}

.dir-editor-btn {
	display: inline-flex;
	margin-right: 1em;
}
</style>