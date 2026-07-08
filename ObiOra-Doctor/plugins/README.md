# Obiora Doctor Plugins

Place custom diagnostic modules in this directory.

Each plugin must expose a `DiagnosticModule` subclass and register itself
in a local `registry.py` or be imported dynamically in a future release.

Example skeleton:

```python
from core.models import Finding, Severity
from core.module import DiagnosticModule

class MyPluginModule(DiagnosticModule):
    name = "myplugin"
    title = "My Plugin"

    def scan(self, context):
        return {"metrics": {}}

    def diagnostic(self, raw_data, context):
        return [Finding(Severity.INFO, "OK", "Plugin actif.")]
```

See `docs/plugin-sdk.md` for the full contract.
