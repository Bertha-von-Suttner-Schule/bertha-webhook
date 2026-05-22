<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
script('bertha_webhook', 'admin');
style('bertha_webhook', 'admin');
?>

<div id="bertha-webhook-admin" class="section">
	<h2><?php p($l->t('Bertha Webhook Bridge')); ?></h2>
	<p class="settings-hint">
		<?php p($l->t('Leitet 1:1-Chat-Nachrichten an einen Bot-User per HMAC-signiertem Webhook weiter.')); ?>
	</p>

	<form id="bertha-webhook-form" autocomplete="off">
		<div class="bertha-field">
			<label for="bertha-bot-user"><?php p($l->t('Bot-User-ID')); ?></label>
			<input type="text" id="bertha-bot-user" name="bot_user"
				placeholder="bertha.ki"
				autocomplete="off" />
			<p class="bertha-help"><?php p($l->t('Nextcloud-User-ID des Bots. Nur Nachrichten in 1:1-Räumen mit diesem User werden weitergeleitet.')); ?></p>
		</div>

		<div class="bertha-field">
			<label for="bertha-webhook-url"><?php p($l->t('Webhook-URL')); ?></label>
			<input type="url" id="bertha-webhook-url" name="webhook_url"
				placeholder="https://n8n.example.com/webhook/bertha-talk-push"
				autocomplete="off" />
			<p class="bertha-help"><?php p($l->t('HTTPS-Endpunkt, der den signierten POST empfängt.')); ?></p>
		</div>

		<div class="bertha-field">
			<label for="bertha-webhook-secret"><?php p($l->t('Shared Secret')); ?></label>
			<input type="password" id="bertha-webhook-secret" name="webhook_secret"
				placeholder="<?php p($l->t('Leer lassen, um bestehendes Secret zu behalten')); ?>"
				autocomplete="new-password" />
			<p class="bertha-help"><?php p($l->t('Mindestens 32 Zeichen empfohlen. Status:')); ?>
				<strong id="bertha-secret-status">…</strong></p>
		</div>

		<div class="bertha-actions">
			<button type="submit" id="bertha-save" class="primary"><?php p($l->t('Speichern')); ?></button>
			<span id="bertha-status" class="bertha-status"></span>
		</div>
	</form>
</div>
