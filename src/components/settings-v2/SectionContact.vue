<script setup>
import {useSettingsStore} from "../../stores/settings";
import ComboInput from "./ComboInput.vue";
const settingsStore = useSettingsStore()
</script>

<template>
	<div class="ps-section-wrap">
		<ComboInput
				prop-name="organization"
				:label="t('appointments', 'Name/Organization')"
				:store="settingsStore"/>
		<ComboInput
				prop-name="email"
				type="email"
				:placeholder="t('appointments', 'Set to User Profile email')"
				:label="t('appointments', 'Organizer Email')"
				:store="settingsStore"/>
		<ComboInput
				type="textarea"
				prop-name="address"
				:label="t('appointments', 'Location')"
				:store="settingsStore"/>
		<ComboInput
				prop-name="phone"
				type="tel"
				:label="t('appointments', 'Phone')"
				:store="settingsStore"/>
	</div>
</template>

<style scoped>
</style>
