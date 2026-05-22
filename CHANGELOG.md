# Changelog

## 0.1.0 (2026-05-22)

- Initial release
- Listener auf `OCA\Talk\Events\ChatMessageSentEvent`
- Filterung: nur 1:1-Räume, nur wenn Bot-User Teilnehmer ist, kein Echo eigener Nachrichten
- HMAC-SHA256-Signatur via `X-Bertha-Random` + `X-Bertha-Signature` Headers
- Admin-Settings-UI für Bot-User, Webhook-URL und Shared Secret
- Kompatibel mit Nextcloud 30–32
