<template>
  <SlideBar :title="t('appointments','Calendars and Schedule')"
            :subtitle="t('appointments','Manage appointments and calendar settings')" @close="close">
    <template slot="main-area">
      <div v-show="isLoading===true" class="sb_loading_cont">
        <span class="icon-loading sb_loading_icon_cont"></span>
        <span class="sb_loading_text">{{t('appointments','Loading')}}</span>
      </div>
      <div
          v-show="isLoading===false"
          :class="{'sb_disable':isSending}"
          class="srgdev-appt-sb-main-cont">
          <template v-if="calInfo.tsMode==='0'">
            <div style="margin: 0 0 2em -.5em">
            <ApptIconButton
                @click="$emit('gotoAddAppt','p0')"
                :text="t('appointments','Add Appointment Slots')"
                icon="icon-add"/>
            <ApptIconButton
                @click="$emit('gotoDelAppt','p0')"
                :text="t('appointments','Remove Old Appointments')"
                icon="icon-delete"/>
            </div>
            <div class="srgdev-appt-info-lcont">
              <label
                  class="tsb-label"
                  for="appt_tsb-main-cal-id">
                {{ t('appointments', 'Main calendar') }}:</label><a
                style="right: 9%"
                class="icon-info srgdev-appt-info-link"
                @click="$root.$emit('helpWanted','maincal')"></a>
            </div>
            <select
                v-model="calInfo.mainCalId"
                class="tsb-input"
                id="appt_tsb-main-cal-id">
              <option value="-1">{{ t('appointments', 'Calendar Required') }}</option>
              <option v-for="cal in cals" :value="cal.id">{{ cal.name }}</option>
            </select>
            <div class="srgdev-appt-info-lcont">
              <label
                  class="tsb-label"
                  for="appt_tsb-dest-cal-id">
                {{ t('appointments', 'Calendar for booked appointments') }}:</label><a
                style="right: 9%"
                class="icon-info srgdev-appt-info-link"
                @click="$root.$emit('helpWanted','destcal')"></a>
            </div>
            <select
                v-model="calInfo.destCalId"
                class="tsb-input"
                id="appt_tsb-dest-cal-id">
              <option value="-1">{{ t('appointments', 'Use Main calendar') }}</option>
              <option v-for="cal in cals" :value="cal.id">{{ cal.name }}</option>
            </select>
          </template>
          <template v-if="calInfo.tsMode==='1'">
            <div class="srgdev-appt-info-lcont">
              <label
                  class="tsb-label"
                  for="appt_tsb-srcm2-cal-id">
                {{ t('appointments', 'Source Calendar (Free Slots)') }}:</label><a
                style="right: 9%"
                class="icon-info srgdev-appt-info-link"
                @click="$root.$emit('helpWanted','sourcecal_nr')"></a>
            </div>
            <select
                v-model="calInfo.nrSrcCalId"
                class="tsb-input"
                id="appt_tsb-srcm2-cal-id">
              <option value="-1">{{ t('appointments', 'Calendar Required') }}</option>
              <option v-for="cal in cals" :value="cal.id">{{ cal.name }}</option>
            </select>
            <div class="srgdev-appt-info-lcont">
              <label
                  class="tsb-label"
                  for="appt_tsb-destm2-cal-id">
                {{ t('appointments', 'Destination Calendar (Booked)') }}:</label><a
                style="right: 9%"
                class="icon-info srgdev-appt-info-link"
                @click="$root.$emit('helpWanted','destcal_nr')"></a>
            </div>
            <select
                v-model="calInfo.nrDstCalId"
                class="tsb-input"
                id="appt_tsb-destm2-cal-id">
              <option value="-1">{{ t('appointments', 'Calendar Required') }}</option>
              <option v-for="cal in cals" :value="cal.id">{{ cal.name }}</option>
            </select>
          </template>
          <div class="srgdev-appt-info-lcont">
            <label
                class="tsb-label"
                for="appt_tsb-ts-mode">
              {{ t('appointments', 'Time slot mode') }}:</label><a
              style="right: 9%"
              class="icon-info srgdev-appt-info-link"
              @click="$root.$emit('helpWanted','ts_mode')"></a>
          </div>
          <select
              v-model="calInfo.tsMode"
              class="tsb-input"
              @change="tsModeChanged"
              id="appt_tsb-ts-mode">
            <option value="0">{{ t('appointments', 'Simple') }}</option>
            <option value="1">{{ t('appointments', 'External') }}</option>
          </select>
          <button
              @click="apply"
              class="primary srgdev-appt-sb-genbtn"
              :class="{'appt-btn-loading':isSending}">{{t('appointments','Apply')}}
          </button>
        </div>
    </template>
  </SlideBar>
</template>

<script>
import SlideBar from "./SlideBar.vue"
import ApptIconButton from "./ApptIconButton";
import AddApptSection from "./AddApptSection";

import {
  ActionButton,
  Actions,
} from '@nextcloud/vue'

import VueSlider from 'vue-slider-component'
import 'vue-slider-component/theme/default.css'

import axios from '@nextcloud/axios'
import ApptIconLabel from "./ApptIconLabel";

export default {

  name: "ApptMgrSlideBar",
  components: {
    ApptIconLabel,
    SlideBar,
    ApptIconButton,
    VueSlider,
    Actions,
    ActionButton,
    AddApptSection,
  },
  mounted: function () {
    this.isLoading=true
    this.start()
  },
  inject: ['getState', 'setState'],
  data: function () {
    return {
      isLoading:true,
      isSending:false,

      calInfo: {
        mainCalId: "-1",
        destCalId: "-1",
        nrSrcCalId: "-1",
        nrDstCalId: "-1",
        tsMode: "0",
      },

      cals: [],
    };
  },

  methods: {

    async start() {
      this.isLoading=true
      try {
        this.calInfo = await this.getState("get_cls", "")
      } catch (e) {
        this.isLoading=false
        console.log(e)
        OC.Notification.showTemporary(this.t('appointments', "Can not request data"), {timeout: 4, type: 'error'})
        return
      }

      this.cals.splice(0, this.cals.length)
      try {
        const res = await axios.get('callist')
        const cals = res.data.split(String.fromCharCode(31))
        for (let i = 0, l = cals.length; i < l; i++) {
          let cal = cals[i].split(String.fromCharCode(30))
          this.cals.push({
            name: cal[0],
            id: cal[2],
          })
        }
        this.isLoading=false
      }catch (e){
        this.isLoading=false
        console.log(e)
        OC.Notification.showTemporary(this.t('appointments', "Can not load calendars"), {timeout: 4, type: 'error'})
      }
    },

    tsModeChanged() {
      this.apply(true)
      this.$emit("showModal", [
        this.t('appointments', 'Warning'),
        this.t('appointments', 'Time slot mode has changed. Public page is going offline…'),
        this.start()])
    },

    apply(tsModeChanged=false){

      // No need to check when ts mode is being changed
      if(tsModeChanged!==true) {
        if (this.calInfo.tsMode === "0") {
          if (this.calInfo.mainCalId === '-1') {
            this.$emit("showModal", [
              this.t('appointments', 'Error'),
              this.t('appointments', 'Main calendar is required')])
            return
          } else {
            if (this.calInfo.mainCalId === this.calInfo.destCalId) {
              this.calInfo.destCalId = "-1"
            }
          }
        } else if (this.calInfo.tsMode === "1") {
          if (this.calInfo.nrSrcCalId === '-1') {
            this.$emit("showModal", [
              this.t('appointments', 'Error'),
              this.t('appointments', 'Source calendar is required')])
            return
          } else if (this.calInfo.nrDstCalId === '-1') {
            this.$emit("showModal", [
              this.t('appointments', 'Error'),
              this.t('appointments', 'Destination calendar is required')])
            return
          } else if (this.calInfo.nrSrcCalId === this.calInfo.nrDstCalId) {
            this.$emit("showModal", [
              this.t('appointments', 'Error'),
              this.t('appointments', 'Source and Destination calendars must be different')])
            return
          }
        }
      }

      this.isSending=true
      // reload page info when tsModeChanged===true
      this.setState('set_cls',this.calInfo,tsModeChanged).then(()=>{
        this.isSending=false
      })

    },

    close() {
      this.$emit('close')
    },
  }
}
</script>
<style scoped>
.tsb-label {
  display: block;
}

.tsb-input {
  margin-top: 0;
  display: block;
  min-width: 80%;
  margin-bottom: 1em;
  color: var(--color-text-lighter);
}


</style>