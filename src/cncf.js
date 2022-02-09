(function () {
    "use strict"
    window.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('srgdev-appt-cncf_action_btn')
        if (btn !== null) {
            btn.addEventListener("click", function (e) {
                /** @type {HTMLElement} */
                let t = e.currentTarget
                const attrName = 'data-appt-action-url'
                if (t !== null && t.hasAttribute(attrName)) {
                    const uri = t.getAttribute(attrName)

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
        } else {
            if (typeof URL === "function" && window.history && window.history.replaceState) {
                const u = new URL(window.location)
                u.searchParams.delete("h")
                window.history.replaceState({}, '', u.toString())
            }
        }
    })
})()
