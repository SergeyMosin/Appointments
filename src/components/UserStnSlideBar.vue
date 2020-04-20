<template>
    <SlideBar :title="t('appointments','Your Contact Information')" :subtitle="t('appointments','Form header and event organizer settings')" @close="close">
        <template slot="main-area">
            <div class="srgdev-appt-sb-main-cont">
                <label
                        class="srgdev-appt-sb-label"
                        for="srgdev-appt_uci-org-name">
                    {{t('appointments','Name:')}}</label>
                <input
                        v-model="uciInfo.organization"
                        class="srgdev-appt-sb-input-text"
                        id="srgdev-appt_uci-org-name"
                        type="text">
                <div class="srgdev-appt-info-lcont">
                    <label class="srgdev-appt-sb-label" for="srgdev-appt_uci-org-email">{{t('appointments','Email:')}}</label><a style="right: 4%" class="icon-info srgdev-appt-info-link"
                        @click="$root.$emit('helpWanted','emaildef')"></a>
                </div>
                <input
                        v-model="uciInfo.email"
                        class="srgdev-appt-sb-input-text"
                        id="srgdev-appt_uci-org-email"
                        type="email">
                <label
                        class="srgdev-appt-sb-label"
                        for="srgdev-appt_uci-org-address">
                    {{t('appointments','Location:')}}</label>
                <textarea
                        v-model="uciInfo.address"
                        class="srgdev-appt-sb-textarea"
                        id="srgdev-appt_uci-org-address"
                        style="overflow: auto;resize: none"
                ></textarea>
                <label
                        class="srgdev-appt-sb-label"
                        for="srgdev-appt_uci-org-phone">
                    {{t('appointments','Phone:')}}</label>
                <input
                        v-model="uciInfo.phone"
                        class="srgdev-appt-sb-input-text"
                        id="srgdev-appt_uci-org-phone"
                        style="max-width: 20em"
                        type="tel">
                <button
                        @click="apply"
                        class="primary srgdev-appt-sb-genbtn">{{t('appointments','Apply')}}
                </button>

            </div>
        </template>
    </SlideBar>
</template>

<script>
    import SlideBar from "./SlideBar.vue"

    export default {
        name: "MailStnSlideBar",
        components: {
            SlideBar
        },
        props:{
            title:'',
            subtitle:'',
            uciInfo: {
                type: Object,
                default: function () {
                    return {organization: "", email: "", address: "", phone: ""}
                }
            }
        },
        methods: {
            apply(){
                this.$emit('apply',this.uciInfo)
            },
            close(){
                this.$emit('close')
            }
        }
    }
</script>
