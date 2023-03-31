<script setup>
import {NcCheckboxRadioSwitch} from "@nextcloud/vue";

const props = defineProps({
	label: String
})
</script>

<template>
	<NcCheckboxRadioSwitch
			v-bind="$attrs"
			v-on="$listeners">
		{{ label }}
	</NcCheckboxRadioSwitch>
</template>

<style scoped>
>>> .checkbox-radio-switch__content {
	min-height: 32px;
}
</style>