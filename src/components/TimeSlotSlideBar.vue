<template>
    <SlideBar :title="t('appointments','Calendars and Schedule')" :subtitle="t('appointments','Manage appointments and calendar settings')" @close="close">
        <template slot="main-area">
            <div class="srgdev-appt-sb-main-cont">
                <NavAccountItem
                        v-on="$listeners"
                        :curCal="curCal"></NavAccountItem>

                <ApptIconButton
                        :disabled="curCal.url===''"
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
                        :disabled="curCal.url===''"
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
                <ApptIconButton
                        :disabled="curCal.url===''"
                        :loading="calInfo.isLoading"
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
                        <option value="-1">{{curCal.name}}</option>
                        <option v-for="cal in cals" :value="cal.id">{{cal.name}}</option>
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
    import NavAccountItem from "./NavAccountItem";
    import AddApptSection from "./AddApptSection";

    import{
        ActionButton,
        Actions,
    } from '@nextcloud/vue'

    import VueSlider from 'vue-slider-component'
    import 'vue-slider-component/theme/default.css'

    import axios from '@nextcloud/axios'
    import {linkTo} from '@nextcloud/router'
    import {detectColor} from "../utils";


    export default {
        name: "TimeSlotSlideBar",
        components: {
            SlideBar,
            ApptIconButton,
            NavAccountItem,
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
            curCal:{
                type: Object,
                default: function () {
                    return {
                        icon: "icon-calendar-dark",
                        name: this.t('appointments','Select Calendar'),
                        url: "",
                        rIcon: "",
                        clr: "",
                        isCalLoading:false
                    }
                }
            },
            calInfo: {
                type: Object,
                default: function () {
                    return {
                        whenCanceled:"mark",
                        destCalId:"-1",
                        isLoading:false,
                        isReady:false
                    }
                },
            }

        },
        watch: {
            'calInfo.isReady':function (val) {
                if(val===true){
                    this.toggleExpando(1)
                }
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
            }

        },

        data: function() {
            return {
                expando:[0,0,0],
                rsValue:58,
                remType:"empty",

                tzName:'',
                tzData:'',
                tzLoading:false,

                cals:[]
            };
        },

        methods: {

            removeOld(){
                this.$emit("remOldAppts",{type:this.remType,before:this.rsValue==="100"?1:7})
            },
            openRemOld(){
                // this is need to fetch calInfo
                if(this.expando[0]===0) {
                    this.$emit('getCalInfo', 'openNot')
                }
                this.toggleExpando(0)
            },

            applyCalSettings(){
                this.$emit('setCalInfo',this.calInfo)
            },

            openCalSettings(){
                if(this.expando[1]===1){
                    // just close
                    this.toggleExpando(1)
                }else{
                    this.$emit('getCalInfo')
                    this.getCalList()
                }

            },

            getCalList(){
                this.cals.splice(0,this.cals.length)
                axios.get('callist')
                    .then(response=>{
                        let cals=response.data.split(String.fromCharCode(31))
                        const curCalId=this.curCal.url // url is id
                        for(let i=0,l=cals.length;i<l;i++){
                            let cal=cals[i].split(String.fromCharCode(30))
                            if(curCalId!==cal[2])
                                this.cals.push({
                                    name:cal[0],
                                    id:cal[2]
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

                if(!this.isGridReady){
                    this.$emit('setupGrid')
                }

                this.tzName="UTC"
                this.tzData="UTC"

                this.$parent.$parent.getState("get_tz").then(res=>{
                    if(res!==null && res.toLowerCase()!=='utc') {
                        let url=linkTo('appointments','ajax/zones.json')
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
    }

</style>