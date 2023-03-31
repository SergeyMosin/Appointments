<script setup>
import IconMenu from "vue-material-design-icons/MenuSwapOutline.vue";
import IconInfo from "vue-material-design-icons/InformationOutline.vue";
import NcLoadingIcon from "@nextcloud/vue/dist/Components/NcLoadingIcon.js";
import {NcPopover} from "@nextcloud/vue"
import {ref, useSlots} from "vue";

const slots = useSlots()

const emit = defineEmits(['open-accordion'])

const props = defineProps({
	label: String,
	loading: Boolean,
	accordion: Boolean
})

const isOpen = ref(false)

const toggleOpen = () => {
	if (props.accordion === true) {
		if (isOpen.value === false) {
			emit('open-accordion')
		}
		isOpen.value = !isOpen.value
	}
}

</script>

<template>
	<div>
		<div class="flex-wrapper">
			<label
					v-bind="$attrs"
					@click="toggleOpen"
					:class="[
						'wrapper-label',
						{'wrapper-label-accordion':accordion}
				]">
			<span
					v-if="accordion"
					class="wrapper-label-arrow">
				<slot name="accordionIcon">
					<IconMenu :size="24"/>
				</slot>
			</span>
				<span :class="['wrapper-label-text',{'wrapper-label-text-open':isOpen}]">
				{{ label }}
			</span>
				<span v-if="loading" class="wrapper-label-loading">
				<NcLoadingIcon :size="16"/>
			</span>
			</label>
			<div v-if="slots?.helpPopover" class="wrapper-help">
				<NcPopover :focus-trap="false">
					<template #trigger>
						<IconInfo class="wrapper-help-icon" :size="16"/>
					</template>
					<template>
						<div class="wrapper-help-content">
							<slot name="helpPopover"/>
						</div>
					</template>
				</NcPopover>
			</div>
		</div>
		<div v-if="accordion && isOpen"
				 class="wrapper-slot">
			<slot/>
		</div>
	</div>
</template>

<style scoped>
.flex-wrapper {
	display: flex;
	align-items: center;
	justify-content: space-between;
}

.wrapper-label {
	display: inline-block;
	overflow: hidden;
	vertical-align: middle;
}

.wrapper-label-accordion,
.wrapper-label-accordion > .wrapper-label-arrow,
.wrapper-label-accordion > .wrapper-label-text {
	cursor: pointer;
}

.wrapper-label-text {
	display: inline-block;
	vertical-align: middle;
}

.wrapper-label-text-open {
	font-weight: bold;;
}

.wrapper-label-arrow,
.wrapper-label-loading {
	display: inline-block;
	vertical-align: middle;
}

.wrapper-label-arrow {
	//margin-left: -8px;
	margin-right: .5em;
}

.wrapper-label-arrow >>> .material-design-icon {
	cursor: pointer;
}

.wrapper-label-loading {
	margin-left: .125em;
}

.wrapper-help-icon {
	cursor: pointer;
	opacity: .5;
}

.wrapper-help-content {
	padding: .5em;
	max-width: 32em;
}

.flex-wrapper:hover .wrapper-help-icon {
	opacity: 1;
}

.wrapper-slot {
	margin-top: .5em;
	margin-left: 2.25em;
}

</style>