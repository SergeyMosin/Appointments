<script>
// because of https://github.com/nextcloud/nextcloud-focus-trap/issues/2
import {NcActionInput, NcModal} from "@nextcloud/vue"

export default {
  components: {NcModal},
  extends: NcModal,
  name: "ModalExt",
  methods: {
    useFocusTrap() {
    }
  }
}
</script>
