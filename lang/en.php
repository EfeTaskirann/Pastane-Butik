<?php
declare(strict_types=1);

/**
 * English translations.
 *
 * Nested array format — access: t('nav.home') → 'Home'
 * Flat dot-keys are also supported: t('nav.home')
 * Placeholders: t('common.welcome', ['name' => 'Efe']) → "Welcome, Efe"
 *
 * RULE: Keep keys in sync with lang/tr.php. Every key MUST exist in both files.
 *
 * @package Pastane
 */

return [
    // ============================================
    // COMMON
    // ============================================
    'common' => [
        'welcome'       => 'Welcome, {{name}}',
        'loading'       => 'Loading...',
        'saving'        => 'Saving...',
        'sending'       => 'Sending...',
        'yes'           => 'Yes',
        'no'            => 'No',
        'ok'            => 'OK',
        'cancel'        => 'Cancel',
        'close'         => 'Close',
        'back'          => 'Back',
        'next'          => 'Next',
        'previous'      => 'Previous',
        'search'        => 'Search',
        'filter'        => 'Filter',
        'all'           => 'All',
        'none'          => 'None',
        'select'        => 'Select',
        'required'      => 'Required',
        'optional'      => 'Optional',
        'edit'          => 'Edit',
        'delete'        => 'Delete',
        'update'        => 'Update',
        'create'        => 'Create',
        'add'           => 'Add',
        'save'          => 'Save',
        'reset'         => 'Reset',
        'confirm'       => 'Confirm',
        'continue'      => 'Continue',
        'show'          => 'Show',
        'hide'          => 'Hide',
        'active'        => 'Active',
        'inactive'      => 'Inactive',
        'status'        => 'Status',
        'date'          => 'Date',
        'time'          => 'Time',
        'total'         => 'Total',
        'subtotal'      => 'Subtotal',
        'price'         => 'Price',
        'quantity'      => 'Quantity',
        'note'          => 'Note',
        'notes'         => 'Notes',
        'details'       => 'Details',
        'more'          => 'More',
        'and_more'      => 'and up',
    ],

    // ============================================
    // NAV
    // ============================================
    'nav' => [
        'home'          => 'Home',
        'about'         => 'About Us',
        'products'      => 'Products',
        'menu'          => 'Menu',
        'contact'       => 'Contact',
        'story'         => 'Our Story',
        'cart'          => 'Cart',
        'orders'        => 'My Orders',
        'login'         => 'Log In',
        'logout'        => 'Log Out',
        'register'      => 'Register',
        'dashboard'     => 'Dashboard',
        'settings'      => 'Settings',
        'profile'       => 'Profile',
    ],

    // ============================================
    // BTN
    // ============================================
    'btn' => [
        'add_to_cart'   => 'Add to Cart',
        'view_cart'     => 'View Cart',
        'checkout'      => 'Checkout',
        'order_now'     => 'Order Now',
        'contact_us'    => 'Contact Us',
        'discover'      => 'Discover',
        'explore'       => 'Explore',
        'view_menu'     => 'View Menu',
        'view_details'  => 'View Details',
        'back_to_menu'  => 'Back to Menu',
        'remove'        => 'Remove',
        'clear_cart'    => 'Clear Cart',
        'try_again'     => 'Try Again',
        'go_back'       => 'Go Back',
    ],

    // ============================================
    // FORM
    // ============================================
    'form' => [
        'name'          => 'Full Name',
        'first_name'    => 'First Name',
        'last_name'     => 'Last Name',
        'email'         => 'Email',
        'phone'         => 'Phone',
        'address'       => 'Address',
        'city'          => 'City',
        'district'      => 'District',
        'postal_code'   => 'Postal Code',
        'password'      => 'Password',
        'password_confirm' => 'Confirm Password',
        'message'       => 'Message',
        'subject'       => 'Subject',
        'note_for_seller' => 'Note for Seller',
        'placeholder_name'    => 'Your full name',
        'placeholder_email'   => 'you@example.com',
        'placeholder_phone'   => '+90 555 555 55 55',
        'placeholder_address' => 'Delivery address',
    ],

    // ============================================
    // ORDER
    // ============================================
    'order' => [
        'title'           => 'My Order',
        'your_cart'       => 'Your Cart',
        'cart_empty'      => 'Your cart is empty',
        'cart_empty_desc' => 'You can add tasty items from the menu.',
        'cart_count'      => '{{count}} items',
        'select_portion'  => 'Select portion',
        'out_of_stock'    => 'Out of Stock',
        'limited_stock'   => 'Limited',
        'in_stock'        => 'In Stock',
        'menu_empty'      => 'There are no items in the menu yet.',
        'your_table'      => 'Table {{no}}',
        'waiting'         => 'Pending',
        'approved'        => 'Approved',
        'preparing'       => 'Preparing',
        'ready'           => 'Ready',
        'delivered'       => 'Delivered',
        'cancelled'       => 'Cancelled',
        'completed'       => 'Completed',
        'order_received'  => 'Your order has been placed!',
        'order_number'    => 'Order No: #{{id}}',
        'payment_method'  => 'Payment Method',
        'pay_cash'        => 'Cash',
        'pay_card'        => 'Credit Card',
        'pay_online'      => 'Online Payment',
        'delivery_type'   => 'Delivery Type',
        'delivery_home'   => 'Home Delivery',
        'pickup'          => 'Pickup',
    ],

    // ============================================
    // ADMIN
    // ============================================
    'admin' => [
        'panel_title'     => 'Admin Panel',
        'dashboard'       => 'Dashboard',
        'products'        => 'Products',
        'categories'      => 'Categories',
        'orders'          => 'Orders',
        'calendar'        => 'Calendar / Orders',
        'tables'          => 'Tables',
        'table_orders'    => 'Table Orders',
        'kitchen'         => 'Kitchen Display',
        'waiter'          => 'Waiter Panel',
        'reports'         => 'Sales Reports',
        'customers'       => 'Registered Customers',
        'messages'        => 'Messages',
        'themes'          => 'Themes',
        'activity_log'    => 'Activity Log',
        'settings'        => 'Settings',
        'general'         => 'General',
        'two_factor'      => 'Two-Factor (2FA)',
        'email_settings'  => 'Email (SMTP)',
        'sms_settings'    => 'SMS',
        'backup'          => 'Backup',
        'view_site'       => 'View Site',
        'new_tab'         => 'New tab',
        'hello_user'      => 'Hello, {{name}}',
        'unread'          => '{{count}} unread',
    ],

    // ============================================
    // ERROR
    // ============================================
    'error' => [
        'generic'          => 'An error occurred. Please try again.',
        'not_found'        => 'Page not found.',
        'forbidden'        => 'You do not have permission to access this page.',
        'unauthorized'     => 'Please log in.',
        'csrf'             => 'Security validation failed.',
        'invalid_input'    => 'Invalid input.',
        'required_field'   => 'This field is required.',
        'invalid_email'    => 'Please enter a valid email address.',
        'invalid_phone'    => 'Please enter a valid phone number.',
        'password_mismatch'=> 'Passwords do not match.',
        'password_short'   => 'Password must be at least {{min}} characters.',
        'qr_required'      => 'QR Code Required',
        'qr_required_desc' => 'Please scan the QR code on the table to view the menu.',
        'table_inactive'   => 'Table Not Active',
        'table_inactive_desc' => 'This table is not active right now. Please ask the waiter for help.',
        'out_of_stock_msg' => 'This product is currently out of stock.',
        'rate_limit'       => 'Too many requests. Please wait a moment.',
        'server_error'     => 'Server error. Please try again soon.',
    ],

    // ============================================
    // SUCCESS
    // ============================================
    'success' => [
        'saved'            => 'Saved.',
        'updated'          => 'Updated.',
        'deleted'          => 'Deleted.',
        'created'          => 'Created.',
        'sent'             => 'Sent.',
        'login'            => 'Logged in.',
        'logout'           => 'Logged out.',
        'order_placed'     => 'Your order has been placed successfully.',
        'added_to_cart'    => 'Item added to cart.',
        'removed_from_cart'=> 'Item removed from cart.',
        'cart_cleared'     => 'Cart cleared.',
        'message_sent'     => 'Your message has been sent.',
        'language_changed' => 'Language changed.',
    ],

    // ============================================
    // HOME
    // ============================================
    'home' => [
        'hero_title'       => 'Sweet Dreams',
        'hero_subtitle'    => 'Boutique Cakes & Desserts',
        'about_title'      => 'Our Story',
        'about_paragraph_1' => 'From layer cakes to cheesecakes, from cupcakes to handmade cookies, we prepare every dessert with love and passion. Using quality ingredients and carefully selected recipes, we create the most special flavors for you.',
        'about_paragraph_2' => 'We bake to order, so freshness is guaranteed. From birthdays to weddings, celebrations to treats — we are with you on every special occasion.',
        'promo_student'    => 'Discount for university students!',
        'contact_title'    => 'Contact',
        'contact_subtitle' => 'Reach out and let us craft the perfect dessert for you.',
        'footer_copyright' => '© {{year}} Sweet Dreams. All rights reserved.',
        'products_title'   => 'Our Flavors',
        'products_subtitle'=> 'Handcrafted, fresh, each prepared with care.',
        'delivery_title'   => 'Delivery Information',
        'calendar_title'   => 'Availability Calendar',
        'calendar_subtitle'=> 'Check our availability before placing your order.',
        'faq_title'        => 'Frequently Asked Questions',
        'faq_subtitle'     => 'Answers to the questions you might have.',
        'footer_tagline'   => 'Handcrafted treats',
    ],

    // ============================================
    // LANGUAGE
    // ============================================
    'language' => [
        'switcher_label'   => 'Language',
        'turkish'          => 'Turkish',
        'english'          => 'English',
        'tr_short'         => 'TR',
        'en_short'         => 'EN',
    ],

    // ============================================
    // A11Y
    // ============================================
    'a11y' => [
        'skip_to_content'  => 'Skip to content',
        'toggle_menu'      => 'Toggle menu',
        'toggle_theme'     => 'Toggle theme',
        'switch_to_dark'   => 'Switch to dark theme',
        'switch_to_light'  => 'Switch to light theme',
        'main_menu'        => 'Main menu',
        'language_menu'    => 'Language selector',
    ],
];
