<template>
  <SlideBar :title="t('appointments','Talk App Integration')" :subtitle="t('appointments','Talk room settings for appointments')" icon="icon-appt-go-back" @close="close">
    <template slot="main-area">
      <div v-show="isLoading===true" class="sb_loading_cont">
        <span class="icon-loading sb_loading_icon_cont"></span>
        <span class="sb_loading_text">{{t('appointments','Loading')}}</span>
      </div>
      <div
          v-show="isLoading===false"
          :class="{'sb_disable':isSending}"
          class="srgdev-appt-sb-main-cont">
        <input
            v-model="talkInfo.enabled"
            type="checkbox"
            id="srgdev-appt_talk-enabled"
            class="checkbox"><label style="margin-left: -3px;" class="srgdev-appt-sb-label-inline" for="srgdev-appt_talk-enabled">{{t('appointments','Create rooms for confirmed appointments')}}</label><br>
        <div
            class="srgdev-appt-sb-indent_small"
            :class="{'sb_disable_nav-item':talkInfo.enabled===false}"
            style="margin-top: 1.25em">
          <div class="srgdev-appt-sb-chb-cont"><input
              v-model="talkInfo.delete"
              :disabled="talkInfo.enabled===false"
              type="checkbox"
              id="srgdev-appt_talk-delete"
              class="checkbox"><label class="srgdev-appt-sb-label-inline" for="srgdev-appt_talk-delete">{{t('appointments','Delete when appointments is removed')}}</label></div>
          <div class="srgdev-appt-sb-chb-cont top-margin"><input
              v-model="talkInfo.lobby"
              :disabled="talkInfo.enabled===false"
              @click="checkClick('lobby',$event)"
              type="checkbox"
              id="srgdev-appt_talk-lobby"
              class="checkbox"><label class="srgdev-appt-sb-label-inline" for="srgdev-appt_talk-lobby">{{t('appointments','Enable lobby')}}</label></div>
          <div class="srgdev-appt-info-lcont srgdev-appt-sb-chb-cont  top-margin"><input
              v-model="talkInfo.password"
              :disabled="talkInfo.enabled===false"
              @click="checkClick('pass',$event)"
              type="checkbox"
              id="srgdev-appt_talk-password"
              class="checkbox"><label class="srgdev-appt-sb-label-inline" for="srgdev-appt_talk-password">{{t('appointments','Guest password')}}</label><a
              class="icon-info srgdev-appt-info-link"
              @click="$root.$emit('helpWanted','talkPassword')"></a></div>
          <label
              class="tsb-label top-margin"
              for="srgdev-appt_talk-nameFormat">
            {{t('appointments', 'Talk room name')}}:</label>
          <select
              v-model="talkInfo.nameFormat"
              :disabled="talkInfo.enabled===false"
              @focus="noKeyNoFocus"
              @click="checkClick('name',$event)"
              class="tsb-input"
              id="srgdev-appt_talk-nameFormat">
            <option :value="0">{{ t('appointments', 'Guest name + Date/Time') }}</option>
            <option :value="1">{{ t('appointments', 'Date/Time + Guest name') }}</option>
            <option :value="2">{{ t('appointments', 'Guest name only') }}</option>
          </select>
          <div class="srgdev-appt-info-lcont top-margin">
            <label
                class="srgdev-appt-sb-label-inline"
                for="srgdev-appt_talk-eml-override">
              {{t('appointments','Customize email text:')}}</label><a
              class="icon-info srgdev-appt-info-link"
              @click="$root.$emit('helpWanted','talkEmailTxt')"></a>
          </div>
          <textarea
              :disabled="talkInfo.enabled===false"
              @click="checkClick('email',$event)"
              @focus="noKeyNoFocus"
              :placeholder="t('appointments','Chat/Call link:')+' https://my_domain.com/index.php/call/to6d6y4e'"
              v-model="talkInfo.emailText"
              class="srgdev-appt-sb-textarea"
              id="srgdev-appt_talk-eml-override"
          ></textarea>
          <div class="srgdev-appt-info-lcont srgdev-appt-sb-chb-cont  top-margin" style="margin-top: 1.375em"><input
              v-model="talkInfo.formFieldEnable"
              :disabled="talkInfo.enabled===false"
              @click="checkClick('form',$event)"
              type="checkbox"
              id="srgdev-appt_talk-formFiled"
              class="checkbox"><label class="srgdev-appt-sb-label-inline" for="srgdev-appt_talk-formFiled">{{t('appointments','Add "Meeting Type" form field')}}</label><a
              class="icon-info srgdev-appt-info-link"
              @click="$root.$emit('helpWanted','talkFF')"></a></div>
          <div v-show="(talkInfo.formFieldEnable===true && talkInfo.enabled===true)" class="srgdev-appt-sb-indent_small">
            <label
                class="srgdev-appt-sb-label"
                for="srgdev-appt_talk-ff-label">
              {{t('appointments','Label text:')}}</label>
            <input
                :placeholder="talkInfo.formDefLabel"
                v-model="talkInfo.formLabel"
                class="srgdev-appt-sb-input-text"
                id="srgdev-appt_talk-ff-label"
                type="text">
            <label
                class="srgdev-appt-sb-label"
                for="srgdev-appt_talk-ff-plh">
              {{t('appointments','Placeholder text:')}}</label>
            <input
                :placeholder="talkInfo.formDefPlaceholder"
                v-model="talkInfo.formPlaceholder"
                class="srgdev-appt-sb-input-text"
                id="srgdev-appt_talk-ff-plh"
                type="text">
            <label
                class="srgdev-appt-sb-label"
                for="srgdev-appt_talk-ff-real">
              {{t('appointments','"In-person" option text:')}}</label>
            <input
                :placeholder="talkInfo.formDefReal"
                v-model="talkInfo.formTxtReal"
                class="srgdev-appt-sb-input-text"
                id="srgdev-appt_talk-ff-real"
                type="text">
            <label
                class="srgdev-appt-sb-label"
                for="srgdev-appt_talk-ff-virtual">
              {{t('appointments','"Online" option text:')}}</label>
            <input
                :placeholder="talkInfo.formDefVirtual"
                v-model="talkInfo.formTxtVirtual"
                class="srgdev-appt-sb-input-text"
                id="srgdev-appt_talk-ff-virtual"
                type="text">
            <div class="srgdev-appt-info-lcont top-margin">
              <label
                  class="srgdev-appt-sb-label-inline"
                  for="srgdev-appt_talk-type-change">
                {{t('appointments','Type change email text:')}}</label><a
                class="icon-info srgdev-appt-info-link"
                @click="$root.$emit('helpWanted','talkTypeChange')"></a>
            </div>
            <textarea
                :disabled="talkInfo.enabled===false"
                @focus="noKeyNoFocus"
                :placeholder="t('appointments','See documentation')"
                v-model="talkInfo.formTxtTypeChange"
                class="srgdev-appt-sb-textarea"
                id="srgdev-appt_talk-type-change"
            ></textarea>
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
export default {
  name: "TalkSlideBar",
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
      hasKey:false,
      talkInfo:{
        enabled:false,
        delete:false,
        emailText:"",
        lobby:false,
        password:false,
        nameFormat:0,

        formFieldEnable: false,
        formLabel:"",
        formPlaceholder:"",
        formTxtReal:"",
        formTxtVirtual:"",

        formDefLabel:"",
        formDefPlaceholder:"",
        formDefReal:"",
        formDefVirtual:"",

        formTxtTypeChange:""
      }
    }
  },
  methods: {
    async start() {
      this.isLoading=true

      try {
        const ks= await this.getState("get_k")
        this.hasKey=ks!==""
        this.talkInfo = await this.getState("get_talk", "")
        this.isLoading=false
      } catch (e) {
        this.isLoading=false
        console.log(e)
        OC.Notification.showTemporary(this.t('appointments', "Can not request data"), {timeout: 4, type: 'error'})
      }
    },

    checkClick(what,evt){
      if(this.hasKey===false) {
        evt.preventDefault()
        let txt
        switch (what){
          case "lobby":
            txt=this.t('appointments', "Automatically enable Talk lobby when an appointment is confirmed.")
            break
          case "pass":
            txt=this.t('appointments', "Automatically set Talk room pseudo random password when an appointment is confirmed.")
            break
          case "name":
            txt=this.t('appointments', "Customize Talk room name.")
            break
          case "email":
            txt=this.t('appointments', "Customize Talk room URL email text.")
            break
          case "form":
            txt=this.t('appointments', '"Meeting type" form field.')
            break
          default:
            txt="New feature"
        }
        this.$emit("showCModal", txt)
      }else if(what==='lobby'){
        if(this.talkInfo.lobby===false) {
          this.$emit("showModal", [
            this.t('appointments', 'Warning'),
            this.t('appointments', 'Guest will be placed in the lobby until you allow access to the Room manually via Talk app side menu.')])
        }
        if(this.talkInfo.password===true){
          this.talkInfo.password=false
        }
      }else if(what==='pass'){
        if(this.talkInfo.lobby===true){
          this.talkInfo.lobby=false
        }
      }
    },

    noKeyNoFocus(evt){
      if(this.hasKey===false){
        evt.currentTarget.blur()
        evt.preventDefault()
      }
    },

    apply(){
      this.isSending=true
      this.setState('set_talk',this.talkInfo).then(()=>{
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
.top-margin{
  margin-top: .75em;
}
.tsb-label {
  display: block;
  margin-top: .75em;
}
.tsb-input {
  margin-top: 0;
  display: block;
  min-width: 80%;
  margin-bottom: .5em;
  color: var(--color-main-text);
}
</style>
