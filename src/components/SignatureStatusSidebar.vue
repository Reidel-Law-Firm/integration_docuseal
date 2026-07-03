<template>
	<div v-if="requests.length > 0 || loading" class="docuseal-sidebar">
		<div class="sidebar-header">
			<h3 class="sidebar-title">
				<DocuSealIcon :size="20" />
				{{ t('integration_docuseal', 'Firme DocuSeal') }}
			</h3>
			<NcButton type="tertiary" :aria-label="t('integration_docuseal', 'Aggiorna')" @click="loadRequests">
				&#8635;
			</NcButton>
		</div>

		<NcLoadingIcon v-if="loading" :size="32" />

		<div v-if="requests.length === 0 && !loading" class="empty-state">
			<p>{{ t('integration_docuseal', 'Nessuna richiesta di firma per questo file.') }}</p>
		</div>

		<div v-for="request in requests" :key="request.id" class="signature-request">
			<div class="request-header">
				<span class="request-status" :class="'status-' + request.status">
					{{ statusLabel(request.status) }}
				</span>
				<span class="request-date">{{ formatDate(request.createdAt) }}</span>
			</div>

			<!-- Progress bar -->
			<div v-if="request.submitters && request.submitters.length > 1" class="progress-bar">
				<div
					class="progress-fill"
					:class="'status-bg-' + request.status"
					:style="{ width: progressPercent(request) + '%' }" />
				<span class="progress-text">{{ completedCount(request) }}/{{ request.submitters.length }}</span>
			</div>

			<div class="submitters-list">
				<div v-for="submitter in request.submitters" :key="submitter.id" class="submitter-item">
					<span class="submitter-status-icon" :class="'icon-' + submitter.status">
						<template v-if="submitter.status === 'completed'">&#10003;</template>
						<template v-else-if="submitter.status === 'declined'">&#10007;</template>
						<template v-else-if="submitter.status === 'opened'">&#128065;</template>
						<template v-else>&#9203;</template>
					</span>
					<div class="submitter-info">
						<span class="submitter-name">{{ submitter.name || submitter.email }}</span>
						<span class="submitter-status-text">{{ statusLabel(submitter.status) }}</span>
					</div>
					<!-- Resend button for pending submitters -->
					<NcButton
						v-if="canResend(submitter)"
						type="tertiary"
						:aria-label="t('integration_docuseal', 'Reinvia promemoria')"
						@click="resendReminder(request.id, submitter.id)">
						&#9993;
					</NcButton>
				</div>
			</div>

			<div class="request-actions">
				<!-- Signed document link -->
				<a v-if="request.signedFileId" :href="getFileUrl(request.signedFileId)" class="signed-link">
					&#128196; {{ t('integration_docuseal', 'Apri documento firmato') }}
				</a>

				<!-- Embed signing button -->
				<NcButton
					v-if="hasEmbedOption(request)"
					type="secondary"
					@click="openEmbed(request)">
					{{ t('integration_docuseal', 'Firma ora') }}
				</NcButton>

				<!-- Audit trail button -->
				<NcButton
					v-if="request.submissionId"
					type="tertiary"
					@click="showAuditTrail(request)">
					{{ t('integration_docuseal', 'Audit trail') }}
				</NcButton>

				<!-- Cancel button -->
				<NcButton
					v-if="canCancel(request)"
					type="error"
					@click="cancelRequest(request)">
					{{ t('integration_docuseal', 'Annulla') }}
				</NcButton>
			</div>
		</div>

		<!-- Embed modal -->
		<NcModal v-if="showEmbed" size="large" @close="showEmbed = false">
			<div class="embed-container">
				<h3>{{ t('integration_docuseal', 'Firma il documento') }}</h3>
				<iframe
					:src="currentEmbedUrl"
					class="embed-frame"
					frameborder="0"
					allow="camera" />
			</div>
		</NcModal>

		<!-- Audit trail modal -->
		<NcModal v-if="showAudit" @close="showAudit = false">
			<div class="audit-container">
				<h3>{{ t('integration_docuseal', 'Audit Trail') }}</h3>
				<div v-if="auditData">
					<div v-for="sub in auditData.submitters" :key="sub.email" class="audit-entry">
						<strong>{{ sub.name || sub.email }}</strong>
						<div class="audit-timeline">
							<div v-if="sub.sentAt" class="timeline-item">
								&#128228; {{ t('integration_docuseal', 'Inviato:') }} {{ formatDateTime(sub.sentAt) }}
							</div>
							<div v-if="sub.openedAt" class="timeline-item">
								&#128065; {{ t('integration_docuseal', 'Aperto:') }} {{ formatDateTime(sub.openedAt) }}
							</div>
							<div v-if="sub.completedAt" class="timeline-item completed">
								&#10003; {{ t('integration_docuseal', 'Firmato:') }} {{ formatDateTime(sub.completedAt) }}
							</div>
							<div v-if="sub.declinedAt" class="timeline-item declined">
								&#10007; {{ t('integration_docuseal', 'Rifiutato:') }} {{ formatDateTime(sub.declinedAt) }}
							</div>
						</div>
					</div>
					<div v-if="auditData.auditLogUrl" class="audit-log-link">
						<a :href="auditData.auditLogUrl" target="_blank" rel="noopener">
							&#128196; {{ t('integration_docuseal', 'Scarica log di audit completo (PDF)') }}
						</a>
					</div>
				</div>
				<NcLoadingIcon v-else :size="32" />
			</div>
		</NcModal>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import { showSuccess, showError } from '@nextcloud/dialogs'
import axios from '@nextcloud/axios'
import DocuSealIcon from '../icons/DocuSealIcon.vue'

export default {
	name: 'SignatureStatusSidebar',

	components: {
		NcButton,
		NcModal,
		NcLoadingIcon,
		DocuSealIcon,
	},

	props: {
		fileId: {
			type: Number,
			required: true,
		},
	},

	data() {
		return {
			requests: [],
			loading: false,
			showEmbed: false,
			currentEmbedUrl: null,
			showAudit: false,
			auditData: null,
		}
	},

	watch: {
		fileId: {
			immediate: true,
			handler() {
				this.loadRequests()
			},
		},
	},

	methods: {
		t,
		async loadRequests() {
			this.loading = true
			try {
				const url = generateUrl('/apps/integration_docuseal/requests/file/{fileId}', { fileId: this.fileId })
				const response = await axios.get(url)
				this.requests = response.data || []
			} catch (e) {
				console.error('Error loading signature requests:', e)
			}
			this.loading = false
		},

		statusLabel(status) {
			const labels = {
				pending: t('integration_docuseal', 'In attesa'),
				sent: t('integration_docuseal', 'Inviato'),
				opened: t('integration_docuseal', 'Visualizzato'),
				completed: t('integration_docuseal', 'Completato'),
				declined: t('integration_docuseal', 'Rifiutato'),
				expired: t('integration_docuseal', 'Scaduto'),
				cancelled: t('integration_docuseal', 'Annullato'),
			}
			return labels[status] || status
		},

		formatDate(timestamp) {
			if (!timestamp) return ''
			return new Date(timestamp * 1000).toLocaleDateString('it-IT', {
				day: '2-digit',
				month: '2-digit',
				year: 'numeric',
				hour: '2-digit',
				minute: '2-digit',
			})
		},

		formatDateTime(isoString) {
			if (!isoString) return ''
			return new Date(isoString).toLocaleString('it-IT', {
				day: '2-digit',
				month: '2-digit',
				year: 'numeric',
				hour: '2-digit',
				minute: '2-digit',
				second: '2-digit',
			})
		},

		getFileUrl(fileId) {
			return generateUrl('/f/' + fileId)
		},

		completedCount(request) {
			return (request.submitters || []).filter(s => s.status === 'completed').length
		},

		progressPercent(request) {
			const total = (request.submitters || []).length
			if (total === 0) return 0
			return Math.round((this.completedCount(request) / total) * 100)
		},

		canResend(submitter) {
			return submitter.status !== 'completed' && submitter.status !== 'declined'
		},

		canCancel(request) {
			return request.status !== 'completed'
				&& request.status !== 'declined'
				&& request.status !== 'expired'
				&& request.status !== 'cancelled'
		},

		hasEmbedOption(request) {
			return this.canCancel(request)
				&& request.submitters?.some(s => s.embedSrc && s.status !== 'completed')
		},

		async resendReminder(requestId, submitterId) {
			try {
				const url = generateUrl('/apps/integration_docuseal/requests/{id}/resend/{submitterId}', {
					id: requestId,
					submitterId,
				})
				await axios.post(url)
				showSuccess(t('integration_docuseal', 'Promemoria inviato con successo'))
			} catch (e) {
				showError(t('integration_docuseal', 'Errore nell\'invio del promemoria'))
			}
		},

		async cancelRequest(request) {
			if (!confirm(t('integration_docuseal', 'Sei sicuro di voler annullare questa richiesta di firma?'))) {
				return
			}
			try {
				const url = generateUrl('/apps/integration_docuseal/requests/{id}/cancel', { id: request.id })
				await axios.post(url)
				showSuccess(t('integration_docuseal', 'Richiesta annullata'))
				await this.loadRequests()
			} catch (e) {
				showError(t('integration_docuseal', 'Errore nell\'annullamento'))
			}
		},

		async openEmbed(request) {
			try {
				const url = generateUrl('/apps/integration_docuseal/embed/{requestId}', { requestId: request.id })
				const response = await axios.get(url)
				if (response.data.embedSrc) {
					this.currentEmbedUrl = response.data.embedSrc
					this.showEmbed = true
				}
			} catch (e) {
				showError(t('integration_docuseal', 'Nessun URL di firma disponibile'))
			}
		},

		async showAuditTrail(request) {
			this.auditData = null
			this.showAudit = true
			try {
				const url = generateUrl('/apps/integration_docuseal/requests/{id}/audit', { id: request.id })
				const response = await axios.get(url)
				this.auditData = response.data
			} catch (e) {
				showError(t('integration_docuseal', 'Errore nel caricamento dell\'audit trail'))
				this.showAudit = false
			}
		},
	},
}
</script>

<style scoped>
.docuseal-sidebar {
	padding: 8px 0;
}

.sidebar-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 12px;
}

.sidebar-title {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 1.1em;
	margin: 0;
}

.empty-state {
	text-align: center;
	color: var(--color-text-maxcontrast);
	padding: 20px;
}

.signature-request {
	padding: 12px;
	margin-bottom: 8px;
	background: var(--color-background-dark);
	border-radius: var(--border-radius);
}

.request-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 8px;
}

.request-status {
	font-weight: bold;
	font-size: 0.85em;
	padding: 2px 8px;
	border-radius: 10px;
}

.status-pending, .status-sent { background: var(--color-warning); color: white; }
.status-opened { background: var(--color-primary); color: white; }
.status-completed { background: var(--color-success); color: white; }
.status-declined { background: var(--color-error); color: white; }
.status-expired, .status-cancelled { background: var(--color-text-maxcontrast); color: white; }

.request-date {
	color: var(--color-text-maxcontrast);
	font-size: 0.85em;
}

/* Progress bar */
.progress-bar {
	position: relative;
	height: 6px;
	background: var(--color-border);
	border-radius: 3px;
	margin: 8px 0;
	overflow: hidden;
}

.progress-fill {
	height: 100%;
	border-radius: 3px;
	transition: width 0.3s ease;
}

.status-bg-pending, .status-bg-sent { background: var(--color-warning); }
.status-bg-completed { background: var(--color-success); }
.status-bg-declined { background: var(--color-error); }

.progress-text {
	position: absolute;
	right: 0;
	top: -16px;
	font-size: 0.75em;
	color: var(--color-text-maxcontrast);
}

.submitters-list {
	margin: 8px 0;
}

.submitter-item {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 4px 0;
}

.submitter-status-icon {
	width: 20px;
	text-align: center;
	flex-shrink: 0;
}

.icon-completed { color: var(--color-success); }
.icon-declined { color: var(--color-error); }
.icon-opened { color: var(--color-primary); }

.submitter-info {
	flex: 1;
	min-width: 0;
}

.submitter-name {
	display: block;
	font-size: 0.9em;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.submitter-status-text {
	font-size: 0.8em;
	color: var(--color-text-maxcontrast);
}

.request-actions {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin-top: 8px;
	padding-top: 8px;
	border-top: 1px solid var(--color-border);
}

.signed-link {
	color: var(--color-primary);
	text-decoration: none;
	font-size: 0.9em;
}

.signed-link:hover {
	text-decoration: underline;
}

.embed-container, .audit-container {
	padding: 20px;
}

.embed-frame {
	width: 100%;
	height: 600px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
}

.audit-entry {
	margin-bottom: 16px;
	padding-bottom: 16px;
	border-bottom: 1px solid var(--color-border);
}

.audit-entry:last-child {
	border-bottom: none;
}

.audit-timeline {
	margin-top: 8px;
	padding-left: 8px;
	border-left: 2px solid var(--color-border);
}

.timeline-item {
	padding: 4px 0 4px 8px;
	font-size: 0.9em;
}

.timeline-item.completed {
	color: var(--color-success);
}

.timeline-item.declined {
	color: var(--color-error);
}

.audit-log-link {
	margin-top: 16px;
}

.audit-log-link a {
	color: var(--color-primary);
	text-decoration: none;
}

.audit-log-link a:hover {
	text-decoration: underline;
}
</style>
