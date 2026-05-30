/**
 * Revenue Leak Scanner - Admin JavaScript
 *
 * @package RevenueLeakScanner
 */

(function($) {
    'use strict';

    const RLS = {
        config: window.rlsAdmin || {},
        chart: null,

        /**
         * Initialize the application.
         */
        init: function() {
            this.bindEvents();
            this.loadDashboardData();
            this.initFilters();
        },

        /**
         * Bind event handlers.
         */
        bindEvents: function() {
            // Scan buttons
            $(document).on('click', '#rls-start-scan, #rls-first-scan', this.startScan.bind(this));

            // Mark as fixed
            $(document).on('click', '.rls-btn-fix', this.markFixed.bind(this));

            // Dismiss leak
            $(document).on('click', '.rls-btn-dismiss', this.dismissLeak.bind(this));

            // Settings form
            $(document).on('submit', '#rls-settings-form', this.saveSettings.bind(this));

            // Filters
            $(document).on('change', '#rls-filter-category, #rls-filter-severity', this.filterResults.bind(this));
        },

        /**
         * Start a new scan.
         */
        startScan: function(e) {
            e.preventDefault();

            const self = this;

            // Show scanning animation
            $('#rls-welcome').hide();
            $('#rls-dashboard').hide();
            $('#rls-scanning').show();

            // Animate progress
            const statuses = [
                self.config.strings?.scanning || 'Analyzing checkout flow...',
                'Checking product pages...',
                'Measuring performance...',
                'Testing mobile experience...',
                'Analyzing SEO factors...',
                'Checking trust signals...',
                'Calculating revenue impact...'
            ];

            let progress = 0;
            const progressInterval = setInterval(function() {
                progress += Math.random() * 15;
                if (progress > 90) progress = 90;
                $('#rls-scan-progress').css('width', progress + '%');

                const statusIndex = Math.min(Math.floor(progress / 15), statuses.length - 1);
                $('#rls-scan-status').text(statuses[statusIndex]);
            }, 500);

            // Make AJAX call
            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rls_run_scan',
                    nonce: self.config.nonce,
                    scan_type: 'full'
                },
                success: function(response) {
                    clearInterval(progressInterval);
                    $('#rls-scan-progress').css('width', '100%');
                    $('#rls-scan-status').text(self.config.strings?.scanComplete || 'Scan complete!');

                    setTimeout(function() {
                        $('#rls-scanning').hide();
                        $('#rls-dashboard').show();

                        if (response.success) {
                            self.renderDashboard(response.data);
                            self.showToast('success', 'Scan completed! Found ' + response.data.total_leaks + ' revenue leaks.');
                        } else {
                            self.showToast('error', response.data?.message || 'Scan failed.');
                        }
                    }, 800);
                },
                error: function() {
                    clearInterval(progressInterval);
                    $('#rls-scanning').hide();
                    $('#rls-welcome').show();
                    self.showToast('error', self.config.strings?.scanError || 'Scan failed. Please try again.');
                }
            });
        },

        /**
         * Load dashboard data on page load.
         */
        loadDashboardData: function() {
            const self = this;

            if ($('#rls-dashboard').length === 0 || $('#rls-dashboard').is(':hidden')) {
                // Check if we're on results page
                if ($('#rls-results-app').length) {
                    self.loadResults();
                }
                if ($('#rls-history-app').length) {
                    self.loadHistory();
                }
                return;
            }

            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rls_get_dashboard_data',
                    nonce: self.config.nonce
                },
                success: function(response) {
                    if (response.success && response.data.has_scans) {
                        self.renderDashboard(response.data.latest);
                        if (response.data.comparison) {
                            self.renderTrend(response.data.comparison);
                        }
                        self.renderChart(response.data.history);
                    }
                }
            });
        },

        /**
         * Render dashboard with scan data.
         */
        renderDashboard: function(data) {
            if (!data) return;

            // Update summary
            $('#rls-total-loss').text(this.formatCurrency(data.total_revenue_loss));
            $('#rls-leak-count').text(data.total_leaks || 0);

            // Count critical leaks
            let criticalCount = 0;
            let fixedCount = 0;
            if (data.leaks) {
                data.leaks.forEach(function(leak) {
                    if (leak.severity === 'critical') criticalCount++;
                    if (leak.is_fixed == 1) fixedCount++;
                });
            }
            $('#rls-critical-count').text(criticalCount);
            $('#rls-fixed-count').text(fixedCount);

            // Render categories
            this.renderCategories(data.categories);

            // Render top leaks
            this.renderTopLeaks(data.leaks);
        },

        /**
         * Render category cards.
         */
        renderCategories: function(categories) {
            if (!categories) return;

            const $grid = $('#rls-category-grid');
            $grid.empty();

            const icons = {
                checkout: '🛒',
                product: '📦',
                performance: '⚡',
                mobile: '📱',
                seo: '🔍',
                trust: '🛡️'
            };

            const names = {
                checkout: 'Checkout',
                product: 'Products',
                performance: 'Performance',
                mobile: 'Mobile',
                seo: 'SEO',
                trust: 'Trust'
            };

            const colors = {
                checkout: '#EF4444',
                product: '#F59E0B',
                performance: '#3B82F6',
                mobile: '#8B5CF6',
                seo: '#10B981',
                trust: '#EC4899'
            };

            Object.keys(categories).forEach(function(key) {
                const cat = categories[key];
                const color = colors[key] || '#6B7280';
                const bgColor = color + '15';

                const card = `
                    <div class="rls-category-card">
                        <div class="rls-category-card__icon" style="background: ${bgColor};">
                            ${icons[key] || '📊'}
                        </div>
                        <div class="rls-category-card__name">${names[key] || key}</div>
                        <div class="rls-category-card__impact" style="color: ${cat.revenue_impact > 0 ? '#EF4444' : '#10B981'}">
                            ${cat.revenue_impact > 0 ? '-' : ''}$${Math.round(cat.revenue_impact).toLocaleString()}
                        </div>
                        <div class="rls-category-card__count">${cat.leak_count} issue${cat.leak_count !== 1 ? 's' : ''}</div>
                    </div>
                `;
                $grid.append(card);
            });
        },

        /**
         * Render top leaks list.
         */
        renderTopLeaks: function(leaks) {
            if (!leaks || leaks.length === 0) {
                $('#rls-top-leaks').html('<p class="rls-no-data">' + (this.config.strings?.noLeaks || 'No leaks found.') + '</p>');
                return;
            }

            const $list = $('#rls-top-leaks');
            $list.empty();

            // Show top 5
            const topLeaks = leaks.slice(0, 5);
            const self = this;

            topLeaks.forEach(function(leak) {
                $list.append(self.renderLeakItem(leak));
            });
        },

        /**
         * Render a single leak item.
         */
        renderLeakItem: function(leak) {
            const fixedClass = leak.is_fixed == 1 ? ' rls-leak-item--fixed' : '';
            const impact = typeof leak.revenue_impact === 'string' ? parseFloat(leak.revenue_impact) : leak.revenue_impact;

            return `
                <div class="rls-leak-item${fixedClass}" data-category="${leak.category || ''}" data-severity="${leak.severity || ''}">
                    <div class="rls-leak-item__severity rls-leak-item__severity--${leak.severity}"></div>
                    <div class="rls-leak-item__content">
                        <div class="rls-leak-item__title">${this.escapeHtml(leak.title)}</div>
                        <div class="rls-leak-item__description">${this.escapeHtml(leak.description)}</div>
                        <div class="rls-leak-item__meta">
                            <span class="rls-leak-item__impact">-${this.formatCurrency(impact)}/mo</span>
                            <span class="rls-leak-item__badge rls-leak-item__badge--${leak.severity}">${leak.severity}</span>
                            <span class="rls-leak-item__difficulty">Fix: ${leak.fix_difficulty || 'medium'}</span>
                        </div>
                        ${leak.fix_suggestion ? '<div class="rls-leak-item__fix" style="margin-top: 8px; padding: 8px 12px; background: #F0FDF4; border-radius: 6px; font-size: 12px; color: #166534;"><strong>Fix:</strong> ' + this.escapeHtml(leak.fix_suggestion) + '</div>' : ''}
                    </div>
                    <div class="rls-leak-item__actions">
                        ${leak.is_fixed != 1 ? '<button class="rls-btn rls-btn--sm rls-btn--secondary rls-btn-fix" data-id="' + leak.id + '">Mark Fixed</button>' : '<span style="color: #10B981; font-size: 12px; font-weight: 600;">✓ Fixed</span>'}
                        ${leak.fix_url ? '<a href="' + leak.fix_url + '" class="rls-btn rls-btn--sm rls-btn--ghost" target="_blank">Go Fix →</a>' : ''}
                    </div>
                </div>
            `;
        },


        /**
         * Render trend comparison.
         */
        renderTrend: function(comparison) {
            if (!comparison || !comparison.has_comparison) return;

            const $trend = $('#rls-trend');
            const direction = comparison.revenue_trend === 'improving' ? '↓' : '↑';
            const className = comparison.revenue_trend === 'improving' ? 'rls-summary__trend--improving' : 'rls-summary__trend--worsening';
            const amount = Math.abs(comparison.revenue_change);

            $trend.html(`<span class="${className}">${direction} ${this.formatCurrency(amount)} vs last scan</span>`);
        },

        /**
         * Render trend chart.
         */
        renderChart: function(history) {
            const canvas = document.getElementById('rls-trend-chart') || document.getElementById('rls-history-chart');
            if (!canvas || !history || history.length === 0) return;

            const ctx = canvas.getContext('2d');

            // Destroy existing chart
            if (this.chart) {
                this.chart.destroy();
            }

            const labels = history.reverse().map(function(scan) {
                return new Date(scan.completed_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
            });

            const data = history.map(function(scan) {
                return parseFloat(scan.total_revenue_loss);
            });

            this.chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Monthly Revenue Loss',
                        data: data,
                        borderColor: '#EF4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.05)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#EF4444',
                        pointBorderColor: '#FFFFFF',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#1F2937',
                            titleColor: '#F9FAFB',
                            bodyColor: '#F9FAFB',
                            borderColor: '#374151',
                            borderWidth: 1,
                            cornerRadius: 8,
                            padding: 12,
                            callbacks: {
                                label: function(context) {
                                    return '$' + context.parsed.y.toLocaleString() + '/month lost';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: '#9CA3AF', font: { size: 11 } }
                        },
                        y: {
                            grid: { color: 'rgba(0,0,0,0.04)' },
                            ticks: {
                                color: '#9CA3AF',
                                font: { size: 11 },
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    }
                }
            });
        },

        /**
         * Load results page data.
         */
        loadResults: function() {
            const self = this;

            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rls_get_results',
                    nonce: self.config.nonce
                },
                success: function(response) {
                    $('#rls-results-loading').hide();

                    if (response.success) {
                        self.currentResults = response.data;
                        self.renderResultsPage(response.data);
                    } else {
                        $('#rls-results-list').html('<div class="rls-no-data"><p>' + (response.data?.message || 'No results available.') + '</p></div>');
                    }
                },
                error: function() {
                    $('#rls-results-loading').hide();
                    $('#rls-results-list').html('<div class="rls-no-data"><p>Failed to load results.</p></div>');
                }
            });
        },

        /**
         * Render the results page.
         */
        renderResultsPage: function(data) {
            // Update summary bar
            $('#rls-results-total-loss').text(this.formatCurrency(data.total_revenue_loss));
            $('#rls-results-total-leaks').text(data.total_leaks || 0);
            $('#rls-results-scan-date').text(data.completed_at ? new Date(data.completed_at).toLocaleDateString() : '-');

            // Render all leaks
            const $list = $('#rls-results-list');
            $list.empty();

            if (!data.leaks || data.leaks.length === 0) {
                $list.html('<div class="rls-no-data"><p>No revenue leaks detected. Your store is well-optimized!</p></div>');
                return;
            }

            const self = this;
            data.leaks.forEach(function(leak) {
                $list.append(self.renderLeakItem(leak));
            });
        },

        /**
         * Filter results by category and severity.
         */
        filterResults: function() {
            const category = $('#rls-filter-category').val();
            const severity = $('#rls-filter-severity').val();

            $('.rls-leak-item').each(function() {
                const $item = $(this);
                const itemCategory = $item.data('category');
                const itemSeverity = $item.data('severity');

                let show = true;
                if (category && itemCategory !== category) show = false;
                if (severity && itemSeverity !== severity) show = false;

                $item.toggle(show);
            });
        },

        /**
         * Initialize filters.
         */
        initFilters: function() {
            // Filters are initialized via change event binding
        },

        /**
         * Load history page data.
         */
        loadHistory: function() {
            const self = this;

            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rls_get_history',
                    nonce: self.config.nonce,
                    limit: 20
                },
                success: function(response) {
                    if (response.success) {
                        self.renderHistoryTable(response.data.scans);
                        self.renderChart(response.data.scans);
                    }
                }
            });
        },

        /**
         * Render history table.
         */
        renderHistoryTable: function(scans) {
            const $tbody = $('#rls-history-tbody');
            if (!$tbody.length) return;

            $tbody.empty();

            if (!scans || scans.length === 0) {
                $tbody.html('<tr><td colspan="6" style="text-align: center; padding: 40px;">No scan history available. Run your first scan!</td></tr>');
                return;
            }

            scans.forEach(function(scan) {
                const date = new Date(scan.completed_at).toLocaleDateString('en-US', {
                    year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
                });

                $tbody.append(`
                    <tr>
                        <td>${date}</td>
                        <td><span style="text-transform: capitalize;">${scan.scan_type}</span></td>
                        <td><strong>${scan.total_leaks}</strong></td>
                        <td style="color: #EF4444; font-weight: 600;">$${parseFloat(scan.total_revenue_loss).toLocaleString()}</td>
                        <td><span class="rls-leak-item__badge rls-leak-item__badge--${scan.status === 'completed' ? 'medium' : 'low'}">${scan.status}</span></td>
                        <td><a href="admin.php?page=rls-results&scan_id=${scan.id}" class="rls-link">View →</a></td>
                    </tr>
                `);
            });
        },

        /**
         * Mark a leak as fixed.
         */
        markFixed: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const leakId = $btn.data('id');
            const self = this;

            $btn.text('Saving...').prop('disabled', true);

            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rls_mark_fixed',
                    nonce: self.config.nonce,
                    leak_id: leakId
                },
                success: function(response) {
                    if (response.success) {
                        const $item = $btn.closest('.rls-leak-item');
                        $item.addClass('rls-leak-item--fixed');
                        $btn.replaceWith('<span style="color: #10B981; font-size: 12px; font-weight: 600;">✓ Fixed</span>');
                        self.showToast('success', self.config.strings?.fixApplied || 'Marked as fixed!');
                    } else {
                        $btn.text('Mark Fixed').prop('disabled', false);
                        self.showToast('error', 'Failed to update.');
                    }
                },
                error: function() {
                    $btn.text('Mark Fixed').prop('disabled', false);
                    self.showToast('error', 'Failed to update.');
                }
            });
        },

        /**
         * Dismiss a leak.
         */
        dismissLeak: function(e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const leakId = $btn.data('id');
            const self = this;

            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'rls_dismiss_leak',
                    nonce: self.config.nonce,
                    leak_id: leakId
                },
                success: function(response) {
                    if (response.success) {
                        $btn.closest('.rls-leak-item').fadeOut(300);
                    }
                }
            });
        },

        /**
         * Save settings.
         */
        saveSettings: function(e) {
            e.preventDefault();
            const self = this;
            const $form = $(e.currentTarget);
            const formData = $form.serializeArray();

            const data = { action: 'rls_save_settings', nonce: self.config.nonce };
            formData.forEach(function(item) {
                if (item.name === 'scan_modules[]') {
                    if (!data.scan_modules) data.scan_modules = [];
                    data['scan_modules[]'] = data['scan_modules[]'] || [];
                    // Handle array
                } else {
                    data[item.name] = item.value;
                }
            });

            // Collect checkboxes properly
            data['scan_modules[]'] = [];
            $form.find('input[name="scan_modules[]"]:checked').each(function() {
                data['scan_modules[]'].push($(this).val());
            });

            // Handle email toggle
            if (!$form.find('input[name="email_reports"]').is(':checked')) {
                data.email_reports = 'no';
            }

            $.ajax({
                url: self.config.ajaxUrl,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        const $indicator = $('#rls-save-indicator');
                        $indicator.show();
                        setTimeout(function() { $indicator.fadeOut(); }, 3000);
                        self.showToast('success', 'Settings saved successfully.');
                    } else {
                        self.showToast('error', response.data?.message || 'Failed to save settings.');
                    }
                },
                error: function() {
                    self.showToast('error', 'Failed to save settings.');
                }
            });
        },

        /**
         * Format currency value.
         */
        formatCurrency: function(amount) {
            const num = parseFloat(amount) || 0;
            const symbol = this.config.currency || '$';
            return symbol + num.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        },

        /**
         * Escape HTML to prevent XSS.
         */
        escapeHtml: function(text) {
            if (!text) return '';
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
        },

        /**
         * Show toast notification.
         */
        showToast: function(type, message) {
            const toast = $('<div class="rls-toast rls-toast--' + type + '">' + this.escapeHtml(message) + '</div>');
            $('body').append(toast);

            setTimeout(function() {
                toast.fadeOut(300, function() { $(this).remove(); });
            }, 4000);
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        RLS.init();
    });

})(jQuery);
