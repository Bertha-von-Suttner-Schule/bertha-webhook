<?php

declare(strict_types=1);

namespace OCA\BerthaWebhook\Listener;

use OCA\BerthaWebhook\AppInfo\Application;
use OCA\BerthaWebhook\Service\AppConfigService;
use OCA\Talk\Events\ChatMessageSentEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Room;
use OCP\App\IAppManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Http\Client\IClientService;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * Lauscht auf Chat-Nachrichten in Nextcloud Talk.
 *
 * Filtert auf 1:1-Unterhaltungen mit dem konfigurierten Bot-User
 * und leitet relevante Nachrichten per HMAC-signiertem Webhook weiter.
 *
 * Sicherheits-Schranken:
 * - Bot-User muss Mitglied in NC-Gruppe `_bots` sein
 *   (verhindert Leak privater 1:1-Chats bei Setting-Fehlbedienung)
 * - Absender muss die App nutzen dürfen (NC-Standard-App-Gruppen-Restriction).
 *   Admin kann das in NC-Admin-UI → Apps → "bertha.ki" einstellen.
 *   So lässt sich der Pilotbetrieb auf eine Gruppe (z.B. `_bertha_pilot`) einschränken.
 *
 * @implements IEventListener<ChatMessageSentEvent>
 */
class ChatMessageListener implements IEventListener {

	public function __construct(
		private IClientService $clientService,
		private AppConfigService $config,
		private IAppManager $appManager,
		private IUserManager $userManager,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof ChatMessageSentEvent)) {
			return;
		}

		$room = $event->getRoom();
		$comment = $event->getComment();

		$botUserId = $this->config->getBotUser();
		if ($botUserId === '') {
			return;
		}

		// Eigene Nachrichten ignorieren (Antworten vom Bot selbst)
		if ($comment->getActorType() !== 'users' || $comment->getActorId() === $botUserId) {
			return;
		}

		// Nur 1:1-Unterhaltungen verarbeiten
		if ($room->getType() !== Room::TYPE_ONE_TO_ONE) {
			return;
		}

		// Schranke 1: Bot-User muss in `_bots`-Gruppe sein
		if (!$this->config->isAllowedBot($botUserId)) {
			$this->logger->warning(
				'bertha_webhook: Konfigurierter bot_user "' . $botUserId
				. '" ist nicht in Gruppe "' . AppConfigService::BOTS_GROUP
				. '". Nachricht wird nicht weitergeleitet.',
				['app' => Application::APP_ID]
			);
			return;
		}

		// Schranke 2: Absender muss die App nutzen dürfen
		// (respektiert NC's eingebaute App-Group-Restriction über Apps-Admin-UI)
		$actor = $this->userManager->get($comment->getActorId());
		if ($actor === null || !$this->appManager->isEnabledForUser(Application::APP_ID, $actor)) {
			// Schweigend droppen — kein Logging, sonst Log-Spam bei vielen unberechtigten Usern
			return;
		}

		// Bot-User muss in dieser Unterhaltung Teilnehmer sein
		try {
			$room->getParticipant($botUserId);
		} catch (ParticipantNotFoundException) {
			return;
		}

		$this->forwardToWebhook($event, $comment, $room);
	}

	/**
	 * Extrahiert Message-Metadaten aus dem IComment.
	 *
	 * Talk's parsed messageType und messageParameters sind im ChatMessageSentEvent
	 * NICHT verfuegbar — sie werden erst beim API-Serialisieren durch MessageParser
	 * aufgeloest. Wir setzen hier nur einen Fallback-Indikator hasFilePlaceholder
	 * fuer {file}/{object}-Pattern; die echten File-Daten kommen über den
	 * separaten FileShareListener (ShareCreatedEvent).
	 *
	 * @return array{messageType: string, messageParameters: array<string,mixed>, hasFilePlaceholder: bool}
	 */
	private function extractMessageMeta(\OCP\Comments\IComment $comment): array {
		$rawText = $comment->getMessage();
		return [
			'messageType' => 'unknown',
			'messageParameters' => [],
			'hasFilePlaceholder' => (bool) preg_match('/\{(file|object)\d*\}/', $rawText),
		];
	}

	/**
	 * Extrahiert Reply-Parent-Info aus dem Event.
	 *
	 * Talk's AMessageSentEvent::getParent() liefert das IComment der Nachricht,
	 * auf die geantwortet wurde — bei normalen Messages null, bei Replies/Threads
	 * der Parent-Comment.
	 *
	 * @return array{id: int, actorType: string, actorId: string, message: string, verb: string}|null
	 */
	private function extractParent(ChatMessageSentEvent $event): ?array {
		$parent = $event->getParent();
		if ($parent === null) {
			return null;
		}
		return [
			'id' => (int) $parent->getId(),
			'actorType' => $parent->getActorType(),
			'actorId' => $parent->getActorId(),
			'message' => $parent->getMessage(),
			'verb' => $parent->getVerb(),
		];
	}

	private function forwardToWebhook(ChatMessageSentEvent $event, \OCP\Comments\IComment $comment, Room $room): void {
		$webhookUrl = $this->config->getWebhookUrl();
		$webhookSecret = $this->config->getWebhookSecret();

		if ($webhookUrl === '') {
			$this->logger->warning('bertha_webhook: Keine webhook_url konfiguriert',
				['app' => Application::APP_ID]);
			return;
		}
		if ($webhookSecret === '') {
			$this->logger->warning('bertha_webhook: Kein webhook_secret konfiguriert',
				['app' => Application::APP_ID]);
			return;
		}

		$meta = $this->extractMessageMeta($comment);
		$parent = $this->extractParent($event);

		$payload = json_encode([
			'userId' => $comment->getActorId(),
			'message' => $comment->getMessage(),
			'conversationToken' => $room->getToken(),
			'messageId' => (int) $comment->getId(),
			'timestamp' => time(),
			'messageType' => $meta['messageType'],
			'messageParameters' => $meta['messageParameters'],
			'hasFilePlaceholder' => $meta['hasFilePlaceholder'],
			'parent' => $parent,
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
				'bertha_webhook: Webhook-Aufruf fehlgeschlagen: ' . $e->getMessage(),
				['app' => Application::APP_ID]
			);
		}
	}
}
