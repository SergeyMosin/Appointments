<template>
    <div>
    <div
            :class="[isOpen ? 'icon-triangle-s' : 'icon-triangle-e', 'appt-accordion-title']"
            @click="toggle">{{title}}</div>
    <div
            class="appt-accordion-content"
            v-show="isOpen">
        <slot name="content" />
    </div>
    </div>
</template>

<script>
    export default {
        name: "ApptAccordion",
        props: {
            title: {
                type: String,
                default: '',
                required: true
            },
            open: {
                type: Boolean,
                default: false
            }
        },
        data: function() {
            return {
                isOpen: this.open
            }
        },
        methods: {
            toggle() {
                this.isOpen=!this.isOpen
            },
        }

    }
</script>

<style scoped>
    .appt-accordion-title{
        margin-bottom: .25em;
        cursor: pointer;
        background-position: left center;
        padding-left: 1.75em;
        background-size: 1.5em;
        margin-left: -.5em;
    }
    .appt-accordion-content{
        padding-left: 1.25em;
    }
</style>