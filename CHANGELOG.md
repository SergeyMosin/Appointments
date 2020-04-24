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
