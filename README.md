# PrepMedico Course Management

A WordPress plugin for managing course editions, ASiT membership discounts, and FluentCRM integration for WooCommerce.

## Features

### 1. Edition Management
- **Automatic Edition Tracking**: Each course can have numbered editions (1st, 2nd, 3rd, etc.)
- **Auto-Increment**: Editions automatically increment when the end date passes
- **Manual Control**: Admins can manually increment editions from the dashboard
- **Date Management**: Set start and end dates for each edition
- **Early Bird Pricing**: Configure early bird periods with separate pricing

### 2. ASiT Membership Integration
- **Checkout Field**: Users enter their ASiT membership number at checkout
- **Dynamic Discounts**: Different discount percentages for Early Bird vs Normal periods
- **Automatic Coupon Application**: Coupon is applied when valid membership number is entered
- **Membership Verification Display**: Shows verified status on:
  - Order confirmation (Thank You) page
  - Customer order emails
  - Admin order emails
  - WooCommerce admin order page

### 3. FluentCRM Integration
- **Automatic Contact Updates**: Syncs customer data to FluentCRM on order completion
- **Custom Field Mapping**: Maps course editions to FluentCRM custom fields
- **Tag Application**: Automatically applies course-specific tags
- **ASiT Field Sync**: Stores ASiT membership number in FluentCRM custom field
- **Bulk Sync Tool**: Sync historical orders to FluentCRM

---

## Installation

1. Upload the `woocommerce-edition-management` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **PrepMedico** in the admin menu to configure

---

## Configuration

### Edition Management Settings

Navigate to **PrepMedico → Edition Management**

For each course:
- **Current Edition**: The current edition number
- **Edition Start Date**: When the current edition starts
- **Edition End Date**: When the current edition ends (auto-increment triggers after this date)
- **Early Bird Start**: Optional early bird period start
- **Early Bird End**: Optional early bird period end

### ASiT Coupon Settings

Navigate to **PrepMedico → ASiT Coupon Management**

| Setting | Description |
|---------|-------------|
| Coupon Code | The WooCommerce coupon code for ASiT members (default: `ASIT`) |
| Early Bird Discount | Discount percentage when Early Bird is active |
| Normal Discount | Discount percentage when Early Bird is NOT active |

#### WooCommerce Coupon Setup

1. Go to **WooCommerce → Coupons**
2. Create a coupon with the code matching your ASiT coupon code
3. Set "Discount type" to "Percentage discount"
4. Under "Usage restriction" → "Product categories", add eligible categories
5. The discount amount is dynamically controlled by this plugin

---

## How It Works

### Edition Display

Products in managed categories display the edition number:
- **Product titles**: "1st - FRCS Course"
- **Cart items**: Shows edition information
- **Order items**: Edition is stored in order meta

### ASiT Membership Flow

1. User enters 6-8 digit ASiT membership number at checkout
2. Plugin validates the format
3. ASiT coupon is automatically applied
4. Discount is calculated based on Early Bird status
5. On order completion:
   - Membership number is stored in order meta
   - Number is synced to FluentCRM `asit` field
   - Verification badge appears in emails and order pages

### FluentCRM Sync

On order completion, the plugin:
1. Creates or updates the FluentCRM contact
2. Applies course-specific tags
3. Updates edition custom fields (e.g., `frcs_edition` = "1st FRCS")
4. Updates ASiT membership number if present

---

## Order Meta Fields

| Meta Key | Description |
|----------|-------------|
| `_asit_membership_number` | The ASiT membership number entered at checkout |
| `_wcem_asit_member` | Flag indicating ASiT membership (`yes`) |
| `_wcem_asit_number` | Duplicate of ASiT number for consistency |
| `_course_edition_{slug}` | Edition number for each course |
| `_edition_name_{slug}` | Full edition name (e.g., "1st FRCS") |
| `_edition_start_{slug}` | Edition start date |
| `_edition_end_{slug}` | Edition end date |
| `_wcem_fluentcrm_synced` | FluentCRM sync status |
| `_wcem_fluentcrm_sync_time` | When FluentCRM sync occurred |

---

## Admin Features

### Order Page

The "Course Edition Information" box on order edit pages shows:
- Edition information for each course in the order
- ASiT Member badge with membership number
- FluentCRM sync status
- Action buttons:
  - **Update Edition Data**: Recalculate edition based on current settings
  - **Sync to FluentCRM**: Manually trigger FluentCRM sync

### Bulk ASiT Sync

In **PrepMedico → ASiT Coupon Management**:
1. Click "Scan Orders for ASiT Coupon" to find all orders with ASiT membership
2. Review the count of orders found
3. Click "Sync All to FluentCRM" to update all contacts

---

## Hooks & Filters

### Actions

```php
// After edition is auto-incremented
do_action('pmcm_edition_incremented', $course_slug, $old_edition, $new_edition);

// After FluentCRM sync
do_action('pmcm_fluentcrm_synced', $order_id, $email);
```

### Filters

```php
// Modify courses configuration
add_filter('pmcm_courses', function($courses) {
    return $courses;
});

// Modify ASiT discount
add_filter('pmcm_asit_discount', function($discount, $is_early_bird) {
    return $discount;
}, 10, 2);
```

---

## Course Configuration

Courses are defined in `class-pmcm-core.php`. Each course has:

```php
'frcs' => [
    'name' => 'FRCS',
    'settings_prefix' => 'wcem_frcs_',
    'fluentcrm_tag' => 'FRCS',
    'fluentcrm_field' => 'frcs_edition',
    'edition_management' => true,
    'asit_eligible' => true,
    'child_categories' => ['frcs-online', 'frcs-in-person']
]
```

| Property | Description |
|----------|-------------|
| `name` | Display name |
| `settings_prefix` | Prefix for WordPress options |
| `fluentcrm_tag` | Tag to apply in FluentCRM |
| `fluentcrm_field` | Custom field slug in FluentCRM |
| `edition_management` | Whether this course has edition tracking |
| `asit_eligible` | Whether ASiT discount applies |
| `child_categories` | Sub-categories that inherit parent settings |

---

## FluentCRM Custom Fields

Create these custom fields in FluentCRM (**Settings → Custom Fields**):

| Field Label | Slug | Type |
|-------------|------|------|
| FRCS Edition | `frcs_edition` | Text |
| FRCS-VASC Edition | `frcs_vasc_edition` | Text |
| Library Subscription | `library_subscription` | Text |
| ASiT | `asit` | Text |

---

## Troubleshooting

### Edition not auto-incrementing
- Check that the Edition End Date has passed
- Verify the WordPress cron is running (`wp cron event list`)
- Check the Activity Log in Edition Management page

### ASiT discount not applying
- Verify the WooCommerce coupon exists and is active
- Check that the coupon code matches the setting
- Ensure the product is in an ASiT-eligible category

### FluentCRM not syncing
- Click "Test FluentCRM" on the Edition Management page
- Verify FluentCRM plugin is active
- Check that custom fields exist in FluentCRM
- Review the Activity Log for errors

### ASiT number not showing
- Check order meta for `_asit_membership_number`
- Run bulk scan from ASiT Coupon Management page
- Verify the checkout field is visible (cart must have eligible products)

---

## File Structure

```
woocommerce-edition-management/
├── prepmedico-course-management.php    # Main plugin file
├── includes/
│   ├── class-pmcm-core.php            # Core functions & course config
│   ├── class-pmcm-admin.php           # Admin pages & AJAX handlers
│   ├── class-pmcm-frontend.php        # Frontend display & emails
│   ├── class-pmcm-cart.php            # Cart & order processing
│   ├── class-pmcm-cron.php            # Scheduled tasks
│   ├── class-pmcm-fluentcrm.php       # FluentCRM integration
│   ├── class-pmcm-asit.php            # ASiT checkout & discount
│   └── class-pmcm-shortcodes.php      # Shortcodes
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   └── js/
│       └── admin.js
└── README.md
```

---

## Changelog

### Version 1.4.0
- Added ASiT membership number tracking (replaces simple coupon detection)
- ASiT number displayed in order emails with verification badge
- ASiT number synced to FluentCRM custom field
- Bulk sync tool for historical ASiT orders
- Improved order confirmation page display

### Version 1.3.0
- Added ASiT Member badge to admin order page
- FluentCRM integration for ASiT field
- Bulk sync functionality

### Version 1.2.0
- Edition management system
- Auto-increment functionality
- Early Bird pricing support

### Version 1.1.0
- FluentCRM integration
- Custom field and tag sync

### Version 1.0.0
- Initial release

---

## Support

For issues and feature requests, contact the development team or create an issue in the repository.

---

## License

This plugin is proprietary software developed for PrepMedico.
