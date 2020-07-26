<template>
    <SlideBar :title="t('appointments','Calendars and Schedule')" :subtitle="t('appointments','Manage appointments and calendar settings')" @close="close">
        <template slot="main-area">
            <div class="srgdev-appt-sb-main-cont">
                <ApptIconButton
                        :loading="expLoading===4"
                        @click="openCalListSettings"
                        :text="t('appointments','Calendars')"
                        icon="icon-calendar-dark">
                    <Actions v-show="expando[4]===1" slot="actions">
                        <ActionButton @click.stop="toggleExpando(4)" icon="icon-triangle-n"></ActionButton>
                    </Actions>
                </ApptIconButton>
                <div :data-expand="expando[4]" class="srgdev-appt_expando_cont">
                <template v-if="calInfo.tsMode==='0'">
                    <div class="srgdev-appt-info-lcont">
                        <label
                                class="tsb-label"
                                for="appt_tsb-main-cal-id">
                            {{t('appointments','Main calendar')}}:</label><a
                            style="right: 9%"
                            class="icon-info srgdev-appt-info-link"
                            @click="$root.$emit('helpWanted','maincal')"></a>
                    </div>
                    <select
                            v-model="calInfo.mainCalId"
                            class="tsb-input"
                            id="appt_tsb-main-cal-id">
                        <option value="-1">{{t('appointments','Calendar Required')}}</option>
                        <option v-for="cal in cals" :value="cal.id">{{cal.name}}</option>
                    </select>
                    <div class="srgdev-appt-info-lcont">
                        <label
                                class="tsb-label"
                                for="appt_tsb-dest-cal-id">
                            {{t('appointments','Calendar for booked appointments')}}:</label><a
                            style="right: 9%"
                            class="icon-info srgdev-appt-info-link"
                            @click="$root.$emit('helpWanted','destcal')"></a>
                    </div>
                    <select
                            v-model="calInfo.destCalId"
                            class="tsb-input"
                            id="appt_tsb-dest-cal-id">
                        <option value="-1">{{t('appointments','Use Main calendar')}}</option>
                        <option v-for="cal in cals" :value="cal.id">{{cal.name}}</option>
                    </select>
                </template>
                <template v-if="calInfo.tsMode==='1'">
                    <div class="srgdev-appt-info-lcont">
                        <label
                                class="tsb-label"
                                for="appt_tsb-srcm2-cal-id">
                            {{t('appointments','Source Calendar (Free Slots)')}}:</label><a
                            style="right: 9%"
                            class="icon-info srgdev-appt-info-link"
                            @click="$root.$emit('helpWanted','sourcecal_nr')"></a>
                    </div>
                    <select
                            v-model="calInfo.nrSrcCalId"
                            class="tsb-input"
                            id="appt_tsb-srcm2-cal-id">
                        <option value="-1">{{t('appointments','Calendar Required')}}</option>
                        <option v-for="cal in cals" :value="cal.id">{{cal.name}}</option>
                    </select>
                    <div class="srgdev-appt-info-lcont">
                        <label
                                class="tsb-label"
                                for="appt_tsb-destm2-cal-id">
                            {{t('appointments','Destination Calendar (Booked)')}}:</label><a
                            style="right: 9%"
                            class="icon-info srgdev-appt-info-link"
                            @click="$root.$emit('helpWanted','destcal_nr')"></a>
                    </div>
                    <select
                            v-model="calInfo.nrDstCalId"
                            class="tsb-input"
                            id="appt_tsb-destm2-cal-id">
                        <option value="-1">{{t('appointments','Calendar Required')}}</option>
                        <option v-for="cal in cals" :value="cal.id">{{cal.name}}</option>
                    </select>
                </template>
                <button
                        @click="applyCalListSettings"
                        class="primary srgdev-appt-sb-genbtn">{{t('appointments','Apply')}}
                </button>
                </div>
                <template v-if="calInfo.tsMode!=='1'">
                <ApptIconButton
                        :disabled="calInfo.mainCalId==='-1'"
                        :loading="tzLoading"
                        @click="openAddAppts"
                        :text="t('appointments','Add Appointment Slots')"
                        icon="icon-add">
                    <Actions v-show="expando[2]===1" slot="actions">
                    <ActionButton @click.stop="toggleExpando(2)" icon="icon-triangle-n"></ActionButton>
                </Actions>
                </ApptIconButton>
                <div :data-expand="expando[2]" class="srgdev-appt_expando_cont">
                    <AddApptSection
                        v-on="$listeners"
                        @agDataReady="function() {
                            toggleExpando(2)
                            close()
                        }"
                        :tz-data="tzData"
                        :tz-name="tzName">
                    </AddApptSection>
                </div>
                <ApptIconButton
                        :disabled="calInfo.mainCalId==='-1'"
                        :loading="expLoading===0"
                        @click="openRemOld"
                        :text="t('appointments','Remove Old Appointments')"
                        icon="icon-delete">
                    <Actions v-show="expando[0]===1" slot="actions">
                        <ActionButton @click.stop="toggleExpando(0)" icon='icon-triangle-n'></ActionButton>
                    </Actions>
                </ApptIconButton>
                <div :data-expand="expando[0]" class="srgdev-appt_expando_cont">
                    <label for="appt_tsb-rem-slider">{{t('appointments','Scheduled before')}}:</label>
                    <vue-slider
                            v-model="rsValue"
                            :marks="rsMarks"
                            :process="true"
                            :included="true"
                            :lazy="true"
                            tooltip="none"
                            @change="checkRsMin"
                            id="appt_tsb-rem-slider"
                            class="appt-slider"></vue-slider>
                    <input type="radio"
                           value="empty"
                           v-model="remType"
                           id="appt_tsb-rem-empty"
                           class="radio"
                           checked="checked">
                    <label for="appt_tsb-rem-empty">{{t('appointments','Remove empty slots only')}}</label><br>
                    <input type="radio"
                           value="both"
                           v-model="remType"
                           id="appt_tsb-rem-both"
                           class="radio">
                    <label for="appt_tsb-rem-both">{{t('appointments','Remove empty and booked')}}</label><br>
                    <button
                            @click="removeOld"
                            class="primary srgdev-appt-sb-genbtn">{{t('appointments','Start')}}
                    </button>
                </div>
                </template>
                <template v-if="calInfo.tsMode==='1'">
                    <ApptIconButton
                            :loading="expLoading===3"
                            @click="openNRSettings"
                            :text="t('appointments','External Mode Settings')"
                            icon="icon-sched-mode">
                        <Actions v-show="expando[3]===1" slot="actions">
                            <ActionButton @click.stop="toggleExpando(3)" icon="icon-triangle-n"></ActionButton>
                        </Actions>
                    </ApptIconButton>
                    <div :data-expand="expando[3]" class="srgdev-appt_expando_cont">
                    <div class="srgdev-appt-info-lcont srgdev-appt-sb-chb-cont" style="margin-top: 1.5em"><input
                            type="checkbox"
                            v-model="calInfo.nrPushRec"
                            id="appt_tsb-push-recur"
                            class="checkbox"><label for="appt_tsb-push-recur">{{t('appointments','Optimize recurrence')}}</label><a
                            class="icon-info srgdev-appt-info-link"
                            style="right: 9%"
                            @click="$root.$emit('helpWanted','push_rec_nr')"></a>
                    </div>
                    <div class="srgdev-appt-info-lcont srgdev-appt-sb-chb-cont" style="margin-top: 1.25em"><input
                            type="checkbox"
                            v-model="calInfo.nrRequireCat"
                            id="appt_tsb-require-cat"
                            class="checkbox"><label for="appt_tsb-require-cat">{{t('appointments','Require "Appointment" category')}}</label><a
                            class="icon-info srgdev-appt-info-link"
                            style="right: 9%"
                            @click="$root.$emit('helpWanted','require_cat_nr')"></a>
                    </div>
                    <div class="srgdev-appt-info-lcont srgdev-appt-sb-chb-cont" style="margin-top: 1.25em"><input
                            type="checkbox"
                            v-model="calInfo.nrAutoFix"
                            id="appt_tsb-nr-auto-fix"
                            class="checkbox"><label for="appt_tsb-nr-auto-fix">{{t('appointments','Auto-fix "Source" timeslots')}}</label><a
                            class="icon-info srgdev-appt-info-link"
                            style="right: 9%"
                            @click="$root.$emit('helpWanted','auto_fix_nr')"></a>
                    </div>
                    <button
                            @click="applyCalSettings"
                            class="primary srgdev-appt-sb-genbtn">{{t('appointments','Apply')}}
                    </button>
                    </div>
                </template>
                <ApptIconButton
                        :loading="expLoading===1"
                        @click="openCalSettings"
                        :text="t('appointments','Advanced Settings')"
                        icon="icon-settings">
                    <Actions v-show="expando[1]===1" slot="actions">
                        <ActionButton @click.stop="toggleExpando(1)" icon="icon-triangle-n"></ActionButton>
                    </Actions>
                </ApptIconButton>
                <div :data-expand="expando[1]" class="srgdev-appt_expando_cont">
                    <label
                            class="tsb-label"
                            for="appt_tsb-appt-prep-time">
                        {{t('appointments','Minimum lead time')}}:</label>
                    <select
                            v-model="calInfo.prepTime"
                            class="tsb-input"
                            id="appt_tsb-appt-prep-time">
                        <option value="0">{{t('appointments','No lead time')}}</option>
                        <option value="15">{{t('appointments','15 Minutes')}}</option>
                        <option value="30">{{t('appointments','30 Minutes')}}</option>
                        <option value="60">{{t('appointments','1 Hour')}}</option>
                        <option value="120">{{t('appointments','2 Hours')}}</option>
                    </select>
                    <label
                            class="tsb-label"
                            for="appt_tsb-appt-reset">
                        {{t('appointments','When Attendee Cancels')}}:</label>
                    <select
                            v-model="calInfo.whenCanceled"
                            class="tsb-input"
                            id="appt_tsb-appt-reset">
                        <option value="mark">{{t('appointments','Mark the appointment as canceled')}}</option>
                        <option value="reset">{{t('appointments','Reset (make the timeslot available)')}}</option>
                    </select>
                    <div class="srgdev-appt-info-lcont">
                        <label
                                class="tsb-label"
                                for="appt_tsb-ts-mode">
                            {{t('appointments','Time slot mode')}}:</label><a
                            style="right: 9%"
                            class="icon-info srgdev-appt-info-link"
                            @click="$root.$emit('helpWanted','ts_mode')"></a>
                    </div>
                    <select
                            v-model="calInfo.tsMode"
                            class="tsb-input"
                            @change="tsModeChanged"
                            id="appt_tsb-ts-mode">
                        <option value="0">{{t('appointments','Simple')}}</option>
                        <option value="1">{{t('appointments','External')}}</option>
                    </select>
                    <button
                            @click="applyCalSettings"
                            class="primary srgdev-appt-sb-genbtn">{{t('appointments','Apply')}}
                    </button>
                </div>
            </div>
        </template>
    </SlideBar>
</template>

<script>
    import SlideBar from "./SlideBar.vue"
    import ApptIconButton from "./ApptIconButton";
    import AddApptSection from "./AddApptSection";

    import{
        ActionButton,
        Actions,
    } from '@nextcloud/vue'

    import VueSlider from 'vue-slider-component'
    import 'vue-slider-component/theme/default.css'

    import axios from '@nextcloud/axios'
    import {linkTo} from '@nextcloud/router'

    export default {

        name: "TimeSlotSlideBar",
        components: {
            SlideBar,
            ApptIconButton,
            VueSlider,
            Actions,
            ActionButton,
            AddApptSection,
        },
        props: {
            isGridReady:{
                type: Boolean,
                default: false
            },
            calInfo: {
                type: Object,
                default: function () {
                    return {
                        prepTime:"0",
                        whenCanceled:"mark",
                        mainCalId:"-1",
                        destCalId:"-1",
                        nrSrcCalId:"-1",
                        nrDstCalId:"-1",
                        nrPushRec:true,
                        nrRequireCat:false,
                        nrAutoFix:false,
                        tsMode:"0",
                    }
                },
            },
        },

        computed:{
            rsMarks:function(){
                const options = {month: 'short', day: '2-digit' };
                let d=new Date()
                d.setTime(Date.now()-86400000)
                const y=d.toLocaleString(undefined,options)
                d.setTime(d.getTime()-86400000*6)
                const w=d.toLocaleString(undefined,options)
                return {
                    0:'-∞',
                    58:w,
                    100:y,
                }
            },
        },

        data: function() {
            return {
                expando:[0,0,0,0,0],
                expLoading:-1,

                rsValue:58,
                remType:"empty",

                tzName:'',
                tzData:'',
                tzLoading:false,

                setStateInProgress:false,

                cals:[],
                calsAll:[],
            };
        },

        methods: {
            stateDataReady(nid){
                const expId=nid*-1-1
                this.expLoading=-1
                if(expId<this.expando.length) {
                    // Two settings should not be opened at the same time
                    for(let i=0;i<this.expando.length;i++){
                        if(i!==expId && this.expando[i]===1){
                            this.toggleExpando(i)
                        }
                    }
                    this.toggleExpando(expId)
                }
            },


            removeOld(){
                this.$emit("remOldAppts",{type:this.remType,before:this.rsValue==="100"?1:7})
            },
            openRemOld(){
                if(this.expando[0]===1) {
                    this.toggleExpando(0)
                }else {
                    // this is need to fetch calInfo
                    this.expLoading=0
                    this.$emit('getCalInfo', 0)
                }
            },

            applyCalListSettings(){
                if(this.calInfo.tsMode==="0") {
                    if (this.calInfo.mainCalId === '-1') {
                        this.$emit("showModal", [
                            this.t('appointments', 'Error'),
                            this.t('appointments', 'Main calendar is required')])
                    } else{
                        if(this.calInfo.mainCalId === this.calInfo.destCalId){
                            this.calInfo.destCalId="-1"
                        }
                        this.applyCalSettings()
                    }
                }else if(this.calInfo.tsMode==="1") {
                    if (this.calInfo.nrSrcCalId === '-1') {
                        this.$emit("showModal", [
                            this.t('appointments', 'Error'),
                            this.t('appointments', 'Source calendar is required')])
                    } else if (this.calInfo.nrDstCalId === '-1') {
                        this.$emit("showModal", [
                            this.t('appointments', 'Error'),
                            this.t('appointments', 'Destination calendar is required')])
                    } else if (this.calInfo.nrSrcCalId === this.calInfo.nrDstCalId) {
                        this.$emit("showModal", [
                            this.t('appointments', 'Error'),
                            this.t('appointments', 'Source and Destination calendars must be different')])
                    } else {
                        this.applyCalSettings()
                    }
                }
            },

            tsModeChanged(){
                // this.calInfo.nrSrcCalId="-1"
                // this.calInfo.nrDstCalId="-1"
                // this.calInfo.destCalId="-1"
                // this.$emit('calSelected',{url:"-1"})

                this.$emit('pageOffline');
                this.applyCalSettings()
                this.$emit("showModal",[
                    this.t('appointments','Warning'),
                    this.t('appointments','Time slot mode has changed. Public page is going offline…'),
                    this.openCalListSettings])
            },
            applyCalSettings(){
                this.$emit('setCalInfo',this.calInfo)
            },

            openCalListSettings(){
                if(this.expando[4]===1){
                    // just close
                    this.toggleExpando(4)
                }else{
                    this.expLoading=4
                    this.getCalList()
                    this.$emit('getCalInfo',4)
                    // expando open is triggered in via getCalInfo event
                }
            },


            openNRSettings(){
                if(this.expando[3]===1){
                    // just close
                    this.toggleExpando(3)
                }else{
                    this.expLoading=3
                    this.$emit('getCalInfo',3)
                    // expando open is triggered in via getCalInfo event
                }
            },

            openCalSettings(){
                if(this.expando[1]===1){
                    // just close
                    this.toggleExpando(1)
                }else{
                    this.expLoading=1
                    this.$emit('getCalInfo',1)
                    // expando open is triggered in via getCalInfo event
                }
            },

            getCalList(){
                this.cals.splice(0,this.cals.length)
                axios.get('callist')
                    .then(response=>{
                        let cals=response.data.split(String.fromCharCode(31))
                        for(let i=0,l=cals.length;i<l;i++){
                            let cal=cals[i].split(String.fromCharCode(30))
                            this.cals.push({
                                name: cal[0],
                                id: cal[2],
                            })
                        }
                    })
                    .catch(function (error) {
                        console.log(error);
                    })
            },

            openAddAppts: function(){

                if(this.expando[2]===1){
                    this.toggleExpando(2)
                    return;
                }

                this.tzLoading=true

                // 999 won't trigger slideBar toggle
                this.$emit('getCalInfo', 999)

                if(!this.isGridReady){
                    this.$emit('setupGrid')
                }

                this.tzName="UTC"
                this.tzData="UTC"

                this.$parent.$parent.getState("get_tz").then(res=>{
                    if(res!==null && res.toLowerCase()!=='utc') {
                        let url=linkTo('appointments','ajax/zones.js')
                        return axios.get(url).then(tzr=>{
                            if(tzr.status===200) {
                                let tzd=tzr.data
                                if(typeof tzd==="object"
                                    && tzd.hasOwnProperty('aliases')
                                    && tzd.hasOwnProperty('zones')
                                ){
                                    let tzs=""
                                    if(tzd.zones[res]!==undefined){
                                        tzs=tzd.zones[res].ics.join("\r\n")
                                    }else if(tzd.aliases[res]!==undefined){
                                        let alias=tzd.aliases[res].aliasTo
                                        if(tzd.zones[alias]!==undefined){
                                            res=alias
                                            tzs=tzd.zones[alias].ics.join("\r\n")
                                        }
                                    }
                                    return [res,tzs]
                                }
                            }
                            return null
                        })
                    }else return Promise.resolve(null)
                }).then((r)=>{
                    // close all expandos
                    for(let i=0;i<this.expando.length;i++){
                        if(this.expando[i]===1){
                            this.toggleExpando(i)
                        }
                    }

                    if(r===null || !Array.isArray(r) || r.length!==2 || r[1]===""){
                        console.error("can't get timezone data")
                    }else{
                        this.tzName=r[0]
                        this.tzData= "BEGIN:VTIMEZONE\r\nTZID:"
                            +r[0].trim()+"\r\n"+r[1].trim()+"\r\nEND:VTIMEZONE"
                    }
                    this.tzLoading=false;
                    this.toggleExpando(2)
                }).catch(err=>{
                    // close all expandos
                    for(let i=0;i<this.expando.length;i++){
                        if(this.expando[i]===1){
                            this.toggleExpando(i)
                        }
                    }

                    console.log(err)
                    this.tzLoading=false;
                    this.toggleExpando(2)
                })
            },

            toggleExpando(expId){
                this.expando.splice(expId, 1, this.expando[expId]^1)
            },
            checkRsMin(){
              if(+this.rsValue<58) this.rsValue="58"
            },
            close(){
                this.$emit('close')
            },
        }
    }
</script>
<style scoped>
    #appt_tsb-rem-slider{
        margin: .25em 4.5em 3.25em 0;
    }
    .tsb-label{
        display: block;
    }
    .tsb-input{
        margin-top: 0;
        display: block;
        min-width: 80%;
        margin-bottom: 1em;
        color: var(--color-text-lighter);
    }


</style>