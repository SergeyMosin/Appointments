<script setup>
import PsCheckbox from "../PsCheckbox.vue";
import {computed, useSlots} from "vue";
import {NcPopover} from "@nextcloud/vue";
import IconInfo from "vue-material-design-icons/InformationOutline.vue";

const slots = useSlots()
const emit = defineEmits(['value-updated'])

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
	disabled: {
		type: Boolean,
		required: false,
		default: false
	},
	indeterminate: {
		type: Boolean,
		required: false,
		default: false
	},
	clickInterceptor: {
		type: Function,
		required: false
	}
})

const settings = props.store[props.settingsType];

const isLoading = computed(() => props.store.loading[props.propName] === true)

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
	<div class="wrapper-checkbox"
			 :class="{'wrapper-checkbox__help-padding':slots?.help}">
		<PsCheckbox
				:label="label"
				:checked="settings[propName]"
				:disabled="isLoading || disabled"
				:loading="isLoading"
				:indeterminate="indeterminate"
				class="ps-settings-checkbox"
				@click.native.capture="handleClick"
				@update:checked="(value)=>{store.setOne(propName,value);emit('value-updated',{propName: propName, value: value})}"/>
		<div v-if="slots?.help" class="wrapper-checkbox-help">
			<NcPopover>
				<template #trigger>
					<IconInfo class="wrapper-help-icon" :size="16"/>
				</template>
				<template>
					<div class="wrapper-help-content">
						<slot name="help"/>
					</div>
				</template>
			</NcPopover>
		</div>
	</div>
</template>

<style scoped>

.wrapper-checkbox {
	position: relative;
}

.wrapper-checkbox__help-padding {
	padding-right: 20px;
}

.wrapper-checkbox-help {
	position: absolute;
	right: 0;
	top: 50%;
	margin-top: -8px;
}

.wrapper-help-icon {
	cursor: pointer;
	opacity: .5;
}

.wrapper-checkbox:hover .wrapper-help-icon {
	opacity: 1;
}

.wrapper-help-content {
	padding: .5em;
	max-width: 32em;
}

.wrapper-checkbox >>> .checkbox-radio-switch {
	display: inline-flex;
}
</style>