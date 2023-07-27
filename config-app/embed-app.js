class ChirunPackageConfigElement extends HTMLElement {
    constructor() {
        super();

        const files = JSON.parse(document.getElementById(this.getAttribute('files')).textContent);
        const config = JSON.parse(document.getElementById(this.getAttribute('config')).textContent);
        const media_root = this.getAttribute('media-root');

        var app = Elm.ChirunPackageConfig.init({ node: this, flags: {files, config, media_root} })
    }
}
customElements.define('chirun-package-config', ChirunPackageConfigElement);

