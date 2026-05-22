<?php

declare(strict_types=1);

namespace OCA\BerthaWebhook\Service;

use OCA\BerthaWebhook\AppInfo\Application;
use OCP\IAppConfig;

/**
 * Liest und schreibt die App-Konfiguration.
 *
 * Drei Felder:
 * - bot_user        Bot-User-ID, z.B. "bertha.ki"
 * - webhook_url     Ziel-URL für den HMAC-signierten POST
 * - webhook_secret  Shared Secret für HMAC-SHA256
 */
class AppConfigService {

	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	public function getBotUser(): string {
		return $this->appConfig->getValueString(Application::APP_ID, 'bot_user', '');
	}

	public function getWebhookUrl(): string {
		return $this->appConfig->getValueString(Application::APP_ID, 'webhook_url', '');
	}

	public function getWebhookSecret(): string {
		return $this->appConfig->getValueString(Application::APP_ID, 'webhook_secret', '');
	}

	public function setBotUser(string $value): void {
		$this->appConfig->setValueString(Application::APP_ID, 'bot_user', $value);
	}

	public function setWebhookUrl(string $value): void {
		$this->appConfig->setValueString(Application::APP_ID, 'webhook_url', $value);
	}

	public function setWebhookSecret(string $value): void {
		$this->appConfig->setValueString(Application::APP_ID, 'webhook_secret', $value);
	}

	/**
	 * Konfig als Array für Settings-Form. Secret wird maskiert ausgeliefert.
	 *
	 * @return array{bot_user: string, webhook_url: string, webhook_secret_set: bool}
	 */
	public function getConfigForUi(): array {
		return [
			'bot_user' => $this->getBotUser(),
			'webhook_url' => $this->getWebhookUrl(),
			'webhook_secret_set' => $this->getWebhookSecret() !== '',
		];
	}
}
