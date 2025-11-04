<div class="wrap hj-field-mapping">
    <h1>Form Field Mapping Configuration</h1>
    
    <?php if (isset($_GET['saved'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>Field mapping saved successfully!</p>
        </div>
    <?php endif; ?>
    
    <div class="notice notice-info">
        <p><strong>How it works:</strong></p>
        <ul>
            <li>Select a Fluent Form to configure field mapping</li>
            <li>Map each Fluent Forms field to corresponding Zoho CRM fields</li>
            <li>Only mapped fields will be sent to Zoho CRM</li>
            <li>Each form can have its own unique field mapping configuration</li>
        </ul>
    </div>

    <!-- Form Selection -->
    <div class="form-selection-section">
        <h2>Select Form to Configure</h2>
        <form method="get" id="form-selector">
            <input type="hidden" name="page" value="hj-zoho-form-mapping">
            <table class="form-table">
                <tr>
                    <th>Choose Fluent Form:</th>
                    <td>
                        <select name="form_id" onchange="document.getElementById('form-selector').submit();" style="min-width: 300px;">
                            <option value="">-- Select a Form --</option>
                            <?php foreach ($fluent_forms as $fid => $ftitle): ?>
                                <option value="<?php echo esc_attr($fid); ?>" <?php selected($selected_form_id, $fid); ?>>
                                    Form #<?php echo esc_html($fid); ?>: <?php echo esc_html($ftitle); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($fluent_forms)): ?>
                            <p class="description" style="color: red;">No Fluent Forms found. Please create a form first.</p>
                        <?php endif; ?>
                        
                        <?php if ($selected_form_id): ?>
                            <p class="description">
                                <a href="<?php echo esc_url(admin_url('options-general.php?page=hj-zoho-form-mapping&form_id=' . $selected_form_id . '&debug=1')); ?>" target="_blank">
                                    🔍 Debug Form Structure
                                </a>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </form>
    </div>

    <?php if ($selected_form_id && !empty($form_fields)): ?>
        <!-- Field Mapping Configuration -->
        <form method="post" class="mapping-form">
            <?php wp_nonce_field(HJ_Zoho_Ads_Integration::NONCE, HJ_Zoho_Ads_Integration::NONCE); ?>
            <input type="hidden" name="hj_action" value="save_mapping">
            <input type="hidden" name="form_id" value="<?php echo esc_attr($selected_form_id); ?>">
            
            <div class="section-title">
                Field Mapping for Form #<?php echo esc_html($selected_form_id); ?>
                <?php if (isset($fluent_forms[$selected_form_id])): ?>
                    - <?php echo esc_html($fluent_forms[$selected_form_id]); ?>
                <?php endif; ?>
            </div>

            <div class="section-title">Standard Zoho CRM Fields</div>
            <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
                <p><strong>Detected Fluent Form fields (<?php echo count($form_fields); ?>):</strong>
                <?php echo esc_html(implode(', ', array_keys($form_fields))); ?></p>
            <?php endif; ?>
            <table class="form-table">
                <?php foreach ($zoho_fields['standard'] as $zoho_field => $zoho_label): ?>
                <tr>
                    <th><?php echo esc_html($zoho_label); ?></th>
                    <td>
                        <select name="field_mapping[<?php echo esc_attr($zoho_field); ?>]" style="min-width: 300px;">
                            <option value="">-- No mapping --</option>
                            <?php foreach ($form_fields as $ff_field => $ff_label): ?>
                                <option value="<?php echo esc_attr($ff_field); ?>" 
                                    <?php selected($current_mapping[$zoho_field] ?? '', $ff_field); ?>>
                                    <?php echo esc_html($ff_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Zoho API field: <code><?php echo esc_html($zoho_field); ?></code></p>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>

            <div class="section-title">Custom Zoho Fields</div>
            <table class="form-table">
                <?php foreach ($zoho_fields['custom'] as $zoho_field => $zoho_label): ?>
                <tr>
                    <th><?php echo esc_html($zoho_label); ?></th>
                    <td>
                        <select name="field_mapping[<?php echo esc_attr($zoho_field); ?>]" style="min-width: 300px;">
                            <option value="">-- No mapping --</option>
                            <?php foreach ($form_fields as $ff_field => $ff_label): ?>
                                <option value="<?php echo esc_attr($ff_field); ?>" 
                                    <?php selected($current_mapping[$zoho_field] ?? '', $ff_field); ?>>
                                    <?php echo esc_html($ff_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Zoho API field: <code><?php echo esc_html($zoho_field); ?></code></p>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>

            <div class="section-title">Marketing & Tracking Fields</div>
            <table class="form-table">
                <?php foreach ($zoho_fields['marketing'] as $zoho_field => $zoho_label): ?>
                <tr>
                    <th><?php echo esc_html($zoho_label); ?></th>
                    <td>
                        <select name="field_mapping[<?php echo esc_attr($zoho_field); ?>]" style="min-width: 300px;">
                            <option value="">-- No mapping --</option>
                            <?php foreach ($form_fields as $ff_field => $ff_label): ?>
                                <option value="<?php echo esc_attr($ff_field); ?>" 
                                    <?php selected($current_mapping[$zoho_field] ?? '', $ff_field); ?>>
                                    <?php echo esc_html($ff_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            Zoho API field: <code><?php echo esc_html($zoho_field); ?></code>
                            <?php if (in_array($zoho_field, ['GCLID__c', 'GBRAID__c', 'WBRAID__c'])): ?>
                                <br><em>This field is automatically populated from URL parameters/cookies</em>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>

            <p>
                <button type="submit" class="button button-primary">Save Field Mapping</button>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=hj-zoho-ads')); ?>" class="button button-secondary">Back to Main Settings</a>
            </p>
        </form>

        <!-- Current Mapping Overview -->
        <?php if (!empty($current_mapping)): ?>
            <div class="section-title">Current Mapping Overview</div>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Zoho CRM Field</th>
                        <th>Fluent Forms Field</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($current_mapping as $zoho => $fluent): ?>
                    <tr>
                        <td><code><?php echo esc_html($zoho); ?></code></td>
                        <td><?php echo esc_html($fluent); ?></td>
                        <td>
                            <?php if (array_key_exists($fluent, $form_fields)): ?>
                                <span class="hj-mapping-status active">✓ Active</span>
                            <?php else: ?>
                                <span class="hj-mapping-status warning">⚠ Field not found in form</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="notice notice-warning">
                <p>No field mapping configured for this form yet. Configure the mapping above to start sending form data to Zoho CRM.</p>
            </div>
        <?php endif; ?>

    <?php elseif ($selected_form_id && empty($form_fields)): ?>
        <div class="notice notice-error">
            <p>Could not load fields for the selected form. Please make sure the form exists and has fields configured.</p>
        </div>
    <?php elseif (!$selected_form_id && !empty($fluent_forms)): ?>
        <div class="notice notice-info">
            <p>Please select a Fluent Form above to configure field mapping.</p>
        </div>
    <?php endif; ?>
</div>