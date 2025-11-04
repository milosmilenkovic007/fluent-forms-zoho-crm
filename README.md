# HJ Zoho + Google Ads Integration Plugin

Advanced WordPress plugin that integrates Fluent Forms with Zoho CRM and Google Ads conversion tracking.

## 🚀 Features

### ✅ Form-Specific Field Mapping
- Configure unique field mappings for each Fluent Form
- Visual interface for mapping form fields to Zoho CRM fields
- Support for standard, custom, and marketing fields

### ✅ Zoho CRM Integration
- OAuth 2.0 authentication for all Zoho data centers (EU, US, IN, AU)
- Automatic token refresh
- Duplicate detection and upsert functionality
- Comprehensive logging

### ✅ Google Ads Tracking  
- GCLID, GBRAID, WBRAID tracking
- UTM parameters capture
- Conversion tracking on form submission and thank-you pages
- Automatic cookie management

### ✅ Modular Architecture
- Clean, maintainable code structure
- Separate classes for different functionality
- Auto-loading system
- Asset management

## 📁 File Structure

```
fluent-forms-zoho-crm/
├── fluent-forms-zoho-crm.php          # Main plugin file
├── includes/                          # Core functionality
│   ├── class-hj-zoho-ads-integration.php
│   ├── class-hj-zoho-crm.php
│   └── class-hj-zoho-tracking.php
├── admin/                             # Admin interface
│   ├── class-hj-zoho-admin.php
│   └── views/
│       ├── settings-page.php
│       └── form-mapping-page.php
├── assets/                            # CSS & JavaScript
│   ├── admin.css
│   └── admin.js
└── README.md                          # This file
```

## 🛠 Installation

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to `Settings > HJ Zoho + Ads` to configure

## ⚙️ Configuration

### 1. Zoho CRM Setup
- Go to `Settings > HJ Zoho + Ads`
- Select your Zoho data center
- Enter Client ID, Client Secret, and Redirect URI
- Authorize with Zoho CRM

### 2. Form Field Mapping
- Go to `Settings > Zoho Form Mapping`
- Select a Fluent Form
- Map form fields to Zoho CRM fields
- Save the configuration

### 3. Google Ads (Optional)
- Enter Conversion ID and Label in main settings
- Configure thank-you page slug if needed

## 📋 Form-Specific Mapping

Each Fluent Form can have its own unique field mapping:

1. **Select Form**: Choose which form to configure
2. **Map Fields**: Connect form fields to Zoho CRM fields
3. **Save**: Each form maintains separate mappings

### Supported Field Types:
- **Standard**: First Name, Last Name, Email, Phone, Company, etc.
- **Custom**: Custom Zoho fields (e.g., `My_Custom_Field__c`)
- **Marketing**: GCLID, UTM parameters, lead source tracking

## 🔄 Migration from Old Version

The plugin automatically migrates old `field_map` settings to the new form-specific structure. Your existing mappings will be applied to all configured forms.

## 📊 Logging & Debugging

- View recent activity in `Settings > HJ Zoho + Ads`
- Detailed logs for each form submission
- Success/Error status tracking
- Response details from Zoho API

## 🏗 Development

### Adding New Field Types

1. Edit `get_zoho_fields()` in `admin/class-hj-zoho-admin.php`
2. Add special handling in `process_submission()` if needed

### Extending Functionality

The modular architecture makes it easy to:
- Add new integrations
- Modify field processing logic
- Extend admin interface
- Add new tracking parameters

## 🐛 Troubleshooting

### Common Issues:

**"Class already declared" error:**
- Deactivate the old plugin first
- Clear any caching plugins

**Form not sending data:**
- Check form ID is in allowed list
- Verify field mapping is configured
- Check Zoho authorization status

**Token expired:**
- Plugin automatically refreshes tokens
- Re-authorize if refresh fails

### Debug Steps:
1. Check plugin logs in admin
2. Verify form field names match mapping
3. Test Zoho API connection
4. Check consent requirements if enabled

## 📝 Changelog

### Version 1.1.0
- ✅ Modular architecture
- ✅ Form-specific field mapping  
- ✅ Enhanced admin interface
- ✅ Improved error handling
- ✅ Better asset management
- ✅ Migration support

### Version 1.0.x
- ✅ Basic Zoho CRM integration
- ✅ Google Ads tracking
- ✅ Single field mapping

## 🤝 Support

For support and customization requests, contact the plugin author.

---

**Note:** This plugin requires Fluent Forms to be installed and activated.