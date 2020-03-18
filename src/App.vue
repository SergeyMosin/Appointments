<template>
    <div id="content">
        <AppNavigation>
            <ul>
            <AppNavigationItem
                    @click="getFormData"
                    class="srgdev-pubpage-nav-item"
                    :title="(pageEnabled==='1'
                        ?t('appointments','Public Page [Online]')
                        :t('appointments','Public Page [Disabled]'))"
                    :icon="pageEnabled==='1'
                        ?'icon-screen'
                        :'icon-screen-off'">
                <template slot="actions">
                    <ActionButton :disabled="pageEnabled==='1'" @click="togglePageEnabled('1')" icon="icon-checkmark-color" closeAfterClick>
                        {{t('appointments','Share Online')}}
                    </ActionButton>
                    <ActionButton :disabled="pageEnabled==='0'" @click="togglePageEnabled('0')" icon="icon-category-disabled" closeAfterClick>
                        {{t('appointments','Stop Sharing')}}
                    </ActionButton>
                </template>
            </AppNavigationItem>
            <AppNavigationSpacer/>
            <NavAccountItem :calLoading="isCalLoading" @calSelected="setCalendar" :curCal="curCal"></NavAccountItem>
                <AppNavigationItem
                        @click="showSlideBar"
                        :title="t('appointments','Add Appointments')"
                        icon="icon-add"></AppNavigationItem>
                <AppNavigationItem
                        :loading="ppsLoading"
                        @click="showPPS"
                        :title="t('appointments','Customize Public Page')"
                        icon="icon-category-customization"></AppNavigationItem>
                <AppNavigationItem
                        :pinned="true"
                        @click="showHelp"
                        :title="t('appointments','Help/Tutorial')"
                        icon="icon-info"></AppNavigationItem>
            </ul>

            <SettingsExt @open="settingsOpen">
                <ul class="settings-fieldset-interior">
                <li>
                    <StnInput
                            @submit="setStn"
                            sname="org"
                            :disabled="!settings.loaded"
                            :value="settings.org"
                            :placeholder="t('appointments','Organization Name')">
                    </StnInput>
                </li>
                <li>
                    <StnText
                            @submit="setStn"
                            sname="addr"
                            :disabled="!settings.loaded"
                            :value="settings.addr"
                            :placeholder="t('appointments','Organization Address')">
                    </StnText>
                </li>
                <li>
                    <StnInput
                            @submit="setStn"
                            sname="eml"
                            :disabled="!settings.loaded"
                            :value="settings.eml"
                            :placeholder="t('appointments','Email')">
                    </StnInput>
                </li>
                <li>
                    <StnInput
                            @submit="setStn"
                            sname="phn"
                            :disabled="!settings.loaded"
                            :value="settings.phn"
                            :placeholder="t('appointments','Phone (optional)')">
                    </StnInput>
                </li>
                    <li>
                        <input
                                type="checkbox"
                                :disabled="!settings.loaded"
                                v-model="settings.ics"
                                id="appt-stn_chb-ics_file"
                                class="checkbox"
                                @change="setChbStn('ics')">
                        <label for="appt-stn_chb-ics_file">{{t('appointments','Attach .ics file to confirmation email')}}</label><br>
                    </li>
                <li>
                    <input
                            type="checkbox"
                            id="appt-stn_chb-notify"
                            class="checkbox"
                            @change="notImplemented">
                    <label for="appt-stn_chb-notify">{{t('appointments','Show Notifications on Status Change')}}</label><br>
                </li>

<!--                    <li>-->
<!--                        <input-->
<!--                                type="checkbox"-->
<!--                                id="appt-stn_chb-captcha"-->
<!--                                class="checkbox"-->
<!--                                @change="notImplemented">-->
<!--                        <label for="appt-stn_chb-captcha">{{t('appointments','Use Google reCAPTCHA')}}</label><br>-->
<!--                    </li>-->
<!--                <li>-->
<!--                    <input type="checkbox" id="appt-stn_chb-json_email" class="checkbox" @change="notImplemented">-->
<!--                    <label for="appt-stn_chb-json_email">Add One Click Actions to Email</label><br>-->
<!--                </li>-->
                </ul>
            </SettingsExt>
        </AppNavigation>
        <AppContent class="srgdev-app-content" :aria-expanded="navOpen">

        <div v-show="visibleSection===1" class="srgdev-appt-cal-view-cont">
            <div class="srgdev-appt-cal-view-btns">
                <button @click="addScheduleToCalendar()" class="primary">
                    {{t('appointments','Add to Calendar')}}
                </button>
                <button @click="closePreviewGrid()">
                    {{t('appointments','Discard')}}
                </button>
            </div>
            <ul class="srgdev-appt-grid-header">
                <li v-for="(hi, index) in gridHeader"
                    class="srgdev-appt-gh-li"
                    :style="{width:hi.w}">
                    <div class="srgdev-appt-gh-txt">{{hi.txt}}</div>
                    <Actions
                            menuAlign="right"
                            class="srgdev-appt-gh-act1">
                        <ActionInput
                                :value="hi.n"
                                :closeAfterClick="true"
                                :disabled="hi.hasAppts"
                                @submit="gridApptsAdd(index,$event)"
                                icon="icon-add"
                                class="srgdev-appt-gh-act-inp"
                                type="number"></ActionInput>
                        <ActionButton
                                icon="icon-delete"
                                :disabled="!hi.hasAppts"
                                :closeAfterClick="true"
                                @click="gridApptsDel(index)">{{t('appointments','Remove All')}}</ActionButton>
                        <ActionButton
                                :disabled="!hi.hasAppts"
                                :closeAfterClick="true"
                                v-if="index!==gridHeader.length-1"
                                icon="icon-category-workflow"
                                @click="gridApptsCopy(index)">{{t('appointments','Copy to Next')}}</ActionButton>
                    </Actions>
                </li>
            </ul>
            <div ref="grid_cont" class="srgdev-appt-grid-cont"></div>
            <Modal v-if="evtGridModal!==0" :canClose="false">
                <div class="srgdev-appt-modal_content">
                    <div v-if="evtGridModal===1" class="srgdev-appt-modal-lbl">
                        {{t('appointments', 'Adding appointment to {calendarName} calendar...', {calendarName:curCal.name})}}
                    </div>
                    <div v-if="evtGridModal===2" class="srgdev-appt-modal-lbl">
                        {{t('appointments', 'All appointments had been added to {calendarName} calendar.', {calendarName:curCal.name})}}
                    </div>
                    <div v-if="evtGridModal===3" class="srgdev-appt-modal-lbl">
                        {{t('appointments', 'Error occurred. Check console...')}}
                    </div>
                    <div v-if="evtGridModal===1" class="srgdev-appt-modal-slider">
                        <div class="srgdev-appt-slider-line"></div>
                        <div class="srgdev-appt-slider-inc"></div>
                        <div class="srgdev-appt-slider-dec"></div>
                    </div>
                    <button v-if="evtGridModal>1" class="primary" @click="closeEvtModal">{{t('appointments', 'Close')}}</button>
                </div>
            </Modal>
        </div>
        <div  v-show="visibleSection===0" class="srgdev-appt-main-sec">
            <ul class="srgdev-appt-main-info">
                <li>{{t('appointments', 'Public Page Preview')}}</li>
                <ActionButton icon="icon-clippy" @click="copyPubLink">{{t('appointments', 'Copy public link')}}</ActionButton>
            </ul>
            <div class="srgdev-appt-main-frame-cont">
                <iframe class="srgdev-appt-main-frame" ref="pubPageRef" :src="pubPage"></iframe>
            </div>
        </div>
        <div v-html="helpContent" v-show="visibleSection===2" class="srgdev-appt-help-sec">
        </div>
            <ScheduleSlideBar
                    :title="t('appointments','Schedule Generator')"
                    :subtitle="t('appointments','Add open appointments to you calendar')"
                    @agDataReady="makePreviewGrid"
                    v-show="sbShow===1" @close="sbShow=0"/>
            <FormStnSlideBar
                    :ppsInfo="ppsInfo"
                    @ppsApply="applyPPS"
                    v-show="sbShow===2" @close="sbShow=0"/>
    </AppContent>
    </div>
</template>

<script>
    // noinspection ES6CheckImport
    import{
        AppNavigation,
        AppNavigationItem,
        AppNavigationSpacer,
        ActionButton,
        AppContent,
        ActionCheckbox,
        AppNavigationIconBullet,
        Modal,
        Actions,
    } from '@nextcloud/vue'
    import {detectColor} from "./utils.js";

    import StnText from "./components/StnText.vue";
    import StnInput from "./components/StnInput.vue";
    import SettingsExt from "./components/SettingsExt.vue";
    import ActionInput from "./components/ActionInputExt.vue";
    import NavAccountItem from "./components/NavAccountItem.vue";
    import ScheduleSlideBar from "./components/ScheduleSlideBar.vue";
    import axios from '@nextcloud/axios'

    import gridMaker from "./grid.js"
    import FormStnSlideBar from "./components/FormStnSlideBar";

    // const ttt=require('./ttt.js')

    // const SHARE_NONE=0
    // const SHARE_TOKEN=0
    // const SHARE_ALL=0

    export default {
        name: 'App',
        components: {
            FormStnSlideBar,
            SettingsExt,
            ScheduleSlideBar,
            AppNavigation,
            AppNavigationItem,
            NavAccountItem,
            AppNavigationSpacer,
            ActionButton,
            AppContent,
            ActionCheckbox,
            AppNavigationIconBullet,
            Modal,
            StnInput,
            StnText,
            Actions,
            ActionInput,
        },
        data: function() {
            return {

                // mainForm:'',
                pubPage:'',

                curCal:{},

                isCalLoading:false,

                pageEnabled:'0',

                value: null,
                navOpen:false,
                sbShow:0,

                visibleSection:0,

                evtGridData:[],
                evtGridModal:0,


                settings:{
                    loaded:false,
                    org:"",
                    addr:"",
                    eml:"",
                    phn:"",
                    ics:false
                },

                helpContent:"",
                tken:"",

                isGridReady:false,
                /**
                 * @type {{ts:number,txt:string,w:string,n:number,hasAppts:boolean}[]}
                 */
                gridHeader:[],
                gridApptLen:0,
                gridApptTs:0,
                gridApptTZ:"L",

                ppsInfo:{},
                ppsLoading:false
            };
        },
        computed: {
        },
        beforeMount() {
            this.resetCurCal()
            this.isCalLoading=true

            axios.post('state', {
                a: 'get'
            })
            .then(response=>{
                if(response.status===200) {
                    const rda = response.data.split(String.fromCharCode(31))
                    if(rda.length===2){
                        const t=rda[0].split(String.fromCharCode(30))
                        if(t.length===3){
                            this.setCurCal({
                                name: t[0],
                                clr: detectColor(t[1]),
                                url: t[2]
                            })
                        }
                        this.pageEnabled=rda[1]
                        this.getPubUri()
                    }
                }
                this.isCalLoading=false
            })
            .catch(error=> {
                this.isCalLoading=false
                console.log(error);
            });
        },

        mounted() {
            this.getFormData()
            this.$root.$on('helpWanted', this.helpWantedHandler)
        },
        beforeDestroy() {
            this.$root.$off('helpWanted', this.helpWantedHandler)
        },
        methods: {

            applyPPS(info){

                let ji
                try {
                    ji=JSON.stringify(info)
                }catch (e) {
                    console.log(e)
                    OC.Notification.showTemporary(this.t('appointments',"Can't apply Public Page settings"),{timeout:4,type:'error'})
                }
                axios.post('state', {
                    a: 'set_pps',
                    d: ji
                }).then(response => {
                    if(response.status===200) {
                        this.getFormData()
                        OCP.Toast.success(this.t('appointments','New Settings Applied.'))
                    }
                }).catch((error) => {
                    console.log(error)
                    OC.Notification.showTemporary(this.t('appointments',"Can't apply Public Page settings"),{timeout:4,type:'error'})
                })
            },

            showPPS(){
                if(this.sbShow===2){
                    // close
                    this.toggleSlideBar(2)
                    return
                }

                this.ppsLoading=true
                axios.post('state', {
                    a: 'get_pps',
                }).then(response => {
                    if(response.status===200) {
                        this.ppsInfo= response.data
                        this.toggleSlideBar(2)
                    }
                }).catch((error) => {
                    console.log(error)
                    OC.Notification.showTemporary(this.t('appointments',"Can't get Public Page settings"),{timeout:4,type:'error'})
                }).then(()=>{
                    // always executed
                    this.ppsLoading=false
                })
            },

            gridApptsAdd(cID,event){
                let hd=this.gridHeader[cID]

                hd.n=event.target.querySelector('input[type=number]').value

                let nbr=parseInt(hd.n)
                if(isNaN(nbr) || nbr<1) return

                if(isNaN(this.gridApptLen) || this.gridApptLen<10){
                    this.gridApptLen=10
                }

                gridMaker.addAppt(0,this.gridApptLen,nbr,cID,this.curCal.clr)
                hd.hasAppts=true
            },

            gridApptsDel(cID){
                gridMaker.resetColumn(cID)
                this.gridHeader[cID].hasAppts=false
            },

            gridApptsCopy(cID){
                gridMaker.cloneColumns(cID,cID+1,this.curCal.clr)
                this.gridHeader[cID+1].hasAppts=true
            },

            async settingsOpen(){
                this.settings.org=""
                this.settings.addr = ""
                this.settings.eml = ""
                this.settings.phn = ""
                this.settings.ics = false
                this.settings.loaded=false

                const rda = await this.getStn()
                if(rda===null) return

                this.settings.org = rda[0]
                this.settings.addr = rda[1].replace(/<br>/g, "\n")
                this.settings.eml = rda[2]
                this.settings.phn = rda[3]
                this.settings.ics = rda[4]==="1"
                this.settings.loaded = true
            },

            setStn(v,n){
                axios.post('state', {
                    a: 'set_settings',
                    n: n,
                    v: v.trim()
                }).then(response => {
                    if(response.status===200) {
                        this.getFormData()
                    }
                }).catch((error) => {
                    console.log(v,n,error)
                    OC.Notification.showTemporary(t('appointments','Settings Error Occurred. Check console...'),{timeout:4,type:'warning'})
                });
            },

            setChbStn(n){
                this.setStn(this.settings[n]===true?'1':'0',n)
            },

            /**
             * @return {Promise<string[]|null>}
             */
            async getStn(){
                try {
                    const res= await axios.post('state', {a: 'get_settings'})
                    if(res.status===200){
                        const rda=res.data.split(String.fromCharCode(30))
                        if(rda.length===5) {
                            return rda
                        }else{
                            // noinspection ExceptionCaughtLocallyJS
                            throw new Error("Bad settings lenght: "+rda.length)
                        }
                    }else{
                        // noinspection ExceptionCaughtLocallyJS
                        throw new Error("Bad status: "+res.status)
                    }
                }catch (e) {
                    console.log(e)
                    OC.Notification.showTemporary(t('appointments',"Can't get Settings. Check console..."),{timeout:8,type:'error'})
                    return null
                }
            },


            helpWantedHandler(section){
                this.toggleSlideBar(0)
                this.showHelp(section)
                // document.getElementById("sec_"+section).scrollIntoView()
            },

            showHelp(sec){
                this.visibleSection=2

                axios.get('help')
                    .then(response => {
                        if(response.status===200) {
                            this.helpContent=response.data
                            if(sec!==undefined) {
                                this.$nextTick(function () {
                                    let elm=document.getElementById("srgdev-sec_" + sec)
                                    if(elm!==null){
                                        elm.scrollIntoView()
                                        elm.className+=' srgdev-appt-temp-highlight'
                                        setTimeout(function () {
                                            elm.className=elm.className.replace(' srgdev-appt-temp-highlight','')
                                        },1000)
                                    }
                                })
                            }
                        }})
                    .catch((error) => {
                        console.log(error)
                        this.helpContent=''
                    })
            },


            async copyPubLink(){
                // copy link for calendar to clipboard
                try {
                    await this.$copyText(this.tken)
                    OCP.Toast.success(this.t('appointments','Public link copied to clipboard...'))
                } catch (error) {
                    console.log(error)
                    OCP.Toast.error(this.t('appointments','Public link could not be copied to clipboard...'))
                }
            },

            getPubUri(){
                axios.post('state', {
                    a: 'get_puburi'
                }).then(response => {
                    if(response.status===200) {
                        this.tken=response.data
                    }
                }).catch((error) => {
                    console.log(error)
                    this.tken=''
                })
            },

            getFormData(){
                this.pubPage='form?v='+Date.now();
                this.visibleSection=0
            },

            togglePageEnabled: async function(enable){
                if(this.curCal.url===""){
                    this.noCalSet()
                    return
                }

                if(this.pageEnabled===enable) return

                // Check settings... Org name, address and email are needed...
                if(enable==='1') {
                    const rda = await this.getStn()
                    if (rda === null) return

                    let n = ''
                    for (let i = 2; i > -1; i--){
                        if (rda[i] === '') {
                            if (i === 0) n = this.t('appointments','Organization Name')
                            else if (i === 1) n = this.t('appointments','Address');
                            else n = this.t('appointments','Email')
                            OC.Notification.showTemporary(
                                this.t('appointments',"Error: {fieldName} empty, check settings...",{fieldName:n}),{timeout:8,type:'error'})
                        }
                    }
                    if(n!=='') return
                }

                axios.post('state', {
                    a: 'enable',
                    v: enable
                }).then(response => {
                    if(response.status===200) {
                        this.pageEnabled=enable
                        // set color



                    }
                }).catch((error) => {
                    console.log(error)
                    OC.Notification.showTemporary(this.t('appointments',"Page enable error. Check console..."),{timeout:4,type:'error'})
                }).then(()=>{
                    // always executed
                    this.getFormData()
                });
            },

            setCalendar:function(c){
                // clr: "#795AAB"
                // icon: "http://127.0.0.1:8080/svg/aptgo/circ?color=795AAB"
                // name: "Personal"
                // url: "personal"

                this.pageEnabled=0
                axios.post('state', {
                    a: 'set',
                    url: c.url
                }).then(response => {
                    if(response.status===200) {
                        this.setCurCal(c)
                    }
                }).catch((error) => {
                    this.resetCurCal()
                    console.log(error)
                }).then(()=>{
                    // always executed
                    this.getFormData()
                });
            },

            showSlideBar: function(){
                if(!this.isGridReady){
                    gridMaker.setup(this.$refs["grid_cont"],5,"srgdev-appt-grd-")
                    this.isGridReady=true
                }

                if(this.curCal.url===""){
                    this.noCalSet()
                    return
                }

                this.toggleSlideBar(1)
            },

            toggleSlideBar(sbn){
                this.closeNav()
                if(this.sbShow===sbn) this.sbShow=0
                else this.sbShow=sbn
            },


            closeNav:function () {
                let elm=document.getElementById("app-navigation-toggle")
                if(elm!==null && elm.hasAttribute('aria-expanded')
                    && elm.getAttribute('aria-expanded')==='true'){
                    elm.dispatchEvent(new Event('click'))
                }
            },

            makePreviewGrid(d){

                // TODO: Load already existing appointments

                gridMaker.resetAllColumns()

                const NBR_DAYS=5
                // Generate local names for days and month(s)
                let tff
                if(window.Intl && typeof window.Intl === "object") {
                    let f = new Intl.DateTimeFormat([],
                        {weekday:"short",month: "2-digit", day: "2-digit"})
                    tff=f.format
                }else{
                    tff=function (d) {
                        return td.toDateString().slice(0,10)
                    }
                }

                let ws=d.week
                let td=new Date(ws)

                let pd=td.getDate()+"-"+(td.getMonth()+1)+"-"+td.getFullYear()


                // Same formula as @see grid.js#makeColumns(n)
                let w=Math.floor((100 - 1) / NBR_DAYS) + "%"

                for(let ts,i=0;i<NBR_DAYS;i++){
                    ts=ws+i*86400000
                    td.setTime(ts)
                    this.$set(this.gridHeader,i,{
                        ts:ts,
                        txt:tff(td),
                        w:w,
                        n:'8', // Initial value for "add" input must be string
                        hasAppts:false,
                    })
                }

                this.gridApptLen=d.dur
                this.gridApptTs=d.week
                this.gridApptTZ=d.tz

                this.visibleSection=1

                // dd-mm-yyyy
                axios.get('calgetweek', {
                    params:{
                        t:pd
                    }
                }).then(response=>{
                    if(response.status===200) {
                        if(response.data!==""){
                            gridMaker.addPastAppts(response.data,this.curCal.clr)
                        }
                    }
                }).catch(error=>{
                    console.log(error);
                })
            },

            addScheduleToCalendar(){
                const tsa=gridMaker.getStarEnds(this.gridApptTs)
                this.evtGridModal=1

                axios.post('caladd', {
                    d: tsa.join(','),
                    tz:this.gridApptTZ
                }).then(response=>{
                    if(response.status===200) {
                        this.evtGridModal=2
                    }
                }).catch(error=>{
                    this.evtGridModal=3
                    console.log(error);
                })
                //     .finally(()=>{
                //     this.closePreviewGrid()
                // })

            },

            closePreviewGrid() {
                this.visibleSection=0;
            },

            closeEvtModal(){
                this.getFormData()
                this.evtGridModal=0
            },

            resetCurCal(){
                this.curCal={
                    icon:"icon-calendar-dark",
                    name:"Select Calendar",
                    url:"",
                    rIcon:"",
                    clr:""
                }
            },
            setCurCal(c){
                this.curCal.icon='srgdev-icon-override'
                this.curCal.name=c.name
                this.curCal.url=c.url
                this.curCal.rIcon="--srgdev-dot-img: url(" +
                    window.location.protocol + '//' + window.location.host + "/svg/core/places/calendar?color=" + c.clr.slice(1) + ");"
                this.curCal.clr=c.clr
            },
            noCalSet(){
                OC.Notification.showTemporary(this.t('appointments',"Select a Calendar First..."),{timeout:5,type:'warning'})
            },
            notImplemented(){
                OC.Notification.showTemporary(this.t('appointments',"Not Implemented Yet."),{timeout:5,type:'error'})
            }
        }
    }
</script>

<style scoped>
</style>


