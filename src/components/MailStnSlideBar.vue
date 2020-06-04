<template>
    <SlideBar :title="t('appointments','Emails and Notifications')" :subtitle="t('appointments','Control when emails and notifications are sent')" @close="close">
        <template slot="main-area">
            <div class="srgdev-appt-sb-main-cont">
                <input
                        v-model="emlInfo.icsFile"
                        type="checkbox"
                        id="srgdev-appt_emn-ics-file"
                        class="checkbox"><label style="margin-left: -3px;" class="srgdev-appt-sb-label-inline" for="srgdev-appt_emn-ics-file">{{t('appointments','Attach .ics file to confirm/cancel emails')}}</label><br><br>
                <div class="srgdev-appt-info-lcont">
                    <span class="srgdev-appt-sb-label">{{t('appointments','Email Attendee when the appointment is:')}}</span><a
                        class="icon-info srgdev-appt-info-link"
                        @click="$root.$emit('helpWanted','emailatt')"></a>
                </div>
                <div class="srgdev-appt-sb-indent">
                    <input
                            v-model="emlInfo.attMod"
                            type="checkbox"
                            id="srgdev-appt_emn-att-modified"
                            class="checkbox"><label class="srgdev-appt-sb-label-inline" for="srgdev-appt_emn-att-modified">{{t('appointments','Modified (Time, Status, Location)')}}</label><br>
                    <input
                            v-model="emlInfo.attDel"
                            type="checkbox"
                            id="srgdev-appt_emn-att-deleted"
                            class="checkbox"><label class="srgdev-appt-sb-label-inline" for="srgdev-appt_emn-att-deleted">{{t('appointments','Deleted')}}</label><br>
                </div>
                <div class="srgdev-appt-info-lcont">
                    <span class="srgdev-appt-sb-label">{{t('appointments','Email Me when an appointment is:')}}</span><a
                        class="icon-info srgdev-appt-info-link"
                        @click="$root.$emit('helpWanted','emailme')"></a>
                </div>
                <div class="srgdev-appt-sb-indent">
                    <input
                            v-model="emlInfo.meReq"
                            type="checkbox"
                            id="srgdev-appt_emn-me-request"
                            class="checkbox"><label class="srgdev-appt-sb-label-inline" for="srgdev-appt_emn-me-request">{{t('appointments','Requested')}}</label><br>
                    <input
                            v-model="emlInfo.meConfirm"
                            type="checkbox"
                            id="srgdev-appt_emn-me-confirm"
                            class="checkbox"><label class="srgdev-appt-sb-label-inline" for="srgdev-appt_emn-me-confirm">{{t('appointments','Confirmed')}}</label><br>
                    <input
                            v-model="emlInfo.meCancel"
                            type="checkbox"
                            id="srgdev-appt_emn-me-cancel"
                            class="checkbox"><label class="srgdev-appt-sb-label-inline" for="srgdev-appt_emn-me-cancel">{{t('appointments','Canceled')}}</label><br>
                </div>
                <div
                        style="margin-bottom: .75em"
                        class="srgdev-appt-info-lcont">
                    <input
                            v-model="emlInfo.skipEVS"
                            type="checkbox"
                            id="srgdev-appt_emn-skip-evs"
                            class="checkbox"><label style="margin-left: -3px;" class="srgdev-appt-sb-label-inline" for="srgdev-appt_emn-skip-evs">{{t('appointments','Skip email validation step')}}</label><a
                        class="icon-info srgdev-appt-info-link"
                        @click="$root.$emit('helpWanted','emailskipevs')"></a>
                </div>
                <label
                        v-show="emlInfo.skipEVS===false"
                        class="srgdev-appt-sb-label-inline"
                        for="srgdev-appt_emn-vld-note">
                    {{t('appointments','Additional VALIDATION email text:')}}</label>
                <textarea
                        v-show="emlInfo.skipEVS===false"
                        v-model="emlInfo.vldNote"
                        class="srgdev-appt-sb-textarea"
                        id="srgdev-appt_emn-vld-note"
                ></textarea>
                <div class="srgdev-appt-info-lcont">
                    <label
                        class="srgdev-appt-sb-label-inline"
                        for="srgdev-appt_emn-cnf-note">
                    {{t('appointments','Additional CONFIRMATION email text:')}}</label><a
                        class="icon-info srgdev-appt-info-link"
                        @click="$root.$emit('helpWanted','emailmoretext')"></a>
                </div>
                <textarea
                        v-model="emlInfo.cnfNote"
                        class="srgdev-appt-sb-textarea"
                        id="srgdev-appt_emn-cnf-note"
                ></textarea>
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
            emlInfo: {
                type: Object,
                default: function () {
                    return {
                        icsFile: false,
                        skipEVS: false,
                        attMod: false,
                        attDel: false,
                        meReq: false,
                        meConfirm: false,
                        meCancel: false,
                        vldNote: "",
                        cnfNote: ""
                    }
                }
            }
        },
        methods: {
            apply(){
                this.$emit('apply',this.emlInfo)
            },
            close(){
                this.$emit('close')
            }
        }
    }
</script>
