<script setup>
import {ref} from "vue";
import {useSettingsStore} from "../../stores/settings";
import {NcButton, NcNoteCard} from "@nextcloud/vue"
import {showError, showSuccess} from "@nextcloud/dialogs";

const settingsStore = useSettingsStore()

const busy = ref(false)
const fileInput = ref(null)
const result = ref(null)

async function handleExport() {
	busy.value = true
	try {
		const data = await settingsStore.exportPage()
		if (data === null) {
			return
		}
		const text = typeof data === 'string' ? data : JSON.stringify(data, null, 2)
		const blob = new Blob([text], {type: 'application/json'})
		const url = URL.createObjectURL(blob)
		const a = document.createElement('a')
		const label = (settingsStore.settings.label || 'page').replace(/[^A-Za-z0-9_-]+/g, '-')
		a.href = url
		a.download = 'appointments-' + label + '.json'
		document.body.appendChild(a)
		a.click()
		a.remove()
		URL.revokeObjectURL(url)
	} finally {
		busy.value = false
	}
}

function pickFile() {
	result.value = null
	fileInput.value.value = ''
	fileInput.value.click()
}

async function handleFile(evt) {
	const file = evt.target.files[0]
	if (!file) {
		return
	}
	let text
	try {
		text = await file.text()
		const doc = JSON.parse(text)
		if (doc.format !== 'appointments-page') {
			throw new Error('format')
		}
	} catch (e) {
		showError(t('appointments', 'This file is not an exported page settings file.'))
		return
	}
	if (!window.confirm(t('appointments', 'Replace the settings of this page with the settings from "{file}"? Settings missing from the file are reset to their defaults. The page URL does not change.', {file: file.name}))) {
		return
	}
	busy.value = true
	try {
		const data = await settingsStore.importPage(text)
		if (data === null) {
			return
		}
		if (data.error) {
			showError(data.error)
			return
		}
		result.value = data
		if (data.errors.length === 0) {
			showSuccess(t('appointments', 'Settings imported'))
		}
	} finally {
		busy.value = false
	}
}
</script>

<template>
	<div class="ps-section-wrap">
		<div class="ps-vert-spacing backup-buttons">
			<NcButton :disabled="busy" @click="handleExport">
				{{ t('appointments', 'Export settings') }}
			</NcButton>
			<NcButton :disabled="busy" @click="pickFile">
				{{ t('appointments', 'Import settings') }}
			</NcButton>
			<input ref="fileInput"
					type="file"
					accept="application/json,.json"
					style="display: none"
					@change="handleFile">
		</div>
		<NcNoteCard type="info">
			{{ t('appointments', 'The export is a JSON file with all settings of this page that differ from the defaults, including reminders. Secrets such as the hCaptcha secret key are never exported. Importing applies the file to this page; the page URL is kept.') }}
		</NcNoteCard>
		<NcNoteCard
				v-if="result !== null && (result.errors.length > 0 || result.warnings.length > 0)"
				:type="result.errors.length > 0 ? 'error' : 'warning'"
				:heading="t('appointments', 'Import finished with problems')">
			<ul>
				<li v-for="(msg, i) in result.errors.concat(result.warnings)" :key="i">{{ msg }}</li>
			</ul>
		</NcNoteCard>
	</div>
</template>

<style scoped>
.backup-buttons {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
}
</style>
