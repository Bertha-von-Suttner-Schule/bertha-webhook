# Bertha Webhook Bridge

Nextcloud-App, die Chat-Nachrichten und File-Shares aus 1:1-Raeumen mit einem konfigurierten Bot-User per HMAC-signiertem Webhook an einen externen Endpunkt (z.B. n8n) weiterleitet.

Teil der [bertha.Cloud](https://cloud.bertha-online.de)-Infrastruktur der Bertha-von-Suttner-Schule.

## Funktionsweise

Zwei Event-Listener decken unterschiedliche Nachrichtentypen ab:

**ChatMessageListener** (`ChatMessageSentEvent`) — Textnachrichten und Commands:
- Payload: `{userId, message, conversationToken, messageId, timestamp, messageType, messageParameters, hasFilePlaceholder}`

**FileShareListener** (`ShareCreatedEvent`) — Dateianhänge und Sprachnachrichten:
- Fängt File-Shares an Talk-Räume ab (`shareType=10`)
- Nötig weil `ChatMessageSentEvent` nicht für File-Shares feuert (Talk erstellt die `{file}`-Nachricht über einen internen Code-Pfad)
- Audio-Dateien (`audio/*`) werden als `type: "voice-message"` markiert
- Payload: `{userId, message: "{file}", conversationToken, messageId: "share-<id>", timestamp, messageType: "file-share", messageParameters: {file: {...}}}`

**Gemeinsame Filterung** (beide Listener):
- Nur 1:1-Räume (`Room::TYPE_ONE_TO_ONE`) — Gruppenräume werden ignoriert
- Bot-User muss Teilnehmer sein
- Bot-User muss in NC-Gruppe `_bots` sein (Whitelist gegen unbeabsichtigte Konfiguration)
- Absender muss die App nutzen dürfen (NC-Standard-Gruppen-Beschränkung, Verwaltung → Apps)
- Eigene Nachrichten/Shares des Bots werden ignoriert (kein Echo)

**Webhook-Format** (beide Listener identisch):
- POST an die konfigurierte Webhook-URL
- Header `X-Bertha-Random` (32 Bytes hex)
- Header `X-Bertha-Signature` (HMAC-SHA256 über `random + body`)

## Installation

### Variante A: Stable Release (empfohlen)

```bash
cd /var/www/html/custom_apps  # oder /var/www/html/apps-extra, je nach NC-Setup
curl -fsSL -o bertha_webhook.tar.gz \
  https://github.com/Bertha-von-Suttner-Schule/bertha-webhook/releases/latest/download/bertha_webhook-latest.tar.gz
tar -xzf bertha_webhook.tar.gz
rm bertha_webhook.tar.gz
chown -R www-data:www-data bertha_webhook

sudo -u www-data php /var/www/html/occ app:enable bertha_webhook
```

### Variante B: Aus Git

```bash
cd /var/www/html/custom_apps
git clone https://github.com/Bertha-von-Suttner-Schule/bertha-webhook.git bertha_webhook
chown -R www-data:www-data bertha_webhook
sudo -u www-data php /var/www/html/occ app:enable bertha_webhook
```

## Konfiguration

**Voraussetzung:** Lege in NC die Gruppe `_bots` an und füge dort den Bot-User-Account ein (z.B. `bertha.ki`). Nur Mitglieder dieser Gruppe sind als Bot zugelassen — eine Schranke gegen unbeabsichtigtes Weiterleiten regulärer 1:1-Chats.

**Pilot/User-Whitelist:** Wenn nur eine bestimmte Gruppe (z.B. `_bertha_pilot`) den Bot ansprechen darf: **Verwaltung → Apps → "Bertha Webhook Bridge" → "Auf Gruppen beschränken"** und die Pilot-Gruppe auswählen. Ohne Beschränkung können alle NC-Nutzer:innen den Bot anschreiben.

In Nextcloud: **Verwaltungseinstellungen → Bertha Webhook Bridge**

| Feld | Beispiel |
|---|---|
| Bot-User | Dropdown aus `_bots`-Gruppen-Mitgliedern (z.B. `bertha.ki`) |
| Webhook-URL | `https://n8n.example.com/webhook/bertha-talk-push` |
| Shared Secret | 64-stelliger Hex-String (mind. 32 Zeichen) |

Alle Setting-Änderungen werden mit Actor und Diff im NC-Log protokolliert (Audit).

Alternativ per `occ` (Bot-User-Validation greift weiterhin — User muss in `_bots` sein):

```bash
sudo -u www-data php occ group:add _bots
sudo -u www-data php occ group:adduser _bots bertha.ki
sudo -u www-data php occ config:app:set bertha_webhook bot_user --value="bertha.ki"
sudo -u www-data php occ config:app:set bertha_webhook webhook_url --value="https://n8n.example.com/webhook/bertha-talk-push"
sudo -u www-data php occ config:app:set bertha_webhook webhook_secret --value="<64-stelliger-hex-string>"
```

## HMAC-Validierung empfangsseitig

```javascript
// Node.js / n8n Code-Node
const crypto = require('crypto');
const random = headers['x-bertha-random'];
const signature = headers['x-bertha-signature'];
const expected = crypto.createHmac('sha256', WEBHOOK_SECRET)
  .update(random + rawBody)
  .digest('hex');
if (signature !== expected) throw new Error('HMAC validation failed');
```

> **Wichtig (n8n 2.x):** Der originale Request-Body liegt unter `$input.first().binary.data.data` als Base64, **nicht** unter `$input.first().json.rawBody`. PHP escaped `/` zu `\/` in JSON-Bodies, JavaScript nicht — eigene Re-Serialisierung schlägt fehl.

## Performance

Die App ist auf minimale Serverlast ausgelegt. Beide Listener verwenden gestaffelte Early-Returns — teure Operationen (DB-Queries, HTTP-Calls) laufen nur fuer die wenigen qualifizierenden Nachrichten.

**ChatMessageListener** — feuert bei jeder Talk-Nachricht:

| Pruefung | Kosten | Filtert |
|---|---|---|
| Bot konfiguriert? | AppConfig-Cache (RAM) | Wenn nicht konfiguriert |
| Eigene Nachricht? | String-Vergleich (RAM) | Bot-Echo |
| **1:1-Raum?** | **Bereits im Event geladen (RAM)** | **~95% aller Nachrichten** |
| Bot in `_bots`? | 1 DB-Query | Fehlkonfiguration |
| User darf App nutzen? | 1 DB-Query | Nicht freigeschaltete User |
| Bot ist Teilnehmer? | 1 DB-Query | 1:1-Raeume ohne Bot |

Der entscheidende Filter (`TYPE_ONE_TO_ONE`) ist ein reiner RAM-Check und verwirft den Grossteil aller Nachrichten (Gruppenraeume, Ankuendigungen, etc.) bevor eine DB-Query noetig ist.

**FileShareListener** — feuert bei jedem Share in Nextcloud:

| Pruefung | Kosten | Filtert |
|---|---|---|
| **Share-Typ = Talk-Raum?** | **Integer-Vergleich (RAM)** | **~99% aller Shares** |
| Weitere Pruefungen | Wie ChatMessageListener | Nur Talk-Room-Shares |

Normales Filesharing (an User, Gruppen, Links) wird nach einem einzigen Integer-Vergleich verworfen.

**Webhook-POST:** Der HTTP-Call zum Webhook-Endpoint blockiert den PHP-Prozess bis zum konfigurierten Timeout (5s). Das betrifft nur qualifizierende Nachrichten (1:1-Chats mit dem Bot). Bei einer Schule mit ~2200 Usern sind das typischerweise wenige Nachrichten pro Minute — vernachlaessigbar gegenueber der sonstigen Talk-Last.

## Lizenz

AGPL-3.0-or-later
