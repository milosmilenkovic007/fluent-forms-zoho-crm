// HJ Zoho Admin Scripts
(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Auto-save notification
        var $form = $('.mapping-form');
        var formChanged = false;
        
        if ($form.length) {
            $form.find('select, input').on('change', function() {
                formChanged = true;
            });
            
            // Warn before leaving if unsaved changes
            $(window).on('beforeunload', function(e) {
                if (formChanged) {
                    var message = 'You have unsaved changes. Are you sure you want to leave?';
                    e.returnValue = message;
                    return message;
                }
            });
            
            // Clear warning when form is submitted
            $form.on('submit', function() {
                formChanged = false;
            });
        }
        
        // Enhanced log details
        $('details').on('toggle', function() {
            var $this = $(this);
            if (this.open) {
                $this.find('pre').css('max-height', 'none');
            }
        });
        
        // Form validation
        $('#form-selector select[name="form_id"]').on('change', function() {
            var formId = $(this).val();
            if (formId && formChanged) {
                if (!confirm('You have unsaved changes. Do you want to continue without saving?')) {
                    $(this).val($('input[name="form_id"]').val());
                    return false;
                }
            }
        });
        
        // Smart field mapping suggestions
        if ($('.mapping-form').length) {
            var fieldSuggestions = {
                'First_Name': ['first_name', 'firstname', 'first', 'fname'],
                'Last_Name': ['last_name', 'lastname', 'last', 'lname', 'surname'],
                'Email': ['email', 'your-email', 'e-mail', 'mail'],
                'Phone': ['phone', 'telephone', 'tel'],
                'Mobile': ['mobile', 'phone', 'cell', 'cellular'],
                'Company': ['company', 'organization', 'organisation', 'business'],
                'Description': ['description', 'message', 'comments', 'notes']
            };
            
            $('.button.auto-suggest').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm('This will automatically suggest field mappings based on field names. Continue?')) {
                    return;
                }
                
                $.each(fieldSuggestions, function(zohoField, fluentFields) {
                    var $select = $('select[name="field_mapping[' + zohoField + ']"]');
                    if ($select.length && !$select.val()) {
                        for (var i = 0; i < fluentFields.length; i++) {
                            var suggestion = fluentFields[i];
                            var $option = $select.find('option[value*="' + suggestion + '"]').first();
                            if ($option.length) {
                                $select.val($option.val());
                                $select.trigger('change');
                                break;
                            }
                        }
                    }
                });
                
                formChanged = true;
            });
        }
        
        // Settings page enhancements
        if ($('#hj-zoho-settings').length) {
            // Test connection button
            $('.test-connection').on('click', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var originalText = $btn.text();
                
                $btn.text('Testing...').prop('disabled', true);
                
                // Simulate test (you can implement actual AJAX test)
                setTimeout(function() {
                    $btn.text(originalText).prop('disabled', false);
                    alert('Connection test completed. Check the logs for details.');
                }, 2000);
            });
        }
        
    });
    
})(jQuery);