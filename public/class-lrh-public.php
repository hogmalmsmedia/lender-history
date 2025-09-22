<?php
/**
 * Public-facing functionality
 */
if (!defined('ABSPATH')) {
    exit;
}

class LRH_Public {
    
    /**
     * Enqueue public styles
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'lrh-public',
            LRH_PLUGIN_URL . 'public/css/lrh-public.css',
            [],
            time() // Use time() for development, change to LRH_VERSION for production
        );
    }
    
    /**
     * Enqueue public scripts
     */
    public function enqueue_scripts() {
        // Registrera scripts (laddar inte automatiskt)
        $this->register_scripts();
        
        // Huvudscriptet laddas alltid
        wp_enqueue_script('lrh-public');
        
        // Localize script
        wp_localize_script('lrh-public', 'lrh_public', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('lrh_public_nonce')
        ]);
    }
    
    /**
     * Register all scripts (but don't enqueue them)
     */
    private function register_scripts() {
        // Main public script - laddas alltid
        wp_register_script(
            'lrh-public',
            LRH_PLUGIN_URL . 'public/js/lrh-public.js',
            ['jquery'],
            time(), // Use time() for development, change to LRH_VERSION for production
            true
        );
        
        // Chart.js - laddas endast när interactive chart används
        wp_register_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            [],
            '4.4.0',
            true
        );
        
        // Chart.js Theme - gemensam styling för alla grafer
        // Laddas automatiskt när Chart.js laddas
        wp_register_script(
            'lrh-chart-theme',
            LRH_PLUGIN_URL . 'public/js/lrh-chart-theme.js',
            ['chartjs'], // Beroende av Chart.js
            time(), // Use time() for development, change to LRH_VERSION for production
            true
        );
        
        // Interactive chart script - laddas endast när shortcoden används
        wp_register_script(
            'lrh-interactive-chart',
            LRH_PLUGIN_URL . 'public/js/lrh-interactive-chart.js',
            ['jquery', 'chartjs', 'lrh-chart-theme'], // Lägg till theme som dependency
            time(), // Use time() for development, change to LRH_VERSION for production
            true
        );
    }
    
    /**
     * Check if page has sparklines (kan behållas för bakåtkompatibilitet)
     * Men eftersom du använder SVG nu behövs detta troligen inte
     */
    private function has_sparklines() {
        // Check if we're on a singular post/page with sparkline shortcodes
        if (is_singular()) {
            global $post;
            if ($post && (
                has_shortcode($post->post_content, 'lrh_rate_comparison') ||
                has_shortcode($post->post_content, 'lrh_rate_sparkline') ||
                strpos($post->post_content, 'show_sparkline="yes"') !== false
            )) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if page has interactive charts
     * Denna metod kan användas om du vill pre-checka för optimering
     */
    public function has_interactive_chart() {
        if (is_singular()) {
            global $post;
            if ($post && (
                has_shortcode($post->post_content, 'lrh_interactive_chart') ||
                has_shortcode($post->post_content, 'lrh_external_chart')
            )) {
                return true;
            }
        }
        return false;
    }
}