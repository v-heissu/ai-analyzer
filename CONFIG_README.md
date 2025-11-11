# Configurazione API Keys

## Setup Rapido

### 1. Crea il tuo file di configurazione

```bash
cp config.example.json config.json
```

### 2. Modifica `config.json` con le tue API keys

```json
{
  "apis": {
    "openai": {
      "key": "sk-proj-XXXXX"
    },
    "gemini": {
      "key": "AIzaXXXXX"
    },
    "claude": {
      "key": "sk-ant-XXXXX"
    },
    "grok": {
      "key": "xai-XXXXX"
    },
    "perplexity": {
      "key": "pplx-XXXXX"
    },
    "dataforseo": {
      "login": "tuaemail@example.com",
      "password": "tuapassword"
    }
  },
  "default_brand": "NomeTuoBrand"
}
```

**Nota:** I modelli si selezionano direttamente dai dropdown nell'interfaccia, non serve metterli nel config!

### 3. Ricarica la pagina

Le API keys verranno caricate automaticamente all'avvio!

## Sicurezza

- ✅ `config.json` è **escluso da git** (già nel `.gitignore`)
- ✅ Le tue chiavi **NON verranno mai committate**
- ✅ Solo `config.example.json` viene tracciato da git (senza chiavi)

## Ottenere le API Keys

### OpenAI
- Vai su https://platform.openai.com/api-keys
- Crea una nuova API key
- Copia la chiave che inizia con `sk-proj-...`

### Google Gemini
- Vai su https://aistudio.google.com/app/apikey
- Crea una nuova API key
- Copia la chiave che inizia con `AIza...`

### Anthropic Claude
- Vai su https://console.anthropic.com/settings/keys
- Crea una nuova API key
- Copia la chiave che inizia con `sk-ant-...`

### xAI Grok
- Vai su https://console.x.ai/
- Crea una nuova API key
- Copia la chiave che inizia con `xai-...`

### Perplexity
- Vai su https://www.perplexity.ai/settings/api
- Crea una nuova API key
- Copia la chiave che inizia con `pplx-...`

### DataForSEO
- Vai su https://dataforseo.com/
- Registrati e vai su API Dashboard
- Usa la tua email e password

## Nota Importante

**MAI committare `config.json` su git pubblico!**

Se per errore hai committato le chiavi:
1. Rigenera TUTTE le API keys dai rispettivi provider
2. Rimuovi il file dalla history git
3. Aggiorna il file `.gitignore`

## Selezione Modelli

I modelli AI si selezionano direttamente dai **dropdown nell'interfaccia**. Non serve specificarli nel config.json.

**Modelli consigliati:**
- **OpenAI**: `gpt-4o` (ottimo rapporto qualità/prezzo)
- **Gemini**: `gemini-2.5-flash` (veloce ed economico)
- **Claude**: `claude-3-5-sonnet-20241022` (qualità massima)
- **Grok**: `grok-beta` (unico disponibile)
- **Perplexity**: `llama-3.1-sonar-small-128k-online` (con ricerca web)
