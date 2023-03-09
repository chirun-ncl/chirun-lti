import {websocket} from '../chirun_lti.mjs';

const status_json = JSON.parse(document.getElementById('status_json').textContent);

const output = window.output = {stdout: '', stderr: ''};

function progress_websocket() {
    const ws_url = `${window.location.pathname}/progress`

    const ws = websocket(ws_url);

    const stdout_display = document.getElementById('stdout');
    const stderr_display = document.getElementById('stderr');

    let received_anything = false;

    const message_handlers = {
        'cached_stdout': ({cached_stdout}) => {
            stdout_display.textContent = cached_stdout;
        },
        'stdout_bytes': ({bytes}) => {
            if(!received_anything) {
                stdout_display.innerHTML = '';
            }
            const str = atob(bytes);
            output.stdout += str;
            stdout_display.textContent = output.stdout;
        },
        'stderr_bytes': ({bytes}) => {
            if(!received_anything) {
                stderr_display.innerHTML = '';
            }
            const str = atob(bytes);
            output.stderr += str;
            stderr_display.textContent = output.stderr;
        },
        'status_change': ({status, end_time, time_taken}) => {
            document.body.classList.remove('build-status-building');
            document.body.classList.add(`build-status-${status}`);
            for(let e of document.querySelectorAll('.end-time')) {
                e.textContent = end_time;
            }
            document.getElementById('time-taken').textContent = time_taken;
        },
    };

    ws.onmessage = ({data}) => {
        data = JSON.parse(data);
        console.log(data);
        const {type} = data;
        if(type in message_handlers) {
            message_handlers[type](data);
        } else {
            console.error(`Unknown message type ${type}`);
        }
        received_anything = true;
    }
}

if(status_json.status == 'building') {
    progress_websocket();
}
