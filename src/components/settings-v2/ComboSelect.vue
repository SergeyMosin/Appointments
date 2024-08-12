<script setup>
import LabelAccordion from "../LabelAccordion.vue";
import PsSelect from "../PsSelect.vue";
import {computed, useSlots} from "vue";

const emit = defineEmits(['combo-select-input'])

const slots = useSlots()
const props = defineProps({
	propName: {
		type: String,
		required: true,
	},
	options: {
		type: Array,
		required: true
	},
	store: {
		type: Object,
		required: true
	},
	label: {
		type: String,
		required: true
	},
	defaultValue: {
		type: String|Number,
		required: false,
		default: '-1'
	},
	placeholder: {
		type: String,
		required: false,
		default: t('appointments', 'Calendar Required')
	},
	emitInput: {
		type: Boolean,
		required: false,
		default: false
	},
	clickInterceptor: {
		type: Function,
		required: false
	}
})

const isLoading = computed(() => props.store.loading[props.propName] === true)
const dropdownShouldOpen = computed(() => !(props.clickInterceptor && props.store.k === false))

/**
 * @param {{value:String|Number,label:String}} evt
 */
const handleInput = (evt) => {
	if (props.emitInput === true) {
		emit('combo-select-input', {prop: props.propName, value: evt})
	} else {
		props.store.setOne(props.propName, evt.value)
	}
}
/**
 * @param {MouseEvent} evt
 */
const handleClick = (evt) => {
	if (props.store.k === false && props.clickInterceptor
			&& props.clickInterceptor({
				event: evt,
				label: props.label,
				propName: props.propName
			}) === true) {
		evt.stopPropagation()
		evt.preventDefault()
		return false
	}
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
		<PsSelect
				class="ps-vert-spacing"
				:placeholder-label="placeholder"
				:selected-value="store.settings[propName]||defaultValue"
				:options="options"
				:disabled="isLoading"
				:noDrop="clickInterceptor && store.k === false"
				@click.native="handleClick"
				@input="handleInput"/>
	</div>
</template>

<style scoped>
.ps-wide-select .ps-vert-spacing{
	width: 90%;
}
</style>