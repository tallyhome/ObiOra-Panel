# Format des rapports

Chaque scan cree un dossier horodate contenant :

- `report.json` : format machine, schema versionne
- `report.md` : lecture humaine
- `report.html` : rapport client
- `report.txt` : sortie terminal

## Schema JSON

```json
{
  "version": "0.2.0",
  "generated_at": "2026-07-07T18:00:00+00:00",
  "host": { "hostname": "...", "system": "Linux", "os": {} },
  "score": 97,
  "results": [
    {
      "module": "ram",
      "status": "ok",
      "score": 96,
      "findings": [],
      "metrics": {},
      "duration_ms": 12
    }
  ]
}
```

## Mode support

`obiora-doctor scan --support` genere un rapport anonymise (IP, domaines, secrets rediges).
