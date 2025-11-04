<?php
/**
 * Zoho CRM integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class HJ_Zoho_CRM {

    public function __construct() {
        // Constructor
    }

    public function process_submission($formData, $field_mapping, $options) {
        $token = $this->ensure_token($options);
        if (!$token) {
            return [
                'success' => false,
                'name' => 'N/A',
                'message' => 'No valid Zoho token'
            ];
        }

        // Build lead data based on configured mappings
        $lead = [];
        
        foreach ($field_mapping as $zoho_field => $fluent_field) {
            if (empty($fluent_field)) continue;
            
            $value = $this->get_form_field_value($formData, $fluent_field);
            
            if ($value !== null && $value !== '') {
                // Special handling for phone fields
                if (in_array($zoho_field, ['Phone', 'Mobile']) && $this->looks_like_phone($value)) {
                    [$phone, $phone_valid] = $this->pick_first_valid_phone($value);
                    if ($phone_valid) {
                        $lead[$zoho_field] = (string)$phone;
                    }
                } 
                // Special handling for text fields that might need cleaning
                elseif (in_array($zoho_field, ['Description', 'Form_Message__c'])) {
                    $lead[$zoho_field] = $this->clean_text($this->val_to_string($value));
                }
                // Regular field mapping
                else {
                    $lead[$zoho_field] = $this->val_to_string($value);
                }
            }
        }
        
        // Ensure required fields have default values
        if (empty($lead['Lead_Source'])) $lead['Lead_Source'] = 'Website';
        if (empty($lead['Lead_Status'])) $lead['Lead_Status'] = 'New';
        if (empty($lead['Company'])) $lead['Company'] = '-';
        
        // Handle Lead_Source_URL
        if (empty($lead['Lead_Source_URL'])) {
            $url = $this->find_first($formData, ['lead_source_url']) ?: 
                   (isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : home_url('/'));
            $lead['Lead_Source_URL'] = $url;
        }
        
        // Special handling for GCLID field
        $gclid = $this->find_first($formData, ['gclid','zc_gad']);
        if ($gclid) {
            if (!empty($options['use_dollar_gclid'])) {
                $lead['$gclid'] = (string)$gclid;
            } elseif (isset($field_mapping['GCLID__c'])) {
                $lead['GCLID__c'] = (string)$gclid;
            }
        }

        // Remove empty fields
        $lead = array_filter($lead, function($v){ return !($v === null || $v === ''); });

        // Determine upsert strategy
        $has_email = !empty($lead['Email']);
        $has_phone = !empty($lead['Mobile']) || !empty($lead['Phone']);
        $use_upsert = $has_email || $has_phone;
        
        $dup_fields = [];
        if ($has_email) $dup_fields[] = 'Email';
        if (!empty($lead['Mobile'])) $dup_fields[] = 'Mobile';
        if (!empty($lead['Phone'])) $dup_fields[] = 'Phone';

        // Send to Zoho
        $result = $this->send_to_zoho($token, $options['dc'], [$lead], $response, $use_upsert ? 'upsert' : 'insert', $dup_fields);
        
        $name = trim(($lead['First_Name'] ?? '') . ' ' . ($lead['Last_Name'] ?? ''));
        
        return [
            'success' => $result,
            'name' => $name ?: 'N/A',
            'message' => $response
        ];
    }

    private function ensure_token($options) {
        if (empty($options['access_token']) || $options['token_expires_at'] <= time() + 300) {
            return $this->refresh_token($options);
        }
        return $options['access_token'];
    }

    private function refresh_token($options) {
        if (empty($options['refresh_token'])) {
            return false;
        }

        $urls = $this->dc_base_urls($options['dc']);
        $token_url = $urls['accounts'] . '/oauth/v2/token';

        $args = [
            'body' => [
                'grant_type'    => 'refresh_token',
                'client_id'     => $options['client_id'],
                'client_secret' => $options['client_secret'],
                'refresh_token' => $options['refresh_token'],
            ],
            'timeout' => 30,
        ];

        $res = wp_remote_post($token_url, $args);
        
        if (is_wp_error($res)) {
            return false;
        }

        $body = wp_remote_retrieve_body($res);
        $data = json_decode($body, true);

        if (empty($data['access_token'])) {
            return false;
        }

        // Update stored tokens
        $o = HJ_Zoho_Ads_Integration::get_opts();
        $o['access_token'] = $data['access_token'];
        if (!empty($data['refresh_token'])) {
            $o['refresh_token'] = $data['refresh_token'];
        }
        $o['token_expires_at'] = time() + ($data['expires_in'] ?? 3600);
        HJ_Zoho_Ads_Integration::update_opts($o);

        return $data['access_token'];
    }

    private function send_to_zoho($token, $dc, $rows, &$resp_out, $mode = 'insert', $duplicate_check_fields = []) {
        $urls = $this->dc_base_urls($dc);
        $base = $urls['api'] . '/crm/v2/Leads';
        $url  = ($mode === 'upsert') ? ($base . '/upsert') : $base;

        $payload = ['data' => array_map(function($r){ return (object)$r; }, $rows)];
        if ($mode === 'upsert' && $duplicate_check_fields) {
            $payload['duplicate_check_fields'] = array_values(array_unique($duplicate_check_fields));
        }

        $args = [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($payload),
            'method'  => 'POST',
            'timeout' => 25,
        ];

        $res = wp_remote_post($url, $args);
        if (is_wp_error($res)) { 
            $resp_out = $res->get_error_message(); 
            return false; 
        }

        $code = wp_remote_retrieve_response_code($res);
        $body = wp_remote_retrieve_body($res);
        $resp_out = $body;

        if ($code >= 200 && $code < 300) {
            $j = json_decode($body, true);
            if (!empty($j['data'][0]['status']) && in_array($j['data'][0]['status'], ['success','updated'], true)) {
                return true;
            }
        }
        return false;
    }

    private function get_form_field_value($formData, $fieldName) {
        // Direct field name lookup
        if (isset($formData[$fieldName])) {
            return $formData[$fieldName];
        }
        
        // Try nested field lookup (e.g., names.first_name)
        if (strpos($fieldName, '.') !== false) {
            $parts = explode('.', $fieldName);
            $value = $formData;
            foreach ($parts as $part) {
                if (is_array($value) && array_key_exists($part, $value)) {
                    $value = $value[$part];
                } else {
                    return null;
                }
            }
            return $value;
        }
        
        return null;
    }

    private function looks_like_phone($value) {
        $value = trim((string)$value);
        if (empty($value)) return false;
        
        // Basic phone number pattern check
        return preg_match('/^[\+\d\s\-\(\)\.]{6,20}$/', $value) === 1;
    }

    private function pick_first_valid_phone($raw) {
        $raw = trim((string)$raw);
        if ($raw === '') return ['', false];

        $cands = preg_split('/[,\;\/\|\r\n]+|\s{2,}/', $raw);
        if (!$cands) $cands = [$raw];

        foreach ($cands as $cand) {
            $cand = trim($cand);
            if ($cand === '') continue;

            $norm = $this->normalize_phone_candidate($cand);
            if ($this->is_valid_phone_for_zoho($norm)) {
                return [$norm, true];
            }
        }
        return ['', false];
    }

    private function normalize_phone_candidate($s) {
        $s = trim((string)$s);
        if (strpos($s, '00') === 0) {
            $s = '+' . substr($s, 2);
        }
        $has_plus = (substr($s, 0, 1) === '+');
        $digits   = preg_replace('/\D+/', '', $s);
        if ($has_plus) return '+' . $digits;
        return $digits;
    }

    private function is_valid_phone_for_zoho($s) {
        if ($s === '') return false;
        $digits = ltrim($s, '+');
        $len    = strlen($digits);
        if ($len < 6 || $len > 15) return false;
        if (!preg_match('/^\+?[0-9]+$/', $s)) return false;
        if ($s[0] === '+' && ($len < 7 || $len > 15)) return false;
        return true;
    }

    private function val_to_string($val) {
        if (is_array($val)) {
            $flat = [];
            array_walk_recursive($val, function($v) use (&$flat){ $flat[] = (string)$v; });
            return trim(implode("\n", array_filter($flat, function($s){ return $s !== ''; })));
        }
        return trim((string)$val);
    }

    private function clean_text($v) {
        $v = wp_strip_all_tags($v, true);
        $v = preg_replace("/\r\n|\r/", "\n", $v);
        $v = preg_replace("/\n{3,}/", "\n\n", $v);
        return trim($v);
    }

    private function find_first($arr, $keys) {
        foreach ($keys as $k) {
            if (strpos($k, '.') !== false) {
                $parts = explode('.', $k);
                $val = $arr;
                foreach ($parts as $p) {
                    if (is_array($val) && array_key_exists($p, $val)) { 
                        $val = $val[$p]; 
                    } else { 
                        $val = null; 
                        break; 
                    }
                }
                if ($val !== null && $val !== '') return is_array($val) ? reset($val) : $val;
            } else {
                if (isset($arr[$k]) && $arr[$k] !== '') return is_array($arr[$k]) ? reset($arr[$k]) : $arr[$k];
            }
        }
        return '';
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