<?php

declare(strict_types=1);

namespace OCA\BerthaWebhook\Service;

use OCA\BerthaWebhook\AppInfo\Application;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUserManager;

/**
 * Liest und schreibt die App-Konfiguration.
 *
 * Drei Felder:
 * - bot_user        Bot-User-ID, z.B. "bertha.ki"
 * - webhook_url     Ziel-URL für den HMAC-signierten POST
 * - webhook_secret  Shared Secret für HMAC-SHA256
 */
class AppConfigService {

	/**
	 * Sicherheits-Schranke: bot_user MUSS Mitglied dieser NC-Gruppe sein.
	 * Verhindert, dass ein Admin einen regulären User-Account als Bot konfiguriert
	 * und damit ungewollt 1:1-Chats nach extern leakt.
	 */
	public const BOTS_GROUP = '_bots';

	public function __construct(
		private IAppConfig $appConfig,
		private IGroupManager $groupManager,
		private IUserManager $userManager,
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
	 * Listet User in der `_bots`-Gruppe als Auswahl für die Settings-UI.
	 *
	 * @return list<array{uid: string, displayName: string}>
	 */
	public function getAvailableBots(): array {
		$group = $this->groupManager->get(self::BOTS_GROUP);
		if ($group === null) {
			return [];
		}
		$out = [];
		foreach ($group->getUsers() as $user) {
			$out[] = [
				'uid' => $user->getUID(),
				'displayName' => $user->getDisplayName(),
			];
		}
		return $out;
	}

	public function groupExists(): bool {
		return $this->groupManager->get(self::BOTS_GROUP) !== null;
	}

	/**
	 * Prüft, ob ein User-Account als Bot zugelassen ist (Mitglied in `_bots`).
	 * Schweigend false bei unbekanntem User oder fehlender Gruppe.
	 */
	public function isAllowedBot(string $uid): bool {
		if ($uid === '') {
			return false;
		}
		$user = $this->userManager->get($uid);
		if ($user === null) {
			return false;
		}
		$group = $this->groupManager->get(self::BOTS_GROUP);
		if ($group === null) {
			return false;
		}
		return $group->inGroup($user);
	}

	/**
	 * Konfig als Array für Settings-Form. Secret wird maskiert ausgeliefert.
	 *
	 * @return array{bot_user: string, webhook_url: string, webhook_secret_set: bool, bots_group: string, bots_group_exists: bool, available_bots: list<array{uid: string, displayName: string}>}
	 */
	public function getConfigForUi(): array {
		return [
			'bot_user' => $this->getBotUser(),
			'webhook_url' => $this->getWebhookUrl(),
			'webhook_secret_set' => $this->getWebhookSecret() !== '',
			'bots_group' => self::BOTS_GROUP,
			'bots_group_exists' => $this->groupExists(),
			'available_bots' => $this->getAvailableBots(),
		];
	}
}
