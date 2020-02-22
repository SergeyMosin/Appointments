<!--
based on https://github.com/nextcloud/contacts/blob/master/src/components/Settings/SettingsNewAddressbook.vue
-->
<template>
	<form class="appt-stn-input-form" :disabled="disabled"
		  @submit.prevent.stop="onSubmit">
		<input :disabled="disabled"
			   :placeholder="placeholder"
			   class="appt-stn-input-field"
			   type="text"
			   autocomplete="off"
			   spellcheck="false"
			   :value="value"
			   ref="appt-stn_inp"
		>
		<input :disabled="disabled" class="icon-confirm" type="submit" value="">
	</form>
</template>

<script>
	export default {
		name: 'StnInput',
		props: {
			disabled: {
				type: Boolean,
				default: false
			},
			placeholder: {
				type: String,
				default: ""
			},
			value: {
				type: String,
				default: ""
			},
			sname: {
				type: String,
				default: ''
			}
		},
		methods:{
			onSubmit(event) {
				event.preventDefault()
				event.stopPropagation()
				if (!this.disabled) {
					this.$emit('submit', this.$refs['appt-stn_inp'].value, this.sname)
				} else {
					return false
				}
			}
		}
	}
</script>