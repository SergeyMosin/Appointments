<template>
    <!-- form and input -->
    <span class="appt-stn-txt-cont">
    <form class="appt-stn-txt-form"
          :disabled="disabled" @submit.prevent="onSubmit">
        <textarea ref="appt-stn_txt" :disabled="disabled" :value="value" :placeholder="placeholderML"
                  class="appt-stn-txt-ta"/>
        <input :disabled="disabled" type="submit" value="" class="icon-confirm appt-stn-txt-submit">
    </form>
    </span>
</template>

<script>
    export default {
        name: "StnText",
        props: {
            disabled: {
                type: Boolean,
                default: false
            },
            value: {
                type: String,
                default: ''
            },
            placeholder: {
                type: String,
                default: ''
            },
            sname: {
                type: String,
                default: ''
            }
        },
        computed:{
          placeholderML:function () {
              return this.placeholder.replace(/\|/g, '\n')
          }
        },
        methods: {
            onSubmit(event) {
                event.preventDefault()
                event.stopPropagation()
                if (!this.disabled) {
                    this.$emit('submit', this.$refs['appt-stn_txt'].value,this.sname)
                } else {
                    return false
                }
            }
        }
    }
</script>
