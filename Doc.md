# Dynamic Course Management with Simultaneous Editions

## Overview
Transform the hardcoded course configuration into a fully dynamic admin-managed system, allowing:
- Add/remove course categories from WordPress dashboard
- Map categories to FluentCRM tags and custom fields
- Run two editions simultaneously per course (Current + Next)
- Customer edition selection when both editions overlap

---

## Phase 1: Database Schema & Migration

### New WordPress Options Structure

**Option: `pmcm_course_mappings`** (array)
```php
[
    'frcs' => [
        'name' => 'FRCS',
        'category_slug' => 'frcs',
        'fluentcrm_tag' => 'FRCS',
        'fluentcrm_field' => 'frcs_edition',
        'edition_management' => true,
        'asit_eligible' => true,
        'children' => ['mock-viva', 'sba-q-bank', ...]
    ],
    // ... more courses
]
```

**Edition Slots (per course):**
```
{prefix}current_edition        -> Current slot edition number
{prefix}current_start          -> Current slot start date
{prefix}current_end            -> Current slot end date
{prefix}current_early_bird_*   -> Current slot early bird settings

{prefix}next_edition           -> Next slot edition number (optional)
{prefix}next_start             -> Next slot start date
{prefix}next_end               -> Next slot end date
{prefix}next_early_bird_*      -> Next slot early bird settings
{prefix}next_enabled           -> Is next slot active? (yes/no)
```

### Migration Script
- One-time migration from hardcoded `$default_courses` to `pmcm_course_mappings`
- Preserve existing edition numbers and dates
- Run on plugin update

**Files to modify:**
- [class-pmcm-core.php](includes/class-pmcm-core.php) - Replace hardcoded arrays with `get_option('pmcm_course_mappings')`

---

## Phase 2: Course Configuration Admin Page

### New Admin Menu: "Course Configuration"

**UI Layout:**
```
┌─────────────────────────────────────────────────────────────┐
│  COURSE CONFIGURATION                                       │
├─────────────────────────────────────────────────────────────┤
│  [+ Add New Course]                                         │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ FRCS                                          [Edit] │   │
│  │ Category: frcs                                       │   │
│  │ FluentCRM Tag: FRCS | Field: frcs_edition           │   │
│  │ Children: mock-viva, sba-q-bank, ...                │   │
│  │ ASiT Eligible: Yes | Edition Mgmt: Yes              │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ FRCOphth Part 1                               [Edit] │   │
│  │ ...                                                  │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

**Add/Edit Course Modal:**
- Category dropdown (fetches all WooCommerce product categories)
- Display name (text input)
- FluentCRM Tag name (text input)
- FluentCRM Custom Field slug (text input)
- Child categories (multi-select dropdown)
- ASiT eligible (checkbox)
- Edition management (checkbox)

**FluentCRM Setup Instructions Panel:**
```
┌─────────────────────────────────────────────────────────────┐
│  📋 FluentCRM Custom Field Setup                            │
│                                                             │
│  Before adding a course, create the custom field in        │
│  FluentCRM:                                                 │
│                                                             │
│  1. Go to FluentCRM → Settings → Custom Fields             │
│  2. Click "Add Field"                                       │
│  3. Field Type: Text                                        │
│  4. Field Label: e.g., "FRCS Edition"                      │
│  5. Field Slug: e.g., "frcs_edition" (use this below)      │
│  6. Save                                                    │
└─────────────────────────────────────────────────────────────┘
```

**Files to modify:**
- [class-pmcm-admin.php](includes/class-pmcm-admin.php) - Add new submenu page and AJAX handlers

---

## Phase 3: Edition Management Page Updates

### Two-Slot UI Per Course

**Updated Edition Management Layout:**
```
┌─────────────────────────────────────────────────────────────┐
│  FRCS                                                       │
├─────────────────────────────────────────────────────────────┤
│  CURRENT EDITION (Slot A)                                   │
│  ┌───────────────────────────────────────────────────────┐ │
│  │ Edition #: [12]  Start: [2024-01-15]  End: [2024-06-30]│ │
│  │ Early Bird: [x] Enabled  Start: [...]  End: [...]     │ │
│  └───────────────────────────────────────────────────────┘ │
│                                                             │
│  NEXT EDITION (Slot B)  [ ] Enable Next Edition            │
│  ┌───────────────────────────────────────────────────────┐ │
│  │ Edition #: [13]  Start: [2024-05-01]  End: [2024-12-31]│ │
│  │ Early Bird: [x] Enabled  Start: [...]  End: [...]     │ │
│  └───────────────────────────────────────────────────────┘ │
│                                                             │
│  ⚠️ Overlap Detected: May 1 - Jun 30                       │
│     Customers will choose between 12th and 13th edition    │
└─────────────────────────────────────────────────────────────┘
```

**Logic:**
- Current edition: Always active when dates are set
- Next edition: Optional, toggled by checkbox
- When date ranges overlap, show warning and customer will be prompted to select

**Files to modify:**
- [class-pmcm-admin.php](includes/class-pmcm-admin.php) - Update Edition Management UI

---

## Phase 4: Edition Selection via URL Parameter

### User Flow (Simplified)
Users select edition from a **custom table on the page** (built by client), NOT from product page:

```
┌────────────────────────────────────────────────────────────────┐
│ Course Edition │ Dates / Target Exam       │ Registration      │
├────────────────────────────────────────────────────────────────┤
│ 11th           │ Nov 1, 2025 - Jan 18, 2026│ [Enrol for course]│
│ 12th           │ Mar 7, 2026 - May 25, 2026│ [Enrol for course]│
│ 13th           │ Coming soon               │ To Be Announced   │
└────────────────────────────────────────────────────────────────┘
```

### How It Works
1. User clicks "Enrol for course" under 11th edition
2. Link includes edition parameter: `/product/frcs-course/?edition=11`
3. Product page detects `?edition=11` from URL
4. Edition is captured automatically when adding to cart
5. **NO radio buttons needed on product page**

### Implementation Details

**class-pmcm-frontend.php changes:**
```php
public static function display_edition_selector() {
    // Check for edition in URL: ?edition=11
    $url_edition = isset($_GET['edition']) ? intval($_GET['edition']) : 0;

    if ($url_edition > 0) {
        // Determine slot (current or next) based on edition number
        // Add hidden fields for cart capture
        echo '<input type="hidden" name="pmcm_edition_number" value="' . $url_edition . '">';
        echo '<input type="hidden" name="pmcm_selected_course" value="' . $parent_slug . '">';

        // Show "Selected Edition" badge (not a selector)
        echo '<div class="pmcm-edition-selected">Selected: 11th FRCS</div>';
    } else {
        // No URL param - default to current edition (hidden field only)
    }
}
```

**class-pmcm-cart.php changes:**
```php
public static function save_edition_to_cart(...) {
    // Priority: 1) POST pmcm_edition_number, 2) URL ?edition=, 3) Current edition
    $url_edition = isset($_GET['edition']) ? intval($_GET['edition']) : 0;
    $post_edition = isset($_POST['pmcm_edition_number']) ? intval($_POST['pmcm_edition_number']) : 0;

    $selected_edition = $post_edition ?: $url_edition ?: $current_edition;
    // Use selected_edition for cart data
}
```

### Shortcode for Edition Table (Optional)
Provide a shortcode to generate the edition table:
```
[pmcm_edition_table course="frcs" show_upcoming="yes"]
```

**Files to modify:**
- [class-pmcm-frontend.php](includes/class-pmcm-frontend.php) - Read edition from URL parameter, show badge, NO radio buttons
- [class-pmcm-cart.php](includes/class-pmcm-cart.php) - Capture edition from `pmcm_edition_number` POST field or URL `?edition=` param
- [class-pmcm-shortcodes.php](includes/class-pmcm-shortcodes.php) - Add edition table shortcode (optional, client has custom table)

---

## Phase 5: Core Logic Updates

### class-pmcm-core.php Changes

**Replace hardcoded arrays:**
```php
public static function get_courses() {
    $courses = get_option('pmcm_course_mappings', []);
    if (empty($courses)) {
        // Fallback to migration/default
        $courses = self::get_default_courses();
    }
    return apply_filters('pmcm_courses', $courses);
}
```

**New methods:**
```php
// Get active editions for a course (1 or 2)
public static function get_active_editions($course_slug) { ... }

// Check if customer must choose edition
public static function requires_edition_choice($course_slug) { ... }

// Get edition by slot (current/next)
public static function get_edition_slot($course_slug, $slot = 'current') { ... }
```

### class-pmcm-cron.php Changes

**Updated auto-increment logic:**
```
When current edition ends:
  IF next edition exists AND is enabled:
    - Next becomes Current (copy next settings to current)
    - Clear Next slot
  ELSE:
    - Increment current edition by 1
    - Clear dates (existing behavior)
```

**Files to modify:**
- [class-pmcm-core.php](includes/class-pmcm-core.php) - Database-backed course config + new methods
- [class-pmcm-cron.php](includes/class-pmcm-cron.php) - Two-slot auto-increment logic

---

## Phase 6: FluentCRM & Order Processing

### Order Meta Storage
No changes needed - edition number and name already stored. Just ensure:
- Selected slot's edition number is used
- Works with both automatic and customer-selected editions

### FluentCRM Sync
No changes needed - uses edition name from order meta.

---

## File Change Summary

| File | Changes |
|------|---------|
| [class-pmcm-core.php](includes/class-pmcm-core.php) | Replace hardcoded arrays with database lookups, add `get_active_editions()`, `requires_edition_choice()`, migration method |
| [class-pmcm-admin.php](includes/class-pmcm-admin.php) | Add "Course Configuration" page, update Edition Management for 2-slot UI, AJAX handlers for course CRUD |
| [class-pmcm-cart.php](includes/class-pmcm-cart.php) | Capture edition from URL parameter (`$_GET['edition']`) or hidden field, support `pmcm_edition_number` override |
| [class-pmcm-frontend.php](includes/class-pmcm-frontend.php) | Read edition from URL parameter, add hidden fields, show "Selected Edition" badge - NO radio buttons |
| [class-pmcm-cron.php](includes/class-pmcm-cron.php) | Update auto-increment for slot promotion |
| [class-pmcm-product-expiration.php](includes/class-pmcm-product-expiration.php) | NEW: Product expiration dates and edition locking |

---

## Verification Plan

1. **Migration Test:**
   - Deactivate/reactivate plugin
   - Verify existing courses migrated to database
   - Check edition numbers preserved

2. **Course Configuration:**
   - Add new course via admin
   - Map to FluentCRM field
   - Verify appears in Edition Management

3. **URL Parameter Edition Selection:**
   - Visit product page with `?edition=11` parameter
   - Verify "Selected Edition: 11th FRCS" badge displays
   - Add to cart and verify correct edition captured in session
   - Visit same product WITHOUT URL parameter
   - Verify defaults to current edition

4. **Customer Selection Flow:**
   - From custom edition table, click link to product with `?edition=12`
   - Add to cart, complete checkout
   - Verify correct edition in order meta
   - Verify FluentCRM receives correct edition

5. **Auto-Increment:**
   - Set current edition end date to yesterday
   - Run cron manually
   - Verify next becomes current (or increment if no next)

6. **Product Expiration:**
   - Set `_expiration_date` on a product to yesterday
   - Verify product shows as "Out of Stock"
   - Set `_pmcm_edition_number` to lock product to edition 12
   - Verify add-to-cart blocked when edition mismatch

---

## Decisions Confirmed

- **Simultaneous editions:** 2 slots (Current + Next) per course
- **FluentCRM fields:** Manually created first, admin enters slug in plugin
- **Customer selection:** Via URL parameter (`?edition=11`) from custom table on page - NO radio buttons on product page
- **Auto-increment:** Next slot promotes to Current when Current ends
- **UI Design:** Modern design (to be refined during implementation)

---

# Phase 7: Product Registration Close / Expiration System

## Problem Statement

Child category products (individual topics like "Mock Viva", "SBA Q-Bank") need:
1. **Registration close dates** - per-product expiration that marks them out of stock
2. **Edition locking** - 12th edition topics shouldn't mix with 13th edition
3. **Current workaround** - external code using `_expiration_date` meta field

## Current External Code (to integrate)

The user has code that:
- Adds `_expiration_date` meta field to WooCommerce products
- Checks on product page load and marks product out of stock when date passes
- Needs to be integrated into the plugin as a new class file

## Proposed Solution

### New File: `class-pmcm-product-expiration.php`

**Purpose:** Handle product-level registration close dates and edition locking

**Features:**
1. Add `_expiration_date` field to product edit page
2. Add `_pmcm_edition_number` field to lock product to specific edition
3. Auto-mark products out of stock when expired
4. Filter products in shop/category pages by active edition
5. Prevent add-to-cart for expired products

### Database Fields (Product Meta)

| Meta Key | Purpose |
|----------|---------|
| `_expiration_date` | Date when product becomes unavailable |
| `_pmcm_edition_number` | Lock product to specific edition (optional) |
| `_pmcm_edition_locked` | Whether product is edition-specific |

### Implementation

**1. Product Meta Fields (Admin)**
```php
// Add to product edit page
add_action('woocommerce_product_options_general_product_data', 'add_expiration_fields');
```

**2. Stock Status Check (Frontend)**
```php
// Check on product page and shop pages
add_action('woocommerce_before_single_product', 'check_product_expiration');
add_action('woocommerce_product_query', 'filter_expired_products');
```

**3. Add-to-Cart Validation**
```php
// Prevent adding expired products
add_filter('woocommerce_add_to_cart_validation', 'validate_product_edition');
```

**4. Edition Inheritance Logic**
- If product has `_pmcm_edition_number`: Use that edition (locked)
- If product has NO edition number: Inherit from parent course's current edition
- If product is expired: Block purchase

### Integration with Existing Code

**class-pmcm-cart.php changes:**
- Before saving edition to cart, check if product is locked to specific edition
- If locked and edition doesn't match current selection, show error

**class-pmcm-frontend.php changes:**
- In edition selector, check if product is available for each edition
- Gray out editions where product is expired/unavailable

### File Structure

```
includes/
├── class-pmcm-core.php
├── class-pmcm-admin.php
├── class-pmcm-cart.php
├── class-pmcm-frontend.php
├── class-pmcm-product-expiration.php  ← NEW
└── ...
```

### Verification

1. Create a product in child category (e.g., "Mock Viva" under FRCS)
2. Set `_expiration_date` to yesterday
3. Verify product shows as "Out of Stock"
4. Set `_pmcm_edition_number` to 12
5. With 13th edition active, verify product is not purchasable
6. Switch to 12th edition, verify product becomes available (if not expired)
