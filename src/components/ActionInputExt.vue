<script>
import {NcActionInput} from '@nextcloud/vue'
export default {
    components: {NcActionInput},
    extends: NcActionInput,
    name: "ActionInput",
    methods: {
        onSubmit(event) {
            NcActionInput.methods.onSubmit.call(this,event)
            if (!this.disabled && this.$parent && this.$parent.closeMenu) {
                this.$parent.closeMenu()
            }
        }
    }
}
</script>