# KiriminAja WordPress Plugin

Official WooCommerce integration plugin for KiriminAja shipping services.

[![Version](https://img.shields.io/badge/version-2.0.10-blue.svg)](https://github.com/kiriminaja/plugin-wp/releases)
[![WordPress](https://img.shields.io/badge/wordpress-6.0+-green.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/woocommerce-5.0+-purple.svg)](https://woocommerce.com/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-red.svg)](LICENSE)

**API Reference:** https://developer.kiriminaja.com/docs

## 🚀 Quick Start

### Requirements

- WordPress 6.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.0 or higher

### Installation

1. Download the latest release
2. Upload to `/wp-content/plugins/kiriminaja`
3. Activate through the WordPress admin panel
4. Configure via **KiriminAja > Configuration**

## 📖 Documentation

- [VERSION_MANAGEMENT.md](VERSION_MANAGEMENT.md) - How to release new versions
- [COMPLIANCE_CHECKLIST.md](COMPLIANCE_CHECKLIST.md) - WordPress.org compliance status
- [download-libraries.sh](download-libraries.sh) - Download required vendor libraries

## 🔧 Development

### Setup Development Environment

```bash
# Clone repository
git clone https://github.com/kiriminaja/plugin-wp.git
cd plugin-wp

# Install dependencies
composer install

# Download vendor libraries (Select2)
chmod +x download-libraries.sh
./download-libraries.sh
```

### Release New Version

```bash
# Use the automated version script
chmod +x update-version.sh
./update-version.sh 2.0.11

# Review and commit
git commit -am "Bump version to 2.0.11"
git tag 2.0.11
git push --tags
```

See [VERSION_MANAGEMENT.md](VERSION_MANAGEMENT.md) for complete release process.

## 🏗️ Project Structure

```
plugin-wp/
├── assets/              # Plugin assets
│   ├── admin/          # Admin CSS/JS
│   ├── wp/             # Frontend CSS/JS
│   └── vendor/         # Bundled libraries (Select2)
├── inc/                # Core plugin code
│   ├── Base/           # Base classes
│   ├── Controllers/    # AJAX & request handlers
│   ├── Services/       # Business logic
│   └── Repositories/   # Data access
├── templates/          # View templates
├── wc/                 # WooCommerce integration
├── vendor/             # Composer dependencies
├── kiriminaja.php      # Main plugin file
└── readme.txt          # WordPress.org readme
```

## 🔐 Security

All AJAX endpoints require:

- Nonce verification
- Capability checks (`manage_options`)
- Input sanitization
- Output escaping

Report security issues to: security@kiriminaja.com

## 🤝 Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open Pull Request

## 📝 Changelog

See [readme.txt](readme.txt) for detailed changelog.

### Latest Version (2.0.10)

- Fix request pickup date issue

## 📄 License

GPL-2.0-or-later - see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html)

## 🙋 Support

- Documentation: https://developer.kiriminaja.com
- Support: support@kiriminaja.com
- Issues: https://github.com/kiriminaja/plugin-wp/issues

## 👥 Credits

Developed and maintained by [KiriminAja](https://kiriminaja.com)

---

Made with ❤️ in Indonesia 🇮🇩
