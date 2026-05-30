<?php
/**
 * History Template.
 *
 * @package RevenueLeakScanner
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="rls-app" id="rls-history-app">
    <div class="rls-app__header">
        <div class="rls-app__header-left">
            <div class="rls-app__logo">
                <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
                    <rect width="32" height="32" rx="8" fill="url(#logo-gradient3)"/>
                    <path d="M8 22L12 14L16 18L20 10L24 16" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="24" cy="16" r="2" fill="#FF6B6B"/>
                    <defs>
                        <linearGradient id="logo-gradient3" x1="0" y1="0" x2="32" y2="32">
                            <stop stop-color="#667EEA"/>
                            <stop offset="1" stop-color="#764BA2"/>
                        </linearGradient>
                    </defs>
                </svg>
                <h1 class="rls-app__title"><?php esc_html_e( 'Scan History', 'revenue-leak-scanner' ); ?></h1>
            </div>
        </div>
    </div>

    <!-- Trend Chart -->
    <div class="rls-chart-card">
        <h3 class="rls-section__title"><?php esc_html_e( 'Revenue Leak Trend Over Time', 'revenue-leak-scanner' ); ?></h3>
        <div class="rls-chart__container">
            <canvas id="rls-history-chart" height="250"></canvas>
        </div>
    </div>

    <!-- Past Scans Table -->
    <div class="rls-history-table-card">
        <h3 class="rls-section__title"><?php esc_html_e( 'Past Scans', 'revenue-leak-scanner' ); ?></h3>
        <div class="rls-table-wrapper">
            <table class="rls-table" id="rls-history-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Date', 'revenue-leak-scanner' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'revenue-leak-scanner' ); ?></th>
                        <th><?php esc_html_e( 'Issues Found', 'revenue-leak-scanner' ); ?></th>
                        <th><?php esc_html_e( 'Revenue Loss', 'revenue-leak-scanner' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'revenue-leak-scanner' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'revenue-leak-scanner' ); ?></th>
                    </tr>
                </thead>
                <tbody id="rls-history-tbody">
                    <!-- Populated by JavaScript -->
                </tbody>
            </table>
        </div>
    </div>
</div>
