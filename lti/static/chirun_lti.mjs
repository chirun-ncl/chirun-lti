export function websocket(path) {
    const ws_scheme = window.location.protocol == "https:" ? "wss" : "ws";
    const ws_url = ws_scheme + '://' + window.location.host + '/ws' + path;
    return new WebSocket(ws_url);
}
