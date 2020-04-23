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
                        :loading="sbLoading===5"
                        @click="openSlideBarAGen"
                        :title="t('appointments','Add Appointment Slots')"
                        icon="icon-add"></AppNavigationItem>
                <AppNavigationSpacer/>
                <AppNavigationItem
                        :loading="sbLoading===3"
                        @click="openSlideBar(3,'get_uci',uciInfo)"
                        :title="t('appointments','User/Organization Info')"
                        icon="icon-user"></AppNavigationItem>
                <AppNavigationItem
                        :loading="sbLoading===2"
                        @click="openSlideBar(2,'get_pps',ppsInfo)"
                        :title="t('appointments','Customize Public Page')"
                        icon="icon-category-customization"></AppNavigationItem>
                <AppNavigationItem
                        :loading="sbLoading===4"
                        @click="openSlideBar(4,'get_eml',emlInfo)"
                        :title="t('appointments','Email Settings')"
                        icon="icon-mail"></AppNavigationItem>
                <AppNavigationItem
                        :pinned="true"
                        @click="showHelp"
                        :title="t('appointments','Help/Tutorial')"
                        icon="icon-info"></AppNavigationItem>
            </ul>
        </AppNavigation>
        <AppContent style="transition: none;" class="srgdev-app-content" :aria-expanded="navOpen">

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
                    <div v-if="evtGridModal===4" class="srgdev-appt-modal-lbl">
                        <div style="font-size: 110%;font-weight: bold">{{modalHeader}}</div>
                        <div style="user-select: text; cursor: text;">{{modalText}}</div>
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
                    :subtitle="t('appointments','Add open appointments to your calendar')"
                    :tz-name="gridTZName"
                    :tz-data="gridTZData"
                    @agDataReady="makePreviewGrid"
                    v-show="sbShow===1" @close="sbShow=0"/>
            <FormStnSlideBar
                    :pps-info="ppsInfo"
                    @apply="setState('set_pps',$event)"
                    v-show="sbShow===2" @close="sbShow=0"/>
            <UserStnSlideBar
                    :uci-info="uciInfo"
                    @apply="setState('set_uci',$event)"
                    v-show="sbShow===3" @close="sbShow=0"/>
            <MailStnSlideBar
                    :eml-info="emlInfo"
                    @apply="setState('set_eml',$event)"
                    v-show="sbShow===4" @close="sbShow=0"/>
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

    import ActionInput from "./components/ActionInputExt.vue";
    import NavAccountItem from "./components/NavAccountItem.vue";
    import ScheduleSlideBar from "./components/ScheduleSlideBar.vue";
    import axios from '@nextcloud/axios'

    import gridMaker from "./grid.js"
    import FormStnSlideBar from "./components/FormStnSlideBar.vue";
    import UserStnSlideBar from "./components/UserStnSlideBar.vue";
    import MailStnSlideBar from "./components/MailStnSlideBar.vue";

    import {linkTo} from '@nextcloud/router'

    export default {
        name: 'App',
        components: {
            FormStnSlideBar,
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
            Actions,
            ActionInput,
            UserStnSlideBar,
            MailStnSlideBar
        },
        data: function() {
            return {

                // mainForm:'',
                pubPage:'',

                curCal:{},

                isCalLoading:false,

                pageEnabled:'0',

                value: null, // <-???
                navOpen:false,
                sbShow:0,
                sbLoading:0,

                visibleSection:0,

                evtGridData:[],
                evtGridModal:0,
                modalHeader:"",
                modalText:"",

                helpContent:"",

                isGridReady:false,
                /**
                 * @type {{ts:number,txt:string,w:string,n:number,hasAppts:boolean}[]}
                 */
                gridHeader:[],
                gridApptLen:0,
                gridApptTs:0,
                gridApptTZ:"L",

                gridTZData:"",
                gridTZName:"",

                // SlideBars...
                ppsInfo:{},
                uciInfo:{},
                emlInfo:{},

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
            this.$root.$on('helpWanted', this.helpWantedHandler)
        },
        beforeDestroy() {
            this.$root.$off('helpWanted', this.helpWantedHandler)
        },
        methods: {

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

            /**
             * @param {number} sbn SlideBar number
             * @param {string} action get_xxx...
             * @param {Object} props props/info object for the slide bar
             */
            openSlideBar(sbn,action,props){
                if(this.sbShow===sbn){
                    // already open, close...
                    this.toggleSlideBar(sbn)
                    return
                }
                this.sbLoading=sbn
                this.getState(action).then(res=>{
                    if(res!==null) {
                        for (let key in res) {
                            if (res.hasOwnProperty(key)) {
                                this.$set(props, key, res[key])
                            }
                        }
                        this.toggleSlideBar(sbn)
                    }
                    this.sbLoading=0
                })
            },

            /** @return {Promise<JSON|string|null>} */
            async getState(action){
                try {
                    const res= await axios.post('state', {a: action})
                    if(res.status===200){
                        return res.data
                    }else{
                        console.log(res)
                        OC.Notification.showTemporary(t('appointments',"Can't get Settings. Check console")+"\xa0\xa0\xa0\xa0",{timeout:8,type:'error'})
                        return null
                    }
                }catch (e) {
                    console.log(e)
                    OC.Notification.showTemporary(t('appointments',"Can't get Settings. Check console")+"\xa0\xa0\xa0\xa0",{timeout:8,type:'error'})
                    return null
                }
            },

            /**
             * @param {string} action
             * @param {Object} value
             */
            setState(action,value){
                let ji=""
                try {
                    ji=JSON.stringify(value)
                }catch (e) {
                    console.log(e)
                    OC.Notification.showTemporary(this.t('appointments',"Can't apply settings"),{timeout:4,type:'error'})
                }
                axios.post('state', {
                    a: action,
                    d: ji
                }).then(response => {
                    if(response.status===200) {
                        this.getFormData()
                        OCP.Toast.success(this.t('appointments','New Settings Applied.'))
                    }
                }).catch((error) => {
                    console.log(error)
                    OC.Notification.showTemporary(this.t('appointments',"Can't apply settings"),{timeout:4,type:'error'})
                })
            },

            openSlideBarAGen: function(){

                if(this.sbShow===1){
                    this.toggleSlideBar(1)
                    return;
                }

                this.sbLoading=5;
                if(!this.isGridReady){
                    gridMaker.setup(this.$refs["grid_cont"],5,"srgdev-appt-grd-")
                    this.isGridReady=true
                }

                if(this.curCal.url===""){
                    this.noCalSet()
                    this.sbLoading=0;
                    return
                }

                this.gridTZName="UTC"
                this.gridTZData="UTC"

                this.getState("get_tz").then(res=>{
                    if(res!==null && res.toLowerCase()!=='utc') {
                        let url=linkTo('appointments','ajax/zones.json')
                        return axios.get(url).then(tzr=>{
                            if(tzr.status===200) {
                                let tzd=tzr.data
                                if(typeof tzd==="object"
                                    && tzd.hasOwnProperty('aliases')
                                    && tzd.hasOwnProperty('zones')
                                ){
                                    let tzs=""
                                    if(tzd.zones[res]!==undefined){
                                        tzs=tzd.zones[res].ics.join("\r\n")
                                    }else if(tzd.aliases[res]!==undefined){
                                        let alias=tzd.aliases[res].aliasTo
                                        if(tzd.zones[alias]!==undefined){
                                            res=alias
                                            tzs=tzd.zones[alias].ics.join("\r\n")
                                        }
                                    }
                                    return [res,tzs]
                                }
                            }
                            return null
                        })
                    }else return Promise.resolve(null)
                }).then((r)=>{
                    if(r===null || !Array.isArray(r) || r.length!==2 || r[1]===""){
                        console.error("can't get timezone data")
                    }else{
                        this.gridTZName=r[0]
                        this.gridTZData= "BEGIN:VTIMEZONE\r\nTZID:"
                            +r[0].trim()+"\r\n"+r[1].trim()+"\r\nEND:VTIMEZONE"
                    }
                    this.sbLoading=0;
                    this.toggleSlideBar(1)
                }).catch(err=>{
                    console.log(err)
                    this.sbLoading=0;
                    this.toggleSlideBar(1)
                })
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



            helpWantedHandler(section){
                this.toggleSlideBar(0)
                this.showHelp(section)
                // document.getElementById("sec_"+section).scrollIntoView()
            },

            showHelp(sec){
                if(typeof sec!=="string" && this.visibleSection===2){
                    this.visibleSection=0
                    return
                }

                this.visibleSection=2

                axios.get('help')
                    .then(response => {
                        if(response.status===200) {
                            this.helpContent=response.data
                            if(sec!==undefined) {
                                this.$nextTick(function () {
                                    let elm=document.getElementById("srgdev-sec_" + sec)
                                    if(elm!==null){
                                        elm.scrollIntoView({block: "center"})
                                        elm.className+=' srgdev-appt-temp-highlight'
                                        setTimeout(function () {
                                            elm.className=elm.className.replace(' srgdev-appt-temp-highlight','')
                                        },2000)
                                    }
                                })
                            }
                        }})
                    .catch((error) => {
                        console.log(error)
                        this.helpContent=''
                    })
            },

            copyPubLink(){
                axios.post('state', {
                    a: 'get_puburi'
                }).then(response => {
                    if(response.status===200) {
                        try {
                            this.doCopy(response.data)
                        }catch (e) {
                            console.log(e)
                        }
                    }
                }).catch((error) => {
                    console.log(error)
                    OCP.Toast.error(this.t('appointments','Can not get public URL from server')+"\xa0\xa0\xa0\xa0")
                })
            },

            doCopy(text){
                const ok_txt=this.t('appointments', 'Public link copied to clipboard')+"\xa0\xa0\xa0\xa0"
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(function() {
                        OCP.Toast.success(ok_txt)
                    }, function(err) {
                        console.error('copy error:',err);
                        this.showModal(this.t('appointments', 'Public URL:'),text)
                    });
                }else{
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
                        console.error('copy error:',err)
                        copyOK=false
                    }
                    document.body.removeChild(textArea);

                    if(copyOK){
                        OCP.Toast.success(ok_txt)
                    }else {
                        this.showModal(this.t('appointments','Public URL:'), text)
                    }
                }
            },

            showModal(title,txt){
                this.modalHeader=title
                this.modalText=txt
                this.evtGridModal=4
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

                    this.getState("get_uci").then(res=>{
                        //organization: "", email: "", address: ""
                        let n=-1
                        let pa=["organization","email","address"];
                        for(let v,i=0,l=pa.length;i<l;i++){
                            v=pa[i]
                            if(!res.hasOwnProperty(v) || res[v].length<2){
                                n=i
                                break
                            }

                        }
                        if(n!==-1){
                            let fn=["'Name'","'Email'","'Location'"][n]
                            OC.Notification.showTemporary(this.t('appointments',"Error: {fieldName} empty, check settings",{fieldName:fn})+"\xa0\xa0\xa0\xa0",{timeout:8,type:'error'})
                            return
                        }
                        this.setPageState(enable)
                    })
                }else{
                    this.setPageState(enable)
                }
            },
            setPageState(enable){
                axios.post('state', {
                    a: 'enable',
                    v: enable
                }).then(response => {
                    if(response.status===200) {
                        this.pageEnabled=enable
                    }
                }).catch((error) => {
                    console.log(error)
                    OC.Notification.showTemporary(this.t('appointments',"Page enable error. Check console")+"\xa0\xa0\xa0\xa0",{timeout:4,type:'error'})
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

            makePreviewGrid(d){

                gridMaker.resetAllColumns()

                const NBR_DAYS=5
                // Generate local names for days and month(s)
                let tff
                if(window.Intl && typeof window.Intl === "object") {
                    let f = new Intl.DateTimeFormat([],
                        {weekday:"short",month: "2-digit", day: "2-digit"})
                    tff=f.format
                }else{
                    // noinspection JSUnusedLocalSymbols
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
                const tsa=gridMaker.getStarEnds(this.gridApptTs,this.gridApptTZ==='UTC')
                this.evtGridModal=1

                axios.post('caladd', {
                    d: tsa.join(','),
                    tz:this.gridApptTZ
                }).then(response=>{
                    if(response.status===200) {
                        if(response.data.substr(0,1)!=='0'){
                            // error
                            console.log(response.data);
                            this.evtGridModal=3
                        }else{
                            // good
                            this.evtGridModal=2
                        }
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
                if(this.evtGridModal<3) this.getFormData()
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
                    window.location.protocol + '//' + window.location.host + OC.webroot+"/index.php/svg/core/places/calendar?color=" + c.clr.slice(1) + ");"
                this.curCal.clr=c.clr
            },

            noCalSet(){
                OC.Notification.showTemporary(this.t('appointments',"Select a Calendar First")+"\xa0\xa0\xa0\xa0",{timeout:5,type:'warning'})
            }
        }
    }
</script>

<style scoped>
</style>


