<?php

declare(strict_types=1);

namespace OCA\BerthaWebhook\Listener;

use OCA\BerthaWebhook\AppInfo\Application;
use OCA\BerthaWebhook\Service\AppConfigService;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Manager as TalkManager;
use OCA\Talk\Room;
use OCP\App\IAppManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Http\Client\IClientService;
use OCP\IUserManager;
use OCP\Share\Events\ShareCreatedEvent;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * Lauscht auf File-Shares an Talk-Raeume.
 *
 * ChatMessageSentEvent feuert NICHT fuer File-Shares — Talk erstellt die
 * {file}-Nachricht ueber einen internen Code-Pfad. Dieser Listener faengt
 * das ShareCreatedEvent ab und sendet einen Webhook mit File-Info, damit
 * der Push Receiver Dateianhänge und Sprachnachrichten verarbeiten kann.
 *
 * @implements IEventListener<ShareCreatedEvent>
 */
class FileShareListener implements IEventListener {

	public function __construct(
		private IClientService $clientService,
		private AppConfigService $config,
		private IAppManager $appManager,
		private IUserManager $userManager,
		private TalkManager $talkManager,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!($event instanceof ShareCreatedEvent)) {
			return;
		}

		$share = $event->getShare();

		if ($share->getShareType() !== IShare::TYPE_ROOM) {
			return;
		}

		$botUserId = $this->config->getBotUser();
		if ($botUserId === '') {
			return;
		}

		if ($share->getSharedBy() === $botUserId) {
			return;
		}

		if (!$this->config->isAllowedBot($botUserId)) {
			return;
		}

		$sharer = $this->userManager->get($share->getSharedBy());
		if ($sharer === null || !$this->appManager->isEnabledForUser(Application::APP_ID, $sharer)) {
			return;
		}

		$roomToken = $share->getSharedWith();
		try {
			$room = $this->talkManager->getRoomByToken($roomToken);
		} catch (\Exception $e) {
			return;
		}

		if ($room->getType() !== Room::TYPE_ONE_TO_ONE) {
			return;
		}

		try {
			$room->getParticipant($botUserId);
		} catch (ParticipantNotFoundException) {
			return;
		}

		$this->forwardFileShare($share, $room);
	}

	private function forwardFileShare(IShare $share, Room $room): void {
		$webhookUrl = $this->config->getWebhookUrl();
		$webhookSecret = $this->config->getWebhookSecret();

		if ($webhookUrl === '' || $webhookSecret === '') {
			return;
		}

		$node = $share->getNode();
		$mimetype = $node->getMimetype();

		$fileType = 'file';
		if (str_starts_with($mimetype, 'audio/')) {
			$fileType = 'voice-message';
		}

		$payload = json_encode([
			'userId' => $share->getSharedBy(),
			'message' => '{file}',
			'conversationToken' => $room->getToken(),
			'messageId' => 'share-' . $share->getId(),
			'timestamp' => time(),
			'messageType' => 'file-share',
			'messageParameters' => [
				'file' => [
					'type' => $fileType,
					'id' => (string) $node->getId(),
					'name' => $node->getName(),
					'mimetype' => $mimetype,
					'size' => $node->getSize(),
					'path' => $node->getName(),
				],
			],
			'hasFilePlaceholder' => true,
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
				'bertha_webhook: File-Share-Webhook fehlgeschlagen: ' . $e->getMessage(),
				['app' => Application::APP_ID]
			);
		}
	}
}
