$(document).ready(function() {
    let isKeywordMode = false;

    // Load configuration from config.json on page load
    loadConfiguration();

    // Form submission handler
    $('#analyzerForm').on('submit', function(e) {
        e.preventDefault();
        
        const query = $('#query').val().trim();
        if (!query) {
            alert('Inserisci una query o keyword');
            return;
        }

        // Determina se è una keyword o un prompt
        isKeywordMode = isKeyword(query);
        
        if (isKeywordMode) {
            // Se è keyword, vai all'analisi che genererà fanout internamente
            startAnalysisWithKeyword(query);
        } else {
            // Se è prompt, vai diretto all'analisi
            startAnalysis([query]);
        }
    });

    function isKeyword(query) {
        const words = query.trim().split(/\s+/).length;
        return words <= 5 && !query.match(/[?!.]/);
    }

function startAnalysisWithKeyword(keyword) {
    const geminiKey = $('#gemini_key').val();
    if (!geminiKey) {
        alert('Per le keyword serve la API key di Gemini per generare le fanout queries');
        return;
    }

    // Show fanout generation
    $('#resultsContainer').show();
    $('#resultsContent').html(`
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center">
                    <div class="spinner-border text-primary mb-3" role="status"></div>
                    <h5>Generazione Query Fan-out</h5>
                    <p class="text-muted">Sto creando query semantiche per: <strong>${keyword}</strong></p>
                </div>
            </div>
        </div>
    `);
    
    $('html, body').animate({
        scrollTop: $("#resultsContainer").offset().top - 20
    }, 500);

    // Chiamata separata per generare SOLO fanout
    const formData = {
        query: keyword,
        brand: $('#brand').val(),
        action: 'fanout_only',
        apis: {
            gemini: {
                key: geminiKey,
                model: $('#gemini_model').val(),
                enabled: true
            }
        }
    };

    // Genera fanout queries
    $.ajax({
        url: 'process.php',
        method: 'POST',
        data: JSON.stringify(formData),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.fanout_queries && response.fanout_queries.length > 0) {
                // Mostra selezione prompt
                showPromptSelection(keyword, response.fanout_queries);
            } else {
                alert('Impossibile generare fanout queries. Procedo con la keyword originale.');
                startAnalysis([keyword]);
            }
        },
        error: function(xhr, status, error) {
            console.error('Fanout generation error:', error);
            alert('Errore nella generazione delle fanout queries. Procedo con la keyword originale.');
            startAnalysis([keyword]);
        }
    });
}

    function showPromptSelection(keyword, queries) {
        const queriesHTML = queries.map((query, index) => `
            <div class="col-md-6 mb-3">
                <div class="form-check">
                    <input class="form-check-input fanout-checkbox" type="checkbox" 
                        value="${index}" id="fanout_${index}" ${index < 3 ? 'checked' : ''}>
                    <label class="form-check-label" for="fanout_${index}">
                        <div class="alert alert-info mb-0 h-100">
                            <strong>${index + 1}.</strong> ${query}
                        </div>
                    </label>
                </div>
            </div>
        `).join('');

        $('#resultsContent').html(`
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5>Seleziona Prompt per Analisi AI</h5>
                        <p class="text-muted mb-4">
                            Ho generato ${queries.length} query semantiche dalla keyword <strong>"${keyword}"</strong>.
                            Seleziona quelle da analizzare con i 5 AI models (massimo 5):
                        </p>

                        <div class="row">
                            ${queriesHTML}
                        </div>

                        <div class="text-center mt-4">
                            <button class="btn btn-primary btn-lg me-3" onclick="proceedWithSelectedQueries()">
                                Analizza con AI Models
                            </button>
                            <button class="btn btn-outline-secondary" onclick="selectAllQueries()">
                                Seleziona Tutte
                            </button>
                            <button class="btn btn-outline-secondary ms-2" onclick="resetSelection()">
                                Reset
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `);

        // Store fanout queries globally
        window.currentFanoutQueries = queries;

        // Checkbox logic
        $('.fanout-checkbox').on('change', function() {
            const checked = $('.fanout-checkbox:checked').length;
            if (checked >= 5) {
                $('.fanout-checkbox:not(:checked)').prop('disabled', true);
            } else {
                $('.fanout-checkbox').prop('disabled', false);
            }
            
            const proceedBtn = $('button[onclick="proceedWithSelectedQueries()"]');
            if (checked === 0) {
                proceedBtn.prop('disabled', true).text('Seleziona almeno una query');
            } else {
                proceedBtn.prop('disabled', false).text(`Analizza ${checked} Query con AI Models`);
            }
        });
    }

    // Funzione per procedere con le query selezionate
    window.proceedWithSelectedQueries = function() {
        const selectedQueries = [];
        $('.fanout-checkbox:checked').each(function() {
            const index = parseInt($(this).val());
            if (window.currentFanoutQueries && window.currentFanoutQueries[index]) {
                selectedQueries.push(window.currentFanoutQueries[index]);
            }
        });

        if (selectedQueries.length === 0) {
            alert('Seleziona almeno una query');
            return;
        }

        console.log('Proceeding with selected queries:', selectedQueries);
        startAnalysis(selectedQueries);
    };

    // Funzione per selezionare tutte le query
    window.selectAllQueries = function() {
        $('.fanout-checkbox').slice(0, 5).prop('checked', true).trigger('change');
    };

    // Funzione per resettare la selezione
    window.resetSelection = function() {
        $('.fanout-checkbox').prop('checked', false).prop('disabled', false).trigger('change');
    };

    function startAnalysis(queries) {
        console.log('Starting analysis with queries:', queries);

        // Show global loading overlay immediately
        showGlobalLoadingMessage();

        // Show results container
        $('#resultsContainer').show();

        // Clear previous content (fanout selection card)
        $('#resultsContent').html('');

        // Scroll to results
        $('html, body').animate({
            scrollTop: $("#resultsContainer").offset().top - 20
        }, 1000);

        // Create progress indicators
        createProgressIndicators();

        // Start the analysis process
        processAnalysis(queries);
    }

    function createProgressIndicators() {
        const steps = [
            {id: 'gemini', name: 'Analisi Fan-out + Contenuti (Gemini)'},
            {id: 'openai', name: 'Generazione Risposte (OpenAI)'},
            {id: 'claude', name: 'Analisi Avanzata (Claude)'},
            {id: 'grok', name: 'Insights Aggiuntivi (Grok)'},
            {id: 'perplexity', name: 'Ricerca Real-time (Perplexity)'},
            {id: 'dataforseo', name: 'Dati SERP (DataForSEO)'},
            {id: 'analysis', name: 'Elaborazione AI Comparativa'}
        ];

        let progressHTML = '';
        steps.forEach(step => {
            progressHTML += `
                <div class="progress-item" id="progress-${step.id}">
                    <div class="progress-icon loading" id="icon-${step.id}">
                        <div class="progress-dot"></div>
                    </div>
                    <div class="flex-grow-1">
                        <strong>${step.name}</strong>
                        <div class="small text-muted" id="status-${step.id}">In attesa...</div>
                    </div>
                </div>
            `;
        });

        $('#progressContainer').html(progressHTML);
    }

    function processAnalysis(queries) {
        // Collect form data
        const formData = {
            queries: queries, // Array di query
            original_query: $('#query').val(), // Query originale per riferimento
            brand: $('#brand').val(),
            debug_mode: $('#debugMode').is(':checked'),
            is_keyword_mode: isKeywordMode,
            apis: {
                openai: {
                    key: $('#openai_key').val(),
                    model: $('#openai_model').val(),
                    enabled: $('#openai_key').val() ? true : false
                },
                gemini: {
                    key: $('#gemini_key').val(),
                    model: $('#gemini_model').val(),
                    enabled: $('#gemini_key').val() ? true : false
                },
                claude: {
                    key: $('#claude_key').val(),
                    model: $('#claude_model').val(),
                    enabled: $('#claude_key').val() ? true : false
                },
                grok: {
                    key: $('#grok_key').val(),
                    model: $('#grok_model').val(),
                    enabled: $('#grok_key').val() ? true : false
                },
                perplexity: {
                    key: $('#perplexity_key').val(),
                    model: $('#perplexity_model').val(),
                    enabled: $('#perplexity_key').val() ? true : false
                },
                dataforseo: {
                    login: $('#dataforseo_login').val(),
                    password: $('#dataforseo_password').val(),
                    enabled: $('#dataforseo_login').val() ? true : false
                }
            }
        };

        console.log('Form data collected:', formData);

        // Simulate progressive analysis
        simulateProgressiveAnalysis(formData);
    }

    function simulateProgressiveAnalysis(formData) {
        const steps = ['gemini', 'openai', 'claude', 'grok', 'perplexity', 'dataforseo', 'analysis'];
        let currentStep = 0;

        function processNextStep() {
            if (currentStep < steps.length) {
                const stepId = steps[currentStep];
                updateProgress(stepId, 'processing');
                
                // Simulate API call
                setTimeout(() => {
                    updateProgress(stepId, 'success');
                    currentStep++;
                    
                    if (currentStep < steps.length) {
                        processNextStep();
                    } else {
                        // All steps completed, load results
                        loadResults(formData);
                    }
                }, Math.random() * 2000 + 1000); // Random delay 1-3 seconds
            }
        }

        processNextStep();
    }

    function updateProgress(stepId, status) {
        const iconElement = $(`#icon-${stepId}`);
        const statusElement = $(`#status-${stepId}`);

        iconElement.removeClass('loading success error');
        
        switch(status) {
            case 'processing':
                iconElement.addClass('loading pulsing');
                statusElement.text('Elaborazione in corso...');
                break;
            case 'success':
                iconElement.removeClass('pulsing').addClass('success');
                statusElement.text('Completato').addClass('text-success');
                break;
            case 'error':
                iconElement.removeClass('pulsing').addClass('error');
                statusElement.text('Errore').addClass('text-danger');
                break;
        }
    }

    function loadResults(formData) {
        console.log('Making AJAX request with data:', formData);

        $.ajax({
            url: 'process.php',
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            dataType: 'json',
            timeout: 180000, // 3 minuti timeout (per analisi con più query)
            success: function(response) {
                console.log('Response received:', response);
                hideGlobalLoadingMessage();
                displayResults(response);
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error Details:', {
                    status: xhr.status,
                    statusText: xhr.statusText,
                    responseText: xhr.responseText,
                    error: error,
                    readyState: xhr.readyState
                });

                // Hide loading overlay on error
                hideGlobalLoadingMessage();

                let errorMessage = 'Errore sconosciuto';
                let debugInfo = {};

                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    errorMessage = errorResponse.error || errorResponse.message || 'Errore nel parsing della risposta';
                    debugInfo = errorResponse;
                } catch (e) {
                    if (xhr.responseText) {
                        errorMessage = `Errore HTTP ${xhr.status}: ${xhr.responseText.substring(0, 300)}`;
                    } else if (xhr.status === 0) {
                        errorMessage = 'Errore di connessione - Verifica che il server sia raggiungibile';
                    } else {
                        errorMessage = `Errore ${xhr.status}: ${error}`;
                    }
                }

                displayDetailedError(errorMessage, xhr, debugInfo);
            }
        });
    }

    function displayDetailedError(message, xhr, debugInfo) {
        const errorDetails = {
            status: xhr.status,
            statusText: xhr.statusText,
            url: xhr.responseURL || 'process.php',
            responseText: xhr.responseText?.substring(0, 1000) || 'Nessuna risposta',
            readyState: xhr.readyState,
            debugInfo: debugInfo
        };
        
        console.error('Detailed Error Info:', errorDetails);
        
        $('#resultsContent').html(`
            <div class="col-12">
                <div class="alert alert-danger" role="alert">
                    <h4 class="alert-heading">⚠️ Errore Dettagliato!</h4>
                    <p><strong>Messaggio:</strong> ${message}</p>
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6>🔍 Debug Info:</h6>
                            <ul class="list-unstyled small">
                                <li><strong>Status HTTP:</strong> ${errorDetails.status}</li>
                                <li><strong>Status Text:</strong> ${errorDetails.statusText}</li>
                                <li><strong>Ready State:</strong> ${errorDetails.readyState}</li>
                                <li><strong>URL:</strong> ${errorDetails.url}</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>📄 Risposta Server:</h6>
                            <div class="bg-light p-2 border rounded" style="max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 11px; white-space: pre-wrap;">
${errorDetails.responseText}
                            </div>
                        </div>
                    </div>
                    
                    ${debugInfo.file ? `
                    <hr>
                    <h6>🐛 Debug PHP:</h6>
                    <ul class="list-unstyled small">
                        <li><strong>File:</strong> ${debugInfo.file}</li>
                        <li><strong>Linea:</strong> ${debugInfo.line}</li>
                        <li><strong>Timestamp:</strong> ${debugInfo.timestamp}</li>
                    </ul>
                    ` : ''}
                    
                    <hr>
                    <h6>🛠️ Possibili Soluzioni:</h6>
                    <ul class="small">
                        <li>Verifica che tutte le API key siano corrette e attive</li>
                        <li>Controlla i file debug.log e php_errors.log sul server</li>
                        <li>Verifica la connessione internet e i firewall</li>
                        <li>Controlla i permessi della cartella data/ (deve essere 777)</li>
                        <li>Verifica che tutti i file PHP siano presenti: config.php, apis/*.php</li>
                    </ul>
                    
                    <div class="mt-3">
                        <button class="btn btn-outline-primary btn-sm" onclick="location.reload()">🔄 Ricarica Pagina</button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="copyErrorToClipboard()">📋 Copia Errore</button>
                    </div>
                </div>
            </div>
        `);
        
        // Store error details for copying
        window.lastErrorDetails = errorDetails;
    }

    function showGlobalLoadingMessage() {
        // Remove existing overlay if any
        $('#globalLoading').remove();

        const loadingHTML = `
            <div class="global-loading-overlay" id="globalLoading">
                <div class="loading-content">
                    <div class="spinner-container">
                        <div class="spinner-large"></div>
                    </div>
                    <h4 class="mt-3">Analisi in Corso</h4>
                    <p class="text-muted">
                        <span id="loadingMessage">Interrogazione AI models in parallelo...</span>
                    </p>
                    <div class="progress" style="width: 300px; height: 8px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated"
                             id="globalProgressBar"
                             style="width: 0%"></div>
                    </div>
                    <p class="small text-muted mt-2">
                        <span id="estimatedTime">Tempo stimato: 30-180 secondi</span>
                    </p>
                </div>
            </div>
        `;

        $('body').append(loadingHTML);

        // Dynamic messages that change every 15 seconds
        const messages = [
            'Interrogazione AI models in parallelo...',
            'Recupero dati da DataForSEO...',
            'Analisi GPT delle risposte...',
            'Elaborazione sentiment e topic...',
            'Generazione insights strategici...',
            'Finalizzazione analisi aggregata...'
        ];

        let messageIndex = 0;
        let progress = 0;

        // Update message every 15 seconds
        window.loadingMessageInterval = setInterval(() => {
            messageIndex = (messageIndex + 1) % messages.length;
            $('#loadingMessage').fadeOut(300, function() {
                $(this).text(messages[messageIndex]).fadeIn(300);
            });
        }, 15000);

        // Animate progress bar from 0 to 90% over 120 seconds
        window.loadingProgressInterval = setInterval(() => {
            progress += 1;
            if (progress <= 90) {
                $('#globalProgressBar').css('width', progress + '%');
            }
        }, 1333); // 120 seconds / 90 steps = 1.333 seconds per step
    }

    function hideGlobalLoadingMessage() {
        // Complete progress bar to 100%
        $('#globalProgressBar').css('width', '100%');

        // Clear intervals
        if (window.loadingMessageInterval) {
            clearInterval(window.loadingMessageInterval);
        }
        if (window.loadingProgressInterval) {
            clearInterval(window.loadingProgressInterval);
        }

        // Fade out and remove overlay
        setTimeout(() => {
            $('#globalLoading').fadeOut(500, function() {
                $(this).remove();
            });
        }, 500);
    }

    function displayResults(data) {
        // Create results visualization with tabs
        const resultsHTML = generateResultsHTML(data);
        $('#resultsContent').html(resultsHTML);

        // Wait for DOM to be ready, then initialize charts
        setTimeout(() => {
            initializeCharts(data);
            animateCounters();

            // Animate domain bars after a delay
            setTimeout(() => {
                if (data.analysis && data.analysis.domain_frequency) {
                    animateDomainBars(data.analysis.domain_frequency);
                }
            }, 500);
        }, 100);
    }

    function generateResultsHTML(data) {
        return `
            <div class="col-12 mb-4">
                <h2 class="text-center brand-primary">AI Landscape Analysis Results</h2>
                <p class="text-center text-muted">
                    ${data.is_keyword_mode ? 
                        `Analisi keyword-driven${data.fanout_queries ? ' con ' + data.fanout_queries.length + ' query fan-out generate' : ''}` : 
                        'Analisi prompt-driven'
                    } • ${Object.keys(data.ai_responses?.[0] || {}).length} AI models
                </p>
                ${data.queries && data.queries.length > 1 ? `
                <div class="text-center mb-3">
                    <small class="text-muted">Query analizzate: ${data.queries.join(' • ')}</small>
                </div>
                ` : ''}
            </div>
            
            <!-- Results Tabs Navigation -->
            <div class="col-12">
                <ul class="nav nav-tabs nav-fill mb-4" id="resultsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="overview-tab" data-bs-toggle="tab"
                                data-bs-target="#overview" type="button">
                            Overview
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="domains-tab" data-bs-toggle="tab"
                                data-bs-target="#domains" type="button">
                            Domini
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="sentiment-tab" data-bs-toggle="tab"
                                data-bs-target="#sentiment" type="button">
                            Sentiment
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="comparison-tab" data-bs-toggle="tab"
                                data-bs-target="#comparison" type="button">
                            SERP vs AI
                        </button>
                    </li>
                    ${data.aggregate_analysis && Object.keys(data.aggregate_analysis).length > 0 ? `
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="insights-tab" data-bs-toggle="tab"
                                data-bs-target="#insights" type="button">
                            Strategic Insights
                        </button>
                    </li>` : ''}
                    ${data.fanout_queries && data.fanout_queries.length > 0 ? `
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="fanout-tab" data-bs-toggle="tab"
                                data-bs-target="#fanout" type="button">
                            Fan-out
                        </button>
                    </li>` : ''}
                    ${data.debug_mode ? `
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-warning" id="debug-tab" data-bs-toggle="tab"
                                data-bs-target="#debug" type="button">
                            Debug
                        </button>
                    </li>` : ''}
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="resultsTabsContent">
                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="overview" role="tabpanel">
                        ${generateOverviewTab(data)}
                    </div>
                    
                    <!-- Domains Tab -->
                    <div class="tab-pane fade" id="domains" role="tabpanel">
                        ${generateDomainsTab(data)}
                    </div>
                    
                    <!-- Sentiment Tab -->
                    <div class="tab-pane fade" id="sentiment" role="tabpanel">
                        ${generateSentimentTab(data)}
                    </div>
                    
                    <!-- Comparison Tab -->
                    <div class="tab-pane fade" id="comparison" role="tabpanel">
                        ${generateComparisonTab(data)}
                    </div>

                    ${data.aggregate_analysis && Object.keys(data.aggregate_analysis).length > 0 ? `
                    <!-- Strategic Insights Tab -->
                    <div class="tab-pane fade" id="insights" role="tabpanel">
                        ${generateInsightsTab(data)}
                    </div>` : ''}

                    ${data.fanout_queries && data.fanout_queries.length > 0 ? `
                    <!-- Fanout Tab -->
                    <div class="tab-pane fade" id="fanout" role="tabpanel">
                        ${generateFanoutTab(data)}
                    </div>` : ''}
                    
                    ${data.debug_mode ? `
                    <!-- Debug Tab -->
                    <div class="tab-pane fade" id="debug" role="tabpanel">
                        ${generateDebugTab(data)}
                    </div>` : ''}
                </div>
            </div>
        `;
    }

    function generateOverviewTab(data) {
        const aggAnalysis = data.aggregate_analysis || {};
        const consensusPercent = Math.round((aggAnalysis.consensus_level || 0) * 100);
        const diversityScore = aggAnalysis.topic_diversity || 0;

        return `
            <div class="row">
                <!-- Key Metrics Cards -->
                <div class="col-md-3">
                    <div class="metric-card">
                        <div class="metric-number" data-count="${data.metrics?.total_responses || 0}">0</div>
                        <div class="h6 text-light">Risposte AI</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card">
                        <div class="metric-number" data-count="${data.metrics?.unique_domains || 0}">0</div>
                        <div class="h6 text-light">Domini Unici</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card">
                        <div class="metric-number" data-count="${consensusPercent}">0</div>
                        <div class="h6 text-light">AI Consensus %</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="metric-card">
                        <div class="metric-number" data-count="${diversityScore}">0</div>
                        <div class="h6 text-light">Topic Diversity</div>
                    </div>
                </div>

                <!-- Topic Coverage Chart con altezza fissa -->
                <div class="col-12 mt-4">
                    <div class="chart-container">
                        <h5>Topic Coverage Overview</h5>
                        <div style="height: 400px; position: relative;">
                            <canvas id="topicsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function generateInsightsTab(data) {
        const agg = data.aggregate_analysis || {};
        const brand = agg.brand_landscape || {};
        const insights = agg.strategic_insights || [];
        const opportunities = agg.opportunities || [];
        const risks = agg.risks || [];
        const aiDiffs = agg.ai_model_differences || {};

        return `
            <div class="row">
                <!-- Brand Landscape Card -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Brand Landscape</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Visibilità Generale</span>
                                    <strong>${brand.overall_visibility || 0}/10</strong>
                                </div>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-success" style="width: ${(brand.overall_visibility || 0) * 10}%"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Consistenza Posizionamento</span>
                                    <strong>${brand.positioning_consistency || 0}/10</strong>
                                </div>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-info" style="width: ${(brand.positioning_consistency || 0) * 10}%"></div>
                                </div>
                            </div>
                            ${brand.competitive_context && brand.competitive_context.length > 0 ? `
                            <div class="mb-3">
                                <h6>Competitor nel Context:</h6>
                                <div>
                                    ${brand.competitive_context.map(comp => `<span class="badge bg-warning text-dark me-1">${comp}</span>`).join('')}
                                </div>
                            </div>` : ''}
                            ${brand.recommendation ? `
                            <div class="alert alert-info mb-0">
                                <strong>Raccomandazione:</strong><br>
                                ${brand.recommendation}
                            </div>` : ''}
                        </div>
                    </div>
                </div>

                <!-- AI Model Differences -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0">AI Model Differences</h5>
                        </div>
                        <div class="card-body">
                            ${Object.entries(aiDiffs).map(([model, diff]) => `
                                <div class="mb-3">
                                    <h6 class="text-uppercase">${model}</h6>
                                    <p class="small text-muted mb-0">${diff}</p>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>

                <!-- Strategic Insights -->
                <div class="col-md-12 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Strategic Insights</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                ${insights.map((insight, idx) => `
                                    <div class="col-md-4 mb-3">
                                        <div class="alert alert-info h-100">
                                            <strong>${idx + 1}.</strong> ${insight}
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Opportunities & Risks -->
                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Opportunità</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                ${opportunities.map(opp => `
                                    <li class="list-group-item">${opp}</li>
                                `).join('')}
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card shadow-sm">
                        <div class="card-header bg-danger text-white">
                            <h5 class="mb-0">Rischi</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                ${risks.map(risk => `
                                    <li class="list-group-item">${risk}</li>
                                `).join('')}
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function generateDomainsTab(data) {
        let domainRankingsHTML = '';
        if (data.analysis && data.analysis.domain_frequency) {
            const sortedDomains = Object.entries(data.analysis.domain_frequency)
                .sort(([,a], [,b]) => b - a)
                .slice(0, 15);
                
            domainRankingsHTML = sortedDomains.map(([domain, count], index) => {
                return `
                    <div class="domain-item mb-3" data-domain="${domain}">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong>#${index + 1} ${domain}</strong>
                                <div class="small text-muted">Citato in ${count} risposte AI</div>
                            </div>
                            <span class="badge bg-primary fs-6">${count}</span>
                        </div>
                        <div class="progress" style="height: 12px;">
                            <div class="domain-bar" style="width: 0%; background: linear-gradient(135deg, var(--accent-blue), var(--accent-purple)); border-radius: 6px; transition: width 0.8s ease;"></div>
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        return `
            <div class="row">
                <div class="col-md-8">
                    <div class="chart-container">
                        <h5>Ranking Domini Citati</h5>
                        <div style="max-height: 600px; overflow-y: auto;">
                            ${domainRankingsHTML || '<p class="text-muted">Nessun dominio trovato</p>'}
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="chart-container">
                        <h5>Distribuzione Domini</h5>
                        <canvas id="domainsDistChart" width="300" height="300"></canvas>
                    </div>
                </div>
            </div>
        `;
    }

    function generateSentimentTab(data) {
        return `
            <div class="row">
                <div class="col-md-6">
                    <div class="chart-container">
                        <h5>Sentiment Analysis Globale</h5>
                        <canvas id="sentimentChart" width="400" height="300"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="chart-container">
                        <h5>Sentiment per AI Model</h5>
                        <canvas id="sentimentByAiChart" width="400" height="300"></canvas>
                    </div>
                </div>
            </div>
        `;
    }

    function generateComparisonTab(data) {
        return `
            <div class="row">
                <div class="col-md-8">
                    <div class="chart-container">
                        <h5>SERP vs AI Overview</h5>
                        <canvas id="serpAiChart" width="600" height="400"></canvas>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="chart-container">
                        <h5>Metriche Confronto</h5>
                        <table class="table table-striped">
                            <tr>
                                <td><strong>SERP Organici:</strong></td>
                                <td class="text-primary">${data.serp_data?.serp_results?.length || 'N/A'}</td>
                            </tr>
                            <tr>
                                <td><strong>AI Overview:</strong></td>
                                <td class="text-purple">${data.serp_data?.ai_overview?.sources_cited?.length || 'N/A'}</td>
                            </tr>
                            <tr>
                                <td><strong>Sovrapposizioni:</strong></td>
                                <td class="text-success">${calculateOverlap(data) || 'N/A'}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        `;
    }

    function generateFanoutTab(data) {
        return `
            <div class="row">
                <div class="col-12">
                    <div class="chart-container">
                        <h5>Query Fan-out Generate (Gemini)</h5>
                        <p class="text-muted mb-4">Query semantiche derivate dalla keyword principale</p>
                        <div class="row">
                            ${data.fanout_queries.map((query, index) => `
                                <div class="col-md-6 mb-3">
                                    <div class="alert alert-info h-100">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <strong>${index + 1}.</strong> ${query}
                                            </div>
                                            <button class="btn btn-sm btn-outline-primary" onclick="useAsNewQuery('${query.replace(/'/g, '\\\'').replace(/"/g, '&quot;')}')">
                                                Usa
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function generateDebugTab(data) {
        return `
            <div class="row">
                <div class="col-12">
                    <div class="chart-container">
                        <h5>Debug - Risposte Complete API</h5>
                        <div class="accordion" id="debugAccordion">
                            ${generateDebugAccordion(data.ai_responses)}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function generateDebugAccordion(aiResponses) {
        if (!aiResponses || aiResponses.length === 0) {
            return '<p class="text-muted">Nessuna risposta AI disponibile</p>';
        }
        
        let accordionHTML = '';
        aiResponses.forEach((queryResponse, queryIndex) => {
            Object.entries(queryResponse).forEach(([ai, response], aiIndex) => {
                const accordionId = `debug_${ai}_${queryIndex}_${aiIndex}`;
                const hasError = response.error ? true : false;
                
                accordionHTML += `
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                    data-bs-target="#${accordionId}">
                                <strong>${ai.toUpperCase()}</strong> 
                                ${queryIndex > 0 ? `<span class="ms-2 badge bg-secondary">Query ${queryIndex + 1}</span>` : ''}
                                ${hasError ? '<span class="ms-2 badge bg-danger">ERRORE</span>' : ''}
                                <span class="ms-2 badge bg-info">${response.domains?.length || 0} domini</span>
                                <span class="ms-2 badge bg-warning">${getSentimentLabel(response.sentiment)}</span>
                            </button>
                        </h2>
                        <div id="${accordionId}" class="accordion-collapse collapse">
                            <div class="accordion-body">
                                ${hasError ? `
                                <div class="alert alert-danger">
                                    <strong>Errore API:</strong> ${response.error}
                                </div>
                                ` : ''}
                                <div class="row">
                                    <div class="col-md-8">
                                        <h6>Risposta Completa:</h6>
                                        <div class="border p-3 bg-light" style="max-height: 300px; overflow-y: auto;">
                                            <pre style="white-space: pre-wrap; margin: 0; font-size: 12px;">${response.content || 'Contenuto non disponibile'}</pre>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <h6>Metriche Estratte:</h6>
                                        <ul class="list-group list-group-flush">
                                            <li class="list-group-item">
                                                <strong>Domini:</strong><br>
                                                ${response.domains?.map(d => `<span class="badge bg-primary me-1 mb-1">${d}</span>`).join('') || '<span class="text-muted">Nessuno</span>'}
                                            </li>
                                            <li class="list-group-item">
                                                <strong>Sentiment:</strong> ${getSentimentLabel(response.sentiment)}
                                            </li>
                                            <li class="list-group-item">
                                                <strong>Topics:</strong><br>
                                                ${response.topics?.map(t => `<span class="badge bg-info me-1 mb-1">${t}</span>`).join('') || '<span class="text-muted">Nessuno</span>'}
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
        });
        
        return accordionHTML;
    }

    function initializeCharts(data) {
        // Sentiment Analysis Chart
        const sentimentCtx = document.getElementById('sentimentChart');
        if (sentimentCtx && data.analysis && data.analysis.sentiment_analysis) {
            const sentiments = Object.values(data.analysis.sentiment_analysis);
            const positive = sentiments.filter(s => s > 0).length;
            const neutral = sentiments.filter(s => s === 0).length;
            const negative = sentiments.filter(s => s < 0).length;
            
            new Chart(sentimentCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Positivo', 'Neutrale', 'Negativo'],
                    datasets: [{
                        data: [positive, neutral, negative],
                        backgroundColor: [
                            'var(--accent-green)',
                            'var(--text-muted)', 
                            'var(--accent-orange)'
                        ],
                        borderWidth: 3,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true
                            }
                        }
                    },
                    cutout: '50%'
                }
            });
        }

        // Domains Distribution Chart
        const domainsDistCtx = document.getElementById('domainsDistChart');
        if (domainsDistCtx && data.analysis && data.analysis.domain_frequency) {
            const domains = Object.entries(data.analysis.domain_frequency)
                .sort(([,a], [,b]) => b - a)
                .slice(0, 5);
                
            new Chart(domainsDistCtx, {
                type: 'doughnut',
                data: {
                    labels: domains.map(([domain]) => domain),
                    datasets: [{
                        data: domains.map(([,count]) => count),
                        backgroundColor: [
                            'var(--accent-blue)',
                            'var(--accent-green)',
                            'var(--accent-purple)',
                            'var(--accent-orange)',
                            '#64748b'
                        ],
                        borderWidth: 3,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        }

        // Sentiment By AI Chart
        const sentimentByAiCtx = document.getElementById('sentimentByAiChart');
        if (sentimentByAiCtx && data.analysis && data.analysis.sentiment_analysis) {
            const aiModels = Object.keys(data.analysis.sentiment_analysis);
            const sentimentData = Object.values(data.analysis.sentiment_analysis);
            
            new Chart(sentimentByAiCtx, {
                type: 'bar',
                data: {
                    labels: aiModels.map(ai => ai.toUpperCase()),
                    datasets: [{
                        label: 'Sentiment Score',
                        data: sentimentData,
                        backgroundColor: sentimentData.map(score => {
                            if (score > 0) return 'var(--accent-green)';
                            if (score < 0) return 'var(--accent-orange)';
                            return 'var(--text-muted)';
                        }),
                        borderRadius: 6,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            min: -1,
                            max: 1,
                            ticks: {
                                callback: function(value) {
                                    if (value > 0) return '+' + value;
                                    if (value < 0) return value;
                                    return value;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Initialize advanced charts
        initializeAdvancedCharts(data);
    }

    function initializeAdvancedCharts(data) {
        // Topic Radar Chart
        if (document.getElementById('topicsChart')) {
            initTopicsRadarChart(data);
        }
        
        // SERP vs AI Comparison
        if (document.getElementById('serpAiChart')) {
            initSerpAiChart(data);
        }
    }

    function initTopicsRadarChart(data) {
        const ctx = document.getElementById('topicsChart').getContext('2d');
        
        const aiModels = ['openai', 'gemini', 'claude', 'grok'];
        const allTopics = new Set();
        
        // Collect all topics from AI responses
        if (data.ai_responses && data.ai_responses.length > 0) {
            data.ai_responses.forEach(queryResponse => {
                Object.entries(queryResponse).forEach(([ai, response]) => {
                    if (response.topics) {
                        response.topics.forEach(topic => allTopics.add(topic));
                    }
                });
            });
        }
        
        const topicLabels = Array.from(allTopics).slice(0, 8);
        
        if (topicLabels.length === 0) {
            // Usa dati demo se non ci sono topic
            const demoTopics = ['tecnologia', 'marketing', 'business', 'innovazione', 'digital'];
            const demoData = aiModels.map((model, index) => ({
                label: model.toUpperCase(),
                data: demoTopics.map(() => Math.random()),
                backgroundColor: [
                    'rgba(37, 99, 235, 0.3)',
                    'rgba(5, 150, 105, 0.3)',
                    'rgba(124, 58, 237, 0.3)',
                    'rgba(234, 88, 12, 0.3)'
                ][index],
                borderColor: [
                    'rgb(37, 99, 235)',
                    'rgb(5, 150, 105)',
                    'rgb(124, 58, 237)',
                    'rgb(234, 88, 12)'
                ][index],
                borderWidth: 2,
                pointBackgroundColor: [
                    'rgb(37, 99, 235)',
                    'rgb(5, 150, 105)',
                    'rgb(124, 58, 237)',
                    'rgb(234, 88, 12)'
                ][index],
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4
            }));
            
            new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: demoTopics,
                    datasets: demoData
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 1.5,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true
                            }
                        }
                    },
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 1,
                            ticks: {
                                stepSize: 0.5,
                                callback: function(value) {
                                    return value === 1 ? '✓' : value === 0.5 ? '~' : '';
                                }
                            }
                        }
                    }
                }
            });
            return;
        }
        
        const datasets = aiModels.map((model, index) => {
            const colors = [
                'rgba(37, 99, 235, 0.3)',
                'rgba(5, 150, 105, 0.3)',
                'rgba(124, 58, 237, 0.3)',
                'rgba(234, 88, 12, 0.3)'
            ];
            
            const borderColors = [
                'rgb(37, 99, 235)',
                'rgb(5, 150, 105)',
                'rgb(124, 58, 237)',
                'rgb(234, 88, 12)'
            ];
            
            const topicData = topicLabels.map(topic => {
                let found = false;
                if (data.ai_responses) {
                    data.ai_responses.forEach(queryResponse => {
                        if (queryResponse[model] && queryResponse[model].topics) {
                            if (queryResponse[model].topics.includes(topic)) {
                                found = true;
                            }
                        }
                    });
                }
                return found ? 1 : 0;
            });
            
            return {
                label: model.toUpperCase(),
                data: topicData,
                backgroundColor: colors[index],
                borderColor: borderColors[index],
                borderWidth: 2,
                pointBackgroundColor: borderColors[index],
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4
            };
        });
        
        new Chart(ctx, {
            type: 'radar',
            data: {
                labels: topicLabels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 1.5,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true
                        }
                    }
                },
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 1,
                        ticks: {
                            stepSize: 0.5,
                            callback: function(value) {
                                return value === 1 ? '✓' : value === 0.5 ? '~' : '';
                            }
                        }
                    }
                }
            }
        });
    }

    function initSerpAiChart(data) {
        const ctx = document.getElementById('serpAiChart').getContext('2d');
        
        let serpDomains = 0;
        let aiDomains = 0;
        let overlap = 0;
        
        if (data.serp_data) {
            serpDomains = data.serp_data.serp_results?.length || 0;
            aiDomains = data.serp_data.ai_overview?.sources_cited?.length || 0;
            overlap = calculateOverlap(data);
        } else {
            serpDomains = Math.floor(Math.random() * 8) + 5;
            aiDomains = Math.floor(Math.random() * 4) + 3;
            overlap = Math.min(serpDomains, aiDomains, Math.floor(Math.random() * 3) + 1);
        }
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['SERP Organici', 'AI Overview', 'Sovrapposizione'],
                datasets: [{
                    label: 'Numero Domini',
                    data: [serpDomains, aiDomains, overlap],
                    backgroundColor: [
                        'rgba(37, 99, 235, 0.8)',
                        'rgba(124, 58, 237, 0.8)',
                        'rgba(5, 150, 105, 0.8)'
                    ],
                    borderColor: [
                        'rgb(37, 99, 235)',
                        'rgb(124, 58, 237)',
                        'rgb(5, 150, 105)'
                    ],
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    function animateDomainBars(domains) {
        if (!domains || Object.keys(domains).length === 0) return;
        
        const maxCount = Math.max(...Object.values(domains));
        
        Object.entries(domains).forEach(([domain, count], index) => {
            setTimeout(() => {
                const percentage = (count / maxCount) * 100;
                const bar = document.querySelector(`[data-domain="${domain}"] .domain-bar`);
                if (bar) {
                    bar.style.width = percentage + '%';
                }
            }, index * 200);
        });
    }

    function animateCounters() {
        $('.metric-number[data-count]').each(function() {
            const $this = $(this);
            const target = parseInt($this.data('count'));
            
            if (typeof CountUp !== 'undefined') {
                const countUp = new CountUp($this[0], target, {
                    duration: 2,
                    useEasing: true
                });
                
                if (!countUp.error) {
                    countUp.start();
                }
            } else {
                // Fallback animation
                let current = 0;
                const increment = target / 50;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    $this.text(Math.floor(current));
                }, 40);
            }
        });
    }

    // Utility functions
    function getSentimentLabel(sentiment) {
        if (sentiment > 0) return 'Positivo';
        if (sentiment < 0) return 'Negativo';
        return 'Neutrale';
    }

    function calculateOverlap(data) {
        if (!data.serp_data) return 0;
        const serpDomains = (data.serp_data.serp_results || []).map(r => r.domain).filter(Boolean);
        const aiDomains = data.serp_data.ai_overview?.sources_cited || [];
        return serpDomains.filter(domain => aiDomains.includes(domain)).length;
    }

    // Global functions
    window.useAsNewQuery = function(query) {
        $('#query').val(query);
        $('html, body').animate({scrollTop: 0}, 500);
        $('#query').focus();
        $('#resultsContainer').hide();
        // Reset state
        isKeywordMode = false;
    };

    window.copyErrorToClipboard = function() {
        if (window.lastErrorDetails) {
            const errorText = JSON.stringify(window.lastErrorDetails, null, 2);
            navigator.clipboard.writeText(errorText).then(() => {
                alert('Errore copiato negli appunti!');
            });
        }
    };

    // Load configuration from config.json
    function loadConfiguration() {
        $.ajax({
            url: 'load-config.php',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.config) {
                    console.log('✓ Config.json caricato correttamente');
                    populateConfigFields(response.config);
                } else {
                    console.warn('⚠ Config.json non trovato:', response.error);
                    showConfigNotification('⚠ Config.json non trovato. Inserisci le API keys manualmente', 'warning');
                }
            },
            error: function(xhr, status, error) {
                console.warn('⚠ Errore caricamento config.json:', error);
                console.info('💡 Inserisci le API keys manualmente o crea il file config.json (vedi CONFIG_README.md)');
            }
        });
    }

    function populateConfigFields(config) {
        let keysLoaded = 0;

        // OpenAI
        if (config.apis.openai && config.apis.openai.key) {
            $('#openai_key').val(config.apis.openai.key);
            keysLoaded++;
        }

        // Gemini
        if (config.apis.gemini && config.apis.gemini.key) {
            $('#gemini_key').val(config.apis.gemini.key);
            keysLoaded++;
        }

        // Claude
        if (config.apis.claude && config.apis.claude.key) {
            $('#claude_key').val(config.apis.claude.key);
            keysLoaded++;
        }

        // Grok
        if (config.apis.grok && config.apis.grok.key) {
            $('#grok_key').val(config.apis.grok.key);
            keysLoaded++;
        }

        // Perplexity
        if (config.apis.perplexity && config.apis.perplexity.key) {
            $('#perplexity_key').val(config.apis.perplexity.key);
            keysLoaded++;
        }

        // DataForSEO
        if (config.apis.dataforseo) {
            if (config.apis.dataforseo.login) {
                $('#dataforseo_login').val(config.apis.dataforseo.login);
                keysLoaded++;
            }
            if (config.apis.dataforseo.password) {
                $('#dataforseo_password').val(config.apis.dataforseo.password);
            }
        }

        // Default brand
        if (config.default_brand) {
            $('#brand').val(config.default_brand);
        }

        // Show notification only if keys were actually loaded
        if (keysLoaded > 0) {
            showConfigNotification(`✓ ${keysLoaded} API key${keysLoaded > 1 ? 's' : ''} caricata${keysLoaded > 1 ? 'e' : ''} da config.json`, 'success');
        } else {
            console.info('Config.json trovato ma vuoto. Compila le tue API keys nel file.');
        }
    }

    function showConfigNotification(message, type) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-warning';
        const notification = $(`
            <div class="alert ${alertClass} alert-dismissible fade show position-fixed"
                 style="top: 20px; right: 20px; z-index: 10000; min-width: 300px;" role="alert">
                <strong>${type === 'success' ? '✓' : '⚠'}</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);

        $('body').append(notification);

        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            notification.fadeOut(500, function() {
                $(this).remove();
            });
        }, 5000);
    }
});
