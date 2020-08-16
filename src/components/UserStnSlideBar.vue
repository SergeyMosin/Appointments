<template>
    <SlideBar :title="t('appointments','Your Contact Information')" :subtitle="t('appointments','Form header and event organizer settings')" @close="close">
        <template slot="main-area">
          <div v-show="isLoading===true" class="sb_loading_cont">
            <span class="icon-loading sb_loading_icon_cont"></span>
            <span class="sb_loading_text">{{t('appointments','Loading')}}</span>
          </div>
          <div
              v-show="isLoading===false"
              :class="{'sb_disable':isSending}"
              class="srgdev-appt-sb-main-cont">
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
                        @click="$root.$emit('helpWanted','emaildef')"><span>{{uciInfo.useDefaultEmail==='yes'?'useDefaultEmail=yes':''}}</span></a>
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
                :disabled="uciInfo.email==='' || uciInfo.organization==='' || uciInfo.address===''"
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
        name: "MailStnSlideBar",
        components: {
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
      data: function () {
        return {
          isLoading:true,
          isSending:false,
          uciInfo: {
            organization: "",
            email: "",
            address: "",
            phone: "",
            useDefaultEmail:"yes"
          }
        }
      },
        methods: {

          async start() {
            this.isLoading=true
            try {
              this.uciInfo = await this.getState("get_uci", "")
              this.isLoading=false
            } catch (e) {
              this.isLoading=false
              console.log(e)
              OC.Notification.showTemporary(this.t('appointments', "Can not request data"), {timeout: 4, type: 'error'})
            }
          },

          apply(){
              this.isSending=true
              this.setState('set_uci',this.uciInfo).then(()=>{
                this.isSending=false
              })
            },
            close(){
                this.$emit('close')
            }
        }
    }
</script>
