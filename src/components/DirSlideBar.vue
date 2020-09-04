<template>
  <SlideBar :title="t('appointments','Directory Page Settings')" :subtitle="t('appointments','Add, remove and edit directory page links')"
            :icon="iconGoBack?'icon-appt-go-back':'icon-close'" @close="close">
    <template slot="main-area">
      <div v-show="isLoading===true" class="sb_loading_cont">
        <span class="icon-loading sb_loading_icon_cont"></span>
        <span class="sb_loading_text">{{t('appointments','Loading')}}</span>
      </div>
      <div
          v-show="isLoading===false"
          :class="{'sb_disable':isSending!==-1}"
          style="padding-left: 0"
          class="srgdev-appt-sb-main-cont">
        <div v-for="(pl,index) in dirInfo" class="srgdev-appt-dir-pl">
          <Actions
              v-show="editNumber===-1"
              menuAlign="right"
              style="position: absolute"
              class="srgdev-appt-dir-pl_actions">
            <ActionButton
                :closeAfterClick="true"
                @click="editPageLink(index)"
                icon="icon-edit">{{t('appointments','Edit')}}</ActionButton>
            <ActionButton
                :closeAfterClick="true"
                @click="deletePageLink(index)"
                icon="icon-delete">{{t('appointments','Delete')}}</ActionButton>
          </Actions>
          <div v-show="editNumber!==index">
            <div class="srgdev-appt-dir-pl_title">{{pl.title}}</div>
            <div class="srgdev-appt-dir-pl_sub">{{pl.subTitle}}</div>
          </div>
          <div v-show="editNumber===index">
            <label
                class="srgdev-appt-dir-pl_label"
                for="srgdev-appt-dpl-title">{{t('appointments','Title')}}</label>
            <input
                :ref="'pl_input'+index"
                v-model="pl.title"
                class="srgdev-appt-dir-pl_input"
                type="text" id="srgdev-appt-dpl-title">
            <label class="srgdev-appt-dir-pl_label" for="srgdev-appt-dpl-sub">{{t('appointments','Sub Title')}}</label>
            <input
                v-model="pl.subTitle"
                class="srgdev-appt-dir-pl_input"
                type="text" id="srgdev-appt-dpl-sub">
            <label class="srgdev-appt-dir-pl_label" for="srgdev-appt-dpl-text">{{t('appointments','Text')}}</label>
            <input
                v-model="pl.text"
                class="srgdev-appt-dir-pl_input"
                type="text" id="srgdev-appt-dpl-text">
            <label class="srgdev-appt-dir-pl_label" for="srgdev-appt-dpl-url">{{t('appointments','URL')}}</label>
            <div class="srgdev-appt-dir-pl_combo">
              <input
                  v-model="pl.url"
                class="srgdev-appt-dir-pl_input"
                type="text" id="srgdev-appt-dpl-url">
              <ActionsOpenUp
                  :ref="'actionsOpenUp'+index"
                  defaultIcon="icon-projects"
                  menuAlign="right"
                  style="position: absolute"
                  class="srgdev-appt-dir-pl_actions srgdev-appt-dir-pl_actions_cmb">
                <ActionButton
                    v-for="l in pageLabels" :key="l.id"
                    :data-pid="l.id"
                    @click="urlSelected(l.id)"
                    :closeAfterClick="true"
                    :icon="urlLoadingId===l.id+index?'icon-loading-small':'icon-public'">
                  {{l.label}}
                </ActionButton>
              </ActionsOpenUp>
            </div>
            <button @click="savePageLink()" class="primary"
                    style="padding-left: 2em;padding-right: 2em"
                    :class="{'appt-btn-loading':isSending===index}">{{t('appointments','Save')}}</button>
            <button @click="cancelEdit()">{{t('appointments','Cancel')}}</button>
          </div>
        </div>
        <button @click="addPageLink()" style="margin-top: 1.25em" class="srgdev-icon-btn"><span class="icon-add srgdev-icon-btn_icon"></span><span class="srgdev-icon-btn_text">{{t('appointments','Add New Link')}}</span></button>
      </div>
    </template>
  </SlideBar>
</template>

<script>
import SlideBar from "./SlideBar.vue"
import {ActionButton, Actions, Multiselect} from "@nextcloud/vue"
import ActionsOpenUp from "./ActionsOpenUp.vue";

export default {
  name: "DirSlideBar",
  components: {
    Multiselect,
    SlideBar,
    Actions,
    ActionsOpenUp,
    ActionButton
  },
  props:{
    title:'',
    subtitle:'',
    iconGoBack:false,
    page0Label:"",
    morePages:Array
    // curPageData:Object,
  },
  mounted: function () {
    this.isLoading=true
    this.start()
  },
  inject: ['getState', 'setState'],
  data: function () {
    return {
      isLoading:true,
      isSending:-1,
      editNumber:-1,
      isEditNew:false,
      pageLabels:[],
      urlLoadingId:"",
      /**
       * @typedef {Object} pageLink
       * @property {string} title
       * @property {string} subTitle
       * @property {string} text
       * @property {string} url
       * // not implemented (yet)...
       * // @property {int} avatar 0=none, 1=title First Letter, 2=url
       * // @property {string} avatarUrl
       */
      /** @type {Array.<pageLink>} dirInfo */
      dirInfo: [],
    }
  },
  methods: {

    async start() {
      this.isLoading=true

      this.pageLabels.push({
        label:this.page0Label,
        id:'p0'
      })
      this.morePages.forEach(p=>{
        this.pageLabels.push({
          label:p.label,
          id:p.pageId
        })
      })

      this.getDir()
    },

    async urlSelected(pageId){
      this.urlLoadingId=pageId+this.editNumber
      const urls= await this.getState('get_puburi',pageId)
      this.dirInfo[this.editNumber].url=urls.split(String.fromCharCode(31))[0]
      this.urlLoadingId=""
      this.$refs["actionsOpenUp"+this.editNumber][0].closeMenu()
    },

    savePageLink(){
      if(this.dirInfo[this.editNumber].title===""
        || this.dirInfo[this.editNumber].url===""){

        this.$emit("showModal", [
          this.t('appointments', 'Error'),
          this.t('appointments', 'Title and URL can be empty')])
        return
      }
      this.sendToServer()
    },

    cancelEdit(){
      if(this.isEditNew===true){
        this.dirInfo.splice(this.dirInfo.length-1,1)
      }
      this.editNumber=-1
    },

    addPageLink() {
      this.dirInfo.push({
        title:"",
        subTitle:"",
        text:"",
        url:""
      })
      this.isEditNew=true
      this.editNumber=this.dirInfo.length-1
      this.$nextTick(()=> {
        this.$refs["pl_input" + this.editNumber][0].focus()
      })
    },

    editPageLink(n){
      this.isEditNew=false
      this.editNumber=n
      this.$nextTick(()=>{
        this.$refs['pl_input'+this.editNumber][0].focus()
      })
    },

    deletePageLink(n){
      this.dirInfo.splice(n,1)
    },

    sendToServer(){
      this.isSending=this.editNumber
      this.setState('set_dir',this.dirInfo,'dir').then(()=>{
        this.isSending=-1
        this.editNumber=-1
        this.getDir()
      })
    },

    getDir(){
      this.isLoading=true
      this.getState('get_dir').then((r)=>{
        this.dirInfo=r
        this.isLoading=false
      })
    },


    close(){
      this.$emit('close')
    }
  }
}
</script>
<style scoped>
.srgdev-appt-dir-pl_input{
  margin-top: 0;
}
</style>