(function () {
	'use strict';

	const initial = OCP.InitialState.loadState('bertha_webhook', 'config') || {};
	const $ = (id) => document.getElementById(id);
	const form = $('bertha-webhook-form');
	const statusEl = $('bertha-status');
	const secretStatus = $('bertha-secret-status');

	function paintStatus() {
		$('bertha-bot-user').value = initial.bot_user || '';
		$('bertha-webhook-url').value = initial.webhook_url || '';
		secretStatus.textContent = initial.webhook_secret_set
			? OC.L10N.translate('bertha_webhook', 'gesetzt')
			: OC.L10N.translate('bertha_webhook', 'NICHT gesetzt');
		secretStatus.style.color = initial.webhook_secret_set ? 'var(--color-success)' : 'var(--color-warning)';
	}

	function setStatus(msg, type) {
		statusEl.textContent = msg;
		statusEl.className = 'bertha-status ' + (type || '');
		if (type === 'success') {
			setTimeout(() => { statusEl.textContent = ''; statusEl.className = 'bertha-status'; }, 3000);
		}
	}

	form.addEventListener('submit', async (ev) => {
		ev.preventDefault();
		setStatus(OC.L10N.translate('bertha_webhook', 'Speichern …'), 'pending');
		const body = {
			bot_user: $('bertha-bot-user').value,
			webhook_url: $('bertha-webhook-url').value,
		};
		const secret = $('bertha-webhook-secret').value;
		if (secret) body.webhook_secret = secret;

		try {
			const resp = await fetch(OC.generateUrl('/apps/bertha_webhook/api/v1/admin/settings'), {
				method: 'PUT',
				headers: {
					'Content-Type': 'application/json',
					'requesttoken': OC.requestToken,
					'OCS-APIREQUEST': 'true',
				},
				body: JSON.stringify(body),
			});
			if (!resp.ok) {
				const data = await resp.json().catch(() => ({}));
				throw new Error(data.error || resp.statusText);
			}
			const data = await resp.json();
			Object.assign(initial, data);
			$('bertha-webhook-secret').value = '';
			paintStatus();
			setStatus(OC.L10N.translate('bertha_webhook', 'Gespeichert.'), 'success');
		} catch (e) {
			setStatus(OC.L10N.translate('bertha_webhook', 'Fehler: ') + e.message, 'error');
		}
	});

	paintStatus();
})();
