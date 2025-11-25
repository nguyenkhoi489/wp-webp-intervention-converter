/**
 * Batch Convert JavaScript
 * Handles sequential AJAX processing of images for batch conversion
 */

(function ($) {
    'use strict';

    let imageIds = [];
    let currentIndex = 0;
    let totalImages = 0;
    let isProcessing = false;

    /**
     * Initialize batch conversion
     */
    function initBatchConvert() {
        $('#batch-convert-btn').on('click', function () {
            if (isProcessing) {
                return;
            }

            if (!confirm('This will convert all existing JPG and PNG images to WebP format. Continue?')) {
                return;
            }

            startBatchConversion();
        });
    }

    /**
     * Start batch conversion process
     */
    function startBatchConversion() {
        isProcessing = true;
        currentIndex = 0;

        // Disable button
        $('#batch-convert-btn').prop('disabled', true);

        // Show progress container
        $('#batch-progress-container').show();

        // Reset progress bar
        updateProgress(0);

        // Get all images to convert
        $.ajax({
            url: webpConverter.ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_images_for_batch',
                nonce: webpConverter.nonce
            },
            success: function (response) {
                if (response.success) {
                    imageIds = response.data.image_ids;
                    totalImages = response.data.total;

                    if (totalImages === 0) {
                        showMessage('No images found to convert.', 'notice-info');
                        resetBatchConvert();
                        return;
                    }

                    // Start processing
                    processNextImage();
                } else {
                    showMessage(webpConverter.strings.error, 'notice-error');
                    resetBatchConvert();
                }
            },
            error: function () {
                showMessage(webpConverter.strings.error, 'notice-error');
                resetBatchConvert();
            }
        });
    }

    /**
     * Process next image in the queue
     * Processes images one by one to avoid PHP timeouts
     */
    function processNextImage() {
        if (currentIndex >= totalImages) {
            // All images processed
            onBatchComplete();
            return;
        }

        const attachmentId = imageIds[currentIndex];

        // Update status text
        const statusText = webpConverter.strings.processing
            .replace('%current%', currentIndex + 1)
            .replace('%total%', totalImages);
        $('#batch-status-text').text(statusText);

        // Convert single image via AJAX
        $.ajax({
            url: webpConverter.ajaxUrl,
            type: 'POST',
            data: {
                action: 'batch_convert_image',
                nonce: webpConverter.nonce,
                attachment_id: attachmentId
            },
            success: function (response) {
                // Move to next image (regardless of success or failure)
                currentIndex++;

                // Update progress bar
                const progress = Math.round((currentIndex / totalImages) * 100);
                updateProgress(progress);

                // Process next image
                // Small delay to prevent server overload
                setTimeout(processNextImage, 100);
            },
            error: function () {
                // Move to next image even on error
                currentIndex++;

                // Update progress bar
                const progress = Math.round((currentIndex / totalImages) * 100);
                updateProgress(progress);

                // Process next image
                setTimeout(processNextImage, 100);
            }
        });
    }

    /**
     * Update progress bar
     * 
     * @param {number} percentage Progress percentage (0-100)
     */
    function updateProgress(percentage) {
        $('#batch-progress-fill').css('width', percentage + '%');
        $('#batch-progress-fill').attr('data-progress', percentage + '%');
    }

    /**
     * Handle batch completion
     */
    function onBatchComplete() {
        const completeText = webpConverter.strings.complete
            .replace('%total%', totalImages);

        $('#batch-status-text').text(completeText);

        // Show success message
        showMessage(completeText, 'notice-success');

        // Reset after 2 seconds
        setTimeout(resetBatchConvert, 2000);
    }

    /**
     * Reset batch conversion UI
     */
    function resetBatchConvert() {
        isProcessing = false;
        $('#batch-convert-btn').prop('disabled', false);

        // Keep progress container visible for 3 seconds, then hide
        setTimeout(function () {
            $('#batch-progress-container').fadeOut();
        }, 3000);
    }

    /**
     * Show message to user
     * 
     * @param {string} message Message text
     * @param {string} type Notice type (notice-success, notice-error, notice-info)
     */
    function showMessage(message, type) {
        const $notice = $('<div class="notice ' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after($notice);

        // Auto dismiss after 5 seconds
        setTimeout(function () {
            $notice.fadeOut(function () {
                $(this).remove();
            });
        }, 5000);
    }

    // Initialize on document ready
    $(document).ready(function () {
        initBatchConvert();
    });

})(jQuery);
