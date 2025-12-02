# Installation on Production Server

## Option 1: SSH Access (Recommended)

If you have SSH access to your server:

```bash
# Navigate to plugin directory
cd /www/wwwroot/soloha.vn/wp-content/plugins/wp-webp-intervention-converter

# Install Composer dependencies
composer install --no-dev --optimize-autoloader
```

## Option 2: Upload vendor folder via FTP

If you don't have SSH access:

1. On your local machine, run:
```bash
cd /Users/nguyenkhoi489/Data/Web_Project/Soloha/wp-content/plugins/wp-webp-intervention-converter
composer install --no-dev --optimize-autoloader
```

2. Upload the entire `vendor/` folder to server via FTP:
   - Local: `wp-content/plugins/wp-webp-intervention-converter/vendor/`
   - Server: `/www/wwwroot/soloha.vn/wp-content/plugins/wp-webp-intervention-converter/vendor/`

3. Make sure the folder structure on server is:
```
wp-webp-intervention-converter/
├── vendor/
│   ├── autoload.php  ← Must exist!
│   ├── composer/
│   └── intervention/
├── includes/
├── assets/
├── wp-webp-intervention-converter.php
└── composer.json
```

## Option 3: Download Pre-built Release

1. Go to GitHub releases
2. Download the pre-built `.zip` file (includes vendor folder)
3. Upload via WordPress admin

## Verify Installation

After installation, the plugin should activate without errors. If you see an admin notice about missing Composer dependencies, follow the instructions shown.

## Troubleshooting

**Error: "Composer dependencies không được cài đặt"**
- This means the `vendor/autoload.php` file is missing
- Use one of the options above to install dependencies
- Make sure file permissions are correct (644 for files, 755 for directories)

**Error: "server cannot process the image"**
- Go to WebP Converter settings
- Temporarily disable "Enable Auto Convert"
- Check `wp-content/debug.log` for detailed errors
- Try uploading smaller images (under 2560px)
