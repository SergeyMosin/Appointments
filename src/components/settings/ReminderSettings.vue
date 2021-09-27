<template>
  <SlideBar :title="t('appointments','Appointment Reminders')"
            :subtitle="t('appointments','Send appointment reminders to attendees')" icon="icon-appt-go-back"
            @close="close">
    <template slot="main-area">
      <div v-show="isLoading===true" class="sb_loading_cont">
        <span class="icon-loading sb_loading_icon_cont"></span>
        <span class="sb_loading_text">{{ t('appointments', 'Loading') }}</span>
      </div>
      <div
          v-show="isLoading===false"
          :class="{'sb_disable':isSending}"
          class="srgdev-appt-sb-main-cont">
        <div v-if="reminderInfo.bjm!=='cron' && reminderInfo.bjm!=='webcron'"
             class="srgdev-appt-warning-div">
          <h3 class="srgdev-appt-warning-h3-rem">Warning</h3>
          You are using <strong>AJAX</strong> scheduling method, which <span style="font-style: italic">"is the least reliable"</span>.
          Please consider use <strong>Webcron</strong> or
          <strong>Cron</strong> scheduling methods. More information is available in <a
            style="text-decoration: underline"
            href="https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/background_jobs_configuration.html#parameters"
            target="_blank">Admin Manual: Cron jobs</a> section.
        </div>
        <div v-for="(item,index) in reminderInfo.data" style="margin-bottom: 1.5em"
             @click="checkKey"
             :key="index"
             :class="{'rem-disable': !hasKey && index>0,'rem-unused': item.seconds==='0'}">
          <label
              :for="'srgdev-appt_rem-data'+index+'-sec'" class="rem-disable-inner">
            {{ (index + 1) + ". " + t('appointments', 'Time before appointment') }}:</label>
          <select
              style="margin-bottom: 4px"
              class="srgdev-appt-sb-input-select rem-disable-inner"
              v-model="item.seconds"
              :id="'srgdev-appt_rem-data'+index+'-sec'">
            <option value="0">{{ t('appointments', 'Not Used') }}</option>
            <option value="3600">{{ t('appointments', '1 Hour') }}</option>
            <option value="7200">{{ t('appointments', '2 Hours') }}</option>
            <option value="14400">{{ t('appointments', '4 Hours') }}</option>
            <option value="28800">{{ t('appointments', '8 Hours') }}</option>
            <option value="86400">{{ t('appointments', '24 hours') }}</option>
            <option value="172800" @click="checkKey" :disabled="!hasKey">{{ t('appointments', '2 Days') }}</option>
            <option value="259200" :disabled="!hasKey">{{ t('appointments', '3 Days') }}</option>
            <option value="345600" :disabled="!hasKey">{{ t('appointments', '4 Days') }}</option>
            <option value="432000" :disabled="!hasKey">{{ t('appointments', '5 Days') }}</option>
            <option value="518400" :disabled="!hasKey">{{ t('appointments', '6 Days') }}</option>
            <option value="604800" :disabled="!hasKey">{{ t('appointments', '7 Days') }}</option>
          </select>
          <input
              v-model="item.actions"
              type="checkbox"
              :disabled="!hasKey && index>0"
              :id="'srgdev-appt_rem-data'+index+'-act'"
              class="checkbox rem-disable-inner">
          <label
              :for="'srgdev-appt_rem-data'+index+'-act'"
              style="margin-left: -3px;"
              class="srgdev-appt-sb-label-inline rem-disable-inner">{{
              t('appointments', 'Add action links')
            }}</label>
        </div>
        <label
            class="srgdev-appt-sb-label"
            for="srgdev-appt_rem-more-text">
          {{ t('appointments', 'Additional reminder email text:') }}</label>
        <textarea
            v-model="reminderInfo.moreText"
            class="srgdev-appt-sb-textarea"
            id="srgdev-appt_rem-more-text"
        ></textarea>
        <button
            @click="apply"
            class="primary srgdev-appt-sb-genbtn"
            :class="{'appt-btn-loading':isSending}">{{ t('appointments', 'Apply') }}
        </button>
      </div>
    </template>
  </SlideBar>
</template>

<script>
import SlideBar from "../SlideBar.vue"
import {showError} from "@nextcloud/dialogs"

export default {
  name: "ReminderSettings",
  components: {
    SlideBar
  },
  props: {
    title: '',
    subtitle: '',
  },
  mounted: function () {
    this.isLoading = true
    this.start()
  },
  inject: ['getState', 'setState'],
  data: function () {
    return {
      isLoading: true,
      isSending: false,
      hasKey: false,
      reminderInfo: {
        data: [{
          seconds: "0",
          actions: true
        }, {
          seconds: "0",
          actions: true
        }, {
          seconds: "0",
          actions: true
        }],
        // friday: false,
        moreText: "",
        bjm: "",
      },
    }
  },
  methods: {
    async start() {
      this.isLoading = true
      try {
        const ks = await this.getState("get_k")
        this.hasKey=ks!==""
        this.reminderInfo = await this.getState("get_reminder", "")
        this.isLoading = false
      } catch (e) {
        this.isLoading = false
        console.log(e)
        showError(this.t('appointments', "Can not request data"))
      }
    },

    checkKey(evt) {
      const t = evt.target
      if (!this.hasKey && (t.className.indexOf("rem-disable") === 0  || t.parentElement.className.indexOf("rem-disable") === 0)) {
        evt.preventDefault()
        evt.stopPropagation()
        this.$emit("showCModal", this.t('appointments', "Multiple Reminders"))
      }
    },

    apply() {
      this.isSending = true
      this.setState('set_reminder', this.reminderInfo).then(() => {
        this.isSending = false
      })
    },
    close() {
      this.$emit('close')
    }
  }
}
</script>

<style lang="scss" scoped>
.rem-disable {
  opacity: .5;

  .rem-disable-inner {
    pointer-events: none;
  }
}

.rem-unused:not(.rem-disable) {
  opacity: .75;
}

.srgdev-appt-warning-h3-rem {
  background: var(--color-warning);
  padding: .2em .3em;
  font-weight: bold;
}

.srgdev-appt-warning-div {
  margin-left: -4%;
  padding-bottom: 1em;
  margin-bottom: 1.5em;
  border-bottom: 2px solid var(--color-warning);
}
</style>