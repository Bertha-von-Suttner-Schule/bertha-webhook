<?php

declare(strict_types=1);

namespace OCA\BerthaKi\AppInfo;

use OCA\BerthaKi\Listener\ChatMessageListener;
use OCA\BerthaKi\Listener\FileShareListener;
use OCA\BerthaKi\Listener\ReactionListener;
use OCA\Talk\Events\ChatMessageSentEvent;
use OCA\Talk\Events\ReactionAddedEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Share\Events\ShareCreatedEvent;

class Application extends App implements IBootstrap {

	public const APP_ID = 'bertha_ki';

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
		$context->registerEventListener(
			ReactionAddedEvent::class,
			ReactionListener::class
		);
	}

	public function boot(IBootContext $context): void {
	}
}
