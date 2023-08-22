<template>
  <Content app-name="appointments" :class="{'srgdev-slider-open':sbShow!==0}">
    <AppNavigation>
      <template #list :class="{'sb_disable':stateInProgress || visibleSection===1}">
        <AppNavigationItem
            :class="{'sb_disable_nav-item':sbShow!==0}"
            @click="curPageId='p0';getFormData('p0')"
            class="srgdev-pubpage-nav-item"
            :title="(
              checkPageLabel(page0.label)+' '+
              (page0.enabled===1
                ?t('appointments','[Online]')
                :t('appointments','[Disabled]'))
              )"
            :icon="pageInfoLoading!==1?
                      (page0.enabled===1
                        ?(!page0.privatePage?'icon-screen':'icon-appt-private-mode-page')
                        :'icon-screen-off'):''"
            :loading="pageInfoLoading===1">
          <template #actions>
            <ActionButton
                v-show="page0.enabled===0"
                @click="setPageEnabled('p0',1)"
                icon="icon-checkmark-color"
                closeAfterClick>
              {{ t('appointments', 'Share Online') }}
            </ActionButton>
            <ActionButton
                v-show="page0.enabled===1"
                @click="setPageEnabled('p0',0)"
                icon="icon-category-disabled"
                closeAfterClick>
              {{ t('appointments', 'Stop Sharing') }}
            </ActionButton>
            <ActionButton @click="showPubLink('p0')" icon="icon-public" closeAfterClick>
              {{ t('appointments', 'Show URL/link') }}
            </ActionButton>
            <ActionInput data-pid="p0" @change="setPageLabel" icon="icon-rename" :value="page0.label">
              {{ checkPageLabel("") }}
            </ActionInput>
            <ActionButton
                @click="addNewPage()"
                icon="icon-add"
                closeAfterClick>
              {{ t('appointments', 'Add New Page') }}
            </ActionButton>
          </template>
        </AppNavigationItem>
        <AppNavigationItem
            v-for="(page,idx) in morePages"
            class="srgdev-pubpage-nav-item"
            :class="{'sb_disable_nav-item':sbShow!==0}"
            @click="curPageId=page.pageId;getFormData(page.pageId)"
            :title="(
              checkPageLabel(page.label)+' '+
              (page.enabled===1
                ?t('appointments','[Online]')
                :t('appointments','[Disabled]'))
              )"
            :icon="pageInfoLoading!==(idx+2)?
                      (page.enabled===1
                        ?(!page.privatePage?'icon-screen':'icon-appt-private-mode-page')
                        :'icon-screen-off'):''"
            :loading="pageInfoLoading===idx+2"
            :key="page.pageId">
          <template #actions>
            <ActionButton v-show="page.enabled===0" @click="setPageEnabled(page.pageId,1)"
                          icon="icon-checkmark-color"
                          closeAfterClick>
              {{ t('appointments', 'Share Online') }}
            </ActionButton>
            <ActionButton v-show="page.enabled===1" @click="setPageEnabled(page.pageId,0)"
                          icon="icon-category-disabled"
                          closeAfterClick>
              {{ t('appointments', 'Stop Sharing') }}
            </ActionButton>
            <ActionButton @click="showPubLink(page.pageId)" icon="icon-public" closeAfterClick>
              {{ t('appointments', 'Show URL/link') }}
            </ActionButton>
            <ActionInput :data-pid="page.pageId" @change="setPageLabel" icon="icon-rename" :value="page.label">
              {{ checkPageLabel("") }}
            </ActionInput>
            <ActionButton @click="deletePage(page.pageId)" icon="icon-delete" closeAfterClick>
              {{ t('appointments', 'Delete') }}
            </ActionButton>
          </template>
        </AppNavigationItem>
        <AppNavigationItem
            v-show="morePages.length>0"
            :class="{'sb_disable_nav-item':sbShow!==0}"
            @click="getFormData('dir')"
            class="srgdev-pubpage-nav-item"
            :title="t('appointments','Directory Page')"
            icon="icon-projects">
          <template #actions>
            <ActionButton @click="toggleSlideBar(12,'dir');sbGotoBack=0" icon="icon-settings-dark" closeAfterClick>
              {{ t('appointments', 'Settings') }}
            </ActionButton>
            <ActionButton @click="showPubLink('dir')" icon="icon-public" closeAfterClick>
              {{ t('appointments', 'Show URL/link') }}
            </ActionButton>
          </template>
        </AppNavigationItem>
        <li style="height: 16px"></li>
        <AppNavigationItem
            @click="openViaPicker(6,$event)"
            :title="t('appointments','Manage Appointment Slots')"
            icon="icon-appt-calendar-clock"/>
        <AppNavigationItem
            @click="openViaPicker(3,$event)"
            :title="t('appointments','User/Organization Info')"
            icon="icon-user"/>
        <AppNavigationItem
            @click="toggleSlideBar(9)"
            :title="t('appointments','Settings')"
            icon="icon-settings-dark"/>
        <li style="height: 16px"></li>
        <li style="margin-left: 12px">
          <NcCheckboxRadioSwitch
              :checked="useNcTheme"
              :loading="stateInProgress"
              @update:checked="toggleUseNcTheme">
            &nbsp;{{ t('appointments', 'Auto Style') }}
          </NcCheckboxRadioSwitch>
        </li>
        <AppNavigationItem
            :pinned="true"
            @click="showHelp"
            :title="t('appointments','Help/Tutorial')"
            icon="icon-info"/>
      </template>
    </AppNavigation>
    <AppContent
        style="transition: none;"
        class="srgdev-app-content"
        :aria-expanded="navOpen">
      <div v-show="visibleSection===2" class="srgdev-appt-cal-view-cont">
        <Modal
            v-if="generalModal!==0"
            class="srgdev-appt-modal-container"
            :canClose="false">
          <div>
            <div class="srgdev-appt-modal_pop">
              <span :data-pop="generalModalPop" class="srgdev-appt-modal_pop_txt">{{ generalModalPopTxt }}</span>
            </div>
            <div v-if="generalModal===1" class="srgdev-appt-modal_content">
              <div class="srgdev-appt-modal-header">
                {{
                  t('appointments', 'Public Page URL') + (generalModalBtnTxt !== "" ? (" - " + generalModalBtnTxt) : "")
                }}
              </div>
              <div v-if="generalModal===1 && generalModalLoadingTxt===''">
                <div class="srgdev-appt-modal-lbl" style="user-select: text; cursor: text;">
                <span
                    style="cursor: text; display: inline-block; vertical-align: middle;">{{ generalModalTxt[0] }}</span>
                  <div style="position: relative;">
                    <div class="srgdev-appt-icon_txt_btn icon-clippy" @click="doCopyPubLink">Copy</div>
                    <a target="_blank" :href="generalModalTxt[0]"
                       class="srgdev-appt-icon_txt_btn icon-external">Visit</a>
                    <ApptAccordion
                        v-show="generalModalTxt[1]!==''"
                        style="display: inline-block; margin-top: 1.25em; margin-left: .5em;"
                        title="Show iframe/embeddable"
                        :open="false">
                      <template #content>
                        <div class="srgdev-appt-modal-lbl_dim"
                             style="cursor: text;position: absolute;left: 0;width: 100%;text-align: center;margin: 0;">
                          {{ generalModalTxt[1] }}
                        </div>
                        <br><br>
                      </template>
                    </ApptAccordion>
                  </div>
                </div>
                <button @click="closeGeneralModal" class="primary srgdev-appt-modal-btn">
                  {{ t('appointments', 'Close') }}
                </button>
              </div>
              <div v-if="generalModal===1 && generalModalLoadingTxt!==''">
                <div class="srgdev-appt-modal-lbl">{{ generalModalLoadingTxt }}</div>
                <div class="srgdev-appt-modal-slider">
                  <div class="srgdev-appt-slider-line"></div>
                  <div class="srgdev-appt-slider-inc"></div>
                  <div class="srgdev-appt-slider-dec"></div>
                </div>
              </div>
            </div>
            <div v-if="generalModal===2" class="srgdev-appt-modal_content">
              <div class="srgdev-appt-modal-header">{{ t('appointments', 'Remove Old Appointments') }}</div>
              <div v-if="generalModal===2 && generalModalLoadingTxt===''">
                <div class="srgdev-appt-modal-lbl">{{ generalModalTxt[0] }}
                  <div :class="{'srgdev-appt-modal-lbl_dim':generalModalTxt[0]!==''}">{{ generalModalTxt[1] }}</div>
                </div>
                <button
                    @click="generalModalActionCallback();generalModalActionCallback=undefined"
                    v-show="generalModalTxt[0]!==''"
                    style="margin-right: 3em"
                    class="primary srgdev-appt-modal-btn">{{ t('appointments', 'Remove') }}
                </button>
                <button
                    v-show="generalModalTxt[0]!==''"
                    @click="closeGeneralModal"
                    class="srgdev-appt-modal-btn">{{ t('appointments', 'Cancel') }}
                </button>
                <button
                    v-show="generalModalTxt[0]===''"
                    @click="closeGeneralModal"
                    class="primary srgdev-appt-modal-btn">{{ t('appointments', 'Close') }}
                </button>
              </div>
              <div v-if="generalModal===2 && generalModalLoadingTxt!==''">
                <div class="srgdev-appt-modal-lbl">{{ generalModalLoadingTxt }}</div>
                <div class="srgdev-appt-modal-slider">
                  <div class="srgdev-appt-slider-line"></div>
                  <div class="srgdev-appt-slider-inc"></div>
                  <div class="srgdev-appt-slider-dec"></div>
                </div>
              </div>
            </div>
            <div v-if="generalModal===3" class="srgdev-appt-modal_content">
              <div class="srgdev-appt-modal-header">{{ generalModalTxt[0] }}</div>
              <div class="srgdev-appt-modal-lbl">{{ generalModalTxt[1] }}
              </div>
              <button
                  v-show="generalModalActionCallback!==undefined"
                  @click="actionGeneralModal"
                  class="srgdev-appt-modal-btn">{{ generalModalActionTxt }}
              </button>
              <button
                  @click="closeGeneralModal"
                  class="primary srgdev-appt-modal-btn">{{
                  generalModalBtnTxt === ""
                      ? t('appointments', 'Close')
                      : generalModalBtnTxt
                }}
              </button>
            </div>
          </div>
        </Modal>
      </div>
      <div v-show="visibleSection===1" class="srgdev-appt-cal-view-cont">
        <div class="srgdev-appt-grid-flex">
          <div v-show="gridMode===0" class="srgdev-appt-cal-view-btns">
            <button @click="addScheduleToCalendar()" class="primary">
              {{ t('appointments', 'Add to Calendar') }}
            </button>
            <button @click="closePreviewGrid()">
              {{ t('appointments', 'Discard') }}
            </button>
          </div>
          <div v-show="gridMode===1" class="srgdev-appt-cal-view-btns">
            <button style="margin-right: 1em" @click="saveTemplate()" class="primary">
              {{ t('appointments', 'Save') }}
            </button>
            <button @click="closePreviewGrid()">
              {{ t('appointments', 'Cancel') }}
            </button>
            <div
                style="float:right; font-style: italic; font-size: 75%; color: var(--color-text-light); padding-right: 1.5em; text-align: right; line-height: normal; margin-left: -9em">
              {{ t('appointments', 'Hint: right-click on appointment to edit.') }}<br>
              {{ t('appointments', 'Time zone') }}: {{ gridTzName }}
            </div>
          </div>
          <div class="srgdev-appt-grid-flex-lower">
            <ul class="srgdev-appt-grid-header">
              <li v-for="(hi, index) in gridHeader"
                  class="srgdev-appt-gh-li"
                  :style="{width:hi.w}">
                <div class="srgdev-appt-gh-txt">{{ hi.txt }}</div>
                <Actions
                    menuAlign="right"
                    :open="gridMenuOpen===index"
                    @open="gridMenuOpen=index"
                    class="srgdev-appt-gh-act1">
                  <ActionInput
                      :value="hi.n"
                      @submit="gridApptsAdd(index,$event)"
                      icon="icon-add"
                      class="srgdev-appt-gh-act-inp"
                      type="number"></ActionInput>
                  <ActionButton
                      icon="icon-delete"
                      :disabled="!hi.hasAppts"
                      :closeAfterClick="true"
                      @click="gridApptsDel(index)">{{ t('appointments', 'Remove All') }}
                  </ActionButton>
                  <ActionButton
                      :disabled="!hi.hasAppts"
                      :closeAfterClick="true"
                      v-if="index!==gridHeader.length-1"
                      icon="icon-category-workflow"
                      @click="gridApptsCopy(index)">{{ t('appointments', 'Copy to Next') }}
                  </ActionButton>
                </Actions>
              </li>
            </ul>
            <div @gridContext="editSingleAppt" ref="grid_cont" class="srgdev-appt-grid-cont"></div>
          </div>
        </div>
        <Modal v-if="evtGridModal!==0" class="srgdev-appt-modal-container" :canClose="false">
          <div :class="evtGridModal===5?'srgdev-appt-modal_content_tmpl':'srgdev-appt-modal_content'">
            <div v-if="evtGridModal===1" class="srgdev-appt-modal-lbl">
              {{
                t('appointments', 'Adding appointment to {calendarName} calendar …', {calendarName: calInfo.curCal_name})
              }}
            </div>
            <div v-if="evtGridModal===2" class="srgdev-appt-modal-lbl">
              {{
                t('appointments', 'All appointments have been added to {calendarName} calendar.', {calendarName: calInfo.curCal_name})
              }}
            </div>
            <div v-if="evtGridModal===3" class="srgdev-appt-modal-lbl">
              <span v-show="modalErrTxt!==''">{{ modalErrTxt }}</span>
              <span v-show="modalErrTxt===''">{{ t('appointments', 'Error occurred. Check console …') }}</span>
            </div>
            <div v-if="evtGridModal===4" class="srgdev-appt-modal-lbl">
              <div style="font-size: 110%;font-weight: bold">{{ modalHeader }}</div>
              <div style="user-select: text; cursor: text;">{{ modalText }}</div>
            </div>
            <div v-if="evtGridModal===1" class="srgdev-appt-modal-slider">
              <div class="srgdev-appt-slider-line"></div>
              <div class="srgdev-appt-slider-inc"></div>
              <div class="srgdev-appt-slider-dec"></div>
            </div>
            <TemplateApptOptions
                v-if="evtGridModal===5"
                :elm="evtGridElm"
                :cid="gridCID"
                :has-key="hasKey"
                :grid-shift="gridShift"
                @showCModal="showCModal"
                @tmplUpdateAppt="gridApptUpdate($event)"
                @tmplAddAppts="gridApptsAddTemplate($event)"
                @close="closeEvtModal"/>
            <button v-if="evtGridModal>1 && evtGridModal!==5" class="primary" @click="closeEvtModal">
              {{ t('appointments', 'Close') }}
            </button>
          </div>
        </Modal>
      </div>
      <div v-show="visibleSection===0" class="srgdev-appt-main-sec">
        <div class="srgdev-appt-main-info">
          {{ pagePreviewLabel + ' ' + t('appointments', 'Preview') }}<span
            style="margin-left: 1.25em"
            v-show="pagePreviewLoading===true"
            class="icon-loading-small srgdev-appt-main-info-span"></span>
        </div>
        <div class="srgdev-appt-main-frame-cont">
          <iframe
              class="srgdev-appt-main-frame"
              @load="pagePreviewLoading=false"
              ref="pubPageRef"
              :src="pubPage"></iframe>
        </div>
      </div>
      <div v-show="visibleSection===3" v-html="helpContent" class="srgdev-appt-help-sec">
      </div>
      <div v-show="visibleSection===4" class="srgdev-appt-main-sec_fid">
        <FormInputsDesigner v-if="visibleSection===4"/>
      </div>
      <PagePickerSlideBar
          v-if="sbShow===11"
          :page0="page0"
          :title="pagePickerTitle"
          :more-pages="morePages"
          @pageSelected="curPageId=$event;toggleSlideBar(sbGotoBack,$event);sbGotoBack=0"
          @close="toggleSlideBar(0)"/>
      <CalendarAndMode
          ref="tsbRef"
          v-if="sbShow===6"
          :cur-page-data="curPageData"
          @showCModal="showCModal"
          @gotoAddAppt="curPageId=$event;toggleSlideBar(7);sbGotoBack=6"
          @gotoDelAppt="curPageId=$event;toggleSlideBar(8);sbGotoBack=6"
          @gotoAdvStn="curPageId=$event;toggleSlideBar(10);sbGotoBack=6"
          @showModal="showSimpleGeneralModal($event)"
          @editTemplate="editApptTemplate($event)"
          @reloadPages="getPages(0,curPageData.pageId)"
          @close="toggleSlideBar(0)"/>
      <UserContactSettings
          v-if="sbShow===3"
          :cur-page-data="curPageData"
          @close="toggleSlideBar(0)"/>
      <SettingsSlideBar
          v-if="sbShow===9"
					:talk-enabled="talkEnabled"
          :show-dir-page="morePages.length>0"
          @gotoPPS="toggleSlideBar(2,'p0');sbGotoBack=9"
          @gotoEML="toggleSlideBar(4);sbGotoBack=9"
          @gotoADV="toggleSlideBar(10);sbGotoBack=9"
          @gotoDIR="toggleSlideBar(12,'dir');sbGotoBack=9"
          @gotoTALK="toggleSlideBar(14);sbGotoBack=9"
          @gotoREM="toggleSlideBar(15);sbGotoBack=9"
          @showModal="showSimpleGeneralModal($event)"
          :cur-page-data="curPageData"
          @close="sbShow=0"/>
      <PublicPageSettings
          v-if="sbShow===2"
          @gotoToFid="sbShow=0;visibleSection=4"
          @close="toggleSlideBar(sbGotoBack);sbGotoBack=0"/>
      <EmailSettings
          v-if="sbShow===4"
          @close="toggleSlideBar(sbGotoBack);sbGotoBack=0"/>
      <TalkSettings
          v-if="sbShow===14"
          @showCModal="showCModal"
          @showModal="showSimpleGeneralModal($event)"
          @close="toggleSlideBar(sbGotoBack);sbGotoBack=0"/>
      <ReminderSettings
          v-if="sbShow===15"
          @showCModal="showCModal"
          @showModal="showSimpleGeneralModal($event)"
          @close="toggleSlideBar(sbGotoBack);sbGotoBack=0"/>
      <AdvancedSettings
          v-if="sbShow===10"
          @close="toggleSlideBar(sbGotoBack);sbGotoBack=0"/>
      <SimpleAddAppointents
          v-if="sbShow===7"
          :cur-page-data="curPageData"
          :is-grid-ready="isGridReady"
          @setupGrid="gridSetup"
          @agDataReady="makePreviewGrid"
          @close="toggleSlideBar($event===true?0:sbGotoBack);sbGotoBack=0"/>
      <SimpleDelAppointments
          v-if="sbShow===8"
          :cur-page-data="curPageData"
          @openGM="openGeneralModal"
          @closeGM="closeGeneralModal"
          @updateGM="updateGeneralModal"
          @close="toggleSlideBar(sbGotoBack);sbGotoBack=0"/>
      <DirectoryPageSettings
          v-if="sbShow===12"
          :page0-label="checkPageLabel(page0.label)"
          :more-pages="morePages"
          :icon-go-back="sbGotoBack!==0"
          @showModal="showSimpleGeneralModal($event)"
          @close="toggleSlideBar(sbGotoBack);sbGotoBack=0"/>
    </AppContent>
  </Content>
</template>

<script>
import Content from '@nextcloud/vue/dist/Components/NcContent.js'
import AppNavigation from '@nextcloud/vue/dist/Components/NcAppNavigation.js'
import AppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import ActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import AppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import AppSettingsDialog from '@nextcloud/vue/dist/Components/NcAppSettingsDialog.js'
import Actions from '@nextcloud/vue/dist/Components/NcActions.js'

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

import Modal from '@nextcloud/vue/dist/Components/NcModal.js'

import {showError, showSuccess} from "@nextcloud/dialogs"
import SettingsSlideBar from "./components/settings/SettingsSlideBar";
import SimpleDelAppointments from "./components/settings/TimeslotSettings/SimpleDelAppointments";
import SimpleAddAppointents from "./components/settings/TimeslotSettings/SimpleAddAppointments";
import ActionInput from "./components/ActionInputExt.vue";
import NavAccountItem from "./components/NavAccountItem.vue";

import axios from '@nextcloud/axios'

import gridMaker from "./grid.js"

import PublicPageSettings from "./components/settings/PublicPageSettings"
import EmailSettings from "./components/settings/EmailSettings"
import TalkSettings from "./components/settings/TalkSettings"
import AdvancedSettings from "./components/settings/AdvancedSettings"
import DirectoryPageSettings from "./components/settings/DirectoryPageSettings"
import UserContactSettings from "./components/settings/UserContactSettings"
import ReminderSettings from "./components/settings/ReminderSettings"

import CalendarAndMode from "./components/settings/TimeslotSettings/CalendarAndMode"
import ApptAccordion from "./components/ApptAccordion.vue"
import PagePickerSlideBar from "./components/PagePickerSlideBar"

import FormInputsDesigner from "./components/FormInputsDesigner"
import TemplateApptOptions from "./components/TemplateApptOptions";
import * as debug from "./use/debugging"

export default {
  name: 'App',
  components: {
    Content,
    AppNavigation,
    AppContent,
    AppNavigationItem,
    AppSettingsDialog,
    ActionButton,
    Modal,
    NcCheckboxRadioSwitch,
    ReminderSettings,
    TemplateApptOptions,
    PagePickerSlideBar,
    CalendarAndMode,
    AdvancedSettings,
    DirectoryPageSettings,
    PublicPageSettings,
    EmailSettings,
    TalkSettings,
    UserContactSettings,
    NavAccountItem,
    Actions,
    ActionInput,
    ApptAccordion,
    SimpleAddAppointents,
    SimpleDelAppointments,
    SettingsSlideBar,
    FormInputsDesigner,
  },

  data: function () {
    return {

      useNcTheme: false,
			talkEnabled: true,

      pubPage: '',

      page0: {
        enabled: 0,
        label: ""
      },

      // this us used to compute curPageData to pass to settings, etc..
      curPageId: "p0",
      pagePreviewLabel: this.checkPageLabel(""),
      pagePreviewLoading: false,

      pageInfoLoading: 0,

      /** @type {{enabled:number,label:string,pageId:string}[]} */
      morePages: [],

      navOpen: false,
      sbShow: 0,
      sbGotoBack: 0,
      pagePickerTitle: "",

      visibleSection: 0,

      evtGridData: [],
      evtGridModal: 0,
      modalErrTxt: "",
      modalHeader: "",
      modalText: "",

      helpContent: "",

      isGridReady: false,

      /**
       * @type {{ts:number,txt:string,w:string,n:number,hasAppts:boolean}[]}
       */
      gridHeader: [],
      gridApptLen: 0,
      gridApptTs: 0,
      gridMode: gridMaker.MODE_SIMPLE,
      gridMenuOpen: -1,

      generalModal: 0,
      generalModalTxt: ["", ""],
      generalModalLoadingTxt: "",
      generalModalPop: 0,
      generalModalPopTxt: "",
      generalModalCloseCallback: undefined,
      generalModalActionCallback: undefined,
      generalModalBtnTxt: "",
      generalModalActionTxt: "",

      calInfo: {},

      stateInProgress: false,

      hasKey: false
    };
  },

  computed: {

    curPageData: function () {
      const ml = this.morePages.length
      if (this.curPageId === 'p0') {
        return {
          enabled: this.page0.enabled,
          label: this.checkPageLabel(this.page0.label),
          stateAction: "cls",
          uciAction: "uci",
          pageId: 'p0',
          pageCount: 1 + ml
        }
      } else {
        let r = {}
        for (let i = 0, pgs = this.morePages; i < ml; i++) {
          if (pgs[i].pageId === this.curPageId) {
            r = {
              enabled: pgs[i].enabled,
              label: pgs[i].label,
              stateAction: "mps",
              uciAction: "mps",
              pageId: pgs[i].pageId,
              pageCount: 1 + ml
            }
            break
          }
        }
        return r
      }
    }
  },

  created() {
    this.gridApptTZ = "L"
    this.gridTzName = ""
    this.gridApptsPageId = "p0"
    this.gridCID = 0
    this.evtGridElm = null

    // calculate grid shift for template, because a week can start on different in some countries
    // in template data array 0 = Monday, so when the week start on:
    //  Monday:   gridShift = 0
    //  Sunday:   gridShift = 1
    //  Saturday: gridShift = 2

    switch (window.firstDay) {
      case 0:
        // Sunday
        this.gridShift = 1
        break
      case 6:
        // Saturday
        this.gridShift = 2
        break
      default:
        // default to Monday
        this.gridShift = 0
    }
  },

  beforeMount() {
    this.resetCalInfo()
  },

  mounted() {
    this.getPages(1, 'p0')

    this.getState("get_initial_config").then(data => {
			this.useNcTheme = data['useNcTheme']
			this.talkEnabled = data['talkEnabled']
    })

    this.$root.$on('helpWanted', this.helpWantedHandler)
    this.$root.$on('startDebug', this.startDebug)

    // ------- testing --
    // if(!this.isGridReady){
    //   this.gridSetup()
    // }
    // this.curPageId="p0"
    // this.editApptTemplate({
    //   tzName:"America/New_York",
    //   pageId:this.curPageId
    // })
  },

  beforeDestroy() {
    this.$root.$off('helpWanted', this.helpWantedHandler)
    this.$root.$off('startDebug', this.startDebug)
  },
  provide: function () {
    return {
      getState: this.getState,
      setState: this.setState
    }
  },


  methods: {

    toggleUseNcTheme() {
      if (this.stateInProgress) {
        return
      }
      const newValue = this.useNcTheme === false
      this.setState('set_use_nc_theme', newValue)
          .then(data => {
            if (data === true) {
              // toggle ok
              this.useNcTheme = newValue
            } // else toggle failed
          })
    },

    editSingleAppt(e) {
      this.evtGridElm = e.detail
      this.evtGridModal = 5
    },

    async editApptTemplate(info) {

      this.gridSetup()

      this.getState("get_k").then(k => {
        this.hasKey = k !== ""
      })

      this.gridTzName = info.tzName

      // wd must be 00:00 on a Monday
      const wd = new Date()
      wd.setHours(0, 0, 0)
      let day = wd.getDay()
      if (day === 0) {
        day++
      } else {
        day = 1 - day
      }

      const d = {
        dur: 0,
        week: wd.setDate(wd.getDate() + day),
        tz: null,
        pageId: info.pageId
      }

      this.makePreviewGrid(d, gridMaker.MODE_TEMPLATE)
    },

    saveTemplate() {
      this.setState('set_t_data', gridMaker.getTemplateData(this.gridShift), this.curPageData.pageId)
    },

    openViaPicker(sbn, evt) {
      if (this.sbShow === 11 && this.sbGotoBack === sbn) {
        // picker from THIS slideBar is showing... close it
        this.toggleSlideBar(0);
        return
      }

      if (sbn === this.sbShow) {
        // the slideBar is showing
        if (this.morePages.length > 0) {
          // multiple pages are available...
          // ... open the pagePicker instead of just closing the slideBar
          this.pagePickerTitle = evt.currentTarget.textContent.trim()
          this.toggleSlideBar(11)
          this.sbGotoBack = sbn
        } else {
          // single page, just close it
          this.toggleSlideBar(0);
        }
      } else {
        if (this.morePages.length === 0) {
          this.curPageId = 'p0'
          this.toggleSlideBar(sbn)
        } else {
          // the picker can be already open update info first
          this.pagePickerTitle = evt.currentTarget.textContent.trim()
          this.sbGotoBack = sbn
          if (this.sbShow !== 11) {
            // the picker is NOT open..., so open it
            this.toggleSlideBar(11)
          }
        }
      }
    },

    getPages(idx, p) {
      this.pageInfoLoading = idx
      this.stateInProgress = true
      axios.post('state', {a: 'get_pages'})
          .then(response => {
            if (response.status === 200) {
              const ap = []
              const d = response.data
              let c = 0
              for (const prop in d) {
                if (d.hasOwnProperty(prop)) {
                  if (prop === 'p0') {
                    this.page0 = Object.assign({}, this.page0, d['p0'])
                  } else {
                    ap[c] = d[prop]
                    ap[c]['pageId'] = prop
                    c++
                  }
                }
              }

              this.morePages = ap
            }
            this.getFormData(p)
            this.pageInfoLoading = 0
            this.stateInProgress = false
          })
          .catch(error => {
            this.stateInProgress = false
            this.pageInfoLoading = 0
            console.log(error);
          });
    },

    setPages(p, v, idx) {
      this.pageInfoLoading = idx
      let ji = ""
      try {
        ji = JSON.stringify(v)
      } catch (e) {
        this.pageInfoLoading = 0
        console.log(e)
        showError(this.t('appointments', "Can't apply settings"))
      }

      axios.post('state', {
        a: 'set_pages',
        p: p,
        v: ji
      }).then(response => {
        if (response.status === 200) {
          // this.getFormData(p)
          this.getPages(idx, p)
        } else if (response.status === 202) {
          this.handle202(response.data)
        }
      }).catch((error) => {
        console.log(error)
        showError(this.t('appointments', "Page settings error. Check console"))
      }).then(() => {
        // always executed
        this.pageInfoLoading = 0
        // p can be pageId or "new" or "delete
        // this.getFormData(p)
      });
    },

    setPageLabel(evt) {
      evt.preventDefault()
      const t = evt.target
      const page = t.getAttribute("data-pid")
      let co
      let idx = 1
      if (page === 'p0') {
        co = Object.assign({}, this.page0)
      } else {
        for (let i = 0, pgs = this.morePages, l = pgs.length; i < l; i++) {
          if (pgs[i].pageId === page) {
            co = Object.assign({}, pgs[i])
            idx = i + 2
            break
          }
        }
      }
      co.label = t.value
      this.setPages(page, co, idx)
      // this.getFormData(page)
    },

    setPageEnabled(page, enable) {
      let p
      let idx = 1
      if (page === 'p0') {
        p = Object.assign({}, this.page0)
      } else {
        for (let i = 0, pgs = this.morePages, l = pgs.length; i < l; i++) {
          if (pgs[i].pageId === page) {
            p = Object.assign({}, pgs[i])
            idx = i + 2
            break
          }
        }
      }

      if (p.enabled === enable) return

      // Check settings... Org name, address and email are needed...
      if (enable === 1) {

        this.pageInfoLoading = idx

        this.getState("get_uci")
            .then(res => {
              if (res === null) {
                this.pageInfoLoading = 0
                return null
              }

              //organization: "", email: "", address: ""
              let n = -1
              let pa = ["organization", "email", "address"];
              for (let v, i = 0, l = pa.length; i < l; i++) {
                v = pa[i]
                if (!res.hasOwnProperty(v) || res[v].length < 2) {
                  n = i
                  break
                }

              }
              if (n !== -1) {
                let fn = ["Name", "Email", "Location"][n]
                showError(this.t('appointments', "Error: '{fieldName}' field is empty, check User/Organization settings", {fieldName: fn}))
                this.pageInfoLoading = 0
              } else {
                p.enabled = 1
                this.setPages(page, p, idx)
              }
            })

      } else {
        p.enabled = 0
        this.setPages(page, p, idx)
      }
    },

    addNewPage() {
      this.setPages("new", {enabled: 0, label: "New Page"}, 1)
    },

    deletePage(page) {
      let i = 0
      for (const pgs = this.morePages, l = pgs.length; i < l; i++) {
        if (pgs[i].pageId === page) {
          break
        }
      }
      i += 2
      this.toggleSlideBar(0)
      this.setPages("delete", {page: page}, i)
    },

    handle202(o) {

      console.log("oooo:", o)

      if (o['contrib'] !== undefined) {
        this.showCModal(o['contrib'])
      } else if (o['info'] !== undefined) {
        this.showIModal(o['info'])
      }
    },

    gridApptsAdd(cID, event) {
      this.gridMenuOpen = -1
      let hd = this.gridHeader[cID]
      hd.n = event.target.querySelector('input[type=number]').value

      if (this.gridMode === gridMaker.MODE_SIMPLE) {
        this.gridApptsAddSimple(cID)
      } else {
        this.gridCID = cID
        this.evtGridElm = null
        this.evtGridModal = 5
      }
    },

    gridApptsAddTemplate(ai) {
      let hd = this.gridHeader[this.gridCID]
      let nbr = parseInt(hd.n)
      if (isNaN(nbr) || nbr < 1) nbr = 1

      gridMaker.addAppt(0, ai.dur[0], nbr, this.gridCID, ai)
      hd.hasAppts = true
    },

    gridApptUpdate(ai) {
      gridMaker.updateAppt(ai)
    },

    gridApptsAddSimple(cID) {
      let hd = this.gridHeader[cID]
      let nbr = parseInt(hd.n)
      if (isNaN(nbr) || nbr < 1) return

      if (isNaN(this.gridApptLen) || this.gridApptLen < 5) {
        this.gridApptLen = 5
      }

      gridMaker.addAppt(0, this.gridApptLen, nbr, cID, this.calInfo.curCal_color)
      hd.hasAppts = true
    },


    gridApptsDel(cID) {
      this.gridMenuOpen = -1
      gridMaker.resetColumn(cID)
      this.gridHeader[cID].hasAppts = false
    },

    gridApptsCopy(cID) {
      this.gridMenuOpen = -1
      gridMaker.cloneColumns(cID, cID + 1, this.calInfo.curCal_color)
      this.gridHeader[cID + 1].hasAppts = true
    },

    gridSetup() {
      if (this.isGridReady === false) {
        gridMaker.setup(this.$refs["grid_cont"], 7, "srgdev-appt-grd-")
        this.isGridReady = true
      }
    },

    /** @return {Promise<JSON|string|Array|null>} */
    async getState(action, p = "") {
      this.stateInProgress = true
      try {
        const res = await axios.post('state', {a: action, p: p})
        this.stateInProgress = false
        if (res.status === 200) {
          return res.data
        } else {
          console.log(res)
          showError(t('appointments', "Can't get Settings. Check console"))
          return null
        }
      } catch (e) {
        this.stateInProgress = false
        console.log(e)
        showError(t('appointments', "Can't get Settings. Check console"))
        return null
      }
    },

    /**
     * @param {string} action
     * @param {Object} value
     * @param {string} pageId
     * @param opt
     */
    async setState(action, value, pageId = '', opt = {}) {
      let ji = ""
      this.stateInProgress = true
      try {
        ji = JSON.stringify(value)
      } catch (e) {
        this.stateInProgress = false
        console.log(e)
        showError(this.t('appointments', "Can't apply settings"))
        return false
      }
      return await axios.post('state', {
        a: action,
        d: ji,
        p: pageId
      }).then(response => {
        this.stateInProgress = false
        if (response.status === 200) {
          if (opt.noFormData === undefined) this.getFormData(pageId)
          if (opt.noToast === undefined) showSuccess(this.t('appointments', 'New Settings Applied.'))
          return action !== 'set_fi' ? true : response.data
        } else if (response.status === 202) {
          this.handle202(response.data)
        }
      }).catch((error) => {
        this.stateInProgress = false
        console.log(error)
        showError(this.t('appointments', "Can't apply settings"))
        return false
      })
    },

    toggleSlideBar(sbn, pageId) {

      // CLose nav
      // const lst = document.getElementsByClassName("app-navigation-toggle")
      // if (lst.length > 0) {
      //   let elm = lst.item(0)
      //   if (elm.hasAttribute('aria-expanded')
      //       && elm.getAttribute('aria-expanded') === 'true') {
      //     elm.dispatchEvent(new Event('click'))
      //   }
      // }

      if (sbn === 0) {
        this.sbShow = 0
        return
      }

      if (this.sbShow === sbn) this.sbShow = 0
      else this.sbShow = sbn

      if (pageId !== undefined) {
        this.getFormData(pageId)
      }

    },

    helpWantedHandler(section) {
      this.toggleSlideBar(0)
      this.showHelp(section)
      // document.getElementById("sec_"+section).scrollIntoView()
    },


    showHelp(sec) {

      if (typeof sec !== "string" && this.visibleSection === 3) {
        this.visibleSection = 0
        return
      }

      this.visibleSection = 3

      axios.get('help')
          .then(response => {
            if (response.status === 200) {
              this.helpContent = response.data
              if (sec !== undefined) {
                this.$nextTick(function () {

                  // We need to re-scroll when images finsh loading otherwise the scroll position is wrong
                  const scrollToHelpSec = function () {
                    const elm = document.getElementById("srgdev-appt_help-cont")
                    if (elm !== null && elm.hasAttribute("data-sec-elm")) {
                      const secElm = document.getElementById(elm.getAttribute("data-sec-elm"))
                      if (secElm !== null) {
                        secElm.scrollIntoView({block: "center"})
                      }
                    }
                  }
                  document.getElementById("srgdev-appt_help-cont").setAttribute("data-sec-elm", "srgdev-sec_" + sec)
                  const imgs = document.getElementsByClassName("quick-start-guide-img")
                  for (let i = 0; i < imgs.length; i++) {
                    imgs.item(i).addEventListener("load", scrollToHelpSec)
                  }

                  let elm = document.getElementById("srgdev-sec_" + sec)
                  if (elm !== null) {
                    elm.scrollIntoView({block: "center"})
                    elm.className += ' srgdev-appt-temp-highlight'
                    setTimeout(function () {
                      elm.className = elm.className.replace(' srgdev-appt-temp-highlight', '')
                    }, 2000)
                  }
                })
              }
            }
          })
          .catch((error) => {
            console.log(error)
            this.helpContent = ''
          })
    },

    startDebug(data = undefined) {

      this.toggleSlideBar(0)
      this.visibleSection = 3

      this.helpContent = '<pre style="font-size:90%; padding: 50px 1em 0"><code>Sending Request. Please Wait.</code></pre>';

      let prm
      if (data === undefined) {
        prm = debug.settingsDump()
      } else if (data.type === "raw_cal") {
        prm = debug.getRawCalData(data.cal_info)
      } else if (data.type === "sync_remote") {
        prm = debug.syncRemoteNow(data.cal_info)
      }
      prm.then(data => {
        this.helpContent = data
      })

    },

    showPubLink(page) {
      this.openGeneralModal(1)
      this.generalModalLoadingTxt = this.t('appointments', 'Fetching URL from the server …')
      if (page === 'p0') {
        // this is actually the header text for this dialog
        this.generalModalBtnTxt = this.page0.label
      } else {
        for (let i = 0, pgs = this.morePages, l = pgs.length; i < l; i++) {
          if (pgs[i].pageId === page) {
            // this is actually the header text for this dialog
            this.generalModalBtnTxt = pgs[i].label
            break
          }
        }
      }

      axios.post('state', {
        a: page === 'dir' ? 'get_diruri' : 'get_puburi',
        p: page
      }).then(response => {
        if (response.status === 200) {
          const ua = response.data.split(String.fromCharCode(31))
          this.generalModalLoadingTxt = ""
          this.$set(this.generalModalTxt, 0, ua[0])
          this.$set(this.generalModalTxt, 1, ua[1])
        } else if (response.status === 202) {
          this.handle202(response.data)
        }
      }).catch((error) => {
        this.closeGeneralModal()
        console.log(error)
        showError(this.t('appointments', 'Cannot get public URL from server'))
      })

    },

    doCopyPubLink() {
      const text = this.generalModalTxt[0]
      const ok_txt = this.t('appointments', 'Public link copied to clipboard')
      const err_txt = this.t('appointments', 'Copy Error')
      if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
          this.showGeneralModalPop(ok_txt)
        }, (err) => {
          console.error('copy error:', err);
          this.showGeneralModalPop(err_txt)
        });
      } else {
        // fallback
        let textArea = document.createElement("textarea");
        textArea.value = text;

        // Avoid scrolling to bottom
        textArea.style.top = "0";
        textArea.style.left = "0";
        textArea.style.position = "fixed";

        textArea.style.width = '2em';
        textArea.style.height = '2em';

        textArea.style.padding = 0;

        textArea.style.border = 'none';
        textArea.style.outline = 'none';
        textArea.style.boxShadow = 'none';

        textArea.style.background = 'transparent';

        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        let copyOK
        try {
          copyOK = document.execCommand('copy');
        } catch (err) {
          console.error('copy error:', err)
          copyOK = false
        }
        document.body.removeChild(textArea);

        if (copyOK) {
          this.showGeneralModalPop(ok_txt)
        } else {
          this.showGeneralModalPop(err_txt)
        }
      }
    },

    checkPageLabel(l) {
      return l === "" ? t('appointments', 'Public Page') : l;
    },

    getFormData(pageId) {
      this.pagePreviewLoading = true
      let lbl = ""
      if (typeof pageId !== "string") {
        this.pubPage = 'form?v=' + Date.now()
        lbl = this.page0.label
      } else {
        if (pageId === 'dir') {
          this.pubPage = 'dir?v=' + Date.now()
          lbl = t('appointments', 'Directory Page')
        } else {
          if (/^p\d{1}$/.test(pageId) === false) {
            // default to the main page
            pageId = 'p0'
          }
          this.pubPage = 'form?p=' + pageId + '&v=' + Date.now()

          if (pageId === 'p0') {
            lbl = this.page0.label
          } else if (pageId === this.curPageId) {
            lbl = this.curPageData.label
          } else {
            for (let i = 0, pgs = this.morePages, l = this.morePages.length; i < l; i++) {
              if (pgs[i].pageId === pageId) {
                lbl = pgs[i].label
                break
              }
            }
          }
        }
      }
      this.pagePreviewLabel = this.checkPageLabel(lbl)
      this.visibleSection = 0
    },

    makePreviewGrid(d, mode = gridMaker.MODE_SIMPLE) {

      this.gridMode = mode
      gridMaker.setMode(mode)

      gridMaker.resetAllColumns()

      const NBR_DAYS = 7
      // Generate local names for days and month(s)
      let tff
      const lang = document.documentElement.hasAttribute('data-locale')
          ? [document.documentElement.getAttribute('data-locale').replaceAll('_', '-'), document.documentElement.lang]
          : [document.documentElement.lang]

      if (window.Intl && typeof window.Intl === "object") {
        let f
        if (mode === gridMaker.MODE_SIMPLE) {
          f = new Intl.DateTimeFormat(lang,
              {weekday: "short", month: "2-digit", day: "2-digit"})
        } else {
          f = new Intl.DateTimeFormat(lang,
              {weekday: "long"})
        }
        tff = f.format
      } else {
        const _sl = mode === gridMaker.MODE_SIMPLE ? 10 : 3
        // noinspection JSUnusedLocalSymbols
        tff = function (d) {
          return d.toDateString().slice(0, _sl)
        }
      }

      let td = new Date(d.week)
      if (this.gridMode === gridMaker.MODE_TEMPLATE && this.gridShift !== 0) {
        // d.week is Monday 00:00:00 in grid mode
        // we need to adjust because a week can start on Mon, Sun or Sat
        td.setDate(td.getDate() - this.gridShift)
      }

      // we need this for simple mode
      const pd = td.getDate() + "-" + (td.getMonth() + 1) + "-" + td.getFullYear()

      // Same formula as @see grid.js#makeColumns(n)
      let w = Math.floor((100 - 1) / NBR_DAYS) + "%"

      for (let ts = td.getTime(), i = 0; i < NBR_DAYS; i++) {
        this.$set(this.gridHeader, i, {
          ts: ts,
          txt: tff(td),
          w: w,
          n: '8', // Initial value for "add" input must be string
          hasAppts: false,
        })
        ts = td.setDate(td.getDate() + 1)
      }

      this.gridApptLen = d.dur
      this.gridApptTs = d.week
      this.gridApptTZ = d.tz
      this.gridApptsPageId = d.pageId

      this.visibleSection = 1

      if (mode === gridMaker.MODE_SIMPLE) {
        this.$set(this.calInfo, "curCal_color", d.calColor)
        this.$set(this.calInfo, "curCal_name", d.calName)

        // dd-mm-yyyy
        axios.post('calgetweek', {
          t: pd,
          p: d.pageId
        }).then(response => {
          if (response.status === 200) {
            if (response.data !== "") {
              gridMaker.addPastAppts(pd + String.fromCharCode(31) + response.data, this.calInfo.curCal_color)
            }
          }
        }).catch(error => {
          this.modalErrTxt = t('appointments', "Bad calendar data. Check selected calendars.")
          this.evtGridModal = 3
          console.log(error);
        })

      } else {
        this.$set(this.calInfo, "curCal_color", null)
        this.$set(this.calInfo, "curCal_name", null)

        // get template data
        this.getState('get_t_data', d.pageId)
            .then(data => {
              gridMaker.addPastAppts(data, null, this.gridShift)
              //activate non-empty columns
              data.forEach((c, i) => {
                const iMod = (i + this.gridShift) % 7
                if (this.gridHeader[iMod] !== undefined) {
                  this.gridHeader[iMod].hasAppts = c.length > 0
                }
              })
            })
      }
      this.$nextTick(gridMaker.scrollGridToTopElm)
    },

    addScheduleToCalendar() {
      const tsa = gridMaker.getStarEnds(this.gridApptTs, this.gridApptTZ === 'UTC')
      this.evtGridModal = 1

      axios.post('caladd', {
        d: tsa.join(','),
        tz: this.gridApptTZ,
        p: this.gridApptsPageId
      }).then(response => {
        if (response.status === 200) {
          if (response.data.substr(0, 1) !== '0') {
            // error
            console.log(response.data);
            if (response.data.length > 6) {
              this.modalErrTxt = response.data.substr(2)
            }
            this.evtGridModal = 3
          } else {
            // good
            this.evtGridModal = 2
          }
        }
      }).catch(error => {
        // What text can we get from the error ???
        this.modalErrTxt = ""
        this.evtGridModal = 3
        console.log(error);
      })
      //     .finally(()=>{
      //     this.closePreviewGrid()
      // })
    },

    closePreviewGrid() {
      this.visibleSection = 0;
    },

    closeEvtModal() {
      if (this.evtGridModal < 3) this.getFormData(this.gridApptsPageId)
      this.modalErrTxt = ""
      this.evtGridModal = 0
      this.evtGridElm = null
    },

    showCModal(txt) {
      this.openGeneralModal(3)
      this.$set(this.generalModalTxt, 0, t('appointments', "Contributor only feature"))
      this.$set(this.generalModalTxt, 1, txt)
      this.generalModalActionCallback = function () {
        this.helpWantedHandler('contrib_info')
      }
      this.generalModalActionTxt = t('appointments', "More Info")
    },

    showIModal(txt) {
      this.openGeneralModal(3)
      this.$set(this.generalModalTxt, 0, t('appointments', "Warning"))
      this.$set(this.generalModalTxt, 1, txt)
    },

    /**
     * @param {Array} txt 0=header, 1=text, 2=optional callBack
     */
    showSimpleGeneralModal(txt) {
      this.openGeneralModal(3)
      this.$set(this.generalModalTxt, 0, txt[0])
      this.$set(this.generalModalTxt, 1, txt[1])
      if (txt.length === 3) {
        this.generalModalCloseCallback = txt[2]
      }
    },

    /** @param {Object} o */
    updateGeneralModal(o) {
      for (const prop in o) {
        if (o.hasOwnProperty(prop) && prop.indexOf('generalModal') === 0) {
          if (prop === 'generalModalTxt') {
            if (o[prop][0] !== undefined) {
              this.$set(this.generalModalTxt, 0, o[prop][0])
            }
            if (o[prop][1] !== undefined) {
              this.$set(this.generalModalTxt, 1, o[prop][1])
            }
          }
          this[prop] = o[prop]
        }
      }
    },

    showGeneralModalPop(txt) {
      const ctx = this
      if (this.generalModalPop !== 0) {
        clearTimeout(this.generalModalPop)
      }
      this.generalModalPopTxt = txt
      this.generalModalPop = setTimeout(function () {
        ctx.generalModalPop = 0
      }, 2000)
    },

    openGeneralModal(id) {
      console.log("openGeneralModal",id)
      this.generalModal = id
      this.visibleSection = 2
      this._clearGeneralModal()
    },

    actionGeneralModal() {
      if (this.generalModalActionCallback !== undefined) {
        this.generalModalCloseCallback = this.generalModalActionCallback
        this.generalModalActionCallback = undefined
      }
      this.closeGeneralModal()
    },

    closeGeneralModal() {
      this.visibleSection = 0
      this.generalModal = 0
      this.generalModalLoadingTxt = ""

      if (this.generalModalCloseCallback !== undefined) {
        this.generalModalCloseCallback()
        this.generalModalCloseCallback = undefined
      }
      this._clearGeneralModal()
    },

    _clearGeneralModal() {
      this.$set(this.generalModalTxt, 0, "")
      this.$set(this.generalModalTxt, 1, "")
      this.generalModalCloseCallback = undefined
      this.generalModalActionCallback = undefined
      this.generalModalPopTxt = ""
      this.generalModalBtnTxt = ""
      this.generalModalActionTxt = ""
      if (this.generalModalPop !== 0) {
        clearTimeout(this.generalModalPop)
      }
      this.generalModalPop = 0
    },

    resetCalInfo() {
      this.$set(this.calInfo, "curCal_color", "#000000")
      this.$set(this.calInfo, "curCal_name", t('appointments', "Select a calendar"))
    },

    noCalSet() {
      showError(this.t('appointments', "Select a Calendar First"))
    }
  }
}
</script>

<style scoped>
.srgdev-appt-modal-container >>> .modal-container {
  width: auto;
  height: auto;
}
</style>


