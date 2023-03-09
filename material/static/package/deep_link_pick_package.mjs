import {websocket} from '../chirun_lti.mjs'

const package_elements = Array.from(document.querySelectorAll('.package'));

const packages = Object.fromEntries(package_elements.map(p => [p.dataset.packageUid, p]))

function packages_websocket() {
    const ws = websocket('/material/deep-link-websocket');

    const message_handlers = {
        'build_status': data => {
            const pkg = packages[data.package];
            if(!pkg) {
                console.error(`Got a message for an unrecognised package ${data.package}`);
            }
            pkg.dataset.buildStatus = data.message.status;
        }
    };

    ws.addEventListener('message', ({data}) => {
        data = JSON.parse(data);
        console.log(data);
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
            packages: Object.keys(packages)
        }));
    });
}

packages_websocket();
