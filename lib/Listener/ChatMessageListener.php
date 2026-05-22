<?php

declare(strict_types=1);

namespace OCA\BerthaWebhook\Listener;

use OCA\BerthaWebhook\AppInfo\Application;
use OCA\Talk\Events\ChatMessageSentEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Room;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use Psr\Log\LoggerInterface;

/**
 * Lauscht auf Chat-Nachrichten in Nextcloud Talk.
 *
 * Filtert auf 1:1-Unterhaltungen mit dem konfigurierten Bot-User
 * und leitet relevante Nachrichten per HMAC-signiertem Webhook weiter.
 *
 * @implements IEventListener<ChatMessageSentEvent>
 */
class ChatMessageListener implements IEventListener {

	public function __construct(
		private IClientService $clientService,
		private IAppConfig $appConfig,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof ChatMessageSentEvent)) {
			return;
		}

		$room = $event->getRoom();
		$comment = $event->getComment();

		$botUserId = $this->appConfig->getValueString(
			Application::APP_ID, 'bot_user', 'bertha.ki'
		);

		// Eigene Nachrichten ignorieren (Antworten von n8n als bertha.ki)
		if ($comment->getActorType() !== 'users' || $comment->getActorId() === $botUserId) {
			return;
		}

		// Nur 1:1-Unterhaltungen verarbeiten
		if ($room->getType() !== Room::TYPE_ONE_TO_ONE) {
			return;
		}

		// Pruefen ob der Bot-User in dieser Unterhaltung ist.
		// In einer 1:1-Unterhaltung gibt es exakt 2 Teilnehmer.
		// Wenn der Absender nicht der Bot ist UND der Bot nicht im Raum ist,
		// dann ist es eine fremde 1:1-Unterhaltung → ignorieren.
		try {
			$room->getParticipant($botUserId);
		} catch (ParticipantNotFoundException) {
			return;
		}

		$this->forwardToWebhook($comment, $room);
	}

	private function forwardToWebhook(\OCP\Comments\IComment $comment, Room $room): void {
		$webhookUrl = $this->appConfig->getValueString(
			Application::APP_ID, 'webhook_url', ''
		);
		$webhookSecret = $this->appConfig->getValueString(
			Application::APP_ID, 'webhook_secret', ''
		);

		if ($webhookUrl === '') {
			$this->logger->warning('bertha_webhook: Keine webhook_url konfiguriert');
			return;
		}

		$payload = json_encode([
			'userId' => $comment->getActorId(),
			'message' => $comment->getMessage(),
			'conversationToken' => $room->getToken(),
			'messageId' => (int) $comment->getId(),
			'timestamp' => time(),
		], JSON_THROW_ON_ERROR);

		// HMAC-Signatur generieren (gleiches Schema wie Talk Bot API)
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
				'bertha_webhook: Webhook-Aufruf fehlgeschlagen: ' . $e->getMessage()
			);
		}
	}
}
