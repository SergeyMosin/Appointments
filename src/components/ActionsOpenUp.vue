<script>
import {NcActions} from '@nextcloud/vue'
export default {
  components: {NcActions},
  name: "ActionsOpenUp",
  extends: NcActions,
  methods: {
    onOpen() {
      this.offsetX = 0
      this.offsetY = 0 - Math.round(this.$refs.menu.clientHeight) - 42
      this.offsetYArrow = Math.round(this.$refs.menu.clientHeight) + 17
      this.rotateArrow = true
    },
  }
}
</script>

