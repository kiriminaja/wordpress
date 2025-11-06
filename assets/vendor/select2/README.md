# Select2 Library

This directory contains the Select2 library files that must be bundled locally.

## Required Files

Download Select2 v4.1.0-rc.0 from: https://github.com/select2/select2/releases/tag/4.1.0-rc.0

Place the following files in this directory structure:

- `css/select2.min.css` - The minified CSS file
- `js/select2.min.js` - The minified JavaScript file

## Download Instructions

```bash
# Download and extract Select2
wget https://github.com/select2/select2/archive/refs/tags/4.1.0-rc.0.zip
unzip 4.1.0-rc.0.zip

# Copy required files
cp select2-4.1.0-rc.0/dist/css/select2.min.css assets/vendor/select2/css/
cp select2-4.1.0-rc.0/dist/js/select2.min.js assets/vendor/select2/js/

# Cleanup
rm -rf select2-4.1.0-rc.0 4.1.0-rc.0.zip
```

## Alternative (using CDN temporarily during development)

If you haven't downloaded the files yet, the plugin will fall back to using CDN URLs. However, this MUST be changed before submitting to WordPress.org.
