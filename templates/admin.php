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

	<div id="bertha-no-group" class="bertha-warning" hidden>
		<strong>⚠️ <?php p($l->t('Gruppe fehlt:')); ?></strong>
		<?php print_unescaped($l->t('Die NC-Gruppe %s existiert noch nicht. Erstelle sie zuerst im Bereich "Konten" und füge dort die Bot-User-Accounts hinzu (z.B. <code>bertha.ki</code>). Erst danach kann hier ein Bot ausgewählt werden.', ['<code id="bertha-group-name"></code>'])); ?>
	</div>

	<div id="bertha-no-bots" class="bertha-warning" hidden>
		<strong>⚠️ <?php p($l->t('Keine Bot-User vorhanden:')); ?></strong>
		<?php print_unescaped($l->t('Die Gruppe %s existiert, ist aber leer. Füge mindestens einen User in diese Gruppe hinzu, der als Bot fungieren soll.', ['<code id="bertha-group-name-2"></code>'])); ?>
	</div>

	<form id="bertha-webhook-form" autocomplete="off">
		<div class="bertha-field">
			<label for="bertha-bot-user"><?php p($l->t('Bot-User')); ?></label>
			<select id="bertha-bot-user" name="bot_user">
				<option value=""><?php p($l->t('— bitte auswählen —')); ?></option>
			</select>
			<p class="bertha-help">
				<?php p($l->t('Nur User in der Gruppe')); ?>
				<code id="bertha-group-name-3">_bots</code>
				<?php p($l->t('sind als Bot zugelassen. Nachrichten in 1:1-Räumen mit diesem User werden weitergeleitet.')); ?>
			</p>
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

	<div class="bertha-info">
		<strong><?php p($l->t('Wer darf den Bot ansprechen?')); ?></strong>
		<?php print_unescaped($l->t('Diese App respektiert Nextclouds Standard-App-Berechtigungen. Um den Zugriff z.B. auf eine Pilot-Gruppe zu beschränken: <strong>Verwaltung → Apps → "Bertha Webhook Bridge" → "Auf Gruppen beschränken"</strong> und die gewünschte Gruppe auswählen. Ohne Einschränkung können alle Nutzer:innen den Bot anschreiben.')); ?>
	</div>

	<p class="settings-hint bertha-audit-note">
		<?php p($l->t('Jede Änderung dieser Einstellungen wird im NC-Log mit Zeitpunkt und ändernder Person protokolliert.')); ?>
	</p>
</div>
