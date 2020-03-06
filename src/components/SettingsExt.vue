<script>
    import {AppNavigationSettings} from '@nextcloud/vue'
    export default {
        components: {AppNavigationSettings},
        extends: AppNavigationSettings,
        name: "SettingsExt",
        methods: {
            toggleMenu() {
                AppNavigationSettings.methods.toggleMenu.call(this)
                if(this.open) this.$emit('open')
            }
        }
    }
</script>
