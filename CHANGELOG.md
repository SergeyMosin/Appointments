## 1.8.6 - 2021-03-01
### Fix
- Infinite loop in 'external mode' issue #184

## 1.8.5 - 2021-02-28
### Fix
- Daylight savings and start_date detection in 'weekly template' mode

## 1.8.4 - 2021-02-23
### Fix
- Talk 10.1.* integration - issue #179

## 1.8.3 - 2021-02-14
### Change
- Add recurrence blocking in external mode - issue #168
- Add more 'leadtime' options - issue #135

## 1.8.2 - 2021-02-07
### Fixed
- Past dates are shown in week template
- Access array offset on value of type null for additional pages

## 1.8.1 - 2021-02-01
### Fixed
- Upgrade failed issue #173 (POSTGRESQL ?)

## 1.8.0 - 2021-01-30
### Added
- Template Mode
### Changed
- Appointment slots can overlap now in the editor
- Use own DB table for settings, instead of 'oc_preferences'
### Fixed
- Modals on wide screens
- Doctrine3 type strings
- 'null' array in extra fields


## 1.7.15 - 2020-12-21
### Fixed 
- define 'more_html' at the start

## 1.7.14 - 2020-11-20
### Changed
- translations

## 1.7.13 - 2020-11-20
### Added
- Extra input field option - issue #24
### Changed 
- Removed "floating" timezones support

## 1.7.12 - 2020-10-31
### Changed 
- Change triple dot to ellipsis PR #144
### Fixed
"Uninitialized string offset: 0 at BackendUtils.php#665" possibly related to issue #149

## 1.7.11 - 2020-10-17
### Added
- display option for 8 and 12 weeks pull #138

## 1.7.10 - 2020-10-11
- translation release - issue# 142 ?

## 1.7.9 - 2020-10-11
### Added
- Meeting type change capabilities - issue# 140

## 1.7.8 - 2020-10-10
### Fixed
- Issue #139, NC20 talk integration.
- Issue #141, NC20 email template error.

## 1.7.7 - 2020-10-03
### Fixed
- Issue #136, wrong location when additional pages are used.

## 1.7.6 - 2020-09-22
### Fixed
- NC19 email template regression

## 1.7.5 - 2020-09-21
### Added
- "Meeting Type" form field for Talk integration
### Changed
- Use NC18 email template even on NC19
### Fixed
- CSS for dark themes


## 1.7.4 - 2020-09-20
### Added
- Talk App integration

## 1.7.3 - 2020-09-11
### Fixed
- issue #120

## 1.7.2 - 2020-09-06
### Fixed
- issue #123 

## 1.7.1 - 2020-09-05
### Fixed
- issue #124 

## 1.7.0 - 2020-09-05
### Added
- Multi-page support
### Changed
- Email confirm/cancel buttons depend on an attendee's PARTSTAT parameter
### Fixed
- Use "mailto" scheme in .ics attachments instead of "acct" 

## 1.6.8 - 2020-08-11
### Fixed
- issue #116

## 1.6.7 - 2020-07-29
### Added
- "Show end time" option
- "Show timezone" option
- spinner to "Book now" button
### Fixed
- pending appointments are not cancelled in "simple mode" with dual calendars

## 1.6.6 - 2020-07-26
### Added
- swipe and mobile style to the public page
### Changed
- moved calendar selectors to "Calendars" section
- internal code cleanup and optimization
### Fixed
- issue #112 (nginx)

## 1.6.5 - 2020-07-20
### Fixed
- finalize issue #111

## 1.6.4 - 2020-07-20
### Fixed
- Lodash security update
- issue #111

## 1.6.3 - 2020-07-14
### Fixed
- Stale calendar info

## 1.6.2 - 2020-07-14
### Added
- "Auto-fix" option for "External Mode"
- Ability to show appointment's title in the form
- Minimum prep/lead time

## 1.6.1 - 2020-07-06
### Changed
- "Appointment" category is optional in "External Mode"

## 1.6.0 - 2020-07-04
### Added
- External Mode to timeslot management
- Cancellation link to confirmation emails
### Changed
- "Sunday" is red now
### Fixed
- Hash table cleanup when deleting old appointments.

## 1.5.2 - 2020-06-15
### Added
- Text to "Public Page URL" dialog buttons
- Setup cancellation link for confirmation emails (awaiting translations)

## 1.5.1 - 2020-06-10
### Added
- css for XL screens
- Saturday to "add appointments" grid

## 1.5.0 - 2020-06-03
### Changed
- Grouped calendar options into 'Manage Appointment Slots'
- Moved 'Attendee Cancels' options to 'Manage Appointment Slots &gt; Advanced Options'
- Moved 'Copy public link' to 'Public Page [...]' menu
### Added
- Options for additional email text
- Added 'Remove Old Appointments' option
- Iframes support

## 1.4.16 - 2020-05-20
### Added
- Option to add 'robots noindex' meta tag
### Fixed
- Preview for "skip email verification step" option

## 1.4.15 - 2020-05-13
### Fixed
- frontend error check fails when date empty

## 1.4.14 - 2020-05-07
### Added
- Option to skip email validation test

## 1.4.13 - 2020-05-07
### Test
- Install test

## 1.4.12 - 2020-05-07
### Changed
- Display all existing appointments in the schedule generator
### Fixed
- Shared calendars support (must have edit permission)

## 1.4.11 - 2020-05-05
### Added
- Translations

## 1.4.10 - 2020-05-01
### Added
- Translations push

## 1.4.9 - 2020-04-29
### Added
- Option to hide phone number input
### Fixed
- Confirm page error (regression from v1.4.8)

## 1.4.8 - 2020-04-28
### Changed
- Timezone info in the help section
### Added
- Longer hours in the 'Schedule Generator' issues #62
### Fixed
- Error reporting when 'Schedule Generator' fails
- Do not re-run old update-hook.

## 1.4.6 - 2020-04-24
### Changed
- Free/Busy - issue #57
### Fixed
- translations 

## 1.4.5 - 2020-04-23
### Fixed
- Makefile and path to timezones.json

## 1.4.4 - 2020-04-23
### Fixed
- Minor fixes

## 1.4.3 - 2020-04-23
### Fixed
- issue #61: emails not sent on postgres instances
### Changed
- Non 'floating' times are "casted" to visitors local times if in different timezone

## 1.4.2 - 2020-04-22
### Fixed
- issue 51: can't set calendar on some instances

## 1.4.1 - 2020-04-21
### Fixed
- Safari wrong time issue 59
### Changed
- Make default email default issues 52 53

## 1.4.0 - 2020-04-20
### Changed
- Moved "User/Organization" settings to navigation area
- "Add Appointments" -> "Add Appointment Slots"
- "Help/Tutorial" is a toggle now
### Added
- Timezone abbreviation for non "floating" time appointments in emails
- Options for sending "update/cancel" emails to attendees when appointments are updated or deleted via the calendar or other external app
- Appointment booked/confirmed/canceled notifications for organizer
- Reset option for cancelled appointments.
- "appointments.use.default.email" config.php option for servers that do not provide email addresses for all users
- Advanced public page customization options
- BackendManager class and IBackendConnector interface
- DavListener class to send email on "updateCalendarObject" and "deleteCalendarObject" events
- "appointments_hash" table to keep track of active appointments
### Fixed
- "Copy public link" button for older versions of Safari
- Minor style tweaks

## 1.1.10 - 2020-03-20
### Fixed
- Webpath for custom install directories
### Changed
- GDPR checkbox style

## 1.1.9 - 2020-03-18
### Fixed
- Fetch public link from server if empty

## 1.1.8 - 2020-03-18
### Fixed
- Minor stability issues

## 1.1.7 - 2020-03-17
### Added
- GDPR Compliance
### Fixed
- NC16 Compatibility

## 1.1.6 - 2020-03-16
### Fixed
- Issue #29
- acorn security alert

## 1.1.5 - 2020-03-11
### Fixed
- Mixed timezones in date/time picker

## 1.1.4 - 2020-03-10
### Added
- Timezone support
- Custom form title option
- Add phone number to .ics files

## 1.1.3 - 2020-03-09
### Fixed
- Max range verified

## 1.1.2 - 2020-03-09
### Fixed
- Daylight savings grid drift
- Extra empty week
### Added
- Empty days text
### Changed
- Max range is 5 weeks now
- Help/Tutorial

## 1.1.1 - 2020-03-06
### Fixed
- Public page settings propagation

## 1.1.0 - 2020-03-06
### Added
- Address in the appointment location filed
- .ics file attachment option
### Changed
- New public page date/time picker UI
- New appointment generator UI
### Fixed
- Daylight savings 1 hour shift

## 1.0.9 - 2020-02-27
### Added
- L10N compatibility

## 1.0.8 - 2020-02-26
### Fixed
- Issue #9 (PostgreSQL related)
### Added
- L10N compatibility (partial)

## 1.0.7 - 2020-02-26
### Fixed
- Mostly style tweaks

## 1.0.6 - 2020-02-25
### Fixed
- PHP 5 token encode error 

## 1.0.5 - 2020-02-25
### Fixed
- Dark theme
- Persist tokens after update 

## 1.0.4 - 2020-02-25
### Fixed
- Token verify bug

## 1.0.3 - 2020-02-25
### Fixed
- Multi-user access
- Token verification

## 1.0.1 - 2020-02-24
### Added
- Initial Release
