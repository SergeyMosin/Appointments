import axios from "@nextcloud/axios";

const errPrefix = '<br><br><span style="width: 1em;display: inline-block;"></span>Error occurred: '

function settingsDump() {
    return axios.get('settings_dump')
        .then(response => {
            if (response.status === 200) {
                return response.data
            } else {
                return errPrefix + 'bad status (' + response.status + ')'
            }
        })
        .catch((error) => {
            console.error(error)
            return errPrefix + 'check console.'
        })
}

function getRawCalData(calInfo) {

    return axios.post('raw_calendar_data', {
        cal_info: JSON.stringify(calInfo)
    })
        .then(response => {
            if (response.status === 200) {
                return response.data
            } else {
                return errPrefix + 'bad status (' + response.status + ')'
            }
        })
        .catch((error) => {
            console.error(error)
            return errPrefix + 'check console.'
        })
}

function syncRemoteNow(calInfo) {

    return axios.post('sync_remote_now', {
        cal_info: JSON.stringify(calInfo)
    })
        .then(response => {
            if (response.status === 200) {
                return response.data
            } else {
                return errPrefix + 'bad status (' + response.status + ')'
            }
        })
        .catch((error) => {
            console.error(error)
            return errPrefix + 'check console.'
        })
}

export {settingsDump, getRawCalData, syncRemoteNow}