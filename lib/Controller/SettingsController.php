<?php

declare(strict_types=1);

namespace OCA\BerthaWebhook\Controller;

use OCA\BerthaWebhook\Service\AppConfigService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class SettingsController extends Controller {

	public function __construct(
		string $appName,
		IRequest $request,
		private AppConfigService $config,
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
		if ($bot_user !== null) {
			$this->config->setBotUser(trim($bot_user));
		}
		if ($webhook_url !== null) {
			$url = trim($webhook_url);
			if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
				return new DataResponse(['error' => 'Ungültige Webhook-URL'], 400);
			}
			$this->config->setWebhookUrl($url);
		}
		if ($webhook_secret !== null && $webhook_secret !== '') {
			$this->config->setWebhookSecret($webhook_secret);
		}
		return new DataResponse($this->config->getConfigForUi());
	}
}
