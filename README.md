# WP WebP Intervention Converter

A complete WordPress plugin that converts JPG/PNG images to WebP format using Intervention Image v3, with automatic file size optimization.

## Features

- ✅ **Auto Convert on Upload**: Automatically converts images to WebP when uploaded to Media Library
- ✅ **File Size Optimization**: Intelligent algorithm reduces quality and/or resizes images to meet file size limits
- ✅ **Batch Conversion**: Convert all existing images with progress tracking
- ✅ **Frontend URL Replacement**: Automatically serves WebP versions on frontend while preserving originals
- ✅ **Configurable Settings**: Control quality, file size limits, and enable/disable auto-conversion
- ✅ **PHP 8.1+ Compatible**: Modern, object-oriented code

## Installation

1. **Upload the plugin** to `/wp-content/plugins/wp-webp-intervention-converter/`

2. **Install Intervention Image v3** via Composer:
   ```bash
   cd wp-content/plugins/wp-webp-intervention-converter
   composer require intervention/image
   ```

3. **Activate the plugin** through the 'Plugins' menu in WordPress

4. **Configure settings** at 'WebP Converter' menu in WordPress admin

## Requirements

- PHP 8.1 or higher
- WordPress 5.0 or higher
- GD or Imagick PHP extension
- Composer (for installing dependencies)

## Usage

### Settings

Navigate to **WebP Converter** in WordPress admin to configure:

- **Enable Auto Convert**: Toggle automatic conversion on image upload
- **Default Quality**: Set WebP quality (40-100, default: 80)
- **Max File Size Limit (KB)**: Maximum allowed WebP file size (default: 200KB)

### Auto Conversion

When auto-convert is enabled, newly uploaded JPG/PNG images are automatically converted to WebP format. The plugin will:

1. Convert the image to WebP at the configured quality
2. Check if file size is under the limit
3. If over limit, automatically reduce quality or resize until it fits

### Batch Conversion

To convert all existing images:

1. Go to **WebP Converter** settings page
2. Scroll to "Batch Convert Existing Images"
3. Click **Start Batch Conversion**
4. Watch the progress bar as images are processed sequentially

### Frontend Display

The plugin automatically replaces image URLs on the frontend:

- Original JPG/PNG files are **NOT deleted**
- WebP versions are served when available
- Browsers that don't support WebP will fall back to originals
- Works with `the_content`, `wp_get_attachment_url`, and `wp_get_attachment_image_src` filters

## File Size Optimization Algorithm

The plugin uses an intelligent optimization algorithm to ensure WebP files stay under the configured size limit:

1. **Start with default quality** (e.g., 80)
2. **Convert and save** as WebP
3. **Check file size**:
   - ✅ If under limit → Done!
   - ❌ If over limit → Continue
4. **Reduce quality by 5** (80 → 75 → 70...)
5. **If quality reaches minimum (40)** and still too large:
   - Resize dimensions by 10% (width × 0.9, height × 0.9)
   - Reset quality to default
   - Repeat optimization
6. **Continue until** file size is under limit or dimensions are too small

## File Structure

```
wp-webp-intervention-converter/
├── wp-webp-intervention-converter.php  # Main plugin file
├── composer.json                        # Intervention Image dependency
├── includes/
│   ├── class-webp-converter.php        # Conversion logic
│   └── class-webp-settings.php         # Settings page
├── assets/
│   ├── js/
│   │   └── batch-convert.js            # AJAX batch processing
│   └── css/
│       └── admin-style.css             # Admin styling
└── README.md                            # This file
```

## Technical Details

### Intervention Image v3

This plugin uses [Intervention Image v3](https://image.intervention.io/v3) for all image manipulations. It's a modern, powerful PHP image handling library.

### WordPress Hooks Used

- `wp_generate_attachment_metadata`: Auto-convert on upload
- `the_content`: Replace URLs in post content
- `wp_get_attachment_url`: Replace attachment URLs
- `wp_get_attachment_image_src`: Replace image source arrays
- AJAX actions: `get_images_for_batch`, `batch_convert_image`

### Security

- Nonce verification for all AJAX requests
- Capability checks (`manage_options`)
- Sanitized input/output
- Escaped HTML output

## Support

For issues or feature requests, please contact the plugin author.

## License

GPL v2 or later

## Changelog

### 1.0.0
- Initial release
- Auto convert on upload
- Batch conversion with progress bar
- File size optimization
- Frontend URL replacement
