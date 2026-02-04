# PrepMedico Course Management

A WordPress plugin for managing course editions, ASiT membership discounts, and FluentCRM integration for WooCommerce.

**Current Version: 2.4.0**

## Features

### 1. Edition Management
- **Automatic Edition Tracking**: Each course can have numbered editions (1st, 2nd, 3rd, etc.)
- **Auto-Increment**: Editions automatically increment when the end date passes
- **Manual Control**: Admins can manually increment editions from the dashboard
- **Date Management**: Set start and end dates for each edition
- **Early Bird Pricing**: Configure early bird periods with separate pricing
- **Next Edition (Slot B)**: Pre-configure the next edition with its own dates and early bird settings
- **Dynamic Course Configuration**: Courses can be added/edited/deleted from the admin UI (no code changes needed)

### 2. ASiT Membership Integration
- **Checkout Field**: Users enter their ASiT membership number at checkout
- **Per-Course Discount Modes**: Each course can be configured independently:
  - `none` - No ASiT discount, field hidden
  - `early_bird_only` - Discount only during early bird period
  - `always` - Discount always applies
- **Dynamic Per-Product Discounts**: Different discount percentages per course based on configuration
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
- **Connection Test**: Built-in FluentCRM connection test button

### 4. Shortcodes
- **Edition display shortcodes** for use in Elementor tables, pages, and templates
- **Current & Next edition support** with automatic fallback logic
- **TBA handling** when next edition dates aren't configured
- **Button shortcodes** with automatic disable states for closed/TBA editions
- See [Shortcodes Reference](#shortcodes-reference) below for full list

---

## Installation

1. Upload the `woocommerce-edition-management` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **PrepMedico** in the admin menu to configure

---

## Admin Pages

The plugin adds a **PrepMedico** menu in the WordPress admin with three sub-pages:

### Edition Management

Modern two-column layout with a course list sidebar (left) and settings panel (right).

For each course:
- **Current Edition**: The current edition number
- **Edition Start Date**: When the current edition starts
- **Edition End Date**: When the current edition ends (auto-increment triggers after this date)
- **Early Bird**: Toggle to enable early bird period with start/end dates
- **Next Edition (Slot B)**: Toggle to pre-configure the next edition number, dates, and early bird settings
- **Manual Increment**: Button to manually increment the edition (+1)
- **Activity Log**: View recent edition changes and cron events
- **Run Edition Check**: Manually trigger the cron check for auto-increment

### ASiT Coupon Management

Modern card-based layout for managing ASiT membership discount settings.

| Setting | Description |
|---------|-------------|
| Coupon Code | The WooCommerce coupon code for ASiT members (default: `ASIT`) |
| Per-Course Settings | Each course has its own discount mode, early bird discount %, and normal discount % |
| Bulk Sync | Scan historical orders and sync ASiT data to FluentCRM |

#### WooCommerce Coupon Setup

1. Go to **WooCommerce → Coupons**
2. Create a coupon with the code matching your ASiT coupon code
3. Set "Discount type" to "Percentage discount"
4. Under "Usage restriction" → "Product categories", add eligible categories
5. The discount amount is dynamically controlled by this plugin per product

### Course Configuration

Modern card-based interface for managing courses.

- **Add New Course**: Modal form to add a new course with all settings
- **Edit Course**: Click any course card to edit its configuration
- **Delete Course**: Remove a course from edition management
- **Child Categories**: Configure sub-categories that inherit parent course settings

---

## How It Works

### Edition Display

Products in managed categories display the edition number:
- **Product titles**: "1st - FRCS Course"
- **Cart items**: Shows edition information
- **Order items**: Edition is stored in order item meta (`_course_edition`)
- **Order confirmation & emails**: Reads saved edition from order meta (not from current DB settings)

### Next Edition (Slot B) Logic

- When a customer purchases a product tagged for the next edition, the next edition number is saved to the order
- Order confirmation and emails display the correct next edition number (not the current edition)
- FluentCRM tags and fields also reflect the correct next edition
- If next edition isn't explicitly configured, shortcodes automatically fallback to current edition + 1
- If next edition dates aren't set, date shortcodes return "TBA" and enrol buttons are disabled

### ASiT Membership Flow

1. User enters 6-8 digit ASiT membership number at checkout
2. Plugin validates the format
3. ASiT coupon is automatically applied
4. Discount is calculated per-product based on course configuration and early bird status
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
| `_course_edition` | Edition number saved to order item meta |
| `_course_edition_{slug}` | Edition number for each course (order-level) |
| `_edition_name_{slug}` | Full edition name (e.g., "1st FRCS") |
| `_edition_start_{slug}` | Edition start date |
| `_edition_end_{slug}` | Edition end date |
| `_wcem_courses_data` | JSON object with full course data for FluentCRM sync |
| `_wcem_fluentcrm_synced` | FluentCRM sync status |
| `_wcem_fluentcrm_sync_time` | When FluentCRM sync occurred |

---

## Shortcodes Reference

### Basic Shortcodes

| Shortcode | Description | Example |
|-----------|-------------|---------|
| `[current_edition]` | Display current edition badge | `[current_edition course="frcs"]` |
| `[edition_info]` | Edition info box with dates | `[edition_info course="frcs" show_dates="yes"]` |
| `[edition_number]` | Ordinal edition number | `[edition_number course="frcs"]` |
| `[registration_status]` | Registration status badge | `[registration_status course="frcs"]` |
| `[early_bird_message]` | Early bird promotional message | `[early_bird_message course="frcs"]` |
| `[course_registration_info]` | Complete registration info box | `[course_registration_info course="frcs"]` |

### Elementor Table Shortcodes

These support `slot="current"` or `slot="next"` for multi-edition tables:

| Shortcode | Description | Example |
|-----------|-------------|---------|
| `[pmcm_edition_ordinal]` | Ordinal number (e.g., "14th") | `[pmcm_edition_ordinal course="frcs" slot="next"]` |
| `[pmcm_edition_number_raw]` | Raw number (e.g., "14") | `[pmcm_edition_number_raw course="frcs" slot="current"]` |
| `[pmcm_edition_dates]` | Date range or single date | `[pmcm_edition_dates course="frcs" slot="next" format="range"]` |
| `[pmcm_edition_status]` | Status text or CSS class | `[pmcm_edition_status course="frcs" slot="next" output="class"]` |
| `[pmcm_edition_button]` | Enrol button with auto-disable | `[pmcm_edition_button course="frcs" slot="next" product="frcs-course" text="Enrol"]` |
| `[pmcm_edition_url]` | Product URL with edition param | `[pmcm_edition_url course="frcs" slot="current" product="frcs-course"]` |
| `[pmcm_edition_marker]` | Edition marker for product mapping | `[pmcm_edition_marker course="frcs" slot="next"]` |
| `[pmcm_edition_product_script]` | JS for edition-product mapping | `[pmcm_edition_product_script course="frcs"]` |
| `[pmcm_edition_products_script]` | Combined JS for all editions | `[pmcm_edition_products_script course="frcs"]` |

### Shortcode Parameters

**`slot` parameter:**
- `current` (default) - Uses current edition data
- `next` - Uses next edition data, with smart fallbacks:
  - **Ordinal/Number**: Falls back to current edition + 1 if next isn't configured
  - **Dates**: Returns "TBA" if next edition dates aren't set
  - **Status**: Returns `dates-tba` / `pmcm-dates-tba` if dates unavailable
  - **Button**: Renders disabled button with `pmcm-dates-tba` class

**`format` parameter (edition_dates):**
- `range` (default) - "January 1, 2025 - March 31, 2025"
- `start` - "January 1, 2025"
- `end` - "March 31, 2025"

**`output` parameter (edition_status):**
- `text` (default) - Returns: `open`, `closed`, `upcoming`, `dates-tba`
- `class` - Returns: `pmcm-open`, `pmcm-closed`, `pmcm-upcoming`, `pmcm-dates-tba`

---

## CSS Classes for Button States

These classes are applied to edition buttons and can be used to style external elements:

| Class | State | Behavior |
|-------|-------|----------|
| `.pmcm-open` | Registration open | Normal clickable button |
| `.pmcm-closed` | Registration closed | Disabled, 50% opacity, no pointer events |
| `.pmcm-upcoming` | Not yet open | Normal styling |
| `.pmcm-dates-tba` | Dates not available | Disabled, 50% opacity, no pointer events |

External enrol buttons with class `.enrol_btn_course` are also automatically disabled when next edition dates aren't available (via dynamic CSS output).

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

Courses are managed via the admin UI (**PrepMedico → Course Configuration**) and stored in the database. Each course has:

```php
'frcs' => [
    'name' => 'FRCS',
    'category_slug' => 'frcs',
    'settings_prefix' => '_frcs_',
    'fluentcrm_tag' => 'FRCS',
    'fluentcrm_field' => 'frcs_edition',
    'edition_management' => true,
    'asit_eligible' => true,
    'asit_discount_mode' => 'early_bird_only',
    'asit_early_bird_discount' => 5,
    'asit_normal_discount' => 0,
    'asit_show_field' => true,
    'children' => ['frcs-rapid-review', 'mock-viva', 'sba-q-bank']
]
```

| Property | Description |
|----------|-------------|
| `name` | Display name |
| `category_slug` | WooCommerce product category slug |
| `settings_prefix` | Prefix for WordPress options |
| `fluentcrm_tag` | Tag to apply in FluentCRM |
| `fluentcrm_field` | Custom field slug in FluentCRM |
| `edition_management` | Whether this course has edition tracking |
| `asit_eligible` | Whether ASiT discount applies |
| `asit_discount_mode` | `none`, `early_bird_only`, or `always` |
| `asit_early_bird_discount` | Discount % during early bird period |
| `asit_normal_discount` | Discount % outside early bird period |
| `asit_show_field` | Whether to show ASiT field at checkout |
| `children` | Sub-categories that inherit parent settings |

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
- Use "Run Edition Check Now" button to manually trigger

### ASiT discount not applying
- Verify the WooCommerce coupon exists and is active
- Check that the coupon code matches the setting
- Ensure the product is in an ASiT-eligible category
- Check the course's ASiT discount mode isn't set to `none`

### FluentCRM not syncing
- Click "Test FluentCRM" on the Edition Management page
- Verify FluentCRM plugin is active
- Check that custom fields exist in FluentCRM
- Review the Activity Log for errors

### ASiT number not showing
- Check order meta for `_asit_membership_number`
- Run bulk scan from ASiT Coupon Management page
- Verify the checkout field is visible (cart must have eligible products)

### Order showing wrong edition number
- The order confirmation and emails read from saved order item meta (`_course_edition`)
- If the order was placed before v2.4.0, the edition may display from current DB settings instead
- Re-saving the order or running "Update Edition Data" can refresh the display

### Next edition shortcodes showing wrong data
- Verify next edition is enabled in Edition Management settings
- If next edition dates aren't set, shortcodes display "TBA" and buttons are disabled
- Edition ordinal/number shortcodes fallback to current + 1 when next isn't configured

---

## File Structure

```
woocommerce-edition-management/
├── woocommerce-edition-management.php  # Main plugin file
├── includes/
│   ├── class-pmcm-core.php             # Core functions & dynamic course config
│   ├── class-pmcm-admin.php            # Admin pages & AJAX handlers
│   ├── class-pmcm-frontend.php         # Frontend display & emails
│   ├── class-pmcm-cart.php             # Cart & order processing
│   ├── class-pmcm-cron.php             # Scheduled tasks
│   ├── class-pmcm-fluentcrm.php        # FluentCRM integration
│   ├── class-pmcm-asit.php             # ASiT checkout & per-product discount
│   ├── class-pmcm-shortcodes.php       # All shortcodes
│   └── class-pmcm-product-expiration.php # Product expiration handling
├── assets/
│   ├── css/
│   │   ├── admin.css                    # Admin panel styles
│   │   └── frontend.css                 # Frontend styles
│   └── js/
│       └── admin.js                     # Admin panel JavaScript
├── uninstall.php                        # Cleanup on plugin deletion
└── README.md
```

---

## Changelog

### Version 2.4.0
- Fixed order confirmation and emails displaying current edition instead of the purchased edition
- Order item name now reads from saved `_course_edition` meta instead of re-querying the database
- `[pmcm_edition_ordinal]` with `slot="next"` falls back to current + 1 when next edition isn't configured
- `[pmcm_edition_number_raw]` with `slot="next"` falls back to current + 1
- `[pmcm_edition_marker]` with `slot="next"` falls back to current + 1
- `[pmcm_edition_dates]` returns "TBA" when next edition dates aren't available
- `[pmcm_edition_status]` returns `dates-tba` / `pmcm-dates-tba` for unconfigured next editions
- `[pmcm_edition_button]` renders disabled button with `pmcm-dates-tba` class when dates unavailable
- Added CSS disabled states for `.pmcm-edition-btn.pmcm-dates-tba` and `.pmcm-edition-btn.pmcm-closed`
- External `.enrol_btn_course` buttons automatically disabled via dynamic CSS when dates not set

### Version 2.3.1
- Fixed Course Configuration modal appearing on page load instead of course cards
- CSS specificity fix: modal default display set to `none !important` with `.wcem-modal-visible` toggle class
- Updated JavaScript to use class toggling instead of jQuery `.show()`/`.hide()` for modal visibility

### Version 2.3.0
- UI/UX fixes across admin pages
- Button fixes

### Version 2.2.0
- Redesigned Course Configuration page with modern card-based layout
- Add/Edit/Delete courses via modal form
- Dynamic course management stored in database

### Version 2.1.0
- Redesigned ASiT Coupon Management page with modern card-based layout
- Per-course ASiT discount configuration (mode, early bird %, normal %)

### Version 2.0.0
- Complete admin panel redesign with modern two-column layout
- Course list sidebar with status badges (Active, Early Bird, Ending Soon, Needs Dates)
- Settings panel with collapsible sections for each course
- Next Edition (Slot B) support for pre-configuring upcoming editions
- Activity log with recent edition changes
- Inter font and Material Icons integration

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



flowchart TD
    A[WordPress Loads] --> B[Plugin Bootstrap File]
    B --> C[Load Core Classes]
    C --> D[Register Hooks & Filters]

    D --> E[Admin Panel]
    D --> F[Frontend Shortcodes]
    D --> G[WooCommerce Cart & Checkout]

    G --> H[Order Created]
    H --> I[Store Edition & Membership Meta]
    I --> J[Sync Data to FluentCRM]


flowchart TD
    A[Admin Sets Current Edition] --> B[Edition Start & End Date Saved]
    B --> C{Edition Ended?}

    C -- No --> D[Show Current Edition]
    C -- Yes --> E[Increment Edition Number]

    E --> F[Mark Old Edition as Completed]
    F --> G[Activate Next Edition]
    G --> H[Frontend Shows New Edition]


flowchart TD
    A[User Adds Course to Cart] --> B[Check Edition Pricing Rules]

    B --> C{Early Bird Active?}
    C -- Yes --> D[Apply Early Bird Price]
    C -- No --> E[Apply Regular Price]

    D --> F[Checkout Page]
    E --> F

    F --> G{ASiT Member?}
    G -- Yes --> H[Apply ASiT Discount]
    G -- No --> I[Skip Discount]

    H --> J[Order Placed]
    I --> J

    J --> K[Save Edition & Membership Data]
    K --> L[Sync Contact to FluentCRM]


## License

This plugin is proprietary software developed for PrepMedico.
