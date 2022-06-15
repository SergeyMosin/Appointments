<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PHP Embedding Test</title>
    <style>
        .iframe_form {
            height: 45em;
            width: 30em;
        }

        .iframe_confirm {
            height: 20em;
            width: 40em;
        }

        .iframe_small {
            height: 18em;
            width: 30em;
        }

        .iframe_error {
            border-color: red;
        }
    </style>
    <script>
        // this is optional
        window.addEventListener("message", (evt) => {
            let cls = ""
            switch (evt.data) {
                case "appt:error_page":
                    // "An error has occurred..." message is shoving in the iframe
                    cls = "iframe_error "
                case "appt:almost_done":
                    // "Almost done..." message is showing in the iframe
                    cls += "iframe_small"
                    const frame = document.getElementById("my_iframe")
                    if (frame !== null) {
                        frame.className = cls
                    }
                    break
                case "appt:action_needed":
                    console.log("'Action needed...' page")
                    break
                case "appt:all_done":
                    console.log("'All done...' page")
                    break
            }
        })
    </script>
</head>
<body>
<div>
    Parent Content Above
</div>
<?php
$src = 'http://nc22.localhost:9090/index.php/apps/appointments/embed/_o2wHj4yTTtQm9E%3D/form';
$iframe_class = "iframe_form";
$key_name = "my_param_key";
if (isset($_GET[$key_name])) {
    // Email Confirm/Cancel button was clicked
    $src = substr($src, 0, -4) . 'cncf?d=' . urlencode($_GET[$key_name]);
    $iframe_class = "iframe_confirm";
}
echo '<iframe id="my_iframe" class="' . $iframe_class . '" src = "' . $src . '"></iframe>';
?>
<div>
    Parent Content Below
</div>
</body>
</html>
