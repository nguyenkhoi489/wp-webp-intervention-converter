<?php
/**
 * WebP Settings Class
 * Handles plugin settings page and options management
 */

if (!defined('ABSPATH')) {
    exit;
}

class WebP_Settings {
    
    /**
     * Constructor - Register hooks
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu(): void {
        add_menu_page(
            __('WebP Converter', 'wp-webp-intervention-converter'),
            __('WebP Converter', 'wp-webp-intervention-converter'),
            'manage_options',
            'webp-converter',
            [$this, 'render_settings_page'],
            'dashicons-images-alt2',
            100
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings(): void {
        register_setting('webp_converter_settings', 'webp_converter_enable_auto_convert');
        register_setting('webp_converter_settings', 'webp_converter_default_quality');
        register_setting('webp_converter_settings', 'webp_converter_max_file_size');
        register_setting('webp_converter_settings', 'webp_converter_delete_original');
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_assets(string $hook): void {
        // Only load on our settings page
        if ($hook !== 'toplevel_page_webp-converter') {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'webp-converter-admin-style',
            WP_WEBP_CONVERTER_PLUGIN_URL . 'assets/css/admin-style.css',
            [],
            WP_WEBP_CONVERTER_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'webp-converter-batch',
            WP_WEBP_CONVERTER_PLUGIN_URL . 'assets/js/batch-convert.js',
            ['jquery'],
            WP_WEBP_CONVERTER_VERSION,
            true
        );
        
        // Localize script with AJAX URL and nonce
        wp_localize_script('webp-converter-batch', 'webpConverter', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('webp_batch_convert'),
            'strings' => [
                'processing' => __('Processing image %current% of %total%...', 'wp-webp-intervention-converter'),
                'complete' => __('Batch conversion complete! Processed %total% images.', 'wp-webp-intervention-converter'),
                'error' => __('An error occurred during batch conversion.', 'wp-webp-intervention-converter'),
            ]
        ]);
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle settings update
        if (isset($_POST['webp_converter_save_settings']) && check_admin_referer('webp_converter_settings_nonce')) {
            $this->save_settings();
        }
        
        // Get current settings
        $enable_auto_convert = get_option('webp_converter_enable_auto_convert', true);
        $default_quality = get_option('webp_converter_default_quality', 80);
        $max_file_size = get_option('webp_converter_max_file_size', 200);
        $delete_original = get_option('webp_converter_delete_original', false);
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('WebP Converter Settings', 'wp-webp-intervention-converter'); ?></h1>
            
            <div class="notice notice-info">
                <p><strong>💡 Gặp lỗi "server cannot process the image"?</strong></p>
                <ul style="margin-left: 20px;">
                    <li>Plugin tự động tăng memory lên 512MB khi xử lý ảnh</li>
                    <li>Nếu vẫn lỗi, hãy tắt "Enable Auto Convert" tạm thời</li>
                    <li>Upload ảnh nhỏ hơn (dưới 2560px) hoặc nén trước khi upload</li>
                    <li>Check error log để xem chi tiết: <code>wp-content/debug.log</code></li>
                </ul>
            </div>
            
            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html__('Settings saved successfully.', 'wp-webp-intervention-converter'); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('webp_converter_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="enable_auto_convert">
                                <?php echo esc_html__('Enable Auto Convert', 'wp-webp-intervention-converter'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="enable_auto_convert" 
                                   name="webp_converter_enable_auto_convert" 
                                   value="1" 
                                   <?php checked($enable_auto_convert, true); ?>>
                            <p class="description">
                                <?php echo esc_html__('Automatically convert images to WebP on upload.', 'wp-webp-intervention-converter'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="default_quality">
                                <?php echo esc_html__('Default Quality', 'wp-webp-intervention-converter'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="default_quality" 
                                   name="webp_converter_default_quality" 
                                   value="<?php echo esc_attr($default_quality); ?>" 
                                   min="40" 
                                   max="100" 
                                   step="5">
                            <p class="description">
                                <?php echo esc_html__('WebP quality (40-100). Default: 80', 'wp-webp-intervention-converter'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="max_file_size">
                                <?php echo esc_html__('Max File Size Limit (KB)', 'wp-webp-intervention-converter'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="max_file_size" 
                                   name="webp_converter_max_file_size" 
                                   value="<?php echo esc_attr($max_file_size); ?>" 
                                   min="50" 
                                   step="10">
                            <p class="description">
                                <?php echo esc_html__('Maximum WebP file size in KB. The converter will reduce quality/resize if needed. Default: 200KB', 'wp-webp-intervention-converter'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="delete_original">
                                <?php echo esc_html__('Delete Original Images', 'wp-webp-intervention-converter'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="checkbox" 
                                   id="delete_original" 
                                   name="webp_converter_delete_original" 
                                   value="1" 
                                   <?php checked($delete_original, true); ?>>
                            <p class="description" style="color: #d63638; font-weight: 600;">
                                ⚠️ <?php echo esc_html__('CẢNH BÁO: Sẽ xóa vĩnh viễn ảnh JPG/PNG gốc sau khi chuyển đổi thành WebP. Hành động này KHÔNG THỂ HOÀN TÁC!', 'wp-webp-intervention-converter'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" 
                           name="webp_converter_save_settings" 
                           class="button button-primary" 
                           value="<?php echo esc_attr__('Save Settings', 'wp-webp-intervention-converter'); ?>">
                </p>
            </form>
            
            <hr>
            
            <h2><?php echo esc_html__('Batch Convert Existing Images', 'wp-webp-intervention-converter'); ?></h2>
            <p><?php echo esc_html__('Convert all existing JPG and PNG images to WebP format.', 'wp-webp-intervention-converter'); ?></p>
            
            <div class="batch-convert-section">
                <button type="button" id="batch-convert-btn" class="button button-secondary">
                    <?php echo esc_html__('Start Batch Conversion', 'wp-webp-intervention-converter'); ?>
                </button>
                
                <div id="batch-progress-container" style="display: none;">
                    <div class="progress-bar-wrapper">
                        <div class="progress-bar" id="batch-progress-bar">
                            <div class="progress-bar-fill" id="batch-progress-fill"></div>
                        </div>
                    </div>
                    <p id="batch-status-text"></p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings(): void {
        // Enable auto convert
        $enable_auto_convert = isset($_POST['webp_converter_enable_auto_convert']) ? true : false;
        update_option('webp_converter_enable_auto_convert', $enable_auto_convert);
        
        // Default quality
        $default_quality = isset($_POST['webp_converter_default_quality']) 
            ? intval($_POST['webp_converter_default_quality']) 
            : 80;
        $default_quality = min(100, max(40, $default_quality));
        update_option('webp_converter_default_quality', $default_quality);
        
        // Max file size
        $max_file_size = isset($_POST['webp_converter_max_file_size']) 
            ? intval($_POST['webp_converter_max_file_size']) 
            : 200;
        $max_file_size = max(50, $max_file_size);
        update_option('webp_converter_max_file_size', $max_file_size);
        
        // Delete original
        $delete_original = isset($_POST['webp_converter_delete_original']) ? true : false;
        update_option('webp_converter_delete_original', $delete_original);
        
        // Redirect with success message
        wp_redirect(add_query_arg('settings-updated', 'true', wp_get_referer()));
        exit;
    }
}
