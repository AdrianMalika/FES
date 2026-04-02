<?php

/**
 * Copy this file to payment_config.php and fill in your keys.
 * payment_config.php can stay out of git if you add it to .gitignore.
 *
 * Stripe: https://stripe.com/docs — test keys under Developers → API keys.
 * Run: composer install (requires stripe/stripe-php).
 *
 * Charges use Malawi Kwacha (mwk). Enable MWK in your Stripe account if required.
 */

if (!defined('FES_PUBLIC_BASE_URL')) {
    // No trailing slash. Must be a public HTTPS URL in live mode (Stripe); ngrok works locally.
    define('FES_PUBLIC_BASE_URL', 'https://your-domain.example/FES');
}

if (!defined('FES_STRIPE_SECRET_KEY')) {
    define('FES_STRIPE_SECRET_KEY', 'sk_test_...');
}

if (!defined('FES_STRIPE_CURRENCY')) {
    define('FES_STRIPE_CURRENCY', 'mwk');
}
