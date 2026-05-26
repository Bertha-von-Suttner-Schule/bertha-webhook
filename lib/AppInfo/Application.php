<?php

declare(strict_types=1);

namespace OCA\BerthaWebhook\AppInfo;

use OCA\BerthaWebhook\Listener\ChatMessageListener;
use OCA\BerthaWebhook\Listener\FileShareListener;
use OCA\Talk\Events\ChatMessageSentEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Share\Events\ShareCreatedEvent;

class Application extends App implements IBootstrap {

	public const APP_ID = 'bertha_webhook';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(
			ChatMessageSentEvent::class,
			ChatMessageListener::class
		);
		$context->registerEventListener(
			ShareCreatedEvent::class,
			FileShareListener::class
		);
	}

	public function boot(IBootContext $context): void {
	}
}
