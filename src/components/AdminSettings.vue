<template>
	<div id="docuseal-admin-settings" class="section">
		<h2>
			<DocuSealIcon :size="24" class="icon" />
			DocuSeal Integration
		</h2>
		<p class="settings-hint">
			{{ t('integration_docuseal', 'Configura la connessione al tuo server DocuSeal Enterprise per abilitare la firma elettronica dei documenti.') }}
		</p>

		<div class="field">
			<label for="docuseal-server-url">
				{{ t('integration_docuseal', 'URL del server DocuSeal') }}
			</label>
			<input
				id="docuseal-server-url"
				v-model="state.server_url"
				type="url"
				:placeholder="t('integration_docuseal', 'https://docuseal.example.com')"
				@input="onInput">
		</div>

		<div class="field">
			<label for="docuseal-api-key">
				{{ t('integration_docuseal', 'API Key') }}
			</label>
			<input
				id="docuseal-api-key"
				v-model="apiKey"
				type="password"
				:placeholder="state.api_key_set ? t('integration_docuseal', '● ● ● ● ● (chiave già configurata)') : t('integration_docuseal', 'Inserisci la API Key')"
				@input="onInput">
			<p class="hint">
				{{ t('integration_docuseal', 'Puoi trovare la API Key nelle impostazioni del tuo server DocuSeal: Impostazioni → API') }}
			</p>
		</div>

		<div class="field">
			<label for="docuseal-webhook-secret">
				{{ t('integration_docuseal', 'Webhook Secret (opzionale)') }}
			</label>
			<input
				id="docuseal-webhook-secret"
				v-model="webhookSecret"
				type="password"
				:placeholder="state.webhook_secret_set ? t('integration_docuseal', '● ● ● ● ● (secret già configurato)') : t('integration_docuseal', 'Inserisci un secret per validare i webhook')"
				@input="onInput">
			<p class="hint">
				{{ t('integration_docuseal', 'Se configurato, i webhook verranno validati con HMAC-SHA256. Usa lo stesso secret nella configurazione webhook di DocuSeal.') }}
			</p>
		</div>

		<div class="field">
			<label>
				{{ t('integration_docuseal', 'URL Webhook (configura in DocuSeal)') }}
			</label>
			<div class="webhook-url-container">
				<input
					:value="webhookUrl"
					type="text"
					readonly
					class="webhook-url"
					@click="copyWebhookUrl">
				<NcButton type="tertiary" @click="copyWebhookUrl">
					{{ t('integration_docuseal', 'Copia') }}
				</NcButton>
			</div>
			<p class="hint">
				{{ t('integration_docuseal', 'Copia questo URL e configuralo nelle impostazioni webhook di DocuSeal: Impostazioni → Webhook') }}
			</p>
		</div>

		<div class="actions">
			<NcButton
				type="primary"
				:disabled="saving"
				@click="saveConfig">
				{{ saving ? t('integration_docuseal', 'Salvataggio...') : t('integration_docuseal', 'Salva') }}
			</NcButton>
			<NcButton
				v-if="state.api_key_set"
				type="error"
				@click="resetConfig">
				{{ t('integration_docuseal', 'Disconnetti') }}
			</NcButton>
		</div>

		<div v-if="connectionStatus !== null" class="connection-status" :class="connectionStatus.success ? 'success' : 'error'">
			<span v-if="connectionStatus.success">&#10003; {{ t('integration_docuseal', 'Connessione riuscita al server DocuSeal') }}</span>
			<span v-else>&#10007; {{ t('integration_docuseal', 'Errore di connessione:') }} {{ connectionStatus.message }}</span>
		</div>

		<!-- Supported file types info -->
		<div class="info-section">
			<h3>{{ t('integration_docuseal', 'Tipi di file supportati') }}</h3>
			<ul>
				<li>PDF (.pdf)</li>
				<li>Microsoft Word (.docx, .doc)</li>
				<li>{{ t('integration_docuseal', 'Immagini') }} (.png, .jpg, .jpeg)</li>
			</ul>
		</div>

		<div class="info-section">
			<h3>{{ t('integration_docuseal', 'Funzionalità') }}</h3>
			<ul>
				<li>{{ t('integration_docuseal', 'Invio diretto file per la firma') }}</li>
				<li>{{ t('integration_docuseal', 'Firma tramite template DocuSeal') }}</li>
				<li>{{ t('integration_docuseal', 'Firma embedded (in-app) o via email') }}</li>
				<li>{{ t('integration_docuseal', 'Tracking stato firme nella sidebar dei file') }}</li>
				<li>{{ t('integration_docuseal', 'Download automatico documenti firmati') }}</li>
				<li>{{ t('integration_docuseal', 'Notifiche in tempo reale') }}</li>
				<li>{{ t('integration_docuseal', 'Ricerca firme dalla barra di ricerca') }}</li>
				<li>{{ t('integration_docuseal', 'Widget Dashboard') }}</li>
			</ul>
		</div>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import { loadState } from '@nextcloud/initial-state'
import { showSuccess, showError } from '@nextcloud/dialogs'
import DocuSealIcon from '../icons/DocuSealIcon.vue'

export default {
	name: 'AdminSettings',

	components: {
		NcButton,
		DocuSealIcon,
	},

	data() {
		return {
			state: loadState('integration_docuseal', 'docuseal-config'),
			apiKey: '',
			webhookSecret: '',
			saving: false,
			connectionStatus: null,
		}
	},

	computed: {
		webhookUrl() {
			return window.location.protocol + '//' + window.location.host
				+ generateUrl('/apps/integration_docuseal/webhook')
		},
	},

	methods: {
		t,
		onInput() {
			this.connectionStatus = null
		},

		async saveConfig() {
			this.saving = true
			try {
				const params = {
					server_url: this.state.server_url,
				}
				if (this.apiKey !== '') {
					params.api_key = this.apiKey
				}
				if (this.webhookSecret !== '') {
					params.webhook_secret = this.webhookSecret
				}
				const url = generateUrl('/apps/integration_docuseal/config')
				const response = await axios.put(url, params)

				this.state = response.data
				this.apiKey = ''
				this.webhookSecret = ''

				if (response.data.connection_test) {
					this.connectionStatus = response.data.connection_test
					if (response.data.connection_test.success) {
						showSuccess(t('integration_docuseal', 'Configurazione salvata e connessione verificata'))
					} else {
						showError(t('integration_docuseal', 'Configurazione salvata ma la connessione ha fallito: ') + response.data.connection_test.message)
					}
				} else {
					showSuccess(t('integration_docuseal', 'Configurazione salvata'))
				}
			} catch (e) {
				showError(t('integration_docuseal', 'Errore nel salvataggio della configurazione'))
				console.error(e)
			}
			this.saving = false
		},

		async resetConfig() {
			if (!confirm(t('integration_docuseal', 'Sei sicuro di voler disconnettere DocuSeal?'))) {
				return
			}
			try {
				const url = generateUrl('/apps/integration_docuseal/config')
				await axios.delete(url)
				this.state = { server_url: '', api_key_set: false, webhook_secret_set: false }
				this.connectionStatus = null
				showSuccess(t('integration_docuseal', 'DocuSeal disconnesso'))
			} catch (e) {
				showError(t('integration_docuseal', 'Errore nella disconnessione'))
			}
		},

		copyWebhookUrl() {
			navigator.clipboard.writeText(this.webhookUrl).then(() => {
				showSuccess(t('integration_docuseal', 'URL webhook copiato negli appunti'))
			})
		},
	},
}
</script>

<style scoped>
#docuseal-admin-settings {
	padding: 20px;
}

h2 {
	display: flex;
	align-items: center;
	gap: 8px;
}

.icon {
	display: inline-flex;
}

.settings-hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
}

.field {
	margin-bottom: 16px;
}

.field label {
	display: block;
	font-weight: bold;
	margin-bottom: 4px;
}

.field input {
	width: 100%;
	max-width: 400px;
}

.field .hint {
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
	margin-top: 4px;
}

.webhook-url-container {
	display: flex;
	gap: 8px;
	align-items: center;
}

.webhook-url {
	cursor: pointer;
	background-color: var(--color-background-dark) !important;
	font-family: monospace;
	font-size: 0.85em;
	flex: 1;
}

.actions {
	display: flex;
	gap: 8px;
	margin-top: 20px;
	margin-bottom: 20px;
}

.connection-status {
	margin-top: 16px;
	margin-bottom: 16px;
	padding: 10px 16px;
	border-radius: var(--border-radius);
}

.connection-status.success {
	background-color: var(--color-success);
	color: white;
}

.connection-status.error {
	background-color: var(--color-error);
	color: white;
}

.info-section {
	margin-top: 24px;
	padding-top: 16px;
	border-top: 1px solid var(--color-border);
}

.info-section h3 {
	margin-bottom: 8px;
}

.info-section ul {
	padding-left: 20px;
	color: var(--color-text-maxcontrast);
}

.info-section li {
	margin-bottom: 4px;
}
</style>
