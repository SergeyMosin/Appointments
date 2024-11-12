<script setup>
import {computed, useSlots} from "vue";
import LabelAccordion from "../LabelAccordion.vue";
import {NcTextField, NcPasswordField} from "@nextcloud/vue";

const slots = useSlots()
const props = defineProps({
	propName: {
		type: String,
		required: true,
	},
	store: {
		type: Object,
		required: true
	},
	label: {
		type: String,
		required: true
	},
	settingsType: {
		type: String,
		required: false,
		default: 'settings'
	},
	type: {
		type: String,
		required: false,
		default: 'text'
	},
	placeholder: {
		type: String,
		required: false
	},
	disabled: {
		Type: Boolean,
		required: false
	},
	focusInterceptor: {
		type: Function,
		required: false
	}
})

const settings = props.store[props.settingsType];

const isLoading = computed(() => props.store.loading[props.propName] === true)

const handleFocus = (evt) => {
	if (props.focusInterceptor && props.focusInterceptor({
		event: evt,
		label: props.label,
		propName: props.propName
	}) === true) {
		evt.preventDefault()
		evt.stopPropagation()
		evt.currentTarget.blur()
		return
	}
	props.store.setOldValue(props.propName, settings[props.propName])
}
</script>

<template>
	<div>
		<LabelAccordion
				:label="label"
				:loading="isLoading">
			<template #helpPopover v-if="slots?.help">
				<slot name="help"/>
			</template>
		</LabelAccordion>
		<textarea
				v-if="type==='textarea'"
				:placeholder="placeholder"
				class="ps-textarea ps-vert-spacing"
				v-model="settings[propName]"
				@focus="handleFocus"
				@blur="store.setOne(propName,settings[propName])"/>
		<NcPasswordField
				v-else-if="type==='password'"
				:label-outside="true"
				:placeholder="placeholder"
				class="ps-text-field  ps-vert-spacing"
				:type="type"
				:disabled="disabled || isLoading"
				:value.sync="settings[propName]"
				@focus="handleFocus"
				@keyup.enter="store.setOne(propName, settings[propName])"
				@blur="store.setOne(propName, settings[propName])"/>
		<NcTextField
				v-else
				:label-outside="true"
				:placeholder="placeholder"
				class="ps-text-field  ps-vert-spacing"
				:type="type"
				:disabled="disabled || isLoading"
				:value.sync="settings[propName]"
				@focus="handleFocus"
				@keyup.enter="store.setOne(propName, settings[propName])"
				@blur="store.setOne(propName, settings[propName])"/>
	</div>
</template>

<style scoped>
</style>