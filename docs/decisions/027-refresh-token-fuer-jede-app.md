# 027 — Refresh-Token für jede App

**Status:** Akzeptiert (2026-11-13) · ersetzt in ADR-024 „Refresh-Token nur bei `offline_access`“

## Kontext

ChatGPT verlor die Verbindung zum Blog jede Stunde und bat neu um Zustimmung (neun Zustimmungen vom 12. bis 13. November). Es fordert genau den Scope aus dem 401-Hinweis an (`blog:read blog:write`), ohne `offline_access`. Der Server gab deshalb kein Refresh-Token aus, und mit dem Access-Token nach einer Stunde endete die Verbindung. Die Zustimmungsseite versprach dagegen schon „hält, bis du sie trennst oder sie 60 Tage nicht benutzt wird“.

## Optionen

- Refresh-Token immer ausgeben.
- `offline_access` in den 401-Hinweis aufnehmen, damit Apps es selbst anfordern.
- Access-Token länger gültig machen (24 Stunden), ohne Refresh-Token.

## Entscheidung

- **Jede App bekommt beim Code-Tausch ein Refresh-Token** (Entscheidung des Inhabers). `offline_access` bleibt als Scope gültig und steht in den Metadaten, steuert aber nichts mehr. Laufzeiten, Rotation, Kulanz und Zweig-Regel bleiben wie in ADR-024.

## Verworfen

- **Hinweis um `offline_access` ergänzen**: hängt davon ab, dass jede App den Hinweis wörtlich übernimmt; Grok und lokale Modelle (ADR-025) tun es vielleicht nicht.
- **Längeres Access-Token**: ChatGPT müsste weiter täglich neu um Zustimmung bitten, ein gestohlenes Token gälte länger.

## Konsequenzen

- Eine Verbindung hält, bis der Inhaber sie unter „Verbundene Apps“ trennt oder sie 60 Tage nicht benutzt wird — für jede App, wie die Zustimmungsseite es sagt.
- Bestehende ChatGPT-Verbindungen haben kein Refresh-Token; sie halten erst nach einer neuen Zustimmung.
- Offen: Erneuert ChatGPT sehr oft oder legt ein abgelöstes Refresh-Token nach mehr als 30 s noch einmal vor, widerruft die Wiederverwendungs-Regel die ganze Verbindung (Audit `oauth.reuse`).
