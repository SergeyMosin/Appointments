<script setup>
import {useSettingsStore} from "../../stores/settings";
import ComboInput from "./ComboInput.vue";
import ComboCheckbox from "./ComboCheckbox.vue";
const settingsStore = useSettingsStore()
const settings = settingsStore.settings
</script>

<template>
	<div class="ps-section-wrap">
		<ComboInput
				prop-name="formTitle"
				:label="t('appointments', 'Form title')"
				:store="settingsStore"/>

		<ComboInput
				type="textarea"
				prop-name="cnfNote"
				:label="t('appointments', 'Some text area')"
				:store="settingsStore"/>

		<ComboCheckbox
				prop-name="endTime"
				class="ps-vert-spacing"
				:label="t('appointments', 'Show end time')"
				:store="settingsStore"/>

	</div>
</template>

<style scoped>

</style>
