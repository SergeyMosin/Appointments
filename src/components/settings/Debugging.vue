<template>
  <div>
    <div v-if="isLoading" class="dbg-section">
      {{ t('appointments', 'Loading…') }}
    </div>
    <div v-else>
      <span @click="$root.$emit('startDebug')" class="srgdev-appt-sb-linker">Settings Dump</span>
      <div class="dbg-section">
        <label
            style="display: block"
            for="appt_debug-raw-cal">
          {{ t('appointments', 'Get raw calendar data') }}:</label>
        <select
            v-model="calsIdx"
            @change="handleGetCalendarData"
            class="dbg-input"
            id="appt_debug-raw-cal">
          <option :value="-1">{{ t('appointments', 'Calendar Required') }}</option>
          <option v-for="(cal,idx) in cals" :value="idx">{{ cal.name }}</option>
        </select>
      </div>
      <div class="dbg-section">
        <label
            style="display: block"
            for="appt_debug-sync-remote">
          {{ t('appointments', 'Sync Remote Calendar Now') }}:</label>
        <select
            :disabled="!hasSubscriptions"
            v-model="calsIdx"
            @change="handleSyncRemote"
            class="dbg-input"
            id="appt_debug-sync-remote">
          <option :value="-1">{{ t('appointments', 'Calendar Required') }}</option>
          <option v-for="(cal,idx) in cals" v-if="cal.isSubscription==='1'" :value="idx">{{ cal.name }}</option>
        </select>
      </div>
      <div class="dbg-section">
        <input
            type="checkbox"
            :disabled="isSending"
            v-model="dbgInfo.log_rem_blocker"
            @change="handleSetLogBlockers"
            id="appt_debug-log-blockers"
            class="checkbox"><label
          for="appt_debug-log-blockers">{{ t('appointments', 'Log remote blockers') }}</label>
      </div>
    </div>
  </div>
</template>

<script>
import {showError} from "@nextcloud/dialogs";
import axios from "@nextcloud/axios";

export default {
  name: "Debugging",
  mounted: function () {
    this.isLoading = true
    this.start()
  },
  inject: ['getState', 'setState'],
  data: function () {
    return {
      isLoading: true,
      cals: [],
      calsIdx: -1,
      dbgInfo: {
        log_rem_blocker: false
      },
      isSending: false,
      hasSubscriptions: false,
    }
  },
  methods: {
    async start() {
      this.isLoading = true
      this.cals.splice(0, this.cals.length)
      try {
        const res = await axios.get('callist?mode=2')
        const cals = res.data.split(String.fromCharCode(31))
        for (let i = 0, cal, d, l = cals.length; i < l; i++) {
          cal = cals[i].split(String.fromCharCode(30))
          d = {
            name: cal[0],
            id: cal[2],
            isReadOnly: cal[3],
            isSubscription: cal[4] || '0'
          }
          if (d.isSubscription === '1') {
            this.hasSubscriptions = true
          }
          this.cals.push(d)
        }

        this.dbgInfo = await this.getState("get_dbg", "")

      } catch (e) {
        console.log(e)
        showError(this.t('appointments', "Can not load calendars or settings"))
      }
      this.isLoading = false
    },
    handleGetCalendarData() {
      if (this.calsIdx > -1) {
        console.log("cal:", this.cals[this.calsIdx])
        this.$root.$emit('startDebug', {type: "raw_cal", cal_info: this.cals[this.calsIdx]})
      }
    },
    handleSyncRemote(){
      if (this.calsIdx > -1) {
        console.log("handleSyncRemote cal:", this.cals[this.calsIdx])
        this.$root.$emit('startDebug', {type: "sync_remote", cal_info: this.cals[this.calsIdx]})
      }
    },
    handleSetLogBlockers() {
      if (this.isSending === true) {
        return
      }

      this.isSending = true
      this.setState('set_dbg', this.dbgInfo, '', {noFormData: true}).then(() => {
        this.isSending = false
      })
    }
  },
}
</script>

<style scoped>
.srgdev-appt-sb-linker:hover {
  text-decoration: underline;
  cursor: pointer;
}

.dbg-section {
  margin-top: 1em;
  padding-bottom: .125em;
}

.dbg-input {
  min-width: 80%;
  display: block;
}
</style>