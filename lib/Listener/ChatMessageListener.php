<?php

declare(strict_types=1);

namespace OCA\BerthaWebhook\Listener;

use OCA\BerthaWebhook\AppInfo\Application;
use OCA\BerthaWebhook\Service\AppConfigService;
use OCA\Talk\Events\ChatMessageSentEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Room;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

/**
 * Lauscht auf Chat-Nachrichten in Nextcloud Talk.
 *
 * Filtert auf 1:1-Unterhaltungen mit dem konfigurierten Bot-User
 * und leitet relevante Nachrichten per HMAC-signiertem Webhook weiter.
 *
 * Sicherheits-Schranke: der konfigurierte Bot-User muss Mitglied in der
 * NC-Gruppe `_bots` sein, sonst wird die Nachricht schweigend verworfen.
 * Verhindert, dass durch fehlerhafte oder böswillige Settings-Änderung
 * private 1:1-Chats eines regulären Users an die Webhook-URL leaken.
 *
 * @implements IEventListener<ChatMessageSentEvent>
 */
class ChatMessageListener implements IEventListener {

	public function __construct(
		private IClientService $clientService,
		private AppConfigService $config,
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

		// Sicherheits-Schranke: Bot-User muss in der `_bots`-Gruppe sein
		if (!$this->config->isAllowedBot($botUserId)) {
			$this->logger->warning(
				'bertha_webhook: Konfigurierter bot_user "' . $botUserId
				. '" ist nicht in Gruppe "' . AppConfigService::BOTS_GROUP
				. '". Nachricht wird nicht weitergeleitet.',
				['app' => Application::APP_ID]
			);
			return;
		}

		// Bot-User muss in dieser Unterhaltung Teilnehmer sein
		try {
			$room->getParticipant($botUserId);
		} catch (ParticipantNotFoundException) {
			return;
		}

		$this->forwardToWebhook($comment, $room);
	}

	private function forwardToWebhook(\OCP\Comments\IComment $comment, Room $room): void {
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
				'bertha_webhook: Webhook-Aufruf fehlgeschlagen: ' . $e->getMessage(),
				['app' => Application::APP_ID]
			);
		}
	}
}
