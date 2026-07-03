<?php

declare(strict_types=1);

namespace OCA\DocuSeal\AppInfo;

use OCA\DocuSeal\Dashboard\DocuSealWidget;
use OCA\DocuSeal\Listener\CSPListener;
use OCA\DocuSeal\Listener\LoadFilesPluginListener;
use OCA\DocuSeal\Notification\Notifier;
use OCA\DocuSeal\Search\DocuSealSearchProvider;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;

class Application extends App implements IBootstrap {

	public const APP_ID = 'integration_docuseal';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerNotifierService(Notifier::class);
		$context->registerSearchProvider(DocuSealSearchProvider::class);
		$context->registerDashboardWidget(DocuSealWidget::class);
		$context->registerEventListener(
			LoadAdditionalScriptsEvent::class,
			LoadFilesPluginListener::class
		);
		$context->registerEventListener(
			AddContentSecurityPolicyEvent::class,
			CSPListener::class
		);
	}

	public function boot(IBootContext $context): void {
	}
}
