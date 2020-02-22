<template>
    <AppNavigationItem
            :key="curCal.name"
            :title="curCal.name"
            :icon="calLoading?null:curCal.icon"
            :style="curCal.rIcon"
            :loading="calLoading">
        <Actions menuAlign="right" @open="getCalendars" forceMenu slot="counter">
            <ActionButton
                    v-for="(cal,index) in calendars"
                    @click="setCalendarFromIndex(index)"
                    :key="cal.name"
                    :icon="cal.icon"
                    :title="cal.name"
                    :disabled="cal.url === ''"
                    class="srgdev_com_circle"
                    closeAfterClick>
            </ActionButton>
        </Actions>
    </AppNavigationItem>
</template>

<script>
    // noinspection ES6CheckImport
    import{
        AppNavigationItem,
        ActionButton,
        Actions,
     } from '@nextcloud/vue'
    import axios from '@nextcloud/axios'

    export default {
        name: 'NavAccountItem',
        props:[
            'curCal','calLoading'
        ],
        data: function() {
            return {
                /** @type {{icon:string,name:string,url:string}[]} */
                calendars:[],
            };
        },
        components: {
            AppNavigationItem,
            ActionButton,
            Actions,
        },
        methods: {
            getCalendars: async function () {

                let l=0;
                this.calendars=[]
                this.$set(this.calendars,0,{
                    icon:"icon-loading",
                    name:"Loading...",
                    clr:"",
                    url:"",
                })
                const f_host = window.location.protocol + '//' + window.location.host+"/svg/appointments/circ?color="
                axios.get('callist')
                    .then(response=>{
                        let cals=response.data.split(String.fromCharCode(31))
                        l=cals.length
                        for(let i=0;i<l;i++){
                            let cal=cals[i].split(String.fromCharCode(30))
                            this.$set(this.calendars, i, {
                                // "Personal", "#795AAB", "personal"
                                icon: f_host + cal[1].slice(1),
                                name: cal[0],
                                clr: cal[1],
                                url: cal[2]
                            })
                        }
                    }).catch(function (error) {
                        console.log(error);
                    }).then(()=>{
                        // always executed
                        if(l===0){
                            this.$set(this.calendars,0,{
                                icon:"icon-error",
                                name:"Loading...",
                                clr:"",
                                url:"",
                            })
                        }
                    });
            },
            setCalendarFromIndex:function (idx) {
                const c=this.calendars[idx]
                this.$emit('calSelected',c)
            }
        }
    }
</script>
