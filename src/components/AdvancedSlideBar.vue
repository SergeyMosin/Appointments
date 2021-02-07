<template>
  <SlideBar :title="t('appointments','Advanced Settings')" :subtitle="t('appointments','These settings affect ALL pages')" icon="icon-appt-go-back" @close="close">
    <template slot="main-area">
      <div v-show="isLoading===true" class="sb_loading_cont">
        <span class="icon-loading sb_loading_icon_cont"></span>
        <span class="sb_loading_text">{{t('appointments','Loading')}}</span>
      </div>
      <div
          v-show="isLoading===false"
          :class="{'sb_disable':isSending}"
          class="srgdev-appt-sb-main-cont"
          style="padding-left: 1%">
        <ApptIconLabel
            :text="t('appointments','Time Slot Settings')"
            icon="icon-appt-timeslot-settings"/>
        <div class="srgdev-appt-sb-indent_small">
          <label
              class="tsb-label"
              for="appt_tsb-appt-prep-time">
            {{ t('appointments', 'Minimum lead time') }}:</label>
          <select
              v-model="calInfo.prepTime"
              class="tsb-input"
              id="appt_tsb-appt-prep-time">
            <option value="0">{{ t('appointments', 'No lead time') }}</option>
            <option value="15">{{ t('appointments', '15 Minutes') }}</option>
            <option value="30">{{ t('appointments', '30 Minutes') }}</option>
            <option value="60">{{ t('appointments', '1 Hour') }}</option>
            <option value="120">{{ t('appointments', '2 Hours') }}</option>
            <option value="240">{{ t('appointments', '4 Hours') }}</option>
            <option value="480">{{ t('appointments', '8 Hours') }}</option>
            <option value="720">{{ t('appointments', '12 Hours') }}</option>
            <option value="1440">{{ t('appointments', '1 day') }}</option>
            <option value="2880">{{ t('appointments', '2 days') }}</option>
            <option value="5760">{{ t('appointments', '4 days') }}</option>
          </select>
          <label
              class="tsb-label"
              for="appt_tsb-appt-reset">
            {{ t('appointments', 'When Attendee Cancels') }}:</label>
          <select
              v-model="calInfo.whenCanceled"
              class="tsb-input"
              id="appt_tsb-appt-reset">
            <option value="mark">{{ t('appointments', 'Mark the appointment as canceled') }}</option>
            <option value="reset">{{ t('appointments', 'Reset (make the timeslot available)') }}</option>
          </select>
        </div>
        <ApptIconLabel
            :text="t('appointments','External Mode Settings')"
            icon="icon-sched-mode"/>
        <div class="srgdev-appt-sb-indent_small">
          <div class="srgdev-appt-info-lcont srgdev-appt-sb-chb-cont" style="margin-top: 1em"><input
              type="checkbox"
              v-model="calInfo.nrPushRec"
              id="appt_tsb-push-recur"
              class="checkbox"><label for="appt_tsb-push-recur">{{ t('appointments', 'Optimize recurrence') }}</label><a
              class="icon-info srgdev-appt-info-link"
              style="right: 9%"
              @click="$root.$emit('helpWanted','push_rec_nr')"></a>
          </div>
          <div class="srgdev-appt-info-lcont srgdev-appt-sb-chb-cont" style="margin-top: 1.25em"><input
              type="checkbox"
              v-model="calInfo.nrRequireCat"
              id="appt_tsb-require-cat"
              class="checkbox"><label
              for="appt_tsb-require-cat">{{ t('appointments', 'Require "Appointment" category') }}</label><a
              class="icon-info srgdev-appt-info-link"
              style="right: 9%"
              @click="$root.$emit('helpWanted','require_cat_nr')"></a>
          </div>
          <div class="srgdev-appt-info-lcont srgdev-appt-sb-chb-cont" style="margin-top: 1.25em"><input
              type="checkbox"
              v-model="calInfo.nrAutoFix"
              id="appt_tsb-nr-auto-fix"
              class="checkbox"><label
              for="appt_tsb-nr-auto-fix">{{ t('appointments', 'Auto-fix "Source" timeslots') }}</label><a
              class="icon-info srgdev-appt-info-link"
              style="right: 9%"
              @click="$root.$emit('helpWanted','auto_fix_nr')"></a>
          </div>
        </div>
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
import ApptIconLabel from "./ApptIconLabel";

export default {
  name: "AdvancedSlideBar",
  components: {
    ApptIconLabel,
    SlideBar
  },
  props:{
    title:'',
    subtitle:'',
  },
  mounted: function () {
    this.isLoading=true
    this.start()
  },
  inject: ['getState', 'setState'],
  data: function (){
    return {
      isLoading:true,
      isSending:false,
      calInfo: {
        prepTime: "0",
        whenCanceled: "mark",
        nrPushRec: true,
        nrRequireCat: false,
        nrAutoFix: false,
      },
    }
  },
  methods: {
    async start() {
      this.isLoading=true
      try {
        this.calInfo = await this.getState("get_cls", "")
        this.isLoading=false
      } catch (e) {
        this.isLoading=false
        console.log(e)
        OC.Notification.showTemporary(this.t('appointments', "Can not request data"), {timeout: 4, type: 'error'})
      }
    },

    apply(){
      this.isSending=true
      this.setState('set_cls',this.calInfo).then(()=>{
        this.isSending=false
      })
    },
    close(){
      this.$emit('close')
    }
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