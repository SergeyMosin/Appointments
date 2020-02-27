<template>
    <div id="content">
        <AppNavigation>
            <ul>
            <AppNavigationItem @click="getFormData" :title="'Booking Page ' + (pageEnabled==='1'?'(Online)':'(Disabled)')" icon="icon-projects">
                <template slot="actions">
                    <ActionButton :disabled="pageEnabled==='1'" @click="togglePageEnabled('1')" icon="icon-category-enabled" closeAfterClick>
                        Share Online
                    </ActionButton>
                    <ActionButton :disabled="pageEnabled==='0'" @click="togglePageEnabled('0')" icon="icon-category-disabled" closeAfterClick>
                        Stop Sharing
                    </ActionButton>
                </template>
            </AppNavigationItem>
            <AppNavigationSpacer/>
            <NavAccountItem :calLoading="isCalLoading" @calSelected="setCalendar" :curCal="curCal"></NavAccountItem>
                <AppNavigationItem @click="showSlideBar" title="Generate Schedule" icon="icon-add"></AppNavigationItem>
                <AppNavigationItem @click="showHelp" title="Help/Tutorial" icon="icon-info"></AppNavigationItem>
            </ul>

            <SettingsExt @open="settingsOpen">
                <ul class="settings-fieldset-interior">
                <li>
                    <StnInput @submit="setStn" sname="org" :disabled="!settings.loaded" :value="settings.org" placeholder="Organization Name"></StnInput>
                </li>
                    <li>
                        <StnText @submit="setStn" sname="addr" :disabled="!settings.loaded" :value="settings.addr" placeholder="Organization Address"></StnText>
                    </li>
                    <li>
                        <StnInput @submit="setStn" sname="eml" :disabled="!settings.loaded" :value="settings.eml" placeholder="Email"></StnInput>
                    </li>
                    <li>
                        <StnInput @submit="setStn" sname="phn" :disabled="!settings.loaded" :value="settings.phn" placeholder="Phone (optional)"></StnInput>
                    </li>
                    <li>
                        <input type="checkbox" id="appt-stn_chb-notify" class="checkbox" @change="notImplemented">
                        <label for="appt-stn_chb-notify">Show Notifications on Status Change</label><br>
                    </li>
                    <li>
                        <input type="checkbox" id="appt-stn_chb-captcha" class="checkbox" @change="notImplemented">
                        <label for="appt-stn_chb-captcha">Use Google reCAPTCHA</label><br>
                    </li>
                    <li>
                        <input type="checkbox" id="appt-stn_chb-json_email" class="checkbox" @change="notImplemented">
                        <label for="appt-stn_chb-json_email">Add One Click Actions to Email</label><br>
                    </li>
                </ul>
            </SettingsExt>
        </AppNavigation>
        <AppContent class="srgdev-app-content" :aria-expanded="navOpen">

        <div v-show="visibleSection===1" class="srgdev-appt-cal-view-cont">
            <div class="srgdev-appt-cal-view-btns">
                <button @click="addScheduleToCalendar()" class="primary">
                    Add to Calendar
                </button>
                <button @click="closePreviewGrid()">
                    Discard
                </button>
            </div>
            <ul v-for="(col) in apptInfo" class="srgdev-appt-cal-view-col">
                <li v-for="(cell) in col"
                    :class="'srgdev-appt-cal-view-cell '+cell.cls">
                    <span class="srgdev-appt-cell-txt" v-html="cell.txt"></span>
                    <span class="srgdev-appt-cell-bkr" :style="'background-color: '+curCal.clr"></span>
                </li>
            </ul>
            <Modal v-if="evtGridModal!==0" :canClose="false">
                <div class="srgdev-appt-modal_content">
                    <div v-if="evtGridModal===1" class="srgdev-appt-modal-lbl">
                        Adding appointment to {{curCal.name}} calendar...
                    </div>
                    <div v-if="evtGridModal===2" class="srgdev-appt-modal-lbl">
                        All appointments had been added to {{curCal.name}} calendar.
                    </div>
                    <div v-if="evtGridModal===3" class="srgdev-appt-modal-lbl">
                        Error occurred. Check console...
                    </div>
                    <div v-if="evtGridModal===1" class="srgdev-appt-modal-slider">
                        <div class="srgdev-appt-slider-line"></div>
                        <div class="srgdev-appt-slider-inc"></div>
                        <div class="srgdev-appt-slider-dec"></div>
                    </div>
                    <button v-if="evtGridModal>1" class="primary" @click="closeEvtModal">Close</button>
                </div>
            </Modal>
        </div>
        <div  v-show="visibleSection===0" class="srgdev-appt-main-sec">
            <ul class="srgdev-appt-main-info">
                <li>Public Form Preview</li>
                <ActionButton icon="icon-clippy" @click="copyPubLink">Copy public link</ActionButton>
            </ul>
            <div class="srgdev-appt-main-frame-cont">
                <iframe class="srgdev-appt-main-frame" ref="pubPageRef" :src="pubPage"></iframe>
            </div>
        </div>
        <div v-html="helpContent" v-show="visibleSection===2" class="srgdev-appt-help-sec">
        </div>
        <ScheduleSlideBar
                title="Schedule Generator"
                subtitle="Add open appointments to you calendar"
                @agDataReady="makePreviewGrid"
                v-show="sbShow" @close="sbShow=false"/>
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
    } from '@nextcloud/vue'
    import {detectColor} from "./utils.js";

    import StnText from "./components/StnText.vue";
    import StnInput from "./components/StnInput.vue";
    import SettingsExt from "./components/SettingsExt.vue";
    import NavAccountItem from "./components/NavAccountItem.vue";
    import ScheduleSlideBar from "./components/ScheduleSlideBar.vue";
    import axios from '@nextcloud/axios'

    // const SHARE_NONE=0
    // const SHARE_TOKEN=0
    // const SHARE_ALL=0

    export default {
        name: 'App',
        components: {
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
            StnText
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
                sbShow:false,
                apptInfo: [],

                visibleSection:0,

                evtGridData:[],
                evtGridModal:0,

                settings:{
                    loaded:false,
                    org:"",
                    addr:"",
                    eml:"",
                    phn:""
                },

                helpContent:"",
                tken:this.getPubUri()
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
        },
        methods: {

            async settingsOpen(){
                this.settings.org=""
                this.settings.addr = ""
                this.settings.eml = ""
                this.settings.phn = ""
                this.settings.loaded=false

                const rda = await this.getStn()
                if(rda===null) return

                this.settings.org = rda[0]
                this.settings.addr = rda[1].replace(/<br>/g, "\n")
                this.settings.eml = rda[2]
                this.settings.phn = rda[3]
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
                    OC.Notification.showTemporary("Settings Error Occurred. Check console.",{timeout:4,type:'warning'})
                });
            },

            /**
             * @return {Promise<string[]|null>}
             */
            async getStn(){
                try {
                    const res= await axios.post('state', {a: 'get_settings'})
                    if(res.status===200){
                        const rda=res.data.split(String.fromCharCode(30))
                        if(rda.length===4) {
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
                    OC.Notification.showTemporary("Can't get Settings. Check console   ",{timeout:8,type:'error'})
                    return null
                }
            },


            showHelp(){
                this.visibleSection=2

                axios.get('help')
                    .then(response => {
                        if(response.status===200) {
                            this.helpContent=response.data
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
                    OCP.Toast.success('Public link copied to clipboard...')
                } catch (error) {
                    console.log(error)
                    OCP.Toast.error('Public link could not be copied to clipboard...')
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
                            if (i === 0) n = 'Organization Name'
                            else if (i === 1) n = 'Address'
                            else n = 'Email'
                            OC.Notification.showTemporary("Error: '"+n+"' empty, check settings...",{timeout:8,type:'error'})
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
                    }
                }).catch((error) => {
                    console.log(error)
                    OC.Notification.showTemporary("Page enable error. Check console...",{timeout:4,type:'error'})
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
                if(this.curCal.url===""){
                    this.noCalSet()
                    return
                }
                this.closeNav()
                this.sbShow=!this.sbShow
            },

            closeNav:function () {
                let elm=document.getElementById("app-navigation-toggle")
                if(elm!==null && elm.hasAttribute('aria-expanded')
                    && elm.getAttribute('aria-expanded')==='true'){
                    elm.dispatchEvent(new Event('click'))
                }
            },
            makePreviewGrid(d){

                // console.log(d)

                const MS_DAY=86400000
                const MS_HOUR=3600000
                const MS_MIN=60000

                // Generate local names for days and month(s)
                let header=[]
                let ws=d.week
                let td=new Date(ws)

                if(window.Intl && typeof window.Intl === "object") {
                    let f = new Intl.DateTimeFormat([],
                        {weekday:"short",month: "2-digit", day: "2-digit"})
                    for(let i=0;i<5;i++){
                        td.setTime(ws+i*86400000)
                        header[i]=f.format(td)
                    }
                }else{
                    for(let i=0;i<5;i++){
                        td.setTime(ws+i*86400000)
                        header[i]=td.toDateString().slice(0,10)
                    }
                }

                let firstAt=d.startTime*MS_MIN
                // 5PM is last appointment
                let lastAt=17*MS_HOUR
                let dur=d.dur*MS_MIN
                let fNbr=d.nbr

                let hadLunch
                let lunchStart=12*MS_HOUR
                let lunchEnd=lunchStart+dur
                if(!d.lunch) {
                    lunchStart=4294967294000
                    lunchEnd=0
                }
                let cls="srgdev-appt-cell"+d.dur
                let ff
                if(window.Intl && typeof window.Intl === "object") {
                    let f = new Intl.DateTimeFormat([],
                        {hour: "numeric", minute: "2-digit"})
                    ff=f.format
                }else{
                    ff=function (d) {
                        return d.toLocaleTimeString()
                    }
                }

                /**
                 * THHMMSS
                 * @param {Date} d
                 * @return {String}
                 */
                let makeET=function(d){
                    const h=d.getHours()
                    const m=d.getMinutes()
                    return "T"+(h<10?"0"+h:""+h)+(m<10?"0"+m:""+m)+"00"
                }

                // Last for this day
                let dLast=ws+lastAt
                let ast,aet
                let clsNms=[]

                lunchStart += ws
                lunchEnd += ws
                hadLunch = !d.lunch
                ast = ''
                aet = ''
                for (let evs,eve, i = 0; i < fNbr; i++) {
                    let nts = ws + firstAt + i * dur
                    if (nts < dLast) {
                        td.setTime(nts)
                        ast=ff(td)
                        evs=makeET(td)
                        td.setTime(nts + dur)
                        aet = ff(td)
                        eve=makeET(td)
                        if (!hadLunch && nts >= lunchStart) {
                            // Lunch Time
                            hadLunch = true
                            clsNms[i]={
                                txt:'Lunch <span class="srgdev-appt-lunch-time">' +ast + ' - ' + aet + '</span>',
                                cls:cls +' srgdev-appt-cell-lunch',
                                isLunch:true,
                                evs:evs,
                                eve:eve,
                            }
                            fNbr++
                        }else{
                            clsNms[i]={
                                txt: ast + ' - <span>' + aet + '</span>',
                                cls: cls,
                                isLunch: false,
                                evs:evs,
                                eve:eve,
                            }
                        }
                    }
                }


                // First element is the "create date" in UTC
                let eventsData=[(new Date).toISOString().slice(0,-5).replace(/[\-:]/g,'')+'Z']
                //YYYYMMDDTHHMMSS(Z)
                let now=Date.now()
                let tba=[]
                let ta
                let l=clsNms.length
                for(let j=0;j<5;j++) {
                    ta = [{
                        txt: header[j],
                        cls: 'srgdev-appt-cal-view-head'
                    }]

                    for (let ed,m,d,i = 0; i < l; i++) {
                        let nts = ws + firstAt + i * dur
                        let cln
                        if (nts < now) {
                            // We are in the past
                            if(clsNms[i].isLunch){
                                cln=clsNms[i].cls+"_past"
                            }else{
                                cln=cls + ' srgdev-appt-cell-past'
                            }
                        } else {
                            // Good time slot
                            cln=clsNms[i].cls
                            if(!clsNms[i].isLunch) {
                                // YYYYMMDD
                                td.setTime(nts)
                                m = td.getMonth() + 1
                                d = td.getDate()
                                ed = td.getFullYear() + (m < 10 ? "0" + m : "" + m) + (d < 10 ? "0" + d : "" + d)
                                eventsData.push(ed + clsNms[i].evs)
                                eventsData.push(ed + clsNms[i].eve)
                                // eventsData.push((nts/1000).toString())
                                // eventsData.push(((nts+dur)/1000).toString())
                            }
                        }
                        ta[i+1] = {
                            txt: clsNms[i].txt,
                            cls: cln,
                        }
                    }
                    tba[j]=ta
                    ws+=MS_DAY
                }
                this.evtGridData=eventsData
                this.apptInfo=tba;
                this.visibleSection=1
            },

            addScheduleToCalendar(){
                let _this=this
                _this.evtGridModal=1

                // console.log(_this.evtGridData)

                axios.post('caladd', {
                    d: this.evtGridData.join(',')
                }).then(function (response) {
                    if(response.status===200) {
                        _this.evtGridModal=2
                    }
                }).catch(function (error) {
                    _this.evtGridModal=3
                    console.log(error);
                })

                this.closePreviewGrid()
            },

            closePreviewGrid() {
                this.visibleSection=0;
                this.apptInfo=[];
                this.evtGridData=[];
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
                OC.Notification.showTemporary("Select a Calendar First...",{timeout:5,type:'warning'})
            },
            notImplemented(){
                OC.Notification.showTemporary("Not Implemented Yet.",{timeout:5,type:'error'})
            }
        }
    }
</script>

<style scoped>
</style>


