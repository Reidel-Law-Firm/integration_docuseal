<?php

declare(strict_types=1);

namespace OCA\DocuSeal\Listener;

use OCA\DocuSeal\AppInfo\Application;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IAppConfig;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;

/**
 * @template-implements IEventListener<AddContentSecurityPolicyEvent>
 */
class CSPListener implements IEventListener {

	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof AddContentSecurityPolicyEvent)) {
			return;
		}

		$serverUrl = $this->appConfig->getValueString(Application::APP_ID, 'server_url', '');
		if ($serverUrl === '') {
			return;
		}

		$parts = parse_url($serverUrl);
		$scheme = $parts['scheme'] ?? null;
		$host = $parts['host'] ?? null;
		if ($scheme === null || $host === null) {
			return;
		}
		if (!empty($parts['port'])) {
			$host .= ':' . $parts['port'];
		}
		$origin = $scheme . '://' . $host;

		// Allow the DocuSeal server origin for embedded signing and builder
		$csp = new ContentSecurityPolicy();
		$csp->addAllowedFrameDomain($origin);
		$csp->addAllowedConnectDomain($origin);
		$csp->addAllowedImageDomain($origin);
		$csp->addAllowedScriptDomain($origin);

		foreach (['addAllowedStyleDomain', 'addAllowedFontDomain', 'addAllowedMediaDomain', 'addAllowedFormActionDomain'] as $method) {
			if (method_exists($csp, $method)) {
				$csp->{$method}($origin);
			}
		}

		$event->addPolicy($csp);
	}
}
