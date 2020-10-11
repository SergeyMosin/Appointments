<template>
  <SlideBar :title="curPageData.label" :subtitle="t('appointments','Add Appointment Slots')" icon="icon-appt-go-back" @close="close">
    <template slot="main-area">
      <div v-show="isLoading===true" class="sb_loading_cont">
        <span class="icon-loading sb_loading_icon_cont"></span>
        <span class="sb_loading_text">{{t('appointments','Loading')}}</span>
      </div>
      <div v-show="isLoading===false" class="srgdev-appt-sb-main-cont">
        <div class="srgdev-appt-sb-narrow">
        <label class="datepicker-label">{{t('appointments','Select Dates:')}}</label>
        <DatePicker
            style="width: 100%"
            :editable="false"
            :disabled-date="compNotBefore"
            :appendToBody="false"
            :popup-style="datePickerPopupStyle"
            :placeholder="t('appointments','Select Dates')"
            v-model="apptWeek"
            :lang="lang"
            @input="setToStartOfWeek"
            :format="weekFormat"
            type="week"></DatePicker>
        <label for="appt_dur-select" class="select-label">{{t('appointments','Appointment Duration:')}}</label>
        <vue-slider
            :min="10"
            :max="120"
            :interval="5"
            tooltip="always"
            tooltipPlacement="bottom"
            :tooltip-formatter="'{value} Min'"
            id="appt_dur-select"
            class="appt-slider"
            v-model="apptDur"></vue-slider>
        <div class="srgdev-appt-info-lcont">
          <label for="appt_tz-select" class="select-label">{{t('appointments','Timezone:')}}</label>
          <a
              class="icon-info srgdev-appt-info-link"
              @click="$root.$emit('helpWanted','timezone')"><span>Please read</span></a>
        </div>
        <select v-model="apptTZ" id="appt_tz-select" class="appt-select">
<!--          <option value="L">Local (floating)</option>-->
          <option value="C">{{tzName}}</option>
        </select>
          <p style="padding-top: .5em; font-size: 80%">Support for FLOATING timezones is being phased out</p>
        <button
            @click="goApptGen"
            :disabled="apptWeek===null"
            class="primary srgdev-appt-sb-genbtn">{{t('appointments','Start')}}
        </button>
        </div>
      </div>
    </template>
  </SlideBar>
</template>

<script>
import SlideBar from "./SlideBar.vue"
import axios from '@nextcloud/axios'
import {linkTo} from '@nextcloud/router'

import DatePicker from 'vue2-datepicker'
import '../../css/datepicker.css';

import VueSlider from 'vue-slider-component'
import 'vue-slider-component/theme/default.css'


export default {
  name: "AddApptSlideBar",
  components: {
    SlideBar,
    VueSlider,
    DatePicker
  },
  props:{
    curPageData:{},
    isGridReady: {
      type: Boolean,
      default: false
    },
    title:'',
    subtitle:'',
  },
  inject: [
    'getState'
  ],
  mounted: function () {
    this.isLoading=true
    this.start()
  },
  computed:{
    lang: function(){
      let days=undefined
      let months=undefined
      const formatLocale={
        firstDayOfWeek:window.firstDay||0
      }
      if(window.Intl && typeof window.Intl === "object"){
        days=[]
        let d=new Date(1970,1,1)
        let f = new Intl.DateTimeFormat([],
            {weekday: "short",})
        for(let i=1;i<8;i++){
          d.setDate(i)
          days[i-1]=f.format(d)
        }
        f = new Intl.DateTimeFormat([],
            {month: "short",})
        d.setDate(1)
        months=[]
        for(let i=0;i<12;i++){
          d.setMonth(i)
          months[i]=f.format(d)
        }
        formatLocale.monthsShort=months
      }
      return {days:days,formatLocale:formatLocale}
    },
    notBeforeDate(){
      let d=new Date()
      d.setHours(0)
      d.setMinutes(0)
      d.setTime(this.getStartOfWeek(d).getTime()-90000000)
      return d
    }
  },

  watch: {
    tzName(val){
      this.apptTZ = val === 'UTC' ? "L" : "C";
    }
  },

  data: function () {
    return {

      isLoading:true,
      tzName: '',
      tzData: '',

      calInfo:{},

      apptWeek:null,

      apptDur:30,

      apptTZ:"C",

      datePickerPopupStyle:{
        top:"75%",
        left:"50%",
        transform: "translate(-50%,0)"
      },
      weekFormat: {
        // Date to String
        stringify: (date,fmt) => {

          if(date){
            const ts=date.getTime() + 6 * 86400000;
            if(window.Intl && typeof window.Intl === "object") {
              let f = new Intl.DateTimeFormat([],
                  {month: "short", day: "2-digit",})
              return f.format(date) + ' - ' + f.format(new Date(ts))
            }else{
              return date.toLocaleDateString()+' - '+(new Date(ts)).toLocaleDateString()
            }
          }else return ''
        }
      },
    }
  },

  methods: {

    async start(){
      this.isLoading=true

      if (!this.isGridReady) {
        this.$emit('setupGrid')
      }

      try{
        const data=this.curPageData
        this.calInfo=await this.getState("get_"+data.stateAction,data.pageId)
      }catch (e){
        console.log(e)
        this.isLoading=false
        OC.Notification.showTemporary(this.t('appointments', "Can not request data"), {timeout: 4, type: 'error'})
        return
      }

      // TODO: get adn display calendar name

      this.tzName = "UTC"
      this.tzData = "UTC"
      try {
        let res= await this.getState("get_tz")
        if (res !== null && res.toLowerCase() !== 'utc') {
          let url = linkTo('appointments', 'ajax/zones.js')
          const tzr=await axios.get(url)
          if (tzr.status === 200) {

            let tzd = tzr.data
            if (typeof tzd === "object"
                && tzd.hasOwnProperty('aliases')
                && tzd.hasOwnProperty('zones')) {

              let tzs = ""
              if (tzd.zones[res] !== undefined) {
                tzs = tzd.zones[res].ics.join("\r\n")

              } else if (tzd.aliases[res] !== undefined) {
                let alias = tzd.aliases[res].aliasTo
                if (tzd.zones[alias] !== undefined) {
                  res = alias
                  tzs = tzd.zones[alias].ics.join("\r\n")
                }
              }

              this.tzName = res
              this.tzData = "BEGIN:VTIMEZONE\r\nTZID:" + res.trim() + "\r\n" + tzs.trim() + "\r\nEND:VTIMEZONE"

              this.isLoading=false
            }else{
              throw new Error("Bad tzr.data")
            }
          }else{
            throw new Error("Bad status: "+tzr.status)
          }
        }else{
          throw new Error("Can't get_tz")
        }
      }catch (e){
        this.isLoading=false
        console.error("Can't get timezone")
        console.log(e)
        OC.Notification.showTemporary(this.t('appointments', "Can't load timezones"), {timeout: 4, type: 'error'})
      }
    },

    getTimeFormat(){
      let date = new Date(0);
      if(date.toLocaleTimeString().indexOf("PM")===-1){
        return 'HH:mm'
      }else{
        return 'hh:mm A'
      }
    },
    setToStartOfWeek(){
      if(this.apptWeek!==null) {
        this.apptWeek=this.getStartOfWeek(this.apptWeek)
      }
    },
    getStartOfWeek(d){

      let gd=d.getDay()
      if (this.lang.formatLocale.firstDayOfWeek === 1) {
        // Sunday (0) is last
        if(gd===0) gd=6
        else gd--
      }else{
        gd--
      }
      return new Date(d.getTime() - gd*86400000)
    },
    compNotBefore(d){
      return d<this.notBeforeDate
    },
    resetAppt(){
      this.apptWeek=null
      this.apptDur=30
    },
    goApptGen(){
      this.close(true)
      let r={
        tz: this.apptTZ==="C"?this.tzData:"L",
        week:(this.apptWeek.getTime()),
        dur:this.apptDur,
        pageId:this.curPageData.pageId,
        calColor:this.calInfo['curCal_color'],
        calName:this.calInfo['curCal_name']
      }
      this.resetAppt()
      this.$emit("agDataReady",r)
    },

    /**
     * @param hard - hard close will close the slidebar instead of going back to the "parent"
     */
    close(hard){
      this.$emit('close',hard)
    }
  }
}
</script>



<style scoped>
.srgdev-appt-sb-narrow{
  width: 85%;
  margin: 0 0 0 2%;
}

.datepicker-label,
.select-label{
  display: block;
  margin-top: 1em;
}
.datepicker-label{
  margin-top: 0;
}
.select-label{
  margin-bottom: .25em;
}
.appt-slider{
  margin-bottom: 3em;
}
.appt-select {
  margin: 0;
  width: 100%;
  padding: 0 0 0 .25em;
}

</style>
