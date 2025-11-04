<div class="wrap">
    <h1>HJ Zoho + Google Ads Integration</h1>
    
    <?php if (isset($_GET['saved'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>Settings saved successfully!</p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['authorized'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>Zoho authorization successful!</p>
        </div>
    <?php endif; ?>
    
    <form method="post">
        <?php wp_nonce_field(HJ_Zoho_Ads_Integration::NONCE, HJ_Zoho_Ads_Integration::NONCE); ?>
        
        <h2 class="title">Zoho CRM</h2>
        <table class="form-table">
            <tr>
                <th>Data Center</th>
                <td>
                    <select name="dc">
                        <?php foreach (['eu'=>'EU','com'=>'COM (US)','in'=>'IN','au'=>'AU'] as $k=>$label): ?>
                            <option value="<?php echo esc_attr($k); ?>" <?php selected($o['dc'],$k); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr><th>Client ID</th><td><input type="text" name="client_id" value="<?php echo esc_attr($o['client_id']); ?>" size="60" class="regular-text"></td></tr>
            <tr><th>Client Secret</th><td><input type="text" name="client_secret" value="<?php echo esc_attr($o['client_secret']); ?>" size="60" class="regular-text"></td></tr>
            <tr><th>Redirect URI</th><td><input type="url" name="redirect_uri" value="<?php echo esc_attr($o['redirect_uri']); ?>" size="60" class="regular-text"></td></tr>
            <tr>
                <th>Authorization Status</th>
                <td>
                    <?php if ($o['access_token'] && $o['token_expires_at'] > time()): ?>
                        <span style="color:green;">✓ Authorized (expires in ~<?php echo intval(($o['token_expires_at']-time())/60); ?> min)</span>
                    <?php else: ?>
                        <span style="color:#999;">✗ Not authorized / Expired</span>
                    <?php endif; ?>
                    &nbsp;&nbsp;<a class="button button-primary" href="<?php echo esc_url($auth_link); ?>">Authorize with Zoho CRM</a>
                </td>
            </tr>
        </table>

        <h2 class="title">Fluent Forms</h2>
        <table class="form-table">
            <tr>
                <th>Allowed Form IDs</th>
                <td>
                    <input type="text" name="form_ids_csv" value="<?php echo esc_attr($o['form_ids_csv']); ?>" size="40" class="regular-text">
                    <p class="description">Comma-separated list of form IDs (e.g., 3,7,12)</p>
                </td>
            </tr>
            <tr>
                <th>Use Zoho $gclid field</th>
                <td>
                    <label>
                        <input type="checkbox" name="use_dollar_gclid" value="1" <?php checked($o['use_dollar_gclid'],1); ?>> 
                        Enable if you're using Zoho's official Google Ads connector
                    </label>
                </td>
            </tr>
            <tr>
                <th>Require Consent</th>
                <td>
                    <label>
                        <input type="checkbox" name="require_consent" value="1" <?php checked($o['require_consent'],1); ?>> 
                        Don't send data to Zoho without consent checkbox
                    </label>
                </td>
            </tr>
            <tr>
                <th>Consent Field Names</th>
                <td>
                    <input type="text" name="consent_field_names" value="<?php echo esc_attr($o['consent_field_names']); ?>" size="40" class="regular-text">
                    <p class="description">Comma-separated field names to check for consent</p>
                </td>
            </tr>
        </table>

        <h2 class="title">Google Ads Conversion Tracking</h2>
        <table class="form-table">
            <tr><th>Conversion ID</th><td><input type="text" name="ads_conversion_id" value="<?php echo esc_attr($o['ads_conversion_id']); ?>" size="40" class="regular-text"></td></tr>
            <tr><th>Conversion Label</th><td><input type="text" name="ads_conversion_label" value="<?php echo esc_attr($o['ads_conversion_label']); ?>" size="40" class="regular-text"></td></tr>
            <tr><th>Thank You Page Slug</th><td><input type="text" name="thankyou_slug" value="<?php echo esc_attr($o['thankyou_slug']); ?>" size="20" class="regular-text"></td></tr>
            <tr>
                <th>Fire on Submit</th>
                <td>
                    <label>
                        <input type="checkbox" name="fire_on_submit" value="1" <?php checked($o['fire_on_submit'],1); ?>> 
                        Trigger conversion immediately after successful form submission
                    </label>
                </td>
            </tr>
        </table>

        <p>
            <button class="button button-primary">Save Settings</button>
            <a href="<?php echo esc_url(admin_url('options-general.php?page=hj-zoho-form-mapping')); ?>" class="button button-secondary">Configure Form Field Mapping</a>
        </p>
    </form>

    <hr>
    
    <h2>Recent Activity Logs</h2>
    <form method="post" style="margin-bottom:10px;">
        <?php wp_nonce_field(HJ_Zoho_Ads_Integration::NONCE, HJ_Zoho_Ads_Integration::NONCE); ?>
        <input type="hidden" name="clear_logs" value="1">
        <button class="button">Clear All Logs</button>
    </form>
    
    <?php if ($logs): ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Form ID</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach (array_reverse(array_slice($logs, -20)) as $row): ?>
                <tr>
                    <td><?php echo esc_html($row['t']); ?></td>
                    <td><?php echo esc_html($row['form_id']); ?></td>
                    <td><?php echo esc_html($row['name']); ?></td>
                    <td>
                        <span class="status-<?php echo esc_attr(strtolower($row['status'])); ?>">
                            <?php echo esc_html($row['status']); ?>
                        </span>
                    </td>
                    <td>
                        <details>
                            <summary>View Details</summary>
                            <pre style="background: #f1f1f1; padding: 10px; font-size: 11px; max-height: 200px; overflow-y: auto;"><?php echo esc_html($row['msg']); ?></pre>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p><em>Showing last 20 entries</em></p>
    <?php else: ?>
        <p>No activity logs yet.</p>
    <?php endif; ?>
</div>