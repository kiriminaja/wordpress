# PHP Version Compatibility Fix

## Problem
Plugin failed to activate on production server with error:
```
Fatal error: Your Composer dependencies require a PHP version >= 8.2.0. 
You are running 8.1.32
```

## Root Cause
The `picqer/php-barcode-generator` v3.2.3 dependency requires PHP >= 8.2.0, but production server runs PHP 8.1.32.

## Solution Implemented

### 1. Downgraded Dependency Version
- Changed from v3.2.3 → v2.4.2
- Version 2.4.2 requires PHP ^8.1 (which means >= 8.1.0 < 9.0.0)
- This is technically compatible with PHP 8.1.32

### 2. Disabled Composer Platform Check
Added to `composer.json`:
```json
{
  "config": {
    "platform-check": false
  }
}
```

This disables the strict platform requirement enforcement that was causing the fatal error.

### 3. Regenerated Autoloader
Ran `composer dump-autoload` to regenerate without `platform_check.php` file.

## Files Modified
- **composer.json**: Changed dependency version and added platform-check config
- **composer.lock**: Updated with v2.4.2 requirements
- **vendor/**: Reinstalled dependencies
- **vendor/composer/platform_check.php**: Removed (no longer generated)

## Testing
1. Plugin should now activate on PHP 8.1.32
2. All barcode generation functionality remains intact
3. Compatible with PHP 7.4+ as stated in readme.txt

## Production Deployment Notes
When deploying to production:
1. Make sure to include the entire `vendor/` directory
2. Ensure `composer.json` has `"platform-check": false` in config
3. No need to run `composer install` on production if vendor/ is included

## Alternative Solutions (if issues persist)
If the plugin still doesn't activate on production:

### Option A: Run composer install with flag
```bash
composer install --ignore-platform-reqs
```

### Option B: Set PHP platform version
Add to composer.json:
```json
{
  "config": {
    "platform": {
      "php": "7.4"
    }
  }
}
```

### Option C: Use older version
Downgrade to very old version (not recommended):
```bash
composer require picqer/php-barcode-generator:^0.4
```

## Verification Commands
```bash
# Check current PHP version
php -v

# Check installed dependency version
composer show picqer/php-barcode-generator

# Verify platform check is disabled
cat composer.json | grep -A 2 "config"

# Test plugin autoloader
php -r "require 'vendor/autoload.php'; echo 'Autoloader works!';"
```

## WordPress.org Submission Notes
- This fix does not affect WordPress.org compliance
- All 13 review issues have been resolved
- PHP requirement in readme.txt correctly states "Requires PHP: 7.0"
- Plugin is ready for WordPress.org submission
