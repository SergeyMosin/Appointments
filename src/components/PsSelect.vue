<script setup>
import {NcSelect} from "@nextcloud/vue";
import {computed, useAttrs} from "vue";

const props = defineProps({
	selectedValue: [Number, String],
	placeholderLabel: String
})
const attrs = useAttrs()

const selectedLabel = computed(() => {
	const opt = attrs.options.find((item) => item.value === props.selectedValue)
	return opt
			? opt.label
			: props.placeholderLabel
					? props.placeholderLabel
					: t('appointments', 'Select One Option')
})

</script>

<template>
	<NcSelect
			v-bind="$attrs"
			v-on="$listeners"
			:value="selectedLabel"
			:labelOutside="true"
			class="ps-nc-select-internal"
			:clearable="false"/>
</template>

<style scoped>
.ps-nc-select-internal.v-select,
.ps-nc-select-internal.v-select >>> .vs__dropdown-toggle {
	height: 36px;
	min-height: 36px;
}

.ps-nc-select-internal.v-select >>> .vs__selected-options,
.ps-nc-select-internal.v-select >>> .vs__selected {
	height: 32px;
	min-height: 32px;
	padding-bottom: 0;
}

.ps-nc-select-internal.v-select >>> .vs__search {
	height: 32px !important;
	margin: 0;
}

.ps-nc-select-internal.v-select >>> .vs__selected {
	margin-top: 0;
}

.ps-nc-select-internal.v-select >>> .vs__search {
	opacity: 0;
	pointer-events: none;
}
</style>