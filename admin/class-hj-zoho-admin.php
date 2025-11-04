<?php
/**
 * Admin functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class HJ_Zoho_Admin {

    public function __construct() {
        $this->hooks();
    }

    private function hooks() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'handle_oauth_return']);
        add_action('admin_init', [$this, 'maybe_save_settings']);
        // Process Mapping save early in the request to allow redirects before output
        add_action('admin_init', [$this, 'maybe_save_mapping']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    public function admin_menu() {
        add_options_page('HJ Zoho + Ads', 'HJ Zoho + Ads', 'manage_options', 'hj-zoho-ads', [$this, 'settings_page']);
        add_submenu_page('options-general.php', 'Zoho Form Mapping', 'Zoho Form Mapping', 'manage_options', 'hj-zoho-form-mapping', [$this, 'form_mapping_page']);
    }

    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'hj-zoho') === false) {
            return;
        }
        
        wp_enqueue_style('hj-zoho-admin', HJ_ZOHO_ADS_PLUGIN_URL . 'assets/admin.css', [], HJ_ZOHO_ADS_VERSION);
        wp_enqueue_script('hj-zoho-admin', HJ_ZOHO_ADS_PLUGIN_URL . 'assets/admin.js', ['jquery'], HJ_ZOHO_ADS_VERSION, true);
    }

    public function settings_page() {
        if (!current_user_can('manage_options')) return;
        
        $o = HJ_Zoho_Ads_Integration::get_opts();
        $urls = $this->dc_base_urls($o['dc']);

        $auth_link = add_query_arg([
            'scope'         => 'ZohoCRM.modules.leads.ALL',
            'client_id'     => $o['client_id'],
            'response_type' => 'code',
            'access_type'   => 'offline',
            'redirect_uri'  => $o['redirect_uri'],
            'prompt'        => 'consent'
        ], $urls['accounts'].'/oauth/v2/auth');

        $logs = get_option(HJ_Zoho_Ads_Integration::LOG, []);
        
        include HJ_ZOHO_ADS_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    public function form_mapping_page() {
        if (!current_user_can('manage_options')) return;
        
        $o = HJ_Zoho_Ads_Integration::get_opts();
        $fluent_forms = $this->get_fluent_forms();
        $selected_form_id = intval($_GET['form_id'] ?? 0);
        
        // If no form selected, default to first available form
        if (!$selected_form_id && !empty($fluent_forms)) {
            $selected_form_id = array_key_first($fluent_forms);
        }
        
        $form_fields = [];
        $zoho_fields = $this->get_zoho_fields();
        $current_mapping = [];
        
        if ($selected_form_id) {
            $form_fields = $this->get_form_fields($selected_form_id);
            $current_mapping = $o['form_mappings'][$selected_form_id] ?? [];
        }
        
        // Debug: Show raw form structure if debug parameter is present
        $show_debug = isset($_GET['debug']) && $_GET['debug'] === '1';
        if ($show_debug && $selected_form_id) {
            $this->show_form_debug($selected_form_id);
        }
        
        include HJ_ZOHO_ADS_PLUGIN_DIR . 'admin/views/form-mapping-page.php';
    }

    // Handle form mapping save early (before page content renders) to avoid header warnings
    public function maybe_save_mapping() {
        if (!current_user_can('manage_options')) return;
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
        if (!isset($_POST['hj_action']) || $_POST['hj_action'] !== 'save_mapping') return;
        if (!isset($_POST[HJ_Zoho_Ads_Integration::NONCE]) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST[HJ_Zoho_Ads_Integration::NONCE])), HJ_Zoho_Ads_Integration::NONCE)) return;
        // At this point we can safely save and redirect without sending output
        $this->save_form_mapping();
    }

    public function maybe_save_settings() {
        if (!current_user_can('manage_options')) return;
        // Only handle saves from the main settings page to avoid clobbering options
        if (!isset($_GET['page']) || $_GET['page'] !== 'hj-zoho-ads') return;
        if (!isset($_POST[HJ_Zoho_Ads_Integration::NONCE]) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST[HJ_Zoho_Ads_Integration::NONCE])), HJ_Zoho_Ads_Integration::NONCE)) return;

        $o = HJ_Zoho_Ads_Integration::get_opts();

        if (isset($_POST['clear_logs'])) {
            update_option(HJ_Zoho_Ads_Integration::LOG, []);
            return;
        }

        $o['dc']               = sanitize_text_field(wp_unslash($_POST['dc'] ?? $o['dc']));
        $o['client_id']        = sanitize_text_field(wp_unslash($_POST['client_id'] ?? ''));
        $o['client_secret']    = sanitize_text_field(wp_unslash($_POST['client_secret'] ?? ''));
        $o['redirect_uri']     = esc_url_raw(wp_unslash($_POST['redirect_uri'] ?? ''));
        $o['form_ids_csv']     = sanitize_text_field(wp_unslash($_POST['form_ids_csv'] ?? $o['form_ids_csv']));
        $o['use_dollar_gclid'] = isset($_POST['use_dollar_gclid']) ? 1 : 0;
        $o['require_consent']  = isset($_POST['require_consent']) ? 1 : 0;
        $o['consent_field_names'] = sanitize_text_field(wp_unslash($_POST['consent_field_names'] ?? 'consent,gdpr_consent'));

        $o['ads_conversion_id']    = sanitize_text_field(wp_unslash($_POST['ads_conversion_id'] ?? ''));
        $o['ads_conversion_label'] = sanitize_text_field(wp_unslash($_POST['ads_conversion_label'] ?? ''));
        $o['thankyou_slug']        = sanitize_title(wp_unslash($_POST['thankyou_slug'] ?? 'thank-you'));
        $o['fire_on_submit']       = isset($_POST['fire_on_submit']) ? 1 : 0;

        HJ_Zoho_Ads_Integration::update_opts($o);
        
        wp_redirect(admin_url('options-general.php?page=hj-zoho-ads&saved=1'));
        exit;
    }

    public function handle_oauth_return() {
        if (!current_user_can('manage_options')) return;
        if (!isset($_GET['code']) || !isset($_GET['page']) || $_GET['page'] !== 'hj-zoho-ads') return;

        $code = sanitize_text_field(wp_unslash($_GET['code']));
        $o = HJ_Zoho_Ads_Integration::get_opts();
        
        if (empty($o['client_id']) || empty($o['client_secret']) || empty($o['redirect_uri'])) {
            wp_die('Missing Zoho OAuth configuration');
        }

        $urls = $this->dc_base_urls($o['dc']);
        $token_url = $urls['accounts'] . '/oauth/v2/token';

        $args = [
            'body' => [
                'grant_type'    => 'authorization_code',
                'client_id'     => $o['client_id'],
                'client_secret' => $o['client_secret'],
                'redirect_uri'  => $o['redirect_uri'],
                'code'          => $code,
            ],
            'timeout' => 30,
        ];

        $res = wp_remote_post($token_url, $args);
        
        if (is_wp_error($res)) {
            wp_die('OAuth Error: ' . $res->get_error_message());
        }

        $body = wp_remote_retrieve_body($res);
        $data = json_decode($body, true);

        if (empty($data['access_token'])) {
            wp_die('OAuth Error: ' . ($data['error'] ?? 'Unknown error'));
        }

        $o['access_token'] = $data['access_token'];
        $o['refresh_token'] = $data['refresh_token'] ?? '';
        $o['token_expires_at'] = time() + ($data['expires_in'] ?? 3600);

        HJ_Zoho_Ads_Integration::update_opts($o);
        
        wp_redirect(admin_url('options-general.php?page=hj-zoho-ads&authorized=1'));
        exit;
    }

    private function save_form_mapping() {
        $form_id = intval($_POST['form_id'] ?? 0);
        if (!$form_id) return;

        $o = HJ_Zoho_Ads_Integration::get_opts();
        
        if (!isset($o['form_mappings'])) {
            $o['form_mappings'] = [];
        }

        if (isset($_POST['field_mapping']) && is_array($_POST['field_mapping'])) {
            $field_mapping = array_map('sanitize_text_field', wp_unslash($_POST['field_mapping']));
            // Remove empty mappings
            $field_mapping = array_filter($field_mapping, function($value) {
                return !empty($value);
            });
            $o['form_mappings'][$form_id] = $field_mapping;
        } else {
            $o['form_mappings'][$form_id] = [];
        }

        HJ_Zoho_Ads_Integration::update_opts($o);
        
        wp_redirect(admin_url('options-general.php?page=hj-zoho-form-mapping&form_id=' . $form_id . '&saved=1'));
        exit;
    }

    private function get_fluent_forms() {
        $forms = [];
        
        if (!class_exists('FluentForm\App\Models\Form')) {
            return $forms;
        }
        
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'fluentform_forms';
            $results = $wpdb->get_results("SELECT id, title FROM {$table_name} WHERE status = 'published' ORDER BY title");
            
            foreach ($results as $form) {
                $forms[$form->id] = $form->title;
            }
        } catch (Exception $e) {
            // Fallback if query fails
        }
        
        return $forms;
    }

    private function get_form_fields($form_id) {
        $fields = [];
        
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'fluentform_forms';
            $form = $wpdb->get_row($wpdb->prepare("SELECT form_fields FROM {$table_name} WHERE id = %d", $form_id));
            
            if ($form && $form->form_fields) {
                $form_data = json_decode($form->form_fields, true);
                
                if (is_array($form_data)) {
                    // First, parse the JSON structure directly (handles containers/columns)
                    $fields = $this->parse_form_fields_recursive($form_data);
                    
                    // If still empty, try Fluent Forms API and re-parse
                    if (empty($fields)) {
                        $fields = $this->get_fields_via_api($form_id);
                    }
                    
                    // Final fallback
                    if (empty($fields)) {
                        $fields = $this->parse_form_fields_alternative($form_data);
                    }
                }
            }
        } catch (Exception $e) {
            // Return empty array if query fails
        }
        
        return $fields;
    }

    private function parse_form_fields_recursive($data) {
        // Robust recursive walker that understands Fluent Forms containers/columns
        $fields = [];

        $walk = function($node) use (&$walk, &$fields) {
            if (!is_array($node)) {
                return;
            }

            // If this structure has a top-level 'fields' key, walk those first
            if (isset($node['fields']) && is_array($node['fields'])) {
                foreach ($node['fields'] as $child) {
                    $walk($child);
                }
                // Continue walking in case there are siblings alongside 'fields'
            }

            // Handle container/columns pattern
            if (isset($node['element']) && $node['element'] === 'container' && isset($node['columns']) && is_array($node['columns'])) {
                foreach ($node['columns'] as $col) {
                    if (isset($col['fields']) && is_array($col['fields'])) {
                        foreach ($col['fields'] as $child) {
                            $walk($child);
                        }
                    }
                }
            }

            // If this is an actual field (not a container), capture it
            if (isset($node['element']) && $node['element'] !== 'container') {
                $type = $node['element'];

                // Only consider items that look like inputs (have attributes + name)
                $name = $node['attributes']['name'] ?? '';
                if (!empty($name)) {
                    $label = $node['settings']['label']
                        ?? $node['settings']['admin_field_label']
                        ?? ($node['attributes']['placeholder'] ?? ucfirst(str_replace('_', ' ', $type)));

                    $type_display = ucfirst(str_replace('_', ' ', $type));
                    $fields[$name] = $label . ' (' . $type_display . ')';
                }
            }

            // If this is a list (numeric keys), walk children
            $is_list = array_keys($node) === range(0, count($node) - 1);
            if ($is_list) {
                foreach ($node as $child) {
                    $walk($child);
                }
            }
        };

        $walk($data);
        return $fields;
    }

    private function get_fields_via_api($form_id) {
        $fields = [];

        // Try to use Fluent Forms DB helper if available
        if (function_exists('wpFluent')) {
            try {
                $form = wpFluent()->table('fluentform_forms')->find($form_id);
                if ($form && !empty($form->form_fields)) {
                    $decoded = json_decode($form->form_fields, true);
                    if (is_array($decoded)) {
                        // Reuse our robust recursive parser to handle containers/columns
                        $fields = $this->parse_form_fields_recursive($decoded);
                    }
                }
            } catch (Exception $e) {
                // Ignore and fall back
            }
        }

        // Alternative: Try FluentForm Model if available
        if (empty($fields) && class_exists('FluentForm\\App\\Models\\Form')) {
            try {
                $formObj = \FluentForm\App\Models\Form::find($form_id);
                if ($formObj && !empty($formObj->form_fields)) {
                    $decoded = json_decode($formObj->form_fields, true);
                    if (is_array($decoded)) {
                        $fields = $this->parse_form_fields_recursive($decoded);
                    }
                }
            } catch (Exception $e) {
                // Ignore and fall back
            }
        }

        return $fields;
    }

    private function parse_form_fields_alternative($data) {
        $fields = [];
        
        // Method 1: Look for direct 'fields' key
        if (isset($data['fields']) && is_array($data['fields'])) {
            foreach ($data['fields'] as $field) {
                if (is_array($field) && isset($field['attributes']['name'])) {
                    $field_name = $field['attributes']['name'];
                    $field_label = $field['settings']['label'] ?? $field['settings']['admin_field_label'] ?? $field_name;
                    $field_type = $field['element'] ?? 'unknown';
                    $fields[$field_name] = "{$field_label} ({$field_type})";
                }
            }
        }
        
        // Method 2: Look for 'form' key with nested fields
        if (empty($fields) && isset($data['form']) && is_array($data['form'])) {
            $fields = $this->parse_form_fields_alternative($data['form']);
        }
        
        // Method 3: Flat search for any item with name attribute
        if (empty($fields)) {
            $this->find_fields_flat($data, $fields);
        }
        
        return $fields;
    }

    private function find_fields_flat($data, &$fields) {
        if (is_array($data)) {
            foreach ($data as $item) {
                if (is_array($item)) {
                    // Check if this item has field attributes and element
                    if (isset($item['element']) && isset($item['attributes'])) {
                        $field_name = '';
                        $field_label = '';
                        $field_type = $item['element'] ?? 'unknown';
                        
                        // Extract field name
                        if (isset($item['attributes']['name']) && !empty($item['attributes']['name'])) {
                            $field_name = $item['attributes']['name'];
                        }
                        
                        // Extract field label
                        if (isset($item['settings']['label']) && !empty($item['settings']['label'])) {
                            $field_label = $item['settings']['label'];
                        } elseif (isset($item['settings']['admin_field_label']) && !empty($item['settings']['admin_field_label'])) {
                            $field_label = $item['settings']['admin_field_label'];
                        } elseif (isset($item['attributes']['placeholder']) && !empty($item['attributes']['placeholder'])) {
                            $field_label = $item['attributes']['placeholder'];
                        } else {
                            $field_label = ucfirst(str_replace('_', ' ', $field_type));
                        }
                        
                        // Handle fields with generic names
                        if (empty($field_name) || $field_name === $field_type) {
                            switch ($field_type) {
                                case 'input_text':
                                    $placeholder = $item['attributes']['placeholder'] ?? '';
                                    if (stripos($placeholder, 'name') !== false) {
                                        $field_name = 'full_name';
                                    } else {
                                        $field_name = 'input_text';
                                    }
                                    break;
                                case 'input_email':
                                    $field_name = 'email';
                                    break;
                                case 'phone':
                                    $field_name = 'mobile_phone';
                                    break;
                                case 'select':
                                    $field_name = 'preferred_contact_method';
                                    break;
                                case 'textarea':
                                    $field_name = 'message';
                                    break;
                                default:
                                    $field_name = $field_type;
                            }
                        }
                        
                        if (!empty($field_name) && !isset($fields[$field_name])) {
                            $field_type_display = ucfirst(str_replace('_', ' ', $field_type));
                            $fields[$field_name] = "{$field_label} ({$field_type_display})";
                        }
                    }
                    
                    // Recursively search nested arrays
                    $this->find_fields_flat($item, $fields);
                }
            }
        }
    }

    private function show_form_debug($form_id) {
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'fluentform_forms';
            $form = $wpdb->get_row($wpdb->prepare("SELECT form_fields, title FROM {$table_name} WHERE id = %d", $form_id));
            
            if ($form && $form->form_fields) {
                echo '<div class="notice notice-info">';
                echo '<h3>Debug Info for Form #' . $form_id . ': ' . esc_html($form->title) . '</h3>';
                echo '<p>Raw JSON Structure:</p>';
                echo '<textarea style="width:100%;height:200px;" readonly>';
                echo esc_textarea(json_encode(json_decode($form->form_fields, true), JSON_PRETTY_PRINT));
                echo '</textarea>';
                echo '</div>';
            }
        } catch (Exception $e) {
            echo '<div class="notice notice-error"><p>Debug error: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }

    private function get_zoho_fields() {
        return [
            'standard' => [
                'First_Name' => 'First Name',
                'Last_Name' => 'Last Name',
                'Email' => 'Email',
                'Phone' => 'Phone',
                'Mobile' => 'Mobile',
                'Company' => 'Company',
                'Description' => 'Description',
                'Lead_Source' => 'Lead Source',
                'Lead_Status' => 'Lead Status',
                'Lead_Source_URL' => 'Lead Source URL',
            ],
            'custom' => [
                'Form_Message__c' => 'Form Message (Custom)',
                'Contact_Through__c' => 'Contact Through (Custom)',
            ],
            'marketing' => [
                'GCLID__c' => 'Google Click ID',
                'GBRAID__c' => 'Google GBRAID',
                'WBRAID__c' => 'Google WBRAID', 
                'UTM_Source__c' => 'UTM Source',
                'UTM_Medium__c' => 'UTM Medium',
                'UTM_Campaign__c' => 'UTM Campaign',
                'UTM_Term__c' => 'UTM Term',
                'UTM_Content__c' => 'UTM Content',
            ]
        ];
    }

    private function dc_base_urls($dc) {
        switch ($dc) {
            case 'com': return ['accounts'=>'https://accounts.zoho.com',    'api'=>'https://www.zohoapis.com'];
            case 'in':  return ['accounts'=>'https://accounts.zoho.in',     'api'=>'https://www.zohoapis.in'];
            case 'au':  return ['accounts'=>'https://accounts.zoho.com.au', 'api'=>'https://www.zohoapis.com.au'];
            case 'eu':
            default:    return ['accounts'=>'https://accounts.zoho.eu',     'api'=>'https://www.zohoapis.eu'];
        }
    }
}