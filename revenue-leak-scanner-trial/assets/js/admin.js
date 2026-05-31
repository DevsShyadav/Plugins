/**
 * Revenue Leak Scanner - Admin JavaScript (Redesigned)
 * Premium SaaS-quality interactive dashboard
 *
 * @package RevenueLeakScanner
 */
(function($) {
    'use strict';

    const RLS = {
        config: window.rlsAdmin || {},
        chart: null,
        currentTab: 'all',
        allLeaks: [],

        init: function() {
            this.bindEvents();
            this.loadPageData();
        },

        bindEvents: function() {
            $(document).on('click', '#rls-start-scan, #rls-first-scan', this.startScan.bind(this));
            $(document).on('click', '.rls-btn-fix', this.markFixed.bind(this));
            $(document).on('click', '.rls-btn-dismiss', this.dismissLeak.bind(this));
            $(document).on('click', '.rls-leak-card__toggle', this.toggleLeakDetails.bind(this));
            $(document).on('click', '.rls-tabs__btn', this.switchTab.bind(this));
            $(document).on('submit', '#rls-settings-form', this.saveSettings.bind(this));
            $(document).on('change', '#rls-filter-category, #rls-filter-severity, #rls-filter-status', this.filterResults.bind(this));
            $(document).on('click', '#rls-dismiss-how', function() { $(this).closest('.rls-how-it-works').fadeOut(); });
        },

        loadPageData: function() {
            if ($('#rls-dashboard').length && $('#rls-dashboard').is(':visible')) {
                this.loadDashboard();
            } else if ($('#rls-results-app').length) {
                this.loadResults();
            } else if ($('#rls-history-app').length) {
                this.loadHistory();
            }
        },

        // ========== SCAN ==========
        startScan: function(e) {
            e.preventDefault();
            const self = this;

            $('#rls-welcome').hide();
            $('#rls-dashboard').hide();
            $('#rls-scanning').show();

            const modules = ['checkout', 'product', 'performance', 'mobile', 'seo', 'trust'];
            let progress = 0, modIndex = 0;

            const progressInterval = setInterval(function() {
                progress += Math.random() * 12 + 3;
                if (progress > 92) progress = 92;
                $('#rls-scan-progress').css('width', progress + '%');

                if (modIndex < modules.length) {
                    $('.rls-scanning__module').removeClass('rls-scanning__module--active rls-scanning__module--done');
                    for (let i = 0; i < modIndex; i++) {
                        $(`.rls-scanning__module[data-module="${modules[i]}"]`).addClass('rls-scanning__module--done');
                    }
                    $(`.rls-scanning__module[data-module="${modules[modIndex]}"]`).addClass('rls-scanning__module--active');
                    $('#rls-scan-status').text('Scanning: ' + modules[modIndex].charAt(0).toUpperCase() + modules[modIndex].slice(1) + '...');
                    modIndex++;
                }
            }, 600);

            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: { action: 'rls_run_scan', nonce: self.config.nonce, scan_type: 'full' },
                timeout: 60000,
                success: function(response) {
                    clearInterval(progressInterval);
                    $('#rls-scan-progress').css('width', '100%');
                    $('.rls-scanning__module').addClass('rls-scanning__module--done').removeClass('rls-scanning__module--active');
                    $('#rls-scan-status').text('Scan complete!');

                    setTimeout(function() {
                        $('#rls-scanning').hide();
                        $('#rls-dashboard').show();
                        if (response.success) {
                            self.renderDashboard(response.data);
                            self.showToast('success', 'Found ' + response.data.total_leaks + ' revenue leaks worth ' + self.formatCurrency(response.data.total_revenue_loss) + '/month!');
                        } else {
                            self.showToast('error', response.data?.message || 'Scan failed.');
                        }
                    }, 1000);
                },
                error: function() {
                    clearInterval(progressInterval);
                    $('#rls-scanning').hide();
                    $('#rls-welcome').show();
                    self.showToast('error', 'Scan failed. Please try again.');
                }
            });
        },

        // ========== DASHBOARD ==========
        loadDashboard: function() {
            const self = this;
            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: { action: 'rls_get_dashboard_data', nonce: self.config.nonce },
                success: function(response) {
                    if (response.success && response.data.has_scans) {
                        self.renderDashboard(response.data.latest);
                        if (response.data.comparison) self.renderTrend(response.data.comparison);
                        self.renderChart(response.data.history);
                    }
                }
            });
        },

        renderDashboard: function(data) {
            if (!data) return;

            // Hero metrics
            $('#rls-total-loss').text(this.formatCurrency(data.total_revenue_loss));
            $('#rls-leak-count').text(data.total_leaks || 0);

            let critical = 0, high = 0, fixed = 0;
            this.allLeaks = data.leaks || [];

            this.allLeaks.forEach(function(leak) {
                if (leak.severity === 'critical') critical++;
                if (leak.severity === 'high') high++;
                if (leak.is_fixed == 1) fixed++;
            });

            $('#rls-critical-count').text(critical);
            $('#rls-high-count').text(high);
            $('#rls-fixed-count').text(fixed);

            // Update tab counts
            $('#rls-tab-count-all').text(this.allLeaks.length);
            const cats = {};
            this.allLeaks.forEach(function(l) { cats[l.category] = (cats[l.category] || 0) + 1; });
            Object.keys(cats).forEach(function(k) { $('#rls-tab-count-' + k).text(cats[k]); });

            // Last scan time
            if (data.completed_at) {
                $('#rls-last-scan-time').text('Last scan: ' + new Date(data.completed_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }));
            }

            // Render leak cards
            this.renderLeakCards(this.allLeaks);

            // Health score
            this.renderHealthScore(data);
        },

        renderLeakCards: function(leaks) {
            const $container = $('#rls-leak-cards');
            $container.empty();

            if (!leaks || leaks.length === 0) {
                $container.html('<div class="rls-empty-state"><div class="rls-empty-state__icon">🎉</div><h3>No Revenue Leaks Found!</h3><p>Your store is well-optimized. Run another scan after making changes.</p></div>');
                return;
            }

            const self = this;
            leaks.forEach(function(leak, index) {
                $container.append(self.renderDetailedLeakCard(leak, index));
            });
        },

        renderDetailedLeakCard: function(leak, index) {
            const impact = parseFloat(leak.revenue_impact) || 0;
            const isFixed = leak.is_fixed == 1;
            const confidence = leak.confidence_score || 50;
            const categoryIcons = { checkout: '🛒', product: '📦', performance: '⚡', mobile: '📱', seo: '🔍', trust: '🛡️' };
            const categoryNames = { checkout: 'Checkout', product: 'Products', performance: 'Performance', mobile: 'Mobile', seo: 'SEO', trust: 'Trust' };
            const difficultyLabels = { easy: '5 min fix', medium: '15-30 min', hard: '1+ hours' };
            const difficultyColors = { easy: '#10B981', medium: '#F59E0B', hard: '#EF4444' };

            let affectedHtml = '';
            if (leak.affected_items) {
                let items = leak.affected_items;
                if (typeof items === 'string') {
                    try { items = JSON.parse(items); } catch(e) { items = [items]; }
                }
                if (Array.isArray(items) && items.length > 0) {
                    affectedHtml = '<div class="rls-leak-card__affected"><strong>Affected:</strong> ' + items.map(i => '<span class="rls-leak-card__affected-tag">' + this.escapeHtml(String(i)) + '</span>').join('') + '</div>';
                }
            }

            return `
                <div class="rls-leak-card ${isFixed ? 'rls-leak-card--fixed' : ''}" data-category="${leak.category}" data-severity="${leak.severity}" data-status="${isFixed ? 'fixed' : 'open'}" data-index="${index}">
                    <div class="rls-leak-card__header">
                        <div class="rls-leak-card__left">
                            <span class="rls-leak-card__severity-dot rls-leak-card__severity-dot--${leak.severity}"></span>
                            <span class="rls-leak-card__category">${categoryIcons[leak.category] || '📊'} ${categoryNames[leak.category] || leak.category}</span>
                            <span class="rls-leak-card__badge rls-leak-card__badge--${leak.severity}">${leak.severity}</span>
                        </div>
                        <div class="rls-leak-card__right">
                            <span class="rls-leak-card__impact">-${this.formatCurrency(impact)}<small>/mo</small></span>
                        </div>
                    </div>
                    <div class="rls-leak-card__body">
                        <h4 class="rls-leak-card__title">${this.escapeHtml(leak.title)}</h4>
                        <p class="rls-leak-card__desc">${this.escapeHtml(leak.description)}</p>
                    </div>
                    <div class="rls-leak-card__footer">
                        <div class="rls-leak-card__meta">
                            <span class="rls-leak-card__difficulty" style="color: ${difficultyColors[leak.fix_difficulty] || '#6B7280'}">⏱️ ${difficultyLabels[leak.fix_difficulty] || leak.fix_difficulty}</span>
                            <span class="rls-leak-card__confidence" title="Confidence score">📊 ${confidence}% confidence</span>
                        </div>
                        <button class="rls-leak-card__toggle" data-index="${index}">${isFixed ? '✅ Fixed' : 'View Details ▾'}</button>
                    </div>
                    <div class="rls-leak-card__details" id="rls-leak-detail-${index}" style="display: none;">
                        ${affectedHtml}
                        ${leak.fix_suggestion ? '<div class="rls-leak-card__fix-box"><div class="rls-leak-card__fix-title">💡 How to Fix</div><p>' + this.escapeHtml(leak.fix_suggestion) + '</p></div>' : ''}
                        <div class="rls-leak-card__actions">
                            ${!isFixed ? '<button class="rls-btn rls-btn--sm rls-btn--primary rls-btn-fix" data-id="' + leak.id + '">✓ Mark as Fixed</button>' : ''}
                            ${leak.fix_url ? '<a href="' + leak.fix_url + '" class="rls-btn rls-btn--sm rls-btn--secondary" target="_blank">Go Fix This →</a>' : ''}
                            ${!isFixed ? '<button class="rls-btn rls-btn--sm rls-btn--ghost rls-btn-dismiss" data-id="' + leak.id + '">Dismiss</button>' : ''}
                        </div>
                    </div>
                </div>
            `;
        },

        toggleLeakDetails: function(e) {
            const $btn = $(e.currentTarget);
            const index = $btn.data('index');
            const $details = $('#rls-leak-detail-' + index);

            if ($details.is(':visible')) {
                $details.slideUp(200);
                $btn.text('View Details ▾');
            } else {
                $details.slideDown(200);
                $btn.text('Hide Details ▴');
            }
        },

        // ========== TABS ==========
        switchTab: function(e) {
            const $btn = $(e.currentTarget);
            const tab = $btn.data('tab');

            $('.rls-tabs__btn').removeClass('rls-tabs__btn--active');
            $btn.addClass('rls-tabs__btn--active');

            this.currentTab = tab;

            if (tab === 'all') {
                $('.rls-leak-card').show();
            } else {
                $('.rls-leak-card').each(function() {
                    $(this).toggle($(this).data('category') === tab);
                });
            }
        },

        // ========== HEALTH SCORE ==========
        renderHealthScore: function(data) {
            const totalPossibleLeaks = 32; // max possible checks
            const leakCount = data.total_leaks || 0;
            const score = Math.max(0, Math.round(((totalPossibleLeaks - leakCount) / totalPossibleLeaks) * 100));

            $('#rls-health-score').text(score + '/100');

            // Animate ring
            const circumference = 2 * Math.PI * 52; // r=52
            const offset = circumference - (score / 100) * circumference;
            setTimeout(function() {
                $('#rls-health-ring').css('stroke-dashoffset', offset);
            }, 300);

            // Color based on score
            const color = score >= 80 ? '#10B981' : score >= 60 ? '#F59E0B' : '#EF4444';
            $('#rls-health-score').css('color', color);

            // Breakdown
            const cats = data.categories || {};
            let breakdownHtml = '';
            const catNames = { checkout: 'Checkout', product: 'Products', performance: 'Performance', mobile: 'Mobile', seo: 'SEO', trust: 'Trust' };
            Object.keys(cats).forEach(function(key) {
                const c = cats[key];
                const status = c.leak_count === 0 ? 'pass' : (c.severity === 'critical' ? 'fail' : 'warn');
                const icon = status === 'pass' ? '✅' : (status === 'fail' ? '❌' : '⚠️');
                breakdownHtml += `<div class="rls-health__item rls-health__item--${status}"><span>${icon}</span><span>${catNames[key] || key}</span><span>${c.leak_count} issues</span></div>`;
            });
            $('#rls-health-breakdown').html(breakdownHtml);
        },

        // ========== RESULTS PAGE ==========
        loadResults: function() {
            const self = this;
            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: { action: 'rls_get_results', nonce: self.config.nonce },
                success: function(response) {
                    $('#rls-results-loading').hide();
                    if (response.success) {
                        self.allLeaks = response.data.leaks || [];
                        self.renderResultsPage(response.data);
                    } else {
                        $('#rls-results-list').html('<div class="rls-empty-state"><div class="rls-empty-state__icon">📋</div><h3>No Results Yet</h3><p>' + (response.data?.message || 'Run a scan to see results.') + '</p></div>');
                    }
                },
                error: function() {
                    $('#rls-results-loading').hide();
                    $('#rls-results-list').html('<div class="rls-empty-state"><h3>Failed to load</h3><p>Please refresh the page.</p></div>');
                }
            });
        },

        renderResultsPage: function(data) {
            $('#rls-results-total-loss').text(this.formatCurrency(data.total_revenue_loss));
            $('#rls-results-total-leaks').text(data.total_leaks || 0);
            $('#rls-results-scan-date').text(data.completed_at ? new Date(data.completed_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : '-');

            let fixed = 0;
            (data.leaks || []).forEach(function(l) { if (l.is_fixed == 1) fixed++; });
            $('#rls-results-fixed').text(fixed);

            const $list = $('#rls-results-list');
            $list.empty();

            if (!data.leaks || data.leaks.length === 0) {
                $list.html('<div class="rls-empty-state"><div class="rls-empty-state__icon">🎉</div><h3>No Issues Found</h3><p>Your store is well-optimized!</p></div>');
                return;
            }

            const self = this;
            data.leaks.forEach(function(leak, i) {
                $list.append(self.renderDetailedLeakCard(leak, i));
            });
        },

        filterResults: function() {
            const category = $('#rls-filter-category').val();
            const severity = $('#rls-filter-severity').val();
            const status = $('#rls-filter-status').val();

            $('.rls-leak-card').each(function() {
                const $card = $(this);
                let show = true;
                if (category && $card.data('category') !== category) show = false;
                if (severity && $card.data('severity') !== severity) show = false;
                if (status && $card.data('status') !== status) show = false;
                $card.toggle(show);
            });
        },

        // ========== CHART ==========
        renderTrend: function(comparison) {
            if (!comparison || !comparison.has_comparison) return;
            const direction = comparison.revenue_trend === 'improving' ? '↓' : '↑';
            const cls = comparison.revenue_trend === 'improving' ? 'rls-hero__trend--good' : 'rls-hero__trend--bad';
            const amt = Math.abs(comparison.revenue_change);
            $('#rls-trend').html(`<span class="${cls}">${direction} ${this.formatCurrency(amt)} vs last scan</span>`);
        },

        renderChart: function(history) {
            const canvas = document.getElementById('rls-trend-chart') || document.getElementById('rls-history-chart');
            if (!canvas || !history || history.length === 0) return;
            if (typeof Chart === 'undefined') return;

            if (this.chart) this.chart.destroy();

            const reversed = [...history].reverse();
            const labels = reversed.map(s => new Date(s.completed_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
            const values = reversed.map(s => parseFloat(s.total_revenue_loss));

            this.chart = new Chart(canvas.getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Monthly Revenue Loss',
                        data: values,
                        borderColor: '#EF4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.05)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#EF4444',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { backgroundColor: '#1F2937', cornerRadius: 8, padding: 12, callbacks: { label: ctx => '-$' + ctx.parsed.y.toLocaleString() + '/month' } } },
                    scales: { x: { grid: { display: false }, ticks: { color: '#9CA3AF', font: { size: 11 } } }, y: { grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { color: '#9CA3AF', font: { size: 11 }, callback: v => '$' + v.toLocaleString() } } },
                    interaction: { intersect: false, mode: 'index' }
                }
            });
        },

        // ========== HISTORY ==========
        loadHistory: function() {
            const self = this;
            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: { action: 'rls_get_history', nonce: self.config.nonce, limit: 20 },
                success: function(r) { if (r.success) { self.renderHistoryTable(r.data.scans); self.renderChart(r.data.scans); } }
            });
        },

        renderHistoryTable: function(scans) {
            const $tbody = $('#rls-history-tbody');
            if (!$tbody.length) return;
            $tbody.empty();
            if (!scans || scans.length === 0) { $tbody.html('<tr><td colspan="6" style="text-align:center;padding:40px;">No scan history yet.</td></tr>'); return; }
            scans.forEach(function(scan) {
                const date = new Date(scan.completed_at).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
                $tbody.append(`<tr><td>${date}</td><td style="text-transform:capitalize;">${scan.scan_type}</td><td><strong>${scan.total_leaks}</strong></td><td style="color:#EF4444;font-weight:600;">-$${parseFloat(scan.total_revenue_loss).toLocaleString()}</td><td><span class="rls-leak-card__badge rls-leak-card__badge--medium">${scan.status}</span></td><td><a href="admin.php?page=rls-results&scan_id=${scan.id}" class="rls-link">View →</a></td></tr>`);
            });
        },

        // ========== ACTIONS ==========
        markFixed: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const leakId = $btn.data('id');
            const self = this;
            $btn.text('Saving...').prop('disabled', true);

            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: { action: 'rls_mark_fixed', nonce: self.config.nonce, leak_id: leakId },
                success: function(r) {
                    if (r.success) {
                        $btn.closest('.rls-leak-card').addClass('rls-leak-card--fixed').attr('data-status', 'fixed');
                        $btn.replaceWith('<span style="color:#10B981;font-weight:600;">✅ Fixed!</span>');
                        self.showToast('success', 'Marked as fixed! Great job.');
                    } else { $btn.text('✓ Mark as Fixed').prop('disabled', false); }
                },
                error: function() { $btn.text('✓ Mark as Fixed').prop('disabled', false); self.showToast('error', 'Failed. Try again.'); }
            });
        },

        dismissLeak: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: { action: 'rls_dismiss_leak', nonce: this.config.nonce, leak_id: $btn.data('id') },
                success: function(r) { if (r.success) $btn.closest('.rls-leak-card').slideUp(300); }
            });
        },

        saveSettings: function(e) {
            e.preventDefault();
            const self = this;
            const $form = $(e.currentTarget);
            const data = { action: 'rls_save_settings', nonce: self.config.nonce };

            $form.serializeArray().forEach(function(item) {
                if (item.name !== 'scan_modules[]') data[item.name] = item.value;
            });
            data['scan_modules[]'] = [];
            $form.find('input[name="scan_modules[]"]:checked').each(function() { data['scan_modules[]'].push($(this).val()); });
            if (!$form.find('input[name="email_reports"]').is(':checked')) data.email_reports = 'no';

            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(r) {
                    if (r.success) { $('#rls-save-indicator').show().delay(3000).fadeOut(); self.showToast('success', 'Settings saved!'); }
                    else self.showToast('error', 'Failed to save.');
                }
            });
        },

        // ========== UTILITIES ==========
        formatCurrency: function(amount) {
            const num = parseFloat(amount) || 0;
            return (this.config.currency || '$') + num.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        },

        escapeHtml: function(text) {
            if (!text) return '';
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        },

        showToast: function(type, message) {
            const toast = $('<div class="rls-toast rls-toast--' + type + '">' + this.escapeHtml(message) + '</div>');
            $('body').append(toast);
            setTimeout(function() { toast.fadeOut(300, function() { $(this).remove(); }); }, 4000);
        }
    };

    $(document).ready(function() { RLS.init(); });
})(jQuery);
