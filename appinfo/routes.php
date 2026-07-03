<?php

declare(strict_types=1);

return [
	'routes' => [
		// Admin config
		['name' => 'config#getConfig', 'url' => '/config', 'verb' => 'GET'],
		['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
		['name' => 'config#resetConfig', 'url' => '/config', 'verb' => 'DELETE'],

		// DocuSeal info & templates
		['name' => 'docuSeal#getInfo', 'url' => '/info', 'verb' => 'GET'],
		['name' => 'docuSeal#getTemplates', 'url' => '/templates', 'verb' => 'GET'],
		['name' => 'docuSeal#getTemplateDetail', 'url' => '/templates/{templateId}', 'verb' => 'GET'],

		// Template builder
		['name' => 'docuSeal#getBuilderToken', 'url' => '/builder-token/{fileId}', 'verb' => 'GET'],
		['name' => 'docuSeal#getFileContent', 'url' => '/file-download/{fileId}', 'verb' => 'GET'],

		// Signing - direct PDF/DOCX upload
		['name' => 'docuSeal#signDirect', 'url' => '/sign/direct/{fileId}', 'verb' => 'POST'],
		// Signing - using a template
		['name' => 'docuSeal#signTemplate', 'url' => '/sign/template', 'verb' => 'POST'],

		// Signature requests management
		['name' => 'docuSeal#getRequests', 'url' => '/requests', 'verb' => 'GET'],
		['name' => 'docuSeal#getRequest', 'url' => '/requests/{id}', 'verb' => 'GET'],
		['name' => 'docuSeal#getFileRequests', 'url' => '/requests/file/{fileId}', 'verb' => 'GET'],

		// Resend reminder to a submitter
		['name' => 'docuSeal#resendReminder', 'url' => '/requests/{id}/resend/{submitterId}', 'verb' => 'POST'],
		// Cancel a pending request
		['name' => 'docuSeal#cancelRequest', 'url' => '/requests/{id}/cancel', 'verb' => 'POST'],

		// Embedded signing
		['name' => 'docuSeal#getEmbedUrl', 'url' => '/embed/{requestId}', 'verb' => 'GET'],

		// Audit trail
		['name' => 'docuSeal#getAuditTrail', 'url' => '/requests/{id}/audit', 'verb' => 'GET'],

		// Webhook receiver
		['name' => 'webhook#receive', 'url' => '/webhook', 'verb' => 'POST'],
	],
];
