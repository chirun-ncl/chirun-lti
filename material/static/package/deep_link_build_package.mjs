import {websocket} from '../chirun_lti.mjs'

const package_uid = JSON.parse(document.getElementById('package_uid').textContent);

function packages_websocket() {
    const ws = websocket('/material/deep-link-websocket');

    const message_handlers = {
        'build_status': data => {
            if(data.package != package_uid) {
                console.error(`Got a message for an unrecognised package ${data.package}`);
            }

            document.body.dataset.buildStatus = data.message.status;
        }
    };

    ws.addEventListener('message', ({data}) => {
        data = JSON.parse(data);
        const handler = message_handlers[data.type];
        if(!handler) {
            console.error(`Got an unrecognised message type: ${data.type}`);
            return;
        }
        handler(data);
    });

    ws.addEventListener('open', () => {
        ws.send(JSON.stringify({
            'type': 'subscribe-to-packages',
            packages: [package_uid]
        }));
    });
}

packages_websocket();
