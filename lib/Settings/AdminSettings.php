<?php

declare(strict_types=1);

namespace OCA\BerthaKi\Settings;

use OCA\BerthaKi\AppInfo\Application;
use OCA\BerthaKi\Service\AppConfigService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;

class AdminSettings implements ISettings {

	public function __construct(
		private AppConfigService $config,
		private IInitialState $initial,
	) {
	}

	public function getForm(): TemplateResponse {
		$this->initial->provideInitialState('config', $this->config->getConfigForUi());
		return new TemplateResponse(Application::APP_ID, 'admin');
	}

	public function getSection(): string {
		return Application::APP_ID;
	}

	public function getPriority(): int {
		return 50;
	}
}
