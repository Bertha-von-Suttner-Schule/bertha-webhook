<?php

declare(strict_types=1);

namespace OCA\BerthaWebhook\Listener;

use OCA\BerthaWebhook\AppInfo\Application;
use OCA\BerthaWebhook\Service\AppConfigService;
use OCA\Talk\Events\ReactionAddedEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Room;
use OCP\App\IAppManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Http\Client\IClientService;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * Lauscht auf Emoji-Reactions in Nextcloud Talk.
 *
 * Filtert auf 1:1-Unterhaltungen mit dem konfigurierten Bot-User und leitet
 * relevante Reactions per HMAC-signiertem Webhook weiter, damit der Push
 * Receiver sie als Intent (Bestaetigung/Ablehnung/Erklaere) verarbeiten kann.
 *
 * Nur ReactionAddedEvent — entfernte Reactions triggern keinen Intent.
 *
 * Endlosschleifen-Schutz: Reactions des Bot-Users selbst werden ignoriert,
 * damit bertha.ki's eigene Reactions (Eingangs-ACK 👀, react-Tool ✅/❌)
 * nicht zurueck durch die Pipeline laufen.
 *
 * @implements IEventListener<ReactionAddedEvent>
 */
class ReactionListener implements IEventListener {

	public function __construct(
		private IClientService $clientService,
		private AppConfigService $config,
		private IAppManager $appManager,
		private IUserManager $userManager,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof ReactionAddedEvent)) {
			return;
		}

		$room = $event->getRoom();

		$botUserId = $this->config->getBotUser();
		if ($botUserId === '') {
			return;
		}

		// Schutz gegen Endlosschleife: eigene Reactions ignorieren
		if ($event->getActorType() !== 'users' || $event->getActorId() === $botUserId) {
			return;
		}

		if ($room->getType() !== Room::TYPE_ONE_TO_ONE) {
			return;
		}

		if (!$this->config->isAllowedBot($botUserId)) {
			return;
		}

		$actor = $this->userManager->get($event->getActorId());
		if ($actor === null || !$this->appManager->isEnabledForUser(Application::APP_ID, $actor)) {
			return;
		}

		try {
			$room->getParticipant($botUserId);
		} catch (ParticipantNotFoundException) {
			return;
		}

		$this->forwardReaction($event, $room);
	}

	private function forwardReaction(ReactionAddedEvent $event, Room $room): void {
		$webhookUrl = $this->config->getWebhookUrl();
		$webhookSecret = $this->config->getWebhookSecret();

		if ($webhookUrl === '' || $webhookSecret === '') {
			return;
		}

		$targetMessage = $event->getMessage();
		$reactionMessage = $event->getReactionMessage();
		$reactionMessageId = $reactionMessage !== null
			? (string) $reactionMessage->getId()
			: ($targetMessage->getId() . '-' . time());

		$payload = json_encode([
			'userId' => $event->getActorId(),
			'displayName' => $event->getActorDisplayName(),
			'message' => $event->getReaction(),
			'conversationToken' => $room->getToken(),
			'messageId' => 'reaction-' . $reactionMessageId,
			'timestamp' => time(),
			'messageType' => 'reaction',
			'reaction' => $event->getReaction(),
			'targetMessage' => [
				'id' => (int) $targetMessage->getId(),
				'actorType' => $targetMessage->getActorType(),
				'actorId' => $targetMessage->getActorId(),
				'message' => $targetMessage->getMessage(),
				'verb' => $targetMessage->getVerb(),
				'confidence' => 'explicit',
			],
			'messageParameters' => [],
			'hasFilePlaceholder' => false,
			'parent' => null,
		], JSON_THROW_ON_ERROR);

		$random = bin2hex(random_bytes(32));
		$signature = hash_hmac('sha256', $random . $payload, $webhookSecret);

		try {
			$client = $this->clientService->newClient();
			$client->post($webhookUrl, [
				'headers' => [
					'Content-Type' => 'application/json',
					'X-Bertha-Random' => $random,
					'X-Bertha-Signature' => $signature,
				],
				'body' => $payload,
				'timeout' => 5,
			]);
		} catch (\Exception $e) {
			$this->logger->error(
				'bertha_webhook: Reaction-Webhook fehlgeschlagen: ' . $e->getMessage(),
				['app' => Application::APP_ID]
			);
		}
	}
}
