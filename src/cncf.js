(function () {
    "use strict"
    window.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('srgdev-appt-cncf_action_btn')
        let msg = ""
        if (btn !== null) {
            btn.addEventListener("click", function (e) {
                /** @type {HTMLElement} */
                let t = e.currentTarget
                const attrName = 'data-appt-action-url-hash'
                if (t !== null && t.hasAttribute(attrName)) {
                    const uri = window.location.pathname+window.location.search+'&h='+t.getAttribute(attrName)

                    //Avoid double clicks
                    t.removeAttribute(attrName)

                    // show spinner
                    document.getElementById("srgdev-ncfp_fbtn-spinner").style.display = "inline-block"

                    if (window.history && window.history.replaceState) {
                        window.history.replaceState({}, '', uri)
                        window.history.go()
                    } else {
                        window.location = uri
                    }
                }
            })

            // embedding stuff @see /test/embedding
            msg = "appt:action_needed"

        } else {
            if (typeof URL === "function" && window.history && window.history.replaceState) {
                const u = new URL(window.location)
                if (u.searchParams.get("h") !== null) {
                    u.searchParams.delete("h")
                    window.history.replaceState({}, '', u.toString())
                }
            }

            // embedding stuff @see /test/embedding
            if (window.location.pathname.slice(-4) === "cncf") {
                msg = "appt:all_done"
            } else {
                const q = window.location.search.substring(0, 6)
                if (q !== "") {
                    if (q === "?sts=0") {
                        msg = "appt:almost_done"
                    } else if (q === "?sts=1" || q === "?sts=2") {
                        msg = "appt:error_page"
                    }
                }
            }
        }

        // embedding stuff @see /test/embedding
        if (window.parent && msg !== "") {
            window.parent.postMessage(msg, "*")
        }
    })
})()
