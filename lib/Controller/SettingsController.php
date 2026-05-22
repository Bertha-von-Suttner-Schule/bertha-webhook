<?php

declare(strict_types=1);

namespace OCA\BerthaWebhook\Controller;

use OCA\BerthaWebhook\AppInfo\Application;
use OCA\BerthaWebhook\Service\AppConfigService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class SettingsController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private AppConfigService $config,
		private LoggerInterface $logger,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	public function index(): DataResponse {
		return new DataResponse($this->config->getConfigForUi());
	}

	/**
	 * @param string|null $bot_user
	 * @param string|null $webhook_url
	 * @param string|null $webhook_secret  Leer/null lassen, um vorhandenes Secret beizubehalten
	 */
	public function update(
		?string $bot_user = null,
		?string $webhook_url = null,
		?string $webhook_secret = null,
	): DataResponse {
		$actor = $this->userSession->getUser()?->getUID() ?? '<unknown>';
		$changes = [];

		if ($bot_user !== null) {
			$new = trim($bot_user);
			if ($new !== '' && !$this->config->isAllowedBot($new)) {
				return new DataResponse([
					'error' => 'User "' . $new . '" ist nicht in der Gruppe "'
						. AppConfigService::BOTS_GROUP
						. '". Bitte erst dort als Bot-Account eintragen.',
				], 400);
			}
			$old = $this->config->getBotUser();
			if ($old !== $new) {
				$this->config->setBotUser($new);
				$changes[] = "bot_user: '$old' → '$new'";
			}
		}

		if ($webhook_url !== null) {
			$new = trim($webhook_url);
			if ($new !== '' && !filter_var($new, FILTER_VALIDATE_URL)) {
				return new DataResponse(['error' => 'Ungültige Webhook-URL'], 400);
			}
			$old = $this->config->getWebhookUrl();
			if ($old !== $new) {
				$this->config->setWebhookUrl($new);
				$changes[] = "webhook_url: '$old' → '$new'";
			}
		}

		if ($webhook_secret !== null && $webhook_secret !== '') {
			$this->config->setWebhookSecret($webhook_secret);
			$changes[] = 'webhook_secret: rotated (len=' . strlen($webhook_secret) . ')';
		}

		// Audit-Log: jede effektive Settings-Änderung wird mit Actor protokolliert
		if (!empty($changes)) {
			$this->logger->warning(
				'bertha_webhook: Settings geändert durch "' . $actor . '" — '
				. implode(', ', $changes),
				['app' => Application::APP_ID, 'audit' => true, 'actor' => $actor]
			);
		}

		return new DataResponse($this->config->getConfigForUi());
	}
}
