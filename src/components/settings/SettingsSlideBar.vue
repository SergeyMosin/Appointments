<template>
  <SlideBar :title="t('appointments','Additional Settings')" :subtitle="t('appointments','Customize and configure the app')" @close="close">
    <template slot="main-area">
      <NcAppNavigationItem
          :title="t('appointments','Customize Public Page')"
          @click="$emit('gotoPPS')"
          icon="icon-category-customization"/>
      <NcAppNavigationItem
          :title="t('appointments','Email Settings')"
          @click="$emit('gotoEML')"
          icon="icon-mail"/>
      <NcAppNavigationItem
					v-if="talkEnabled"
          :title="t('appointments','Talk Integration')"
          @click="$emit('gotoTALK')"
          icon="icon-talk"/>
      <NcAppNavigationItem
          v-show="showDirPage===true"
          :title="t('appointments','Directory Page')"
          @click="$emit('gotoDIR')"
          icon="icon-projects"/>
      <NcAppNavigationItem
          :title="t('appointments','Reminders')"
          @click="$emit('gotoREM')"
          icon="icon-appt-reminder"/>
      <NcAppNavigationItem
          :title="t('appointments','Advanced Settings')"
          @click="$emit('gotoADV')"
          icon="icon-settings-dark"/>
      <div v-show="hasKey===false">
        <NcAppNavigationItem
            v-show="showKeyInput===false"
            :title="t('appointments','Contributor Key')"
            @click="openKeyInput()"
            icon="icon-appt-key"/>
        <ActionInput
            ref="keyInput"
            :value="keyValue"
            @submit="setKey"
            v-show="showKeyInput===true"
            style="display: block"
            class="srgdev-appt-act-input-ext"
            :icon="sendingKey===false?'icon-appt-key':'icon-loading-small'"/>
      </div>
    </template>
  </SlideBar>
</template>

<script>
import SlideBar from "../SlideBar.vue"
import ActionInput from "../ActionInputExt.vue";
import {
  NcAppNavigationItem,
} from '@nextcloud/vue'
import {showError} from "@nextcloud/dialogs"
export default {
  name: "SettingsSlideBar",
  components: {
    SlideBar,
    NcAppNavigationItem,
    ActionInput
  },
  props:{
    title:'',
    subtitle:'',
    showDirPage:false,
		talkEnabled: true,
  },
  inject: ['getState','setState'],
  mounted: function () {
    this.hasKey=true
    this.start()
  },
  data: function () {
    return {
      hasKey:true,
      showKeyInput:false,
      sendingKey:false,
      keyValue:""
    }
  },
  methods: {
    start(){
      this.getState("get_k").then(k=>{
          this.hasKey=k!==""
      })
    },
    openKeyInput(){
      this.showKeyInput=true
      this.$nextTick(()=> {
        const elm=this.$refs["keyInput"];
        if(elm.$refs!==undefined && elm.$refs['form']!==undefined
            && elm.$refs['form'][1]!==undefined
            && typeof elm.$refs['form'][1].focus==="function"
        ){
          elm.$refs['form'][1].focus()
        }
      })
    },
    setKey(evt){
      const elm=evt.target.querySelector('input[type=text]')
      if(elm!==null){
        const v=elm.value
        if(v.length>20){
          this.sendingKey=true
          this.setState('set_k',{k:v}).then(r=>{
            this.sendingKey=false
            if(r===false){
              this.keyValue=v
              showError(this.t('appointments', "Error: Please check key"))
            }else{
              this.keyValue=""
              this.hasKey=true
              this.$emit("showModal", [
                this.t('appointments', 'Thank You'),
                this.t('appointments', 'Key accepted. All contributor only features are unlocked.')])
            }
          })
        }else{
          showError(this.t('appointments', "Error: Invalid Key"))
        }
      }else{
        showError(this.t('appointments', "Value not available"))
      }
    },
    close(){
      this.$emit('close')
    }
  }
}
</script>
