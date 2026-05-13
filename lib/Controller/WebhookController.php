<?php

declare(strict_types=1);

namespace OCA\DocuSeal\Controller;

use Exception;
use OCA\DocuSeal\AppInfo\Application;
use OCA\DocuSeal\Db\SignatureRequestMapper;
use OCA\DocuSeal\Db\SignatureRequestSubmitterMapper;
use OCA\DocuSeal\Service\DocuSealAPIService;
use OCA\DocuSeal\Service\UtilsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

class WebhookController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private SignatureRequestMapper $requestMapper,
		private SignatureRequestSubmitterMapper $submitterMapper,
		private DocuSealAPIService $docuSealAPIService,
		private UtilsService $utilsService,
		private INotificationManager $notificationManager,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Receive webhook from DocuSeal
	 * This endpoint must be publicly accessible (no auth, no CSRF)
	 *
	 * @NoCSRFRequired
	 * @PublicPage
	 */
	#[NoCSRFRequired]
	#[PublicPage]
	public function receive(): DataResponse {
		$body = file_get_contents('php://input');

		// Validate webhook secret if configured
		if (!$this->validateWebhookSecret($body)) {
			$presentHeaders = array_filter([
				'X-Docuseal-Signature' => $this->request->getHeader('X-Docuseal-Signature') !== '',
				'X-Auth-Secret' => $this->request->getHeader('X-Auth-Secret') !== '',
				'X-Webhook-Secret' => $this->request->getHeader('X-Webhook-Secret') !== '',
				'X-Webhook-Token' => $this->request->getHeader('X-Webhook-Token') !== '',
				'X-Docuseal-Secret' => $this->request->getHeader('X-Docuseal-Secret') !== '',
				'Authorization' => $this->request->getHeader('Authorization') !== '',
				'query.secret' => $this->request->getParam('secret', '') !== '',
			]);
			$this->logger->warning('DocuSeal webhook: invalid secret. Configure a custom header in DocuSeal (Settings → Webhooks) named X-Auth-Secret with the same value as the Nextcloud webhook secret.', [
				'app' => Application::APP_ID,
				'credentialsPresent' => array_keys($presentHeaders),
			]);
			return new DataResponse(['error' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
		}

		$payload = json_decode($body, true);

		if ($payload === null) {
			$this->logger->warning('DocuSeal webhook: invalid JSON payload', [
				'app' => Application::APP_ID,
			]);
			return new DataResponse(['error' => 'Invalid payload'], Http::STATUS_BAD_REQUEST);
		}

		$eventType = $payload['event_type'] ?? '';
		$data = $payload['data'] ?? [];

		$this->logger->info('DocuSeal webhook received: ' . $eventType, [
			'app' => Application::APP_ID,
			'submitter_id' => $data['id'] ?? null,
		]);

		try {
			switch ($eventType) {
				case 'form.completed':
					$this->handleFormCompleted($data);
					break;
				case 'form.declined':
					$this->handleFormDeclined($data);
					break;
				case 'form.viewed':
				case 'form.started':
					$this->handleFormViewed($data);
					break;
				case 'submission.completed':
					$this->handleSubmissionCompleted($data);
					break;
				case 'submission.expired':
					$this->handleSubmissionExpired($data);
					break;
				default:
					$this->logger->debug('DocuSeal webhook: unhandled event type ' . $eventType, [
						'app' => Application::APP_ID,
					]);
			}
		} catch (Exception $e) {
			$this->logger->error('DocuSeal webhook error: ' . $e->getMessage(), [
				'app' => Application::APP_ID,
				'event_type' => $eventType,
			]);
			// Return 200 anyway to prevent DocuSeal from retrying
		}

		return new DataResponse(['status' => 'ok']);
	}

	/**
	 * Handle form.completed event - a single signer has completed
	 */
	private function handleFormCompleted(array $data): void {
		$docusealSubmitterId = $data['id'] ?? null;
		if ($docusealSubmitterId === null) {
			return;
		}

		try {
			$submitter = $this->submitterMapper->findByDocuSealId((int)$docusealSubmitterId);
			$submitter->setStatus('completed');
			$submitter->setCompletedAt(time());
			$this->submitterMapper->update($submitter);

			// Get the parent request
			$request = $this->requestMapper->find($submitter->getRequestId());

			// Send notification to the request owner
			$this->sendNotification(
				$request->getUserId(),
				'signature_completed',
				[
					'requestId' => $request->getId(),
					'fileName' => $request->getFileName(),
					'signerEmail' => $data['email'] ?? $submitter->getEmail(),
					'signerName' => $data['name'] ?? $submitter->getName(),
				]
			);

			// Check if all submitters are done
			$this->checkAndUpdateRequestStatus($request);
		} catch (Exception $e) {
			$this->logger->warning('DocuSeal webhook: submitter not found for ID ' . $docusealSubmitterId, [
				'app' => Application::APP_ID,
			]);
		}
	}

	/**
	 * Handle form.declined event
	 */
	private function handleFormDeclined(array $data): void {
		$docusealSubmitterId = $data['id'] ?? null;
		if ($docusealSubmitterId === null) {
			return;
		}

		try {
			$submitter = $this->submitterMapper->findByDocuSealId((int)$docusealSubmitterId);
			$submitter->setStatus('declined');
			$this->submitterMapper->update($submitter);

			$request = $this->requestMapper->find($submitter->getRequestId());
			$request->setStatus('declined');
			$request->setUpdatedAt(time());
			$this->requestMapper->update($request);

			$this->sendNotification(
				$request->getUserId(),
				'signature_declined',
				[
					'requestId' => $request->getId(),
					'fileName' => $request->getFileName(),
					'signerEmail' => $data['email'] ?? $submitter->getEmail(),
					'signerName' => $data['name'] ?? $submitter->getName(),
					'reason' => $data['decline_reason'] ?? '',
				]
			);
		} catch (Exception $e) {
			$this->logger->warning('DocuSeal webhook: submitter not found for declined event', [
				'app' => Application::APP_ID,
			]);
		}
	}

	/**
	 * Handle form.viewed / form.started events
	 */
	private function handleFormViewed(array $data): void {
		$docusealSubmitterId = $data['id'] ?? null;
		if ($docusealSubmitterId === null) {
			return;
		}

		try {
			$submitter = $this->submitterMapper->findByDocuSealId((int)$docusealSubmitterId);
			if ($submitter->getStatus() === 'sent' || $submitter->getStatus() === 'pending') {
				$submitter->setStatus('opened');
				$this->submitterMapper->update($submitter);
			}
		} catch (Exception $e) {
			// Non-critical
		}
	}

	/**
	 * Handle submission.completed event - all signers done
	 */
	private function handleSubmissionCompleted(array $data): void {
		$submissionId = $data['id'] ?? null;
		if ($submissionId === null) {
			return;
		}

		try {
			$request = $this->requestMapper->findBySubmissionId((int)$submissionId);
			$request->setStatus('completed');
			$request->setUpdatedAt(time());
			$this->requestMapper->update($request);

			// Download and save the signed document
			$this->downloadSignedDocument($request, $data);

			$this->sendNotification(
				$request->getUserId(),
				'all_signatures_completed',
				[
					'requestId' => $request->getId(),
					'fileName' => $request->getFileName(),
				]
			);
		} catch (Exception $e) {
			$this->logger->error('DocuSeal webhook: error handling submission.completed: ' . $e->getMessage(), [
				'app' => Application::APP_ID,
			]);
		}
	}

	/**
	 * Handle submission.expired event
	 */
	private function handleSubmissionExpired(array $data): void {
		$submissionId = $data['id'] ?? null;
		if ($submissionId === null) {
			return;
		}

		try {
			$request = $this->requestMapper->findBySubmissionId((int)$submissionId);
			$request->setStatus('expired');
			$request->setUpdatedAt(time());
			$this->requestMapper->update($request);

			$this->sendNotification(
				$request->getUserId(),
				'signature_expired',
				[
					'requestId' => $request->getId(),
					'fileName' => $request->getFileName(),
				]
			);
		} catch (Exception $e) {
			$this->logger->warning('DocuSeal webhook: request not found for expired submission', [
				'app' => Application::APP_ID,
			]);
		}
	}

	/**
	 * Check if all submitters have completed and update request status
	 */
	private function checkAndUpdateRequestStatus($request): void {
		$submitters = $this->submitterMapper->findByRequestId($request->getId());
		$allCompleted = true;

		foreach ($submitters as $submitter) {
			if ($submitter->getStatus() !== 'completed') {
				$allCompleted = false;
				break;
			}
		}

		if ($allCompleted) {
			$request->setStatus('completed');
			$request->setUpdatedAt(time());
			$this->requestMapper->update($request);

			// Try to download the signed document
			if ($request->getSubmissionId() !== null) {
				try {
					$submission = $this->docuSealAPIService->getSubmission($request->getSubmissionId());
					$this->downloadSignedDocument($request, $submission);
				} catch (Exception $e) {
					$this->logger->error('Error downloading signed document: ' . $e->getMessage(), [
						'app' => Application::APP_ID,
					]);
				}
			}
		}
	}

	/**
	 * Download signed document and save to user's Nextcloud
	 */
	private function downloadSignedDocument($request, array $submissionData): void {
		// Find the document URL
		$documentUrl = null;

		// Check combined_document_url first
		if (!empty($submissionData['combined_document_url'])) {
			$documentUrl = $submissionData['combined_document_url'];
		}

		// Fall back to documents array
		if ($documentUrl === null && !empty($submissionData['documents'])) {
			$documentUrl = $submissionData['documents'][0]['url'] ?? null;
		}

		// Check submitters' documents
		if ($documentUrl === null && !empty($submissionData['submitters'])) {
			foreach ($submissionData['submitters'] as $sub) {
				if (!empty($sub['documents'])) {
					$documentUrl = $sub['documents'][0]['url'] ?? null;
					if ($documentUrl !== null) {
						break;
					}
				}
			}
		}

		if ($documentUrl === null) {
			$this->logger->warning('No document URL found in submission data', [
				'app' => Application::APP_ID,
				'submissionId' => $request->getSubmissionId(),
			]);
			return;
		}

		try {
			$content = $this->docuSealAPIService->downloadDocument($documentUrl);

			$signedFileId = $this->utilsService->saveSignedDocument(
				$request->getUserId(),
				$content,
				$request->getFilePath(),
				$request->getFileName(),
			);

			$request->setSignedFileId($signedFileId);
			$request->setUpdatedAt(time());
			$this->requestMapper->update($request);

			$this->logger->info('Signed document saved with file ID: ' . $signedFileId, [
				'app' => Application::APP_ID,
			]);
		} catch (Exception $e) {
			$this->logger->error('Error saving signed document: ' . $e->getMessage(), [
				'app' => Application::APP_ID,
			]);
		}
	}

	/**
	 * Send a Nextcloud notification
	 */
	private function sendNotification(string $userId, string $subject, array $params): void {
		try {
			$notification = $this->notificationManager->createNotification();
			$notification->setApp(Application::APP_ID)
				->setUser($userId)
				->setDateTime(new \DateTime())
				->setObject('signature_request', (string)($params['requestId'] ?? '0'))
				->setSubject($subject, $params);

			$this->notificationManager->notify($notification);
		} catch (Exception $e) {
			$this->logger->error('Error sending notification: ' . $e->getMessage(), [
				'app' => Application::APP_ID,
			]);
		}
	}

	/**
	 * Validate webhook secret if configured.
	 *
	 * DocuSeal lets the admin define one or more custom HTTP headers on each
	 * webhook (Settings → Webhooks → Custom headers). It does NOT compute an
	 * HMAC of the body. The typical setup is to add a header like
	 * `X-Auth-Secret: <shared-secret>` and have the receiver compare the raw
	 * value. We accept the raw secret in any of the commonly-used header
	 * names, plus a `?secret=` query param for legacy setups, and still
	 * support an HMAC-SHA256 signature in `X-Docuseal-Signature` for users
	 * who proxy webhooks through a signer.
	 */
	private function validateWebhookSecret(string $body): bool {
		$webhookSecret = $this->appConfig->getValueString(Application::APP_ID, 'webhook_secret', '');
		if ($webhookSecret === '') {
			return true;
		}

		// 1. HMAC-SHA256 signature (advanced)
		$signature = $this->request->getHeader('X-Docuseal-Signature');
		if ($signature !== '') {
			$expectedSignature = hash_hmac('sha256', $body, $webhookSecret);
			if (hash_equals($expectedSignature, $signature)) {
				return true;
			}
			// X-Docuseal-Signature might also be used as a raw-secret header;
			// fall through to raw-secret checks below.
		}

		// 2. Raw secret in any of the common header names DocuSeal users pick
		$rawSecretHeaders = [
			'X-Auth-Secret',
			'X-Webhook-Secret',
			'X-Webhook-Token',
			'X-Docuseal-Secret',
			'X-Docuseal-Signature',
		];
		foreach ($rawSecretHeaders as $header) {
			$value = $this->request->getHeader($header);
			if ($value !== '' && hash_equals($webhookSecret, $value)) {
				return true;
			}
		}

		// 3. Authorization: Bearer <secret>
		$authHeader = $this->request->getHeader('Authorization');
		if ($authHeader !== '') {
			$bearer = preg_replace('/^Bearer\s+/i', '', $authHeader);
			if ($bearer !== null && $bearer !== '' && hash_equals($webhookSecret, $bearer)) {
				return true;
			}
		}

		// 4. Legacy: ?secret= query param
		$querySecret = (string)$this->request->getParam('secret', '');
		if ($querySecret !== '' && hash_equals($webhookSecret, $querySecret)) {
			return true;
		}

		return false;
	}
}
