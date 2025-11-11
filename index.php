<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Landscape Analyzer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/palette.css">
</head>
<body>
    <div class="container-fluid">
        <header class="row">
            <div class="col-12 text-center py-4">
                <h1 class="display-4 brand-primary">AI Landscape Analyzer</h1>
                <p class="lead text-secondary">Analisi competitiva dell'ecosistema AI in tempo reale</p>
            </div>
        </header>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-lg">
                    <div class="card-body">
                        <form id="analyzerForm">
                            <!-- Input principale -->
                            <div class="mb-4">
                                <label for="query" class="form-label h5">Query o Keyword</label>
                                <textarea class="form-control form-control-lg" id="query" rows="3" 
                                    placeholder="Inserisci una keyword o un prompt dettagliato..."></textarea>
                            </div>

                            <!-- Brand/Dominio -->
<div class="mb-4">
    <label for="brand" class="form-label h5">Brand/Dominio (opzionale)</label>
    <input type="text" class="form-control form-control-lg" id="brand" 
        placeholder="es. nike.com, Apple, Tesla...">
</div>

<!-- Debug Mode -->
<div class="mb-4">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" id="debugMode" value="1">
        <label class="form-check-label" for="debugMode">
            <strong>Debug Mode</strong> - Mostra risposte complete delle API
        </label>
    </div>
</div>
                            <!-- API Configuration Tabs -->
                            <div class="mb-4">
                                <h5 class="mb-3">Configurazione API</h5>
                                <ul class="nav nav-pills mb-3" id="apiTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="openai-tab" data-bs-toggle="pill" 
                                            data-bs-target="#openai-config" type="button">OpenAI</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="gemini-tab" data-bs-toggle="pill" 
                                            data-bs-target="#gemini-config" type="button">Gemini</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="claude-tab" data-bs-toggle="pill" 
                                            data-bs-target="#claude-config" type="button">Claude</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="grok-tab" data-bs-toggle="pill"
                                            data-bs-target="#grok-config" type="button">Grok</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="perplexity-tab" data-bs-toggle="pill"
                                            data-bs-target="#perplexity-config" type="button">Perplexity</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="dataforseo-tab" data-bs-toggle="pill"
                                            data-bs-target="#dataforseo-config" type="button">DataForSEO</button>
                                    </li>
                                </ul>

                                <div class="tab-content">
                                    <div class="tab-pane fade show active" id="openai-config">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" id="openai_key" 
                                                    placeholder="API Key OpenAI">
                                            </div>
                                            <div class="col-md-6">
                                                <select class="form-select" id="openai_model">
                                                    <option value="gpt-4o">GPT-4o</option>
                                                    <option value="gpt-4o-mini">GPT-4o Mini</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="gemini-config">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" id="gemini_key" 
                                                    placeholder="API Key Gemini">
                                            </div>
                                            <div class="col-md-6">
                                                <select class="form-select" id="gemini_model">
                                                    <option value="gemini-2.5-flash" selected>Gemini 2.5 Flash (Latest)</option>
                                                    <option value="gemini-1.5-flash">Gemini 1.5 Flash</option>
                                                    <option value="gemini-1.5-pro">Gemini 1.5 Pro</option>
                                                    <option value="gemini-2.0-flash-exp">Gemini 2.0 Flash (Experimental)</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="claude-config">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" id="claude_key" 
                                                    placeholder="API Key Claude">
                                            </div>
                                            <div class="col-md-6">
                                                <select class="form-select" id="claude_model">
                                                    <option value="claude-3-5-sonnet-20241022">Claude 3.5 Sonnet</option>
                                                    <option value="claude-3-5-haiku-20241022">Claude 3.5 Haiku</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="grok-config">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" id="grok_key"
                                                    placeholder="API Key Grok">
                                            </div>
                                            <div class="col-md-6">
                                                <select class="form-select" id="grok_model">
                                                    <option value="grok-beta">Grok Beta</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="perplexity-config">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" id="perplexity_key"
                                                    placeholder="API Key Perplexity">
                                            </div>
                                            <div class="col-md-6">
                                                <select class="form-select" id="perplexity_model">
                                                    <option value="llama-3.1-sonar-small-128k-online">Llama 3.1 Sonar Small Online</option>
                                                    <option value="llama-3.1-sonar-large-128k-online">Llama 3.1 Sonar Large Online</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="tab-pane fade" id="dataforseo-config">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" id="dataforseo_login" 
                                                    placeholder="DataForSEO Login">
                                            </div>
                                            <div class="col-md-6">
                                                <input type="password" class="form-control" id="dataforseo_password" 
                                                    placeholder="DataForSEO Password">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-primary btn-lg px-5 py-3">
                                    Analizza Landscape AI
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Container -->
        <div id="resultsContainer" class="row mt-5" style="display: none;">
            <!-- Progress Section -->
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5>Analisi in corso...</h5>
                        <div id="progressContainer"></div>
                    </div>
                </div>
            </div>

            <!-- Results will be loaded here -->
            <div id="resultsContent"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/countup.js/2.6.2/countUp.umd.js"></script>
    <script src="assets/app.js"></script>
</body>
</html>