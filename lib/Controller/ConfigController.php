<?php

declare(strict_types=1);

namespace OCA\DocuSeal\Controller;

use Exception;
use OCA\DocuSeal\AppInfo\Application;
use OCA\DocuSeal\Service\DocuSealAPIService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\Security\ICrypto;
use Psr\Log\LoggerInterface;

class ConfigController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IAppConfig $appConfig,
		private ICrypto $crypto,
		private DocuSealAPIService $docuSealAPIService,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get admin configuration
	 */
	public function getConfig(): DataResponse {
		return new DataResponse($this->buildStatePayload());
	}

	/**
	 * Save admin configuration.
	 *
	 * The save itself never depends on the DocuSeal server being reachable:
	 * we always persist what was provided and return 200, even when the
	 * connection probe afterwards fails. The frontend distinguishes the two
	 * via the `connection_test.success` flag.
	 */
	public function setConfig(): DataResponse {
		$serverUrl = $this->request->getParam('server_url');
		$apiKey = $this->request->getParam('api_key');
		$webhookSecret = $this->request->getParam('webhook_secret');

		try {
			if ($serverUrl !== null) {
				$normalized = rtrim(trim((string)$serverUrl), '/');
				if ($normalized !== '' && !preg_match('#^https?://#i', $normalized)) {
					return new DataResponse(
						['error' => 'server_url must start with http:// or https://'],
						Http::STATUS_BAD_REQUEST,
					);
				}
				$this->appConfig->setValueString(Application::APP_ID, 'server_url', $normalized);
			}

			if ($apiKey !== null && $apiKey !== '') {
				$this->appConfig->setValueString(
					Application::APP_ID,
					'api_key',
					$this->crypto->encrypt((string)$apiKey),
				);
			}

			if ($webhookSecret !== null) {
				// Stored in clear text on purpose: webhook validation compares
				// the raw value against the header sent by DocuSeal.
				$this->appConfig->setValueString(
					Application::APP_ID,
					'webhook_secret',
					(string)$webhookSecret,
				);
			}
		} catch (Exception $e) {
			$this->logger->error('DocuSeal: failed to save configuration: ' . $e->getMessage(), [
				'app' => Application::APP_ID,
				'exception' => $e,
			]);
			return new DataResponse(
				['error' => 'Failed to save configuration: ' . $e->getMessage()],
				Http::STATUS_INTERNAL_SERVER_ERROR,
			);
		}

		$payload = $this->buildStatePayload();

		// Best-effort connection probe — never fails the save.
		if ($this->docuSealAPIService->isConfigured()) {
			$payload['connection_test'] = $this->docuSealAPIService->testConnection();
		}

		return new DataResponse($payload);
	}

	/**
	 * Manually trigger a connection test against the DocuSeal server.
	 * Lets the admin re-check the connection without having to re-save.
	 */
	public function testConnection(): DataResponse {
		if (!$this->docuSealAPIService->isConfigured()) {
			return new DataResponse([
				'success' => false,
				'message' => 'DocuSeal is not configured. Set the server URL and API key first.',
			], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse($this->docuSealAPIService->testConnection());
	}

	/**
	 * Reset/disconnect configuration
	 */
	public function resetConfig(): DataResponse {
		$keys = ['server_url', 'api_key', 'webhook_secret'];
		foreach ($keys as $key) {
			$this->appConfig->deleteKey(Application::APP_ID, $key);
		}
		return new DataResponse(['success' => true]);
	}

	private function buildStatePayload(): array {
		return [
			'server_url' => $this->appConfig->getValueString(Application::APP_ID, 'server_url', ''),
			'api_key_set' => $this->appConfig->getValueString(Application::APP_ID, 'api_key', '') !== '',
			'webhook_secret_set' => $this->appConfig->getValueString(Application::APP_ID, 'webhook_secret', '') !== '',
		];
	}
}
