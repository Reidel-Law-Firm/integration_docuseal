<?php

declare(strict_types=1);

namespace OCA\DocuSeal\Service;

use Exception;
use OCA\DocuSeal\AppInfo\Application;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;
use RuntimeException;

class DocuSealAPIService {

	private const ENCRYPTED_KEYS = ['api_key'];

	public function __construct(
		private IClientService $clientService,
		private IAppConfig $appConfig,
		private ICrypto $crypto,
		private IL10N $l10n,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Get DocuSeal server base URL
	 */
	public function getServerUrl(): string {
		return rtrim(
			$this->appConfig->getValueString(Application::APP_ID, 'server_url', ''),
			'/'
		);
	}

	/**
	 * Detect a legacy / unencrypted API key value. Encrypted values produced
	 * by ICrypto contain a pipe separator (cipher|iv[|hmac]) with hex/base64
	 * characters; legacy plaintext keys typically don't match that shape.
	 */
	public function looksLikePlaintextKey(string $value): bool {
		if ($value === '') {
			return false;
		}
		if (strpos($value, '|') === false) {
			return true;
		}
		if (!preg_match('/^[A-Za-z0-9+\/=|]+$/', $value)) {
			return true;
		}
		return false;
	}

	/**
	 * Get decrypted API key. Transparently re-encrypts legacy plaintext values.
	 * Throws RuntimeException on a real decryption failure.
	 */
	public function getApiKey(): string {
		$stored = $this->appConfig->getValueString(Application::APP_ID, 'api_key', '');
		if ($stored === '') {
			return '';
		}

		if ($this->looksLikePlaintextKey($stored)) {
			try {
				$encrypted = $this->crypto->encrypt($stored);
				$this->appConfig->setValueString(Application::APP_ID, 'api_key', $encrypted);
			} catch (Exception $e) {
				$this->logger->warning('Failed to re-encrypt legacy DocuSeal API key: ' . $e->getMessage(), [
					'app' => Application::APP_ID,
				]);
			}
			return $stored;
		}

		try {
			return $this->crypto->decrypt($stored);
		} catch (Exception $e) {
			$this->logger->error('Failed to decrypt DocuSeal API key: ' . $e->getMessage(), [
				'app' => Application::APP_ID,
			]);
			throw new RuntimeException('Unable to decrypt stored DocuSeal API key. Please re-enter it in the admin settings.', 0, $e);
		}
	}

	/**
	 * Read SSL verification preference. Returns false when self-signed
	 * certificates are explicitly allowed by the admin, true otherwise.
	 */
	public function getVerifyOption(): bool {
		$allowSelfSigned = $this->appConfig->getValueString(Application::APP_ID, 'allow_self_signed', '0');
		return !($allowSelfSigned === '1' || $allowSelfSigned === 'true');
	}

	/**
	 * Check if the integration is configured
	 */
	public function isConfigured(): bool {
		return $this->getServerUrl() !== '' && $this->getApiKey() !== '';
	}

	/**
	 * Make an API request to DocuSeal
	 */
	public function request(string $method, string $endpoint, array $params = []): array {
		$serverUrl = $this->getServerUrl();
		$apiKey = $this->getApiKey();

		if ($serverUrl === '' || $apiKey === '') {
			throw new RuntimeException('DocuSeal is not configured');
		}

		$url = $serverUrl . '/api' . $endpoint;
		$client = $this->clientService->newClient();

		$options = [
			'headers' => [
				'X-Auth-Token' => $apiKey,
				'Accept' => 'application/json',
			],
			'timeout' => 30,
			'http_errors' => false,
			'verify' => $this->getVerifyOption(),
		];

		try {
			if ($method === 'GET') {
				if (!empty($params)) {
					$options['query'] = $params;
				}
				$response = $client->get($url, $options);
			} elseif ($method === 'POST') {
				$options['json'] = $params;
				$response = $client->post($url, $options);
			} elseif ($method === 'PUT') {
				$options['json'] = $params;
				$response = $client->put($url, $options);
			} elseif ($method === 'DELETE') {
				$response = $client->delete($url, $options);
			} else {
				throw new RuntimeException('Unsupported HTTP method: ' . $method);
			}

			$status = $response->getStatusCode();
			$body = (string)$response->getBody();

			if ($status >= 400) {
				$this->logger->error('DocuSeal API HTTP error', [
					'app' => Application::APP_ID,
					'method' => $method,
					'endpoint' => $endpoint,
					'status' => $status,
					'body' => $body,
				]);
				throw new RuntimeException('DocuSeal API error (HTTP ' . $status . '): ' . $body);
			}

			return json_decode($body, true) ?? [];
		} catch (RuntimeException $e) {
			throw $e;
		} catch (Exception $e) {
			$this->logger->error('DocuSeal API error: ' . $e->getMessage(), [
				'app' => Application::APP_ID,
				'method' => $method,
				'endpoint' => $endpoint,
			]);
			throw $e;
		}
	}

	/**
	 * Upload a file directly and create a submission
	 *
	 * DocuSeal workflow: 1) Create template from file via /templates/pdf
	 *                    2) Create submission from that template via /submissions
	 */
	public function createDirectSubmission(
		string $fileContent,
		string $fileName,
		array $submitters,
		?string $subject = null,
		?string $message = null,
		bool $sendEmail = true,
		?string $expireAt = null,
	): array {
		// Step 1: Create a template from the uploaded file
		$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
		$mimeMap = [
			'pdf' => 'application/pdf',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'doc' => 'application/msword',
			'png' => 'image/png',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
		];
		$mime = $mimeMap[$ext] ?? 'application/pdf';

		$endpointMap = [
			'pdf' => '/templates/pdf',
			'docx' => '/templates/docx',
			'doc' => '/templates/docx',
			'png' => '/templates/pdf',
			'jpg' => '/templates/pdf',
			'jpeg' => '/templates/pdf',
		];
		$templateEndpoint = $endpointMap[$ext] ?? '/templates/pdf';

		$base64File = 'data:' . $mime . ';base64,' . base64_encode($fileContent);
		$templateName = pathinfo($fileName, PATHINFO_FILENAME) . ' - ' . date('Y-m-d H:i');

		$templateResult = $this->request('POST', $templateEndpoint, [
			'name' => $templateName,
			'documents' => [
				[
					'name' => $fileName,
					'file' => $base64File,
				],
			],
		]);

		$templateId = $templateResult['id'] ?? null;
		if ($templateId === null) {
			throw new RuntimeException('Failed to create template from file');
		}

		$this->logger->info('Created template #' . $templateId . ' from file: ' . $fileName, [
			'app' => Application::APP_ID,
		]);

		// Add default signature field if template has no fields
		if (empty($templateResult['fields'])) {
			$submitterUuid = $templateResult['submitters'][0]['uuid'] ?? null;
			$documentAttachmentUuid = $templateResult['schema'][0]['attachment_uuid'] ?? null;

			$fields = [
				[
					'name' => $this->l10n->t('Signature'),
					'type' => 'signature',
					'required' => true,
					'areas' => [[
						'x' => 0.1,
						'y' => 0.85,
						'w' => 0.35,
						'h' => 0.06,
						'page' => 0,
					]],
				],
				[
					'name' => $this->l10n->t('Date'),
					'type' => 'date',
					'required' => true,
					'areas' => [[
						'x' => 0.55,
						'y' => 0.85,
						'w' => 0.2,
						'h' => 0.04,
						'page' => 0,
					]],
				],
			];

			// Assign submitter UUID if available
			if ($submitterUuid !== null) {
				foreach ($fields as &$field) {
					$field['submitter_uuid'] = $submitterUuid;
					if ($documentAttachmentUuid !== null) {
						foreach ($field['areas'] as &$area) {
							$area['attachment_uuid'] = $documentAttachmentUuid;
						}
						unset($area);
					}
				}
				unset($field);
			}

			$this->request('PUT', '/templates/' . $templateId, [
				'fields' => $fields,
			]);
		}

		// Step 2: Create submission from the template
		// Map submitter roles to the template's first submitter role
		$templateRole = $templateResult['submitters'][0]['name'] ?? 'First Party';
		foreach ($submitters as &$submitter) {
			$submitter['role'] = $templateRole;
		}
		unset($submitter);

		return $this->createTemplateSubmission(
			$templateId,
			$submitters,
			$sendEmail,
			$subject,
			$message,
			$expireAt,
		);
	}

	/**
	 * Create a submission from an existing template
	 */
	public function createTemplateSubmission(
		int $templateId,
		array $submitters,
		bool $sendEmail = true,
		?string $subject = null,
		?string $message = null,
		?string $expireAt = null,
	): array {
		$params = [
			'template_id' => $templateId,
			'send_email' => $sendEmail,
			'submitters' => $submitters,
		];

		if ($subject !== null || $message !== null) {
			$params['message'] = [
				'subject' => $subject ?? $this->l10n->t('Signature request'),
				'body' => $message ?? $this->l10n->t('Please sign the attached document.'),
			];
		}

		if ($expireAt !== null) {
			$params['expire_at'] = $expireAt;
		}

		return $this->request('POST', '/submissions', $params);
	}

	/**
	 * Get a submission's details
	 */
	public function getSubmission(int $submissionId): array {
		return $this->request('GET', '/submissions/' . $submissionId);
	}

	/**
	 * List all templates
	 */
	public function getTemplates(?int $limit = 100, ?string $folder = null): array {
		$params = [];
		if ($limit !== null) {
			$params['limit'] = $limit;
		}
		if ($folder !== null) {
			$params['template_folder'] = $folder;
		}
		return $this->request('GET', '/templates', $params);
	}

	/**
	 * Get a single template
	 */
	public function getTemplate(int $templateId): array {
		return $this->request('GET', '/templates/' . $templateId);
	}

	/**
	 * Download a document from a URL
	 */
	public function downloadDocument(string $url): string {
		$client = $this->clientService->newClient();
		$response = $client->get($url, [
			'headers' => [
				'X-Auth-Token' => $this->getApiKey(),
			],
			'timeout' => 120,
			'verify' => $this->getVerifyOption(),
		]);
		return (string)$response->getBody();
	}

	/**
	 * Test the connection to DocuSeal
	 */
	public function testConnection(): array {
		try {
			$result = $this->getTemplates(1);
			return [
				'success' => true,
				'message' => $this->l10n->t('Connection successful'),
			];
		} catch (Exception $e) {
			return [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}
	}
}
