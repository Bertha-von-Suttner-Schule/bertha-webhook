# Changelog

## 0.2.2 (2026-05-26)

**Rebranding + Icon**

- Display-Name geaendert: "Bertha Webhook Bridge" → "bertha.ki" (ueberall: info.xml, Admin-Settings, Kommentare)
- Neues App-Icon: Chat-Bubble mit Neural-Net-Dots und KI-Spark
- APP_ID bleibt `bertha_webhook` (kein Reinstall noetig). Volle APP_ID-Migration auf `bertha_ki` ist fuer ein kuenftiges Major-Release vorgemerkt

## 0.2.1 (2026-05-26)

**File-Share-Listener**

- Neuer `FileShareListener` fuer `ShareCreatedEvent`: faengt File-Shares an Talk 1:1-Raeume ab und sendet Webhook mit File-Metadaten
- Gleiche Sicherheits-Schranken wie ChatMessageListener (Bot in `_bots`, App-Gruppen-Restriction, 1:1-Check)
- Noetig weil `ChatMessageSentEvent` NICHT fuer File-Shares feuert — Talk erstellt die `{file}`-Nachricht ueber einen internen Code-Pfad
- Audio-Dateien (`audio/*`) werden als `type: "voice-message"` markiert fuer STT-Pipeline

## 0.2.0 (2026-05-26)

**Payload-Erweiterung fuer File-Support**

- Webhook-Payload enthaelt jetzt `messageType` und `messageParameters` aus dem Talk ChatMessage-Objekt
- Ermoeglicht dem Push Receiver die Verarbeitung von Dateianhängen (PDF, Bilder, Spreadsheets) und Sprachnachrichten
- Neues Feld `hasFilePlaceholder` als Fallback-Signal wenn `getChatMessage()` nicht verfuegbar ist
- Fallback: Falls Talk-API `getChatMessage()` nicht unterstuetzt, wird `messageType: 'unknown'` gesendet — n8n holt Details per Talk API nach

## 0.1.2 (2026-05-22)

**User-Whitelist über NC-Standard-App-Group-Restriction**

- Listener respektiert NC's eingebaute App-Group-Beschränkung: nur Absender, für die die App aktiviert ist (Verwaltung → Apps → "Bertha Webhook Bridge" → "Auf Gruppen beschränken"), lösen einen Webhook aus
- Pilotbetrieb möglich ohne eigenes Setting — Admin legt einfach eine Pilot-Gruppe an, aktiviert die App auf diese Gruppe, alle anderen User werden silent ignoriert
- Settings-UI: Hinweis-Box erklärt, wo die Einschränkung gesetzt wird

## 0.1.1 (2026-05-22)

**Security Hardening**

- Bot-User-Whitelist: Der konfigurierte `bot_user` muss Mitglied in der NC-Gruppe `_bots` sein, sonst wird der Listener silent (verhindert Leak privater 1:1-Chats bei Setting-Fehlbedienung)
- Settings-UI zeigt ein Dropdown statt Freitext — nur User aus `_bots` wählbar
- Settings-Änderungen werden ins NC-Log geschrieben (Audit-Log, inkl. Actor + diff)
- Settings-UI informiert sichtbar, falls Gruppe `_bots` fehlt oder leer ist

## 0.1.0 (2026-05-22)

- Initial release
- Listener auf `OCA\Talk\Events\ChatMessageSentEvent`
- Filterung: nur 1:1-Räume, nur wenn Bot-User Teilnehmer ist, kein Echo eigener Nachrichten
- HMAC-SHA256-Signatur via `X-Bertha-Random` + `X-Bertha-Signature` Headers
- Admin-Settings-UI für Bot-User, Webhook-URL und Shared Secret
- Kompatibel mit Nextcloud 30–32
