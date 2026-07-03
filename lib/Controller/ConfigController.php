<?php

declare(strict_types=1);

namespace OCA\DocuSeal\Controller;

use OCA\DocuSeal\AppInfo\Application;
use OCA\DocuSeal\Service\DocuSealAPIService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\Security\ICrypto;

class ConfigController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private IAppConfig $appConfig,
		private ICrypto $crypto,
		private DocuSealAPIService $docuSealAPIService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get admin configuration
	 */
	public function getConfig(): DataResponse {
		$serverUrl = $this->appConfig->getValueString(Application::APP_ID, 'server_url', '');
		$apiKeySet = $this->appConfig->getValueString(Application::APP_ID, 'api_key', '') !== '';
		$webhookSecretSet = $this->appConfig->getValueString(Application::APP_ID, 'webhook_secret', '') !== '';

		return new DataResponse([
			'server_url' => $serverUrl,
			'api_key_set' => $apiKeySet,
			'webhook_secret_set' => $webhookSecretSet,
		]);
	}

	/**
	 * Save admin configuration
	 */
	public function setConfig(): DataResponse {
		$serverUrl = $this->request->getParam('server_url');
		$apiKey = $this->request->getParam('api_key');
		$webhookSecret = $this->request->getParam('webhook_secret');

		if ($serverUrl !== null) {
			$this->appConfig->setValueString(
				Application::APP_ID,
				'server_url',
				rtrim($serverUrl, '/')
			);
		}

		if ($apiKey !== null && $apiKey !== '') {
			$this->appConfig->setValueString(
				Application::APP_ID,
				'api_key',
				$this->crypto->encrypt($apiKey)
			);
		}

		if ($webhookSecret !== null) {
			$this->appConfig->setValueString(
				Application::APP_ID,
				'webhook_secret',
				$webhookSecret
			);
		}

		// Test connection if both are set
		if ($this->docuSealAPIService->isConfigured()) {
			$test = $this->docuSealAPIService->testConnection();
			return new DataResponse([
				'server_url' => $this->appConfig->getValueString(Application::APP_ID, 'server_url', ''),
				'api_key_set' => true,
				'webhook_secret_set' => $this->appConfig->getValueString(Application::APP_ID, 'webhook_secret', '') !== '',
				'connection_test' => $test,
			]);
		}

		return new DataResponse([
			'server_url' => $this->appConfig->getValueString(Application::APP_ID, 'server_url', ''),
			'api_key_set' => $this->appConfig->getValueString(Application::APP_ID, 'api_key', '') !== '',
			'webhook_secret_set' => $this->appConfig->getValueString(Application::APP_ID, 'webhook_secret', '') !== '',
		]);
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
}
