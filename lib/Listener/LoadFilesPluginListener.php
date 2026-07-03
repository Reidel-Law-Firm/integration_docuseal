<?php

declare(strict_types=1);

namespace OCA\DocuSeal\Listener;

use OCA\DocuSeal\AppInfo\Application;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;

/**
 * @template-implements IEventListener<LoadAdditionalScriptsEvent>
 */
class LoadFilesPluginListener implements IEventListener {

	public function handle(Event $event): void {
		if (!($event instanceof LoadAdditionalScriptsEvent)) {
			return;
		}

		Util::addScript(Application::APP_ID, Application::APP_ID . '-filesplugin');
		Util::addStyle(Application::APP_ID, 'files-style');
	}
}
