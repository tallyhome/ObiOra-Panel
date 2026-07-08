# Plugin SDK

## Creer un plugin

1. Creer un fichier dans `plugins/`
2. Heriter de `DiagnosticModule`
3. Implementer `scan()` et `diagnostic()`
4. Retourner des `Finding` avec severite INFO, WARNING ou CRITICAL

## Exemple

```python
from core.models import Finding, Severity
from core.module import DiagnosticModule

class LaravelModule(DiagnosticModule):
    name = "laravel"
    title = "Laravel"

    def scan(self, context):
        return {"metrics": {"apps": 0}}

    def diagnostic(self, raw_data, context):
        return [Finding(Severity.INFO, "Laravel", "Scan initial.")]
```

## Enregistrement

Les plugins externes seront charges dynamiquement dans une version future.
Pour l'instant, ajouter le module dans `modules/registry.py`.
