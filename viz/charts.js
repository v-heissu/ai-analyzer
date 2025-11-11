// Utility functions per le visualizzazioni avanzate

/**
 * Inizializza tutti i chart avanzati
 */
function initializeAdvancedCharts(data) {
    // Topic Radar Chart
    if (document.getElementById('topicsChart')) {
        initTopicsRadarChart(data);
    }
    
    // SERP vs AI Comparison
    if (document.getElementById('serpAiChart')) {
        initSerpAiChart(data);
    }
    
    // Domain Rankings con animazione
    if (data.analysis && data.analysis.domain_frequency) {
        animateDomainBars(data.analysis.domain_frequency);
    }
    
    // Competitive Matrix (se presente)
    if (document.getElementById('competitiveMatrix')) {
        initCompetitiveMatrix(data);
    }
}

/**
 * Topic Radar Chart - Mostra copertura topic per AI model
 */
function initTopicsRadarChart(data) {
    const ctx = document.getElementById('topicsChart').getContext('2d');
    
    // Prepara dati per radar chart
    const aiModels = ['openai', 'gemini', 'claude', 'grok'];
    const allTopics = new Set();
    
    // Raccogli tutti i topic dalle risposte AI
    if (data.ai_responses && data.ai_responses.length > 0) {
        data.ai_responses.forEach(queryResponse => {
            Object.entries(queryResponse).forEach(([ai, response]) => {
                if (response.topics) {
                    response.topics.forEach(topic => allTopics.add(topic));
                }
            });
        });
    }
    
    const topicLabels = Array.from(allTopics).slice(0, 8); // Max 8 topic per leggibilità
    
    if (topicLabels.length === 0) {
        // Fallback se non ci sono topic
        drawNoDataMessage(ctx, 'Nessun topic identificato nelle risposte AI');
        return;
    }
    
    const datasets = aiModels.map((model, index) => {
        const colors = [
            'rgba(37, 99, 235, 0.6)',   // blue
            'rgba(5, 150, 105, 0.6)',   // green  
            'rgba(124, 58, 237, 0.6)',  // purple
            'rgba(234, 88, 12, 0.6)'    // orange
        ];
        
        const borderColors = [
            'rgb(37, 99, 235)',
            'rgb(5, 150, 105)',
            'rgb(124, 58, 237)',
            'rgb(234, 88, 12)'
        ];
        
        const topicData = topicLabels.map(topic => {
            // Cerca il topic nelle risposte del modello
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
            pointRadius: 6
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
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        font: {
                            size: 12
                        }
                    }
                },
                title: {
                    display: true,
                    text: 'Topic Coverage Comparison',
                    font: {
                        size: 16,
                        weight: 'bold'
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
                        },
                        font: {
                            size: 10
                        }
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    },
                    angleLines: {
                        color: 'rgba(0,0,0,0.1)'
                    },
                    pointLabels: {
                        font: {
                            size: 11
                        }
                    }
                }
            },
            animation: {
                duration: 2000,
                easing: 'easeInOutQuart'
            }
        }
    });
}

/**
 * SERP vs AI Overview Comparison Chart
 */
function initSerpAiChart(data) {
    const ctx = document.getElementById('serpAiChart').getContext('2d');
    
    // Calcola metriche da confrontare
    let serpDomains = 0;
    let aiDomains = 0;
    let overlap = 0;
    
    if (data.serp_data) {
        serpDomains = data.serp_data.serp_results?.length || 0;
        aiDomains = data.serp_data.ai_overview?.sources_cited?.length || 0;
        
        // Calcola sovrapposizione
        const serpDomainList = (data.serp_data.serp_results || []).map(r => r.domain).filter(Boolean);
        const aiDomainList = data.serp_data.ai_overview?.sources_cited || [];
        overlap = serpDomainList.filter(domain => aiDomainList.includes(domain)).length;
    } else {
        // Dati simulati per demo
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
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label;
                            const value = context.raw;
                            let description = '';
                            
                            switch(label) {
                                case 'SERP Organici':
                                    description = 'Domini nei risultati organici';
                                    break;
                                case 'AI Overview':
                                    description = 'Domini citati nell\'AI Overview';
                                    break;
                                case 'Sovrapposizione':
                                    description = 'Domini presenti in entrambi';
                                    break;
                            }
                            
                            return `${description}: ${value}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        callback: function(value) {
                            return Math.floor(value);
                        }
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            animation: {
                duration: 1500,
                easing: 'easeOutBounce'
            }
        }
    });
}

/**
 * Competitive Matrix Chart (opzionale)
 */
function initCompetitiveMatrix(data) {
    const ctx = document.getElementById('competitiveMatrix').getContext('2d');
    
    // Prepara dati per bubble chart
    const bubbleData = [];
    const colors = ['#2563eb', '#059669', '#7c3aed', '#ea580c'];
    
    if (data.analysis && data.analysis.domain_frequency) {
        Object.entries(data.analysis.domain_frequency).forEach(([domain, frequency], index) => {
            bubbleData.push({
                x: Math.random() * 100, // Authority (simulata)
                y: frequency * 10, // Visibility 
                r: Math.sqrt(frequency) * 10, // Bubble size
                label: domain,
                backgroundColor: colors[index % colors.length] + '80',
                borderColor: colors[index % colors.length]
            });
        });
    }
    
    new Chart(ctx, {
        type: 'bubble',
        data: {
            datasets: [{
                label: 'Domini Competitivi',
                data: bubbleData,
                backgroundColor: bubbleData.map(d => d.backgroundColor),
                borderColor: bubbleData.map(d => d.borderColor),
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const point = context.raw;
                            return `${point.label}: Authority ${Math.round(point.x)}, Visibility ${Math.round(point.y)}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Domain Authority (simulata)'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Visibility Score'
                    }
                }
            }
        }
    });
}

/**
 * Animazione progressiva delle barre domini
 */
function animateDomainBars(domains) {
    if (!domains || Object.keys(domains).length === 0) return;
    
    const maxCount = Math.max(...Object.values(domains));
    
    Object.entries(domains).forEach(([domain, count], index) => {
        setTimeout(() => {
            const percentage = (count / maxCount) * 100;
            const bar = document.querySelector(`[data-domain="${domain}"] .domain-bar`);
            if (bar) {
                bar.style.width = percentage + '%';
                
                // Aggiungi effetto brillio
                bar.style.boxShadow = '0 0 10px rgba(37, 99, 235, 0.5)';
                setTimeout(() => {
                    bar.style.boxShadow = 'none';
                }, 1000);
            }
        }, index * 200); // Animazione scaglionata
    });
}

/**
 * Disegna messaggio per dati mancanti
 */
function drawNoDataMessage(ctx, message) {
    ctx.fillStyle = '#64748b';
    ctx.font = '16px Inter, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(message, ctx.canvas.width / 2, ctx.canvas.height / 2);
}

/**
 * Genera Word Cloud (opzionale, richiede wordcloud2.js)
 */
function generateWordCloud(topics, containerId) {
    if (typeof WordCloud === 'undefined') return;
    
    const words = topics.map(topic => [topic, Math.random() * 50 + 10]);
    
    WordCloud(document.getElementById(containerId), {
        list: words,
        gridSize: 8,
        weightFactor: 2,
        fontFamily: 'Inter, sans-serif',
        color: function() {
            const colors = ['#2563eb', '#059669', '#7c3aed', '#ea580c'];
            return colors[Math.floor(Math.random() * colors.length)];
        },
        backgroundColor: 'transparent',
        rotateRatio: 0.3
    });
}

/**
 * Genera heatmap semplice con CSS
 */
function generateHeatmap(data, containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    let html = '<div class="heatmap-grid">';
    
    Object.entries(data).forEach(([key, value]) => {
        const intensity = Math.min(value / 10, 1); // Normalizza a 0-1
        const color = `rgba(37, 99, 235, ${intensity})`;
        
        html += `
            <div class="heatmap-cell" style="background-color: ${color};" title="${key}: ${value}">
                ${key}
            </div>
        `;
    });
    
    html += '</div>';
    container.innerHTML = html;
}

// Export per uso modulare (se necessario)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        initializeAdvancedCharts,
        initTopicsRadarChart,
        initSerpAiChart,
        animateDomainBars,
        generateWordCloud,
        generateHeatmap
    };
}
