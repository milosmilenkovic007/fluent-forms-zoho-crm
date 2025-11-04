<?php
/**
 * Main plugin class
 */

if (!defined('ABSPATH')) {
    exit;
}

class HJ_Zoho_Ads_Integration {

    const OPT   = 'hj_zoho_ads_opts';
    const LOG   = 'hj_zoho_ads_logs';
    const NONCE = 'hj_zoho_ads_nonce';

    private $admin;
    private $zoho;
    private $tracking;

    public function __construct() {
        $this->init();
        $this->hooks();
    }

    private function init() {
        // Initialize components
        $this->admin = new HJ_Zoho_Admin();
        $this->zoho = new HJ_Zoho_CRM();
        $this->tracking = new HJ_Zoho_Tracking();
    }

    private function hooks() {
        add_action('fluentform/submission_inserted', [$this, 'on_fluent_submission'], 20, 3);
        add_action('init', [$this, 'maybe_upgrade_options']);
    }

    public static function defaults() {
        return [
            // Zoho
            'dc'               => 'eu',
            'client_id'        => '',
            'client_secret'    => '',
            'redirect_uri'     => '',
            'access_token'     => '',
            'refresh_token'    => '',
            'token_expires_at' => 0,

            // Forms
            'form_ids_csv'     => '3',
            'use_dollar_gclid' => 0,

            // Form-specific mappings
            'form_mappings'    => [], // [form_id => [zoho_field => fluent_field]]

            // Google Ads
            'ads_conversion_id'    => '',
            'ads_conversion_label' => '',
            'thankyou_slug'        => 'thank-you',
            'fire_on_submit'       => 1,

            // Consent
            'require_consent'     => 0,
            'consent_field_names' => 'consent,gdpr_consent',
        ];
    }

    public static function get_opts() {
        $o = get_option(self::OPT, []);
        return wp_parse_args($o, self::defaults());
    }

    public static function update_opts($opts) {
        update_option(self::OPT, $opts);
    }

    public function on_fluent_submission($entryId, $formData, $form) {
        $o = self::get_opts();
        $form_id = isset($form->id) ? intval($form->id) : 0;

        // Check if form is allowed
        $allowed = array_filter(array_map('absint', explode(',', $o['form_ids_csv'])));
        if ($form_id && !in_array($form_id, $allowed, true)) {
            $this->log($form_id, 'N/A', 'Ignored', 'Form ID not allowed');
            return;
        }

        // Check consent if required
        if (!empty($o['require_consent'])) {
            $names = array_map('trim', explode(',', $o['consent_field_names']));
            $ok = false;
            foreach ($names as $n) {
                if (isset($formData[$n]) && $this->is_truthy($formData[$n])) { 
                    $ok = true; 
                    break; 
                }
            }
            if (!$ok) { 
                $this->log($form_id, 'N/A', 'Skipped', 'Consent missing'); 
                return; 
            }
        }

        // Get form-specific mapping
        $form_mapping = $o['form_mappings'][$form_id] ?? [];
        if (empty($form_mapping)) {
            $this->log($form_id, 'N/A', 'Skipped', 'No field mapping configured for this form');
            return;
        }

        // Process submission
        $result = $this->zoho->process_submission($formData, $form_mapping, $o);
        
        if ($result['success']) {
            $this->log($form_id, $result['name'], 'Success', $result['message']);
        } else {
            $this->log($form_id, $result['name'], 'Error', $result['message']);
        }
    }

    private function is_truthy($v) {
        if (is_array($v)) $v = implode(',', $v);
        $v = strtolower(trim((string)$v));
        return in_array($v, ['1','yes','true','on','da','checked','y'], true);
    }

    private function log($form_id, $name, $status, $msg = '') {
        $logs = get_option(self::LOG, []);
        $logs[] = [
            't'       => current_time('mysql'),
            'form_id' => $form_id,
            'name'    => $name,
            'status'  => $status,
            'msg'     => $msg,
        ];
        
        // Keep only last 100 logs
        if (count($logs) > 100) {
            $logs = array_slice($logs, -100);
        }
        
        update_option(self::LOG, $logs);
    }

    public function maybe_upgrade_options() {
        $o = self::get_opts();
        $updated = false;

        // Migrate old field_map to form-specific mappings
        if (isset($o['field_map']) && !isset($o['form_mappings'])) {
            $o['form_mappings'] = [];
            // Apply old mapping to all configured forms
            $allowed_forms = array_filter(array_map('absint', explode(',', $o['form_ids_csv'])));
            foreach ($allowed_forms as $form_id) {
                $o['form_mappings'][$form_id] = $o['field_map'];
            }
            unset($o['field_map']);
            $updated = true;
        }

        if (empty($o['form_ids_csv'])) {
            $o['form_ids_csv'] = '3';
            $updated = true;
        }

        if ($updated) {
            self::update_opts($o);
        }
    }
}