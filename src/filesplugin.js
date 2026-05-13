import { registerFileAction, Permission } from '@nextcloud/files'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import { showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import { createApp } from 'vue'

const SIGNABLE_MIMES = new Set([
	'application/pdf',
	'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	'application/msword',
	'image/png',
	'image/jpeg',
	'image/jpg',
])

let modalApp = null
let DocuSealModalComponent = null
let docuSealConnected = null

async function checkConnection() {
	if (docuSealConnected !== null) {
		return docuSealConnected
	}
	try {
		const url = generateUrl('/apps/integration_docuseal/info')
		const response = await axios.get(url)
		docuSealConnected = response.data.connected === true
	} catch (e) {
		docuSealConnected = false
	}
	return docuSealConnected
}

async function getModal() {
	if (DocuSealModalComponent === null) {
		const { default: DocuSealModal } = await import('./components/DocuSealModal.vue')
		DocuSealModalComponent = DocuSealModal
	}
	return DocuSealModalComponent
}

async function showSignModal(fileInfo) {
	const Component = await getModal()

	if (modalApp) {
		modalApp.unmount()
		const oldContainer = document.getElementById('docuseal-modal-container')
		if (oldContainer) {
			oldContainer.remove()
		}
	}

	const container = document.createElement('div')
	container.id = 'docuseal-modal-container'
	document.body.appendChild(container)

	modalApp = createApp(Component, {
		fileId: fileInfo.fileid,
		fileName: fileInfo.basename,
		fileMime: fileInfo.mime,
		onClose: () => {
			if (modalApp) {
				modalApp.unmount()
				modalApp = null
			}
			container.remove()
		},
	})
	modalApp.mount(container)
}

const DOCUSEAL_SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1511.63 1304.65"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="30"><path d="M1346.2,272.14v727.53c0,19.42-15.74,35.16-35.16,35.16H68.35c-19.42,0-35.16-15.74-35.16-35.16V204.18c0-19.42,15.74-35.16,35.16-35.16h1182.15l-328.67,328.67,99.41,99.41,324.96-324.96Z"/><line x1="1346.2" y1="928.57" x2="33.18" y2="928.57"/><polyline points="504.38 1034.83 504.38 1200.12 875.01 1200.12 875.01 1034.83"/><polyline points="856.3 1200.12 961.64 1200.12 961.64 1274.9 417.75 1274.9 417.75 1200.12 523.08 1200.12"/><polygon points="1021.24 597.1 1008.16 610.18 848.58 670.35 908.75 510.77 921.83 497.69 1021.24 597.1"/><path d="M1346.2,272.14l122.39-122.39c13.73-13.73,20.59-31.71,20.59-49.71s-6.86-35.98-20.59-49.7c-27.45-27.45-71.96-27.45-99.41,0l-118.68,118.68"/><line x1="1021.24" y1="597.1" x2="921.83" y2="497.69"/></g></svg>'

function nodeMime(node) {
	return node?.mime || node?.mimetype || node?.attributes?.mime || ''
}

// @nextcloud/files v4 takes a plain IFileAction object; the callbacks receive
// an ActionContext / ActionContextSingle ({ nodes, view, folder, contents }).
registerFileAction({
	id: 'docuseal-sign',
	displayName: () => t('integration_docuseal', 'Richiedi firma con DocuSeal'),
	iconSvgInline: () => DOCUSEAL_SVG,
	enabled({ nodes }) {
		if (!Array.isArray(nodes) || nodes.length !== 1) {
			return false
		}
		const node = nodes[0]
		if (!SIGNABLE_MIMES.has(nodeMime(node))) {
			return false
		}
		if (typeof node.permissions === 'number' && !(node.permissions & Permission.READ)) {
			return false
		}
		return true
	},
	async exec({ nodes }) {
		const node = nodes[0]
		const connected = await checkConnection()
		if (!connected) {
			showError(t('integration_docuseal', 'DocuSeal non è configurato. Contatta l\'amministratore.'))
			return null
		}
		showSignModal({
			fileid: node.fileid ?? node.attributes?.fileid ?? node.id,
			basename: node.basename ?? node.attributes?.basename ?? node.name,
			mime: nodeMime(node),
		})
		return null
	},
	order: 90,
})
