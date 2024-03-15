<div id="srgdev-appt_help-cont" class="srgdev-appt-hs-inner">
    <h2 class="srgdev-appt-hs-h1">Quick Start Guide</h2>
    <p class="srgdev-appt-hs-p-hb">1. Add a new calendar (in "Calendar" App)</p>
    <img class="quick-start-guide-img" alt="add calendar" style="max-width: 760px" src="<?php print_unescaped(image_path('appointments', 'qs1-add-calendar.jpg')); ?>"/>
    <p class="srgdev-appt-hs-p-hb">2. Add contact info</p>
    <img class="quick-start-guide-img" alt="contact info" style="max-width: 688px" src="<?php print_unescaped(image_path('appointments', 'qs2-contact-info.jpg')); ?>"/>
    <p class="srgdev-appt-hs-p-hb">3. Select Calendar, Apply and Click "Edit Template"</p>
    <img class="quick-start-guide-img" alt="calendar settings" style="max-width: 694px" src="<?php print_unescaped(image_path('appointments', 'qs3-calendar-settings.jpg')); ?>"/>
    <p class="srgdev-appt-hs-p-hb">4. Add appointment slots</p>
    <img class="quick-start-guide-img" alt="add appointment slots" style="max-width: 846px" src="<?php print_unescaped(image_path('appointments', 'qs4-add-appointment-slots.jpg')); ?>"/>
    <p class="srgdev-appt-hs-p-hb">5. Save the template</p>
    <img class="quick-start-guide-img" alt="save template" style="max-width: 857px" src="<?php print_unescaped(image_path('appointments', 'qs5-save-template.jpg')); ?>"/>
    <p class="srgdev-appt-hs-p-hb">6. Enable sharing</p>
    <img class="quick-start-guide-img" alt="enable sharing" style="max-width: 706px" src="<?php print_unescaped(image_path('appointments', 'qs6-enable-sharing.jpg')); ?>"/>
    <p class="srgdev-appt-hs-p-hb">7. Get appointments page URL</p>
    <img class="quick-start-guide-img" alt="get page url" style="max-width: 706px" src="<?php print_unescaped(image_path('appointments', 'qs7-get-page-url.jpg')); ?>"/>
    <p id="srgdev-sec_timezone" class="srgdev-appt-hs-p-hb">Timezones</p>
    <p class="srgdev-appt-hs-p">Your calendar's timezone is used as the base and appointment time will be "casted" to visitors local time.</p>
    <p class="srgdev-appt-hs-p srgdev-appt-hs-p_t">Example: 12:00PM
        <strong>America/New_York timezone</strong> appointment based in New York</p>
    <img alt="local timezone" class="srgdev-appt-hs-tz-img quick-start-guide-img" src="<?php print_unescaped(image_path('appointments', 'actual_timezone.jpg')); ?>"/>

    <hr>

    <h2 class="srgdev-appt-hs-h1">Customize Public Page</h2>
    <p class="srgdev-appt-hs-p"><strong id="srgdev-sec_gdpr">GDPR Compliance</strong></p>
    <p class="srgdev-appt-hs-p">Any text in the "GDPR Compliance" field will trigger display of the "GDPR" check box. The checkbox can be hidden when "GDPR text only (no checkbox)" option is checked.</p>
    <p class="srgdev-appt-hs-p">A check box with plain text (no html) or any html/links without a checkbox will work as is. However, if you need to have a the check and html or a link to your privacy policy please read on... For the link to work properly you should separate it from the &lt;label&gt; element, and the &lt;label&gt;'s
        <strong>"for"</strong> attribute MUST be set to <strong>"appt_gdpr_id"</strong>, example:</p>
    <code class="srgdev-appt-hs-code">
        &lt;label for=&quot;appt_gdpr_id&quot;&gt;Some text &lt;/label&gt;&lt;a href=&quot;PRIVACY_POLCY_URL&quot;&gt;Privacy Policy&lt;/a&gt;&lt;label for=&quot;appt_gdpr_id&quot;&gt; some more text.&lt;/label&gt;
    </code>
    <p class="srgdev-appt-hs-p-h"><strong>Appointment's Title</strong></p>
    <p class="srgdev-appt-hs-p">If an event's title/summary starts with an "_" character then the title will be displayed next or below the time in the form. For example:
        <code class="srgdev-appt-hs-code_short" style="padding-top: .1em; padding-bottom: .1em"><strong>_</strong>Language Lessons</code> will be displayed as "Language Lessons"
    </p>
    <p class="srgdev-appt-hs-p-h"><strong id="srgdev-sec_tmm_title_template">Title Template</strong></p>
    <div class="srgdev-appt-hs-p">
        Following template tokens can be used to customize Appointment's Title:
        <div style="margin-left: 2em">
            <code>%N</code> - Attendee name<br>
            <code>%O</code> - Organization Name<br>
            <code>%P</code> - Page Name (as shown/set in page list sidebar)<br>
            <code>%T</code> - Mask Token (first three letters of name + semi-random token)<br>
        </div>
        For example template like <code class="srgdev-appt-hs-code_short">%N (%O)</code> will set new appointments title to something like <code class="srgdev-appt-hs-code_short">John Smith (Good Org)</code>
    </div>
    <p class="srgdev-appt-hs-p-h"><strong id="srgdev-sec_style">Style Override</strong></p>
    <p class="srgdev-appt-hs-p">Insert custom
        <code>&lt;style&gt;&lt;/style&gt;</code> element to override default page style. Try something like this for example:
    </p>
    <pre><code class="srgdev-appt-hs-code">&lt;style&gt;
#header{
    background: transparent !important;
}
#content{
    background: linear-gradient(to bottom, #ff00cc, #333399) !important;
}
#body-public #content {
    min-height: 100%;
}
form{
    background:whitesmoke;box-shadow: 3px 3px 25px 0px rgba(0,0,0,0.75);
}
.srgdev-ncfp-form-header{
    border-bottom: 3px solid #961AB1;
}
&lt;/style&gt;</code></pre>
    <span style="font-style: italic">* Don't forget to style confirm and error pages</span>
    <h2 class="srgdev-appt-hs-h1">Email Settings</h2>
    <p class="srgdev-appt-hs-p-h">
        <strong id="srgdev-sec_emailatt">Email Attendee when the appointment is modified and/or deleted</strong> - Attendees will be notified via email when their
        <strong>upcoming</strong> appointments are updated or deleted in the calendar app or via some other external mechanism. Only changes to Date/Time, Status or Location will trigger the "Modified" notification.
    </p>
    <p class="srgdev-appt-hs-p-h">
        <strong id="srgdev-sec_emailme">Email Me when an appointment is updated</strong> - A notification email will be sent to you when an appointment is booked via the public page or an upcoming appointment is confirmed or canceled via the email links.
    </p>
    <p class="srgdev-appt-hs-p-h">
        <strong id="srgdev-sec_emailskipevs">Skip email validation step</strong> - When this option is selected the "<em>... action needed</em>" validation email will NOT be sent to the attendee. Instead the "<em>... Appointment is confirmed</em>" message is going to be sent right away, and the "<em>All done</em>" page is going to be shown when the form is submitted.
    </p>
    <p class="srgdev-appt-hs-p-h">
        <strong id="srgdev-sec_emaildef"><code>useDefaultEmail</code></strong> - Most instance of NC won't have the particular configuration allowing to send emails on behalf of organizers. Therefore, the default email address as per
        <a class="srgdev-appt-hs-link" href="https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/email_configuration.html" target="_blank">Mail Settings</a> is used, and your address is added in the "Reply-To:" header field. If your Nextcloud configuration supports sending out emails for individual users, Admins can override the 'useDefaultEmail' directive like so:
        <code class="srgdev-appt-hs-code_short">occ config:app:set appointments useDefaultEmail --value no</code></p>
    <p class="srgdev-appt-hs-p-h">
        <strong id="srgdev-sec_emailmoretext"><code>Additional Email Text</code></strong> - this text is appended as paragraph to the end of validation and confirmation emails. Currently only pain text is allowed, HTML will be escaped.
    </p>
    <p class="srgdev-appt-hs-p-h">
        <strong id="srgdev-sec_icsmoretext"><code>Additional ICS file description</code></strong> - this text (no HTML please) will be appended to the end of the event's "DESCRIPTION" property.
    </p>
    <h2 class="srgdev-appt-hs-h1" id="srgdev-sec_ts_mode">Time slot mode</h2>
    <p class="srgdev-appt-hs-p-h">
        <strong>Weekly Template</strong> - in this mode you can set a weekly template and it will be repeated automatically.
    </p>
    <div style="margin-left: 2em">
        <p class="srgdev-appt-hs-p-h">
            <strong id="srgdev-sec_destcal_tmm">Destination Calendar (Weekly Template)</strong> - Booked/pending appointments will be placed into this calendar.
        </p>
        <p class="srgdev-appt-hs-p-h">
            <strong id="srgdev-sec_conflicts_tmm">Check for conflicts in…</strong> - these calendars will be checked for conflicting events in addition to the Destination Calendar.
        </p>
        <p class="srgdev-appt-hs-p-h"><strong id="srgdev-sec_props_tmm">Appointment Properties</strong> -
            <em>Duration</em>: if you set multiple duration choices for an appointment, then a visitor will be able to pick one of them.
            <em>Title</em>: if this is set then the title will be displayed next or below the time in the form.</p>
        <p class="srgdev-appt-hs-p-h">
            <strong id="srgdev-sec_tmm_subs_sync">Subscriptions Sync Interval</strong> - When linked(subscription) calendars are selected for conflict check, appointments app can pull data from remote servers before checking for scheduling conflicts. It is impractical to pull the data on every request as this will increase processing time especially if multiple remote calendars are selected. Nextcloud (as many other calendar systems) has a cache synchronization mechanism to facilitate timely updates, this option is provided just in-case you feel that the data is not refreshed often enough by nextcloud.
        </p>
    </div>
    <p class="srgdev-appt-hs-p-h">
        <strong>Simple mode</strong> - Use provided "Add Appointment Slots" dialog to add "available" time slots. Recurrence is not suported in this mode.
    </p>
    <div style="margin-left: 2em">
        <p class="srgdev-appt-hs-p-h">
            <strong id="srgdev-sec_maincal">Main Calendar</strong> - when you create new appointments they are placed here and are shown in the your public page(s). It is recommended to create a separate calendar.
        </p>
        <p class="srgdev-appt-hs-p-h">
            <strong id="srgdev-sec_destcal">Calendar for booked appointments</strong> - if this calendar is different from the main calendar, once an appointment is booked it will be moved here.
        </p>
    </div>
    <p class="srgdev-appt-hs-p-h">
        <strong>External mode</strong> - Use Nextcloud's Calendar App or any other CalDAV compatible client to add "available" timeslots. Most recurrence rules are supported in this mode. Two calendars are required: a "Source Calendar" to keep track of your availability timeslots and a "Destination Calendar" for booked appointments.
    </p>
    <div style="margin-left: 2em">
        <p class="srgdev-appt-hs-p-h">
            <strong id="srgdev-sec_sourcecal_nr">Source Calendar (External mode)</strong> - Any event with "Show As" a.k.a. "Time As" a.k.a. "Free/Busy" a.k.a. "Time Transparency" set to "<strong>Free</strong>" (<a class="srgdev-appt-hs-link" href="https://tools.ietf.org/html/rfc5545#section-3.8.2.7">RFC5545 specs</a> "TRANSP:TRANSPARENT") will be available for booking in the public form. Most recurrence rules are supported. Also see
            <span style="font-style: italic">Require "Appointment" category.</span></p>
        <p class="srgdev-appt-hs-p-h">
            <strong id="srgdev-sec_destcal_nr">Destination Calendar (External mode)</strong> - Booked appointments will be placed in here. In addition to booked appointments, any events in this calendar marked as "<strong>Busy</strong>" will prevent conflicting timeslots in the "Source Calendar" from appearing in the public form. Also see
            <span style="font-style: italic">Require "Appointment" category.</span></p>
        <p class="srgdev-appt-hs-p-h">
            <strong id="srgdev-sec_push_rec_nr">Optimize recurrence (External mode)</strong> - If recurrent events are used in the "Source Calendar" the start (DTSTART) date will be pushed forward once in a while in order to improve performance.
        </p>
        <p class="srgdev-appt-hs-p-h">
            <strong id="srgdev-sec_require_cat_nr">Require "Appointment" category (External mode)</strong> - When this option is set only events with with "Category" set to "<strong>Appointment</strong>" (in English) will be considered.
        </p>
        <p class="srgdev-appt-hs-p-h">
            <strong id="srgdev-sec_auto_fix_nr">Auto-fix "Source" timeslots (External mode)</strong> - Some calendar apps do not allow users to set Free/Busy parameter, resulting in timeslots not being available for booking. If this option is enabled
            <strong>AND the first character of the Description/Notes is "_"</strong> then the Free/Busy will be set to "Free" and "Appointment" category will be added automatically when a new event is created in the "Source" calendar.
        </p>
    </div>
    <h2 class="srgdev-appt-hs-h1">Talk App Integration</h2>
    <p class="srgdev-appt-hs-p">Talk rooms/conversations can be automatically created when an appointment is confirmed.
        <strong>FLOATING timezones are not supported.</strong></p>
    <p class="srgdev-appt-hs-p" style="margin-top: 1em">
        <strong id="srgdev-sec_talkPassword">Guest password</strong> - when this option is selected Talk rooms will be password protected. An autogenerated pseudo random password will be sent to attendees along with a room/conversation link.
    </p>
    <p class="srgdev-appt-hs-p" style="margin-top: 1em">
        <strong id="srgdev-sec_talkEmailTxt">Customize email text</strong> - you can override default email message. There are two tokens available, {{url}} and if you use password protection {{pass}}, they will be replaced with the room's URL and the password if used.
    </p>
    <p class="srgdev-appt-hs-p">For example, this:</p>
    <code class="srgdev-appt-hs-code">
        Please use this link {{url}} to contact me.<br>
        Your password is: {{pass}}
    </code>
    <p class="srgdev-appt-hs-p">will look similar to this:</p>
    <code class="srgdev-appt-hs-code">
        Please use this link
        <span class="srgdev-appt-hs-link">https://my_domain.com/index.php/call/to6d6y4e</span> to contact me.<br>
        Your password is: dj984jjr
    </code>
    <p class="srgdev-appt-hs-p" style="margin-top: 1em;margin-bottom: .75em">
        <strong id="srgdev-sec_talkFF">"Meeting Type" form field</strong> - when this option is enabled, a
        <code class="srgdev-appt-hs-code_short">&lt;select&gt;</code> drop-down similar to the one bellow will be added to the form. If a visitor selects an
        <span style="white-space: nowrap">'In-person'</span> meeting, a Talk room for this appointment will NOT be created.
    </p>
    <style type="text/css">
        #srgdev-help_demo_talk_type:invalid {
            border-color: grey;
            color: #aaa;
        }

        #srgdev-help_demo_talk_type:invalid:hover {
            border-color: #0082C9;
        }
    </style>
    <label for="srgdev-help_demo_talk_type">Meeting Type:</label><br>
    <select required="" id="srgdev-help_demo_talk_type">
        <option value="" disabled="" selected="" hidden="">Select meeting type</option>
        <option style="font-size: medium" value="0">In-person meeting</option>
        <option style="font-size: medium" value="1">Online (audio/video)</option>
    </select>
    <p class="srgdev-appt-hs-p" style="margin-top: 1em;margin-bottom: .75em">
        <strong id="srgdev-sec_talkTypeChange">Type change email text</strong> - if this field is not empty and has two tokens
        <code class="srgdev-appt-hs-code_short">{{link_text}}</code> (can contain any text) and
        <code class="srgdev-appt-hs-code_short">{{new_type}}</code> (MUST be new_type), then this text will be attached to the email and attendees will be able to switch their meeting type simply by clicking the link
        <code class="srgdev-appt-hs-code_short">{{link_text}}</code>.</p>
    <p class="srgdev-appt-hs-p">For example, this:</p>
    <code class="srgdev-appt-hs-code">
        Click {{here}} to change your appointment type to {{new_type}}.
    </code>
    <p class="srgdev-appt-hs-p">will look similar to this:</p>
    <code class="srgdev-appt-hs-code">
        Click <span class="srgdev-appt-hs-link">here</span> to change your appointment type to Online (audio/video).
    </code>
    <p class="srgdev-appt-hs-p">Talk rooms will be created and deleted automatically when a meeting type changes.</p>
    <br>

    <h2 class="srgdev-appt-hs-h1">Redirect to a Custom "All Done" Page After Confirm</h2>
    <p class="srgdev-appt-hs-p">
        <strong id="srgdev-sec_confirmedUrl">Redirect Confirmed URL</strong> - when this URL is specified visitors will be redirected there after they confirm their email address. A base64 encoded
        <code class="srgdev-appt-hs-code_short">d=...</code> query parameter containing a JSON object will be added to the URL. Final URL for
        <span style="font-style: italic; white-space: nowrap">https://your-cool-domain.com/finish.html</span> might look something like this:
        <span style="font-style: italic; white-space: nowrap">https://your-cool-domain.com/finish.html?d=eyJpbml0aWFsQ29uZmlybSI6dHJ1ZSwiaWQiOiI4YjhkOD...</span>
    </p>
    <p class="srgdev-appt-hs-p">
        When the
        <code class="srgdev-appt-hs-code_short">d=...</code> param data is base64 decoded the JSON object might be similar to this:
    <pre><code class="srgdev-appt-hs-code">{
    "initialConfirm": true,
    "id": "8b8d87915a32bc4f48eb14439cd52cef",
    "name": "Bruce Banner",
    "dateTimeString": "Wednesday, April 13, 2022, 10:30 AM EDT"
}</code></pre>
    <strong>"initialConfirm"</strong> - is set to "true" ONLY on initial confirm, if a user reloads the page or clicks email "Confirm" button again this will be set to "false"<br>
    <strong>"id"</strong> - a unique hex encoded 128bit number. (only set when the "Generate ID" option is checked)<br>
    <strong>"name"</strong> - Visitor name from the form. (only set when the "Include Form Data" option is checked)<br>
    <strong>"dateTimeString"</strong> - Localized date-time string in visitor's language (only set when the "Include Form Data" option is checked)
    </p>
    <br>

    <h2 class="srgdev-appt-hs-h1">iFrame/Embedding</h2>
    <div class="srgdev-appt-hs-p">
        1. If the iframe is under a different domain use <strong>occ</strong> to set allowed Frame Ancestor Domain:
        <code style="white-space: pre" class="srgdev-appt-hs-code">php occ config:app:set appointments "emb_afad_YourUserName" --value "your.domain.com"</code>
        2. Email confirm/cancel buttons need to be redirected.<br>Use
        <strong>occ</strong> to set base URL for the host page with
        <strong>a query parameter available at the end of the URL</strong>:
        <code style="white-space: pre" class="srgdev-appt-hs-code">php occ config:app:set appointments "emb_cncf_YourUserName" --value "http(s)://your.domain.com/page_url?some_param_name="</code>

        Example using PHP:
        <pre><code class="srgdev-appt-hs-code">...
    &lt;?php
    $src='PROVIDED_EMBEDDABLE_URL';
    if(isset($_GET['some_param_name'])){
    // Email Confirm/Cancel button was clicked
    $src=substr($src,0,-4).'cncf?d='.urlencode($_GET['some_param_name']);
    }
    echo '&lt;iframe src = "'.$src.'"&gt;&lt;/iframe&gt;';
    ?&gt;
...</code></pre>
        More examples:
        <a class="srgdev-appt-hs-link" target="_blank" href="https://github.com/SergeyMosin/Appointments/tree/master/tests/embedding">https://github.com/SergeyMosin/Appointments/tree/master/tests/embedding</a><br>
        Nextcloud <strong>occ</strong>:
        <a class="srgdev-appt-hs-link" target="_blank" href="https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/occ_command.html">https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/occ_command.html</a><br>
        Frame Ancestors:
        <a class="srgdev-appt-hs-link" target="_blank" href="https://w3c.github.io/webappsec-csp/#directive-frame-ancestors">https://w3c.github.io/webappsec-csp/#directive-frame-ancestors</a><br>
        Additional information can be found here:
        <a class="srgdev-appt-hs-link" target="_blank" href="https://github.com/SergeyMosin/Appointments/issues/191#issuecomment-909210230">https://github.com/SergeyMosin/Appointments/issues/191#issuecomment-909210230</a><br>
        Some more information is here:
        <a class="srgdev-appt-hs-link" target="_blank" href="https://github.com/SergeyMosin/Appointments/issues/268#issue-1067123944">https://github.com/SergeyMosin/Appointments/issues/268#issue-1067123944</a><br>
    </div>

    <h2 id="srgdev-sec_buffers" class="srgdev-appt-hs-h1">Booked and Pending Appointment Buffers</h2>
    <div class="srgdev-appt-hs-p">
        It is possible to block-off a period of time before and after a booked(<strong>and pending</strong>) appointment. This could be useful when some preparation/travel time is required before or cleanup/cool-off time needs to be blocked-off after an appointment.<br><br>
        Buffer blocking logic:<br>
        <img class="quick-start-guide-img" alt="appointment buffers" style="max-width: 700px" src="<?php print_unescaped(image_path('appointments', 'appointment-buffers.jpg')); ?>"/>
    </div>
    <br>

    <h2 id="srgdev-sec_rem_lang" class="srgdev-appt-hs-h1">Default Reminders language</h2>
    <div class="srgdev-appt-hs-p">
        Nextcloud Cron uses
        <code style="padding: .25em" class="srgdev-appt-hs-code_short">default_language</code> setting for internal calls as per here:
        <a class="srgdev-appt-hs-link" href="https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/config_sample_php_parameters.html#user-experience" target="_blank">https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/config_sample_php_parameters.html#user-experience</a> which defaults to English. It might be different than the language selected in your user preferences.
    </div>

    <h2 class="srgdev-appt-hs-h1">Advanced/Notification Extensions</h2>
    <div class="srgdev-appt-hs-p">
        See
        <a class="srgdev-appt-hs-link" href="https://github.com/SergeyMosin/Appointments/issues/26" target="_blank">https://github.com/SergeyMosin/Appointments/issues/26</a><br><br>
        Ensure the <span style="font-style: italic">ext_notify_YourUserName</span> app config variable is set like so:
        <code style="white-space: pre" class="srgdev-appt-hs-code">php occ config:app:set appointments "ext_notify_YourUserName" --value "/absolute/path/to/file.php"</code>
    </div>

    <h2 id="srgdev-sec_contrib_info" class="srgdev-appt-hs-h1">Contributor Features</h2>
    <div class="srgdev-appt-hs-p">
        The following features are only available to users that contributed to the development of this app:
        <ol type="a" style="margin: .5em 0 1em 2em">
            <li>Add more than 3 public pages (up to 10) to your setup</li>
            <li>Use the directory webpage to publicly display multiple links</li>
            <li>Additional Reminder options</li>
            <li>More "Talk" integration options</li>
            <li>And some others</li>
        </ol>
        Contributor features can be unlocked by obtaining a <i>contributor key</i> in any of the following ways:
        <ol type="a" style="margin: .5em 0 1em 2em">
            <li>Contribute any amount to this app development or sponsor a feature over at the
                <a class="srgdev-appt-hs-link" target="_blank" href="https://www.srgdev.com/gh-support/nextcloudapps">Funding page</a>.
            </li>
            <li>Contribute code via a pull request on
                <a class="srgdev-appt-hs-link" target="_blank" href="https://github.com/SergeyMosin/Appointments">GitHub</a>.
            </li>
            <li>If you are a member of the Nexcloud team on transifex.com please
                <a class="srgdev-appt-hs-link" target="_blank" href="https://www.srgdev.com/contact.html#cnt_ancr">contact me</a> directly.
            </li>
            <li>Contact me if none of the above methods works for you.</li>
        </ol>
        Once you receive your key/code you can enter it in the
        <code class="srgdev-appt-hs-code_short">Settings &gt; Contributor Key</code> section.<br>
    </div>
</div>

