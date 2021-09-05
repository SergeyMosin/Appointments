<template>
<div class="srgdev-fid_sec">
  <div v-show="isLoading===true" class="sb_loading_cont">
    <span class="icon-loading sb_loading_icon_cont"></span>
    <span class="sb_loading_text">{{t('appointments','Loading')}}</span>
  </div>
  <div v-show="isLoading===false">
  <div class="srgdev-fid_sec_left">
  <label class="fid-label" for="srgdev-fid-ta">JSON Object</label>
  <textarea v-model="fiInfo" id="srgdev-fid-ta" class="fid-textarea"></textarea>
    <div style="font-size: 90%; font-style: italic;">GUI is under development. See <a target="_blank" href="https://github.com/SergeyMosin/Appointments/issues/24#issuecomment-721103321">https://github.com/SergeyMosin/Appointments/issues/24#issuecomment-721103321</a> for more info.</div>
  <div class="srgdev-fid_html" v-show="rawHtml!==''">
    <label class="fid-label">HTML:</label>
    <code class="srgdev-fid_html_raw">{{rawHtml}}</code>
  </div></div>
  <button
      @click="apply"
      class="primary srgdev-appt-sb-genbtn"
      :class="{'appt-btn-loading':isSending}">{{t('appointments','Apply')}}
  </button>
  </div>
</div>
</template>

<script>
import {showError} from "@nextcloud/dialogs"
export default {
  name: "FormInputsDesigner",
  data: function () {
    return {
      isSending:false,
      isLoading:false,
      fiInfo:"",
      rawHtml:''
    }
  },
  inject: ['getState', 'setState'],
  mounted: function () {
    this.isLoading=true
    this.start()
  },
  methods:{
    async start(){
      this.isLoading=true
      try {
        const o = await this.getState("get_fi", "")
        this.fiInfo = JSON.stringify(o[0],null,2)
      } catch (e) {
        console.log(e)
        showError(this.t('appointments', "Can not request data"))
      }
      this.isLoading=false
    },
    apply(){
      this.rawHtml=''
      this.isSending=true
      let o
      if(this.fiInfo.trim()===''){
        o=[]
        this.fiInfo=''
      }else {
        try {
          o = [JSON.parse(this.fiInfo)]
        } catch (e) {
          console.log(e)
          showError(this.t('appointments', "Bad json, check console"))
          return
        }
      }

      this.setState('set_fi',o).then((r)=>{
        if(r===false){
          this.rawHtml=''
        }else if(r===''){
          if(this.fiInfo!==''){
            this.rawHtml="ERROR: Check JSON"
          }
        }else this.rawHtml=r

        this.isSending=false
      })
    }
  }

}
</script>

<style scoped lang="scss">
  .srgdev-fid_sec_left{
    width: 22em;
    position: relative;
    margin-top: 1.75em;
  }
  .srgdev-fid_html{
    position: absolute;
    left: 100%;
    top: 0;
    margin-left: 2em;
    width: 18em;
    &_raw{
      display: block;
      border: 1px solid var(--color-border);
      padding: .25em;
      max-height: 10em;
      overflow: auto;
      width: 100%;
    }
  }
  .fid-label{
    display: block;
  }
  .fid-textarea{
    width: 100%;
    max-width: 100%;
    min-height: 10em;
    max-height: 20em;
  }

</style>