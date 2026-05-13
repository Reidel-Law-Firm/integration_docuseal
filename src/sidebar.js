import { createApp } from 'vue'
import { translate as t } from '@nextcloud/l10n'
import SignatureStatusSidebar from './components/SignatureStatusSidebar.vue'

const SIGNABLE_MIMES = new Set([
	'application/pdf',
	'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	'application/msword',
	'image/png',
	'image/jpeg',
	'image/jpg',
])

function registerSidebarTab() {
	if (!window.OCA?.Files?.Sidebar?.registerTab) {
		return false
	}

	const sidebarTab = new OCA.Files.Sidebar.Tab({
		id: 'docuseal-signatures',
		name: t('integration_docuseal', 'Firme'),
		iconSvg: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1511.63 1304.65"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="30"><path d="M1346.2,272.14v727.53c0,19.42-15.74,35.16-35.16,35.16H68.35c-19.42,0-35.16-15.74-35.16-35.16V204.18c0-19.42,15.74-35.16,35.16-35.16h1182.15l-328.67,328.67,99.41,99.41,324.96-324.96Z"/><line x1="1346.2" y1="928.57" x2="33.18" y2="928.57"/><polyline points="504.38 1034.83 504.38 1200.12 875.01 1200.12 875.01 1034.83"/><polyline points="856.3 1200.12 961.64 1200.12 961.64 1274.9 417.75 1274.9 417.75 1200.12 523.08 1200.12"/><polygon points="1021.24 597.1 1008.16 610.18 848.58 670.35 908.75 510.77 921.83 497.69 1021.24 597.1"/><path d="M1346.2,272.14l122.39-122.39c13.73-13.73,20.59-31.71,20.59-49.71s-6.86-35.98-20.59-49.7c-27.45-27.45-71.96-27.45-99.41,0l-118.68,118.68"/><line x1="1021.24" y1="597.1" x2="921.83" y2="497.69"/></g></svg>',

		async mount(el, fileInfo) {
			if (this._app) {
				this._app.unmount()
			}
			this._app = createApp(SignatureStatusSidebar, {
				fileId: fileInfo.id,
			})
			this._app.mount(el)
		},

		update(fileInfo) {
			// SignatureStatusSidebar reloads whenever the fileId prop changes;
			// we let it observe the change through Vue's reactivity instead of
			// remounting the whole app.
			if (this._app && this._app._instance) {
				this._app._instance.props.fileId = fileInfo.id
			}
		},

		destroy() {
			if (this._app) {
				this._app.unmount()
				this._app = null
			}
		},

		enabled(fileInfo) {
			const mime = fileInfo?.mimetype || fileInfo?.mime
			return typeof mime === 'string' && SIGNABLE_MIMES.has(mime)
		},
	})

	OCA.Files.Sidebar.registerTab(sidebarTab)
	return true
}

// The Files sidebar API may not be ready at the moment the script loads
// (e.g. when entering Files via the URL bar). Retry on the next tick if
// it isn't available yet.
if (!registerSidebarTab()) {
	document.addEventListener('DOMContentLoaded', () => {
		if (!registerSidebarTab()) {
			window.setTimeout(registerSidebarTab, 500)
		}
	})
}
