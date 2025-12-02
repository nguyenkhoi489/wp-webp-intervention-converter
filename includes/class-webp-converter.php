<?php
/**
 * WebP Converter Class
 * Handles image conversion to WebP format using Intervention Image v3
 */

if (!defined('ABSPATH')) {
    exit;
}

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;

class WebP_Converter {
    
    private ImageManager $manager;
    
    /**
     * Queue of files to delete after WordPress finishes processing
     * @var array
     */
    private array $deletion_queue = [];
    
    /**
     * Constructor - Register hooks
     */
    public function __construct() {
        // Initialize Intervention Image v3 with GD driver
        $this->manager = new ImageManager(new GdDriver());
        
        // Hook into upload process (priority 10 - convert during upload)
        add_filter('wp_generate_attachment_metadata', [$this, 'auto_convert_on_upload'], 10, 2);
        
        // Hook to delete files AFTER all processing is done (priority 999 - run last)
        add_filter('wp_generate_attachment_metadata', [$this, 'schedule_deferred_deletion'], 999, 2);
        
        // Shutdown hook to actually delete queued files
        add_action('shutdown', [$this, 'process_deletion_queue']);
        
        // AJAX handlers for batch conversion
        add_action('wp_ajax_get_images_for_batch', [$this, 'ajax_get_images_for_batch']);
        add_action('wp_ajax_batch_convert_image', [$this, 'ajax_batch_convert_image']);
        
        // Frontend URL replacement filters
        add_filter('the_content', [$this, 'replace_content_urls'], 999);
        add_filter('wp_get_attachment_url', [$this, 'replace_attachment_url'], 10, 2);
        add_filter('wp_get_attachment_image_src', [$this, 'replace_image_src'], 10, 4);
        add_filter('post_thumbnail_url', [$this, 'replace_thumbnail_url'], 10, 2);
        
        // Output buffering to catch ALL URLs (including custom templates)
        add_action('template_redirect', [$this, 'start_output_buffer'], 1);
        
        // Register WebP MIME type
        add_filter('mime_types', [$this, 'add_webp_mime_type']);
        add_filter('upload_mimes', [$this, 'add_webp_mime_type']);
        add_filter('wp_check_filetype_and_ext', [$this, 'fix_webp_mime_type'], 10, 5);
    }
    
    /**
     * Add WebP to allowed MIME types
     * 
     * @param array $mimes Existing MIME types
     * @return array Modified MIME types
     */
    public function add_webp_mime_type(array $mimes): array {
        $mimes['webp'] = 'image/webp';
        return $mimes;
    }
    
    /**
     * Fix WebP MIME type detection
     * 
     * @param array $data File data
     * @param string $file File path
     * @param string $filename File name
     * @param array $mimes Allowed MIME types
     * @param string $real_mime Real MIME type
     * @return array Modified file data
     */
    public function fix_webp_mime_type($data, $file, $filename, $mimes, $real_mime) {
        if (false !== strpos($filename, '.webp')) {
            $data['ext'] = 'webp';
            $data['type'] = 'image/webp';
        }
        return $data;
    }
    
    /**
     * Start output buffering to replace URLs in final HTML
     */
    public function start_output_buffer(): void {
        // Don't buffer admin pages or AJAX requests
        if (is_admin() || wp_doing_ajax()) {
            return;
        }
        
        ob_start([$this, 'replace_urls_in_html']);
    }
    
    /**
     * Replace all image URLs in HTML output
     * This catches URLs that are hard-coded in templates
     * 
     * @param string $html Complete HTML output
     * @return string Modified HTML with WebP URLs
     */
    public function replace_urls_in_html(string $html): string {
        // Skip if HTML is empty
        if (empty($html)) {
            return $html;
        }
        
        // Get upload directory info once
        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'];
        $base_dir = $upload_dir['basedir'];
        
        // Replace image URLs in src attributes
        $html = preg_replace_callback(
            '/(src\s*=\s*["\'])([^"\']*\.(jpe?g|png))(["\'])/i',
            function($matches) use ($base_url, $base_dir) {
                $original_url = $matches[2];
                $webp_url = preg_replace('/\.(jpe?g|png)$/i', '.webp', $original_url);
                
                // Check if WebP file exists
                $webp_path = str_replace($base_url, $base_dir, $webp_url);
                if (file_exists($webp_path)) {
                    return $matches[1] . $webp_url . $matches[4];
                }
                
                return $matches[0];
            },
            $html
        );
        
        // Replace in srcset attributes
        $html = preg_replace_callback(
            '/(srcset\s*=\s*["\'])([^"\']+)(["\'])/i',
            function($matches) use ($base_url, $base_dir) {
                $srcset = $matches[2];
                
                // Process each URL in srcset
                $srcset = preg_replace_callback(
                    '/([^\s,]+\.(jpe?g|png))/i',
                    function($url_match) use ($base_url, $base_dir) {
                        $original_url = $url_match[1];
                        $webp_url = preg_replace('/\.(jpe?g|png)$/i', '.webp', $original_url);
                        
                        $webp_path = str_replace($base_url, $base_dir, $webp_url);
                        if (file_exists($webp_path)) {
                            return $webp_url;
                        }
                        
                        return $original_url;
                    },
                    $srcset
                );
                
                return $matches[1] . $srcset . $matches[3];
            },
            $html
        );
        
        return $html;
    }
    
    /**
     * Check if WebP file exists
     * 
     * @param string $webp_url WebP file URL
     * @return bool True if file exists
     */
    private function webp_file_exists(string $webp_url): bool {
        $upload_dir = wp_upload_dir();
        $basedir = trim($upload_dir['basedir']);
        
        // Handle both absolute URLs and relative paths
        if (strpos($webp_url, 'http') === 0) {
            // Absolute URL - convert to path
            // Handle both http:// and https:// by making it protocol-agnostic
            $base_url = preg_replace('/^https?:\/\//', '', $upload_dir['baseurl']);
            $test_url = preg_replace('/^https?:\/\//', '', $webp_url);
            $webp_path = str_replace($base_url, $basedir, $test_url);
        } else {
            // Relative path - prepend basedir
            $webp_path = $basedir . '/' . ltrim($webp_url, '/');
        }
        
        return file_exists($webp_path);
    }
    
    /**
     * Auto convert image on upload
     * 
     * NOTE: We do NOT delete original files here even if delete_original is enabled.
     * This is because WordPress needs the original files to generate thumbnails.
     * Deletion only happens via batch convert or manual actions.
     * 
     * @param array $metadata Attachment metadata
     * @param int $attachment_id Attachment ID
     * @return array Modified metadata
     */
    public function auto_convert_on_upload(array $metadata, int $attachment_id): array {
        // Check if auto convert is enabled
        if (!get_option('webp_converter_enable_auto_convert', true)) {
            return $metadata;
        }
        
        // Get attachment file path
        $file_path = get_attached_file($attachment_id);
        
        // Only process JPG and PNG images
        $mime_type = get_post_mime_type($attachment_id);
        if (!in_array($mime_type, ['image/jpeg', 'image/png'])) {
            return $metadata;
        }
        
        // Convert original image to WebP (but DO NOT delete original)
        $this->convert_to_webp($file_path, false); // false = don't delete during upload
        
        // Convert all thumbnail sizes to WebP
        if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            $base_dir = dirname($file_path);
            
            foreach ($metadata['sizes'] as $size => $size_data) {
                if (isset($size_data['file'])) {
                    $thumbnail_path = $base_dir . '/' . $size_data['file'];
                    if (file_exists($thumbnail_path)) {
                        $this->convert_to_webp($thumbnail_path, false); // false = don't delete
                    }
                }
            }
        }
        
        return $metadata;
    }
    
    /**
     * Schedule files for deferred deletion (runs AFTER WordPress finishes all processing)
     * Priority 999 ensures this runs after all other plugins/themes
     * 
     * @param array $metadata Attachment metadata
     * @param int $attachment_id Attachment ID  
     * @return array Unmodified metadata
     */
    public function schedule_deferred_deletion(array $metadata, int $attachment_id): array {
        // Check if deletion is enabled
        if (!get_option('webp_converter_delete_original', false)) {
            return $metadata;
        }
        
        // Get attachment file path
        $file_path = get_attached_file($attachment_id);
        
        // Only process JPG and PNG images
        $mime_type = get_post_mime_type($attachment_id);
        if (!in_array($mime_type, ['image/jpeg', 'image/png'])) {
            return $metadata;
        }
        
        // Check if WebP version exists before queuing for deletion
        $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file_path);
        if (file_exists($webp_path)) {
            $this->deletion_queue[] = $file_path;
            
            error_log(sprintf(
                'WebP Converter: Queued for deletion: %s',
                basename($file_path)
            ));
        }
        
        // Queue thumbnails for deletion
        if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            $base_dir = dirname($file_path);
            
            foreach ($metadata['sizes'] as $size => $size_data) {
                if (isset($size_data['file'])) {
                    $thumbnail_path = $base_dir . '/' . $size_data['file'];
                    $thumbnail_webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $thumbnail_path);
                    
                    if (file_exists($thumbnail_webp)) {
                        $this->deletion_queue[] = $thumbnail_path;
                    }
                }
            }
        }
        
        return $metadata;
    }
    
    /**
     * Process deletion queue on shutdown (after all processing is complete)
     * This ensures WordPress has finished generating thumbnails before we delete originals
     */
    public function process_deletion_queue(): void {
        if (empty($this->deletion_queue)) {
            return;
        }
        
        $deleted_count = 0;
        $error_count = 0;
        
        foreach ($this->deletion_queue as $file_path) {
            if (file_exists($file_path)) {
                if (@unlink($file_path)) {
                    $deleted_count++;
                    error_log(sprintf(
                        'WebP Converter: Deleted (deferred) %s',
                        basename($file_path)
                    ));
                } else {
                    $error_count++;
                    error_log(sprintf(
                        'WebP Converter Warning: Failed to delete %s',
                        basename($file_path)
                    ));
                }
            }
        }
        
        if ($deleted_count > 0) {
            error_log(sprintf(
                'WebP Converter: Deferred deletion complete. Deleted: %d, Errors: %d',
                $deleted_count,
                $error_count
            ));
        }
        
        // Clear the queue
        $this->deletion_queue = [];
    }
    
    /**
     * Convert image to WebP format with file size optimization
     * 
     * @param string $file_path Path to original image file
     * @param bool $allow_delete Whether to allow deleting original (default: true for batch, false for auto-upload)
     * @return array Conversion result with 'success', 'webp_path', and 'deleted' keys
     */
    public function convert_to_webp(string $file_path, bool $allow_delete = true): array {
        $result = [
            'success' => false,
            'webp_path' => '',
            'deleted' => false
        ];
        
        try {
            // Check if file exists
            if (!file_exists($file_path)) {
                return $result;
            }
            
            // Increase memory limit temporarily for large images
            $original_memory_limit = ini_get('memory_limit');
            @ini_set('memory_limit', '512M');
            
            // Get WebP file path (same directory, different extension)
            $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $file_path);
            
            // Skip if WebP already exists
            if (file_exists($webp_path)) {
                $result['success'] = true;
                $result['webp_path'] = $webp_path;
                @ini_set('memory_limit', $original_memory_limit);
                return $result;
            }
            
            // Check image dimensions before processing
            $image_info = @getimagesize($file_path);
            if ($image_info === false) {
                error_log('WebP Converter Error: Cannot get image size for: ' . $file_path);
                @ini_set('memory_limit', $original_memory_limit);
                return $result;
            }
            
            // Get settings
            $default_quality = get_option('webp_converter_default_quality', 80);
            $max_file_size = get_option('webp_converter_max_file_size', 200) * 1024; // Convert KB to bytes
            $delete_original = get_option('webp_converter_delete_original', false);
            
            // Load image using Intervention Image v3
            $image = $this->manager->read($file_path);
            
            // Get original dimensions
            $original_width = $image->width();
            $original_height = $image->height();
            
            // Log processing for debugging
            error_log(sprintf(
                'WebP Converter: Processing %s (%dx%d, %.2f MB)',
                basename($file_path),
                $original_width,
                $original_height,
                filesize($file_path) / 1024 / 1024
            ));
            
            // Auto-resize if width > 2560px (WordPress recommended max)
            $max_width = 2560;
            if ($original_width > $max_width) {
                $scale_ratio = $max_width / $original_width;
                $new_height = (int) round($original_height * $scale_ratio);
                
                error_log(sprintf(
                    'WebP Converter: Resizing %s from %dx%d to %dx%d',
                    basename($file_path),
                    $original_width,
                    $original_height,
                    $max_width,
                    $new_height
                ));
                
                $image->scale($max_width, $new_height);
                
                // Update dimensions for optimization
                $original_width = $max_width;
                $original_height = $new_height;
            }
            
            // Optimize for file size limit
            $this->optimize_for_size_limit($image, $webp_path, $default_quality, $max_file_size, $original_width, $original_height);
            
            // Free memory
            unset($image);
            
            // Verify WebP was created successfully
            if (file_exists($webp_path)) {
                $result['success'] = true;
                $result['webp_path'] = $webp_path;
                
                error_log(sprintf(
                    'WebP Converter: Created %s (%.2f KB)',
                    basename($webp_path),
                    filesize($webp_path) / 1024
                ));
                
                // Delete original file if:
                // 1. Deletion is allowed for this operation ($allow_delete = true)
                // 2. AND user has enabled delete_original setting
                if ($allow_delete && $delete_original) {
                    if (@unlink($file_path)) {
                        $result['deleted'] = true;
                        error_log('WebP Converter: Deleted original file: ' . basename($file_path));
                    } else {
                        error_log('WebP Converter Warning: Could not delete original file: ' . $file_path);
                    }
                }
            }
            
            // Restore memory limit
            @ini_set('memory_limit', $original_memory_limit);
            
            return $result;
            
        } catch (Exception $e) {
            error_log('WebP Converter Error: ' . $e->getMessage());
            return $result;
        }
    }
    
    /**
     * Optimize image to meet file size limit
     * 
     * ALGORITHM EXPLANATION:
     * 1. Start with default quality (e.g., 80)
     * 2. Convert and save as WebP
     * 3. Check file size:
     *    - If under limit: Done!
     *    - If over limit: Continue to step 4
     * 4. Reduce quality by 5 (80 → 75 → 70 → 65...)
     * 5. If quality reaches minimum (40) and still too large:
     *    - Resize image dimensions by 10% (multiply width/height by 0.9)
     *    - Reset quality back to default
     *    - Repeat optimization loop
     * 6. Continue until file size is under limit or dimensions become too small
     * 
     * @param object $image Intervention Image object
     * @param string $webp_path Output WebP file path
     * @param int $default_quality Starting quality (1-100)
     * @param int $max_file_size Maximum file size in bytes
     * @param int $original_width Original image width
     * @param int $original_height Original image height
     */
    private function optimize_for_size_limit(
        object $image, 
        string $webp_path, 
        int $default_quality, 
        int $max_file_size,
        int $original_width,
        int $original_height
    ): void {
        $quality = $default_quality;
        $min_quality = 40;
        $quality_step = 5;
        $resize_factor = 0.9; // Reduce by 10% each time
        $min_dimension = 200; // Stop if width or height goes below this
        
        $current_width = $original_width;
        $current_height = $original_height;
        
        $max_iterations = 50; // Prevent infinite loops
        $iteration = 0;
        
        while ($iteration < $max_iterations) {
            $iteration++;
            
            // Create a working copy of the image
            $working_image = clone $image;
            
            // Resize if dimensions have changed
            if ($current_width !== $original_width || $current_height !== $original_height) {
                $working_image->scale($current_width, $current_height);
            }
            
            // Encode to WebP with current quality
            $encoded = $working_image->toWebp($quality);
            
            // Save to file
            $encoded->save($webp_path);
            
            // Check file size
            $file_size = filesize($webp_path);
            
            // SUCCESS: File size is under limit
            if ($file_size <= $max_file_size) {
                break;
            }
            
            // File is still too large, need to optimize further
            
            // First, try reducing quality
            if ($quality > $min_quality) {
                $quality -= $quality_step;
                $quality = max($min_quality, $quality);
                continue;
            }
            
            // Quality is at minimum, try resizing
            $new_width = (int) round($current_width * $resize_factor);
            $new_height = (int) round($current_height * $resize_factor);
            
            // Stop if dimensions are too small
            if ($new_width < $min_dimension || $new_height < $min_dimension) {
                // Can't optimize further, save with current settings
                break;
            }
            
            // Apply new dimensions and reset quality to default
            $current_width = $new_width;
            $current_height = $new_height;
            $quality = $default_quality;
        }
    }
    
    /**
     * AJAX: Get all images for batch conversion
     */
    public function ajax_get_images_for_batch(): void {
        check_ajax_referer('webp_batch_convert', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Get all image attachments (JPG and PNG)
        $args = [
            'post_type' => 'attachment',
            'post_mime_type' => ['image/jpeg', 'image/png'],
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ];
        
        $image_ids = get_posts($args);
        
        wp_send_json_success([
            'image_ids' => $image_ids,
            'total' => count($image_ids),
        ]);
    }
    
    /**
     * AJAX: Convert single image (for batch processing)
     */
    public function ajax_batch_convert_image(): void {
        check_ajax_referer('webp_batch_convert', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        
        if (!$attachment_id) {
            wp_send_json_error('Invalid attachment ID');
        }
        
        // Get file path
        $file_path = get_attached_file($attachment_id);
        
        if (!$file_path || !file_exists($file_path)) {
            wp_send_json_error('File not found');
        }
        
        // Get metadata before conversion
        $metadata = wp_get_attachment_metadata($attachment_id);
        
        // Convert original to WebP
        $result = $this->convert_to_webp($file_path);
        
        // If original was deleted, update metadata
        if ($result['deleted'] && !empty($result['webp_path'])) {
            // Update main file path to WebP
            update_attached_file($attachment_id, $result['webp_path']);
            
            // Update file name in metadata
            if (isset($metadata['file'])) {
                $metadata['file'] = preg_replace('/\.(jpe?g|png)$/i', '.webp', $metadata['file']);
            }
            
            // Update MIME type
            wp_update_post([
                'ID' => $attachment_id,
                'post_mime_type' => 'image/webp'
            ]);
        }
        
        // Convert all thumbnails
        if (!empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            $base_dir = dirname($file_path);
            
            foreach ($metadata['sizes'] as $size => $size_data) {
                if (isset($size_data['file'])) {
                    $thumbnail_path = $base_dir . '/' . $size_data['file'];
                    if (file_exists($thumbnail_path)) {
                        $thumb_result = $this->convert_to_webp($thumbnail_path);
                        
                        // Update thumbnail metadata if deleted
                        if ($thumb_result['deleted']) {
                            $metadata['sizes'][$size]['file'] = preg_replace('/\.(jpe?g|png)$/i', '.webp', $size_data['file']);
                            $metadata['sizes'][$size]['mime-type'] = 'image/webp';
                        }
                    }
                }
            }
        }
        
        // Save updated metadata
        wp_update_attachment_metadata($attachment_id, $metadata);
        
        if ($result['success']) {
            wp_send_json_success([
                'message' => 'Image converted successfully',
                'attachment_id' => $attachment_id,
                'deleted' => $result['deleted']
            ]);
        } else {
            wp_send_json_error('Conversion failed');
        }
    }
    
    /**
     * Replace image URLs in post content
     * 
     * @param string $content Post content
     * @return string Modified content
     */
    public function replace_content_urls(string $content): string {
        // Match image URLs in content
        $pattern = '/(https?:\/\/[^\s"\']+\.(jpe?g|png))/i';
        
        $content = preg_replace_callback($pattern, function($matches) {
            $original_url = $matches[0];
            $webp_url = preg_replace('/\.(jpe?g|png)$/i', '.webp', $original_url);
            
            // Check if WebP version exists
            $upload_dir = wp_upload_dir();
            $webp_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $webp_url);
            
            if (file_exists($webp_path)) {
                return $webp_url;
            }
            
            return $original_url;
        }, $content);
        
        return $content;
    }
    
    /**
     * Replace attachment URL
     * 
     * @param string $url Attachment URL
     * @param mixed $attachment_id Attachment ID (can be int, string, or empty)
     * @return string Modified URL
     */
    public function replace_attachment_url(string $url, $attachment_id): string {
        // Return early if attachment_id is invalid
        if (empty($attachment_id) || !is_numeric($attachment_id)) {
            return $url;
        }
        
        // Convert to int
        $attachment_id = intval($attachment_id);
        
        // Only process images
        $mime_type = get_post_mime_type($attachment_id);
        if (!$mime_type || !in_array($mime_type, ['image/jpeg', 'image/png'])) {
            return $url;
        }
        
        // Get WebP URL
        $webp_url = preg_replace('/\.(jpe?g|png)$/i', '.webp', $url);
        
        // Check if WebP file exists
        $upload_dir = wp_upload_dir();
        $webp_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $webp_url);
        
        if (file_exists($webp_path)) {
            return $webp_url;
        }
        
        return $url;
    }
    
    /**
     * Replace thumbnail URL (for get_the_post_thumbnail_url)
     * 
     * This filter catches calls to get_the_post_thumbnail_url() in custom themes
     * 
     * @param string $url Thumbnail URL
     * @param int $post_id Post ID
     * @return string Modified URL
     */
    public function replace_thumbnail_url(string $url, int $post_id): string {
        // Skip if URL is empty
        if (empty($url)) {
            return $url;
        }
        
        // Only process JPG/PNG images
        if (!preg_match('/\.(jpe?g|png)$/i', $url)) {
            return $url;
        }
        
        // Get WebP URL
        $webp_url = preg_replace('/\.(jpe?g|png)$/i', '.webp', $url);
        
        // Check if WebP file exists
        if ($this->webp_file_exists($webp_url)) {
            return $webp_url;
        }
        
        return $url;
    }
    
    /**
     * Replace image src array
     * 
     * @param array|false $image Image data array or false
     * @param mixed $attachment_id Attachment ID (can be int, string, or empty)
     * @param string|int[] $size Image size
     * @param bool $icon Whether to use icon
     * @return array|false Modified image data
     */
    public function replace_image_src($image, $attachment_id, $size, bool $icon) {
        // Return early if image is false or attachment_id is invalid
        if (!$image || empty($attachment_id) || !is_numeric($attachment_id)) {
            return $image;
        }
        
        // Convert to int
        $attachment_id = intval($attachment_id);
        
        // Only process images
        $mime_type = get_post_mime_type($attachment_id);
        if (!$mime_type || !in_array($mime_type, ['image/jpeg', 'image/png'])) {
            return $image;
        }
        
        // Get WebP URL
        $webp_url = preg_replace('/\.(jpe?g|png)$/i', '.webp', $image[0]);
        
        // Check if WebP file exists
        $upload_dir = wp_upload_dir();
        $webp_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $webp_url);
        
        if (file_exists($webp_path)) {
            $image[0] = $webp_url;
        }
        
        return $image;
    }
}
