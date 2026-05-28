(function () {
	'use strict';

	const state = OCP.InitialState.loadState('bertha_ki', 'config') || {};
	const $ = (id) => document.getElementById(id);
	const form = $('bertha-ki-form');
	const statusEl = $('bertha-status');
	const secretStatus = $('bertha-secret-status');
	const select = $('bertha-bot-user');
	const noGroup = $('bertha-no-group');
	const noBots = $('bertha-no-bots');
	const saveBtn = $('bertha-save');

	function paintFromState() {
		// Group-Hint
		[$('bertha-group-name'), $('bertha-group-name-2'), $('bertha-group-name-3')].forEach((el) => {
			if (el) el.textContent = state.bots_group || '_bots';
		});

		if (!state.bots_group_exists) {
			noGroup.hidden = false;
			noBots.hidden = true;
			saveBtn.disabled = true;
		} else if (!state.available_bots || state.available_bots.length === 0) {
			noGroup.hidden = true;
			noBots.hidden = false;
			saveBtn.disabled = true;
		} else {
			noGroup.hidden = true;
			noBots.hidden = true;
			saveBtn.disabled = false;
		}

		// Dropdown
		const placeholder = select.querySelector('option[value=""]');
		select.innerHTML = '';
		if (placeholder) select.appendChild(placeholder);
		(state.available_bots || []).forEach((bot) => {
			const opt = document.createElement('option');
			opt.value = bot.uid;
			opt.textContent = `${bot.displayName} (${bot.uid})`;
			if (bot.uid === state.bot_user) opt.selected = true;
			select.appendChild(opt);
		});

		$('bertha-ki-url').value = state.webhook_url || '';
		secretStatus.textContent = state.webhook_secret_set
			? t('bertha_ki', 'gesetzt')
			: t('bertha_ki', 'NICHT gesetzt');
		secretStatus.style.color = state.webhook_secret_set
			? 'var(--color-success)'
			: 'var(--color-warning)';
	}

	function setStatus(msg, type) {
		statusEl.textContent = msg;
		statusEl.className = 'bertha-status ' + (type || '');
		if (type === 'success') {
			setTimeout(() => {
				statusEl.textContent = '';
				statusEl.className = 'bertha-status';
			}, 3000);
		}
	}

	form.addEventListener('submit', async (ev) => {
		ev.preventDefault();
		if (saveBtn.disabled) return;
		setStatus(t('bertha_ki', 'Speichern …'), 'pending');

		const body = {
			bot_user: select.value,
			webhook_url: $('bertha-ki-url').value,
		};
		const secret = $('bertha-ki-secret').value;
		if (secret) body.webhook_secret = secret;

		try {
			const resp = await fetch(
				OC.generateUrl('/apps/bertha_ki/api/v1/admin/settings'),
				{
					method: 'PUT',
					headers: {
						'Content-Type': 'application/json',
						requesttoken: OC.requestToken,
						'OCS-APIREQUEST': 'true',
					},
					body: JSON.stringify(body),
				}
			);
			if (!resp.ok) {
				const data = await resp.json().catch(() => ({}));
				throw new Error(data.error || resp.statusText);
			}
			const data = await resp.json();
			Object.assign(state, data);
			$('bertha-ki-secret').value = '';
			paintFromState();
			setStatus(t('bertha_ki', 'Gespeichert.'), 'success');
		} catch (e) {
			setStatus(t('bertha_ki', 'Fehler: ') + e.message, 'error');
		}
	});

	paintFromState();
})();
