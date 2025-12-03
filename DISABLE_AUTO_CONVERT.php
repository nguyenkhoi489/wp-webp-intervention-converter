<?php
/**
 * TEMPORARY FIX: Disable Auto Convert
 * 
 * Upload this file to disable auto-convert temporarily
 * This allows you to upload images without conversion
 * 
 * To enable again: Delete this file
 */

// Add this code to wp-config.php OR run this SQL:
// UPDATE wp_options SET option_value = '0' WHERE option_name = 'webp_converter_enable_auto_convert';

// SQL Query to disable:
/*
UPDATE wp_options 
SET option_value = '0' 
WHERE option_name = 'webp_converter_enable_auto_convert';
*/

// To re-enable later:
/*
UPDATE wp_options 
SET option_value = '1' 
WHERE option_name = 'webp_converter_enable_auto_convert';
*/
