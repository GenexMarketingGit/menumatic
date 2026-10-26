<?php
/**
 * AI provider integration class
 * 
 * @package MenuMatic
 */

if (!defined('ABSPATH')) {
    exit;
}

class MenuMatic_OpenAI_API {
    
    private $provider;
    private $provider_label;
    private $api_key;
    private $model;
    private $api_endpoint = '';
    private const DEFAULT_MODEL = 'gpt-5.4-mini';
    
    public function __construct() {
        $plugin = MenuMatic::get_instance();
        $config = $plugin->get_ai_provider_config();
        $this->provider = $config['provider'];
        $this->provider_label = $config['provider_label'];
        $this->api_key = $config['api_key'];
        $this->model = $config['model'];
        $this->api_endpoint = $config['api_url'];
    }

    /**
     * Normalize selected model against an allowlist.
     */
    private function normalize_model($model) {
        return is_string($model) ? trim($model) : self::DEFAULT_MODEL;
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', sprintf(__('%s API key not configured', 'menumatic'), $this->provider_label));
        }

        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
        );
        $body = array(
            'model' => $this->model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => 'Test connection. Please respond with "OK".'
                )
            ),
        );

        if ($this->provider === 'openrouter') {
            $headers['HTTP-Referer'] = home_url('/');
            $headers['X-Title'] = 'MenuMatic';
            $body['max_tokens'] = 10;
        } else {
            $body['max_completion_tokens'] = 10;
        }

        $response = wp_remote_post($this->api_endpoint, array(
            'timeout' => 30,
            'headers' => $headers,
            'body' => json_encode($body)
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['error'])) {
            return new WP_Error('api_error', $data['error']['message']);
        }
        
        return true;
    }
    
    /**
     * Process menu image with the configured AI model
     */
    public function process_menu_image($image_path, $options = array()) {
        try {
            if (empty($this->api_key)) {
                throw new Exception(sprintf(__('%s API key not configured', 'menumatic'), $this->provider_label));
            }

            if (!file_exists($image_path) || !is_readable($image_path)) {
                throw new Exception('Image file not found or is unreadable.');
            }
            
            $file_size = filesize($image_path);
            if ($file_size > 20 * 1024 * 1024) { // 20MB limit
                throw new Exception('Image file is too large (Max: 20MB).');
            }

            // Check if image needs chunking for comprehensive processing
            $image_info = getimagesize($image_path);
            if (!$image_info) {
                throw new Exception('Unable to determine image dimensions.');
            }
            
            $width = $image_info[0];
            $height = $image_info[1];
            $aspect_ratio = $height / $width;
            $file_size_mb = $file_size / (1024 * 1024);
            
            // Determine if we need chunking based on multiple factors
            $needs_chunking = $this->needs_image_chunking($width, $height, $file_size_mb);
            
            if ($needs_chunking) {
                error_log('MenuMatic: Large/complex menu detected (dimensions: ' . $width . 'x' . $height . ', size: ' . round($file_size_mb, 2) . 'MB), using chunking approach');
                return $this->process_menu_with_chunking($image_path, $options);
            }

            // For normal images, use standard processing
            $menu_result = $this->call_openai_isolated($image_path, $options, false);
            
            if (is_wp_error($menu_result)) {
                throw new Exception($menu_result->get_error_message());
            }

            // Extract the raw JSON from the result
            if (is_array($menu_result) && isset($menu_result['raw_json'])) {
                $menu_json = $menu_result['raw_json'];
                $menu_data = $menu_result['json_data'];
            } else {
                throw new Exception('Invalid result format from AI processing');
            }
            
            // Count total items for reporting
            $total_items = 0;
            if (isset($menu_data['sections'])) {
                foreach ($menu_data['sections'] as $section) {
                    if (isset($section['items'])) {
                        $total_items += count($section['items']);
                    }
                }
            }
            
            // Prepare result with processing info
            $result = [
                'html' => $menu_result['html'], // Use the HTML that was already built
                'raw_json' => $menu_json,
                'chunking_info' => [
                    'chunks_processed' => 0,
                    'validation_performed' => false,
                    'items_found' => $total_items,
                    'processing_method' => 'standard'
                ]
            ];
            
            return $result;

        } catch (Exception $e) {
            error_log('MenuMatic: Exception in process_menu_image: ' . $e->getMessage());
            return new WP_Error('processing_error', $e->getMessage());
        }
    }
    
    /**
     * Determine if an image needs chunking based on multiple factors
     */
    private function needs_image_chunking($width, $height, $file_size_mb) {
        $aspect_ratio = $height / $width;
        $total_pixels = $width * $height;
        $megapixels = $total_pixels / (1024 * 1024);
        
        // Chunking criteria:
        // 1. Very tall images (aspect ratio > 2.5:1)
        // 2. Very wide images (aspect ratio < 1:2.5)
        // 3. Large images (> 4 megapixels)
        // 4. Large file size (> 8MB)
        // 5. Very high resolution (width > 3000 or height > 3000)
        
        $needs_chunking = false;
        $reasons = [];
        
        if ($aspect_ratio > 2.5) {
            $needs_chunking = true;
            $reasons[] = 'very tall image (aspect ratio: ' . round($aspect_ratio, 2) . ')';
        }
        
        if ($aspect_ratio < 0.4) {
            $needs_chunking = true;
            $reasons[] = 'very wide image (aspect ratio: ' . round($aspect_ratio, 2) . ')';
        }
        
        if ($megapixels > 4) {
            $needs_chunking = true;
            $reasons[] = 'high resolution (' . round($megapixels, 1) . ' megapixels)';
        }
        
        if ($file_size_mb > 8) {
            $needs_chunking = true;
            $reasons[] = 'large file size (' . round($file_size_mb, 2) . 'MB)';
        }
        
        if ($width > 3000 || $height > 3000) {
            $needs_chunking = true;
            $reasons[] = 'very high resolution (' . $width . 'x' . $height . ')';
        }
        
        if ($needs_chunking) {
            error_log('MenuMatic: Chunking needed due to: ' . implode(', ', $reasons));
        }
        
        return $needs_chunking;
    }
    
    /**
     * Process menu image using chunking approach with secondary validation
     */
    private function process_menu_with_chunking($image_path, $options = array()) {
        try {
            error_log('MenuMatic: Starting chunked processing for large menu');
            
            // Create image chunks
            $chunks = $this->create_image_chunks($image_path);
            if (is_wp_error($chunks)) {
                error_log('MenuMatic: Chunking failed: ' . $chunks->get_error_message());
                
                // If chunking fails due to memory constraints, fall back to enhanced processing
                if ($chunks->get_error_code() === 'memory_error') {
                    error_log('MenuMatic: Falling back to enhanced processing due to memory constraints');
                    return $this->process_long_menu_image($image_path, $options);
                }
                
                throw new Exception($chunks->get_error_message());
            }
            
            // Process each chunk
            $chunk_results = [];
            $chunk_count = count($chunks);
            error_log('MenuMatic: Processing ' . $chunk_count . ' chunks');
            
            foreach ($chunks as $index => $chunk_path) {
                error_log('MenuMatic: Processing chunk ' . ($index + 1) . '/' . $chunk_count);
                
                $chunk_result = $this->call_openai_isolated($chunk_path, $options, false, $index + 1);
                
                if (is_wp_error($chunk_result)) {
                    error_log('MenuMatic: Chunk ' . ($index + 1) . ' failed: ' . $chunk_result->get_error_message());
                    // Continue with other chunks even if one fails
                    continue;
                }
                
                // Extract the raw JSON from the chunk result
                if (is_array($chunk_result) && isset($chunk_result['raw_json'])) {
                    $chunk_results[$index] = $chunk_result['raw_json'];
                } else {
                    error_log('MenuMatic: Chunk ' . ($index + 1) . ' returned unexpected format: ' . print_r($chunk_result, true));
                    continue;
                }
            }
            
            // Clean up chunk files
            foreach ($chunks as $chunk_path) {
                if (file_exists($chunk_path)) {
                    @unlink($chunk_path);
                }
            }
            
            if (empty($chunk_results)) {
                throw new Exception('All chunks failed to process');
            }
            
            // Combine chunk results
            $combined_result = $this->combine_chunk_results($chunk_results);
            if (is_wp_error($combined_result)) {
                throw new Exception($combined_result->get_error_message());
            }
            
            // Perform secondary validation
            $validated_result = $this->perform_secondary_validation($image_path, $combined_result, $options);
            if (is_wp_error($validated_result)) {
                error_log('MenuMatic: Secondary validation failed, returning combined result');
                $final_result = $combined_result;
                $validation_performed = false;
            } else {
                $final_result = $validated_result;
                $validation_performed = true;
            }
            
            // Count total items for reporting
            $final_data = json_decode($final_result, true);
            $total_items = 0;
            if (isset($final_data['sections'])) {
                foreach ($final_data['sections'] as $section) {
                    if (isset($section['items'])) {
                        $total_items += count($section['items']);
                    }
                }
            }
            
            // Prepare result with chunking info
            $result = [
                'html' => $this->build_html_from_structured_data($final_data),
                'raw_json' => $final_result,
                'chunking_info' => [
                    'chunks_processed' => $chunk_count,
                    'validation_performed' => $validation_performed,
                    'items_found' => $total_items,
                    'processing_method' => 'chunked'
                ]
            ];
            
            return $result;
            
        } catch (Exception $e) {
            error_log('MenuMatic: Exception in process_menu_with_chunking: ' . $e->getMessage());
            return new WP_Error('chunking_error', $e->getMessage());
        }
    }
    
    /**
     * Process very long menu images using enhanced processing (fallback method)
     */
    private function process_long_menu_image($image_path, $options = array()) {
        try {
            // Enhanced processing with high token limit and aggressive prompt
            error_log('MenuMatic: Processing long menu with enhanced settings (high tokens, comprehensive prompt)');
            $result = $this->call_openai_isolated($image_path, $options, true);
            
            if (is_wp_error($result)) {
                // Fallback to standard processing if enhanced fails
                error_log('MenuMatic: Enhanced processing failed, falling back to standard processing');
                $result = $this->call_openai_isolated($image_path, $options, false);
                
                if (is_wp_error($result)) {
                    throw new Exception($result->get_error_message());
                }
            }
            
            // Extract the raw JSON and structured data from the result
            if (is_array($result) && isset($result['raw_json'])) {
                $menu_json = $result['raw_json'];
                $menu_data = $result['json_data'];
            } else {
                throw new Exception('Invalid result format from AI processing');
            }
            
            // Count total items for reporting
            $total_items = 0;
            if (isset($menu_data['sections'])) {
                foreach ($menu_data['sections'] as $section) {
                    if (isset($section['items'])) {
                        $total_items += count($section['items']);
                    }
                }
            }
            
            // Prepare result with processing info
            $enhanced_result = [
                'html' => $result['html'], // Use the HTML that was already built
                'raw_json' => $menu_json,
                'chunking_info' => [
                    'chunks_processed' => 0,
                    'validation_performed' => false,
                    'items_found' => $total_items,
                    'processing_method' => 'enhanced'
                ]
            ];
            
            return $enhanced_result;
            
        } catch (Exception $e) {
            error_log('MenuMatic: Exception in process_long_menu_image: ' . $e->getMessage());
            return new WP_Error('processing_error', $e->getMessage());
        }
    }
    
    /**
     * Create overlapping image chunks for large menus with memory-efficient processing
     */
    private function create_image_chunks($image_path) {
        try {
            $image_info = getimagesize($image_path);
            if (!$image_info) {
                return new WP_Error('image_error', 'Unable to get image information');
            }
            
            $width = $image_info[0];
            $height = $image_info[1];
            $mime_type = $image_info['mime'];
            
            // Check memory requirements and increase if needed
            $memory_needed = $this->estimate_memory_needed($width, $height);
            $current_memory = $this->get_memory_limit();
            
            if ($memory_needed > $current_memory) {
                error_log('MenuMatic: Image requires ' . round($memory_needed / 1024 / 1024) . 'MB memory, current limit is ' . round($current_memory / 1024 / 1024) . 'MB');
                
                // Try to increase memory limit
                $new_memory_limit = max(512, ceil($memory_needed / 1024 / 1024) + 128) . 'M';
                if (ini_set('memory_limit', $new_memory_limit)) {
                    error_log('MenuMatic: Successfully increased memory limit to ' . $new_memory_limit);
                } else {
                    error_log('MenuMatic: Failed to increase memory limit, using alternative chunking method');
                    return $this->create_image_chunks_alternative($image_path);
                }
            }
            
            $chunks = [];
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/menumatic';
            
            if (!is_dir($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }
            
            // For very large images, use smaller chunks to reduce memory usage
            $max_chunk_size = ($width * $height > 50000000) ? 1500 : 2000; // 50MP threshold
            
            // Determine chunk dimensions and overlap
            $chunk_width = min($max_chunk_size, $width);
            $chunk_height = min($max_chunk_size, $height);
            $overlap_x = min(200, $chunk_width * 0.1);
            $overlap_y = min(200, $chunk_height * 0.1);
            
            error_log('MenuMatic: Using chunk size ' . $chunk_width . 'x' . $chunk_height . ' with overlap ' . $overlap_x . 'x' . $overlap_y);
            
            // Create GD image resource
            $source_image = $this->create_image_resource($image_path, $mime_type);
            if (is_wp_error($source_image)) {
                return $source_image;
            }
            
            $chunk_index = 0;
            
            // Create chunks with overlap
            for ($y = 0; $y < $height; $y += ($chunk_height - $overlap_y)) {
                for ($x = 0; $x < $width; $x += ($chunk_width - $overlap_x)) {
                    $actual_width = min($chunk_width, $width - $x);
                    $actual_height = min($chunk_height, $height - $y);
                    
                    // Skip very small chunks
                    if ($actual_width < 500 || $actual_height < 500) {
                        continue;
                    }
                    
                    // Create chunk with memory monitoring
                    $chunk_result = $this->create_single_chunk($source_image, $x, $y, $actual_width, $actual_height, $mime_type, $temp_dir, $chunk_index);
                    
                    if (!is_wp_error($chunk_result)) {
                        $chunks[] = $chunk_result;
                        error_log('MenuMatic: Created chunk ' . $chunk_index . ' at position (' . $x . ',' . $y . ') with dimensions ' . $actual_width . 'x' . $actual_height);
                    } else {
                        error_log('MenuMatic: Failed to create chunk ' . $chunk_index . ': ' . $chunk_result->get_error_message());
                    }
                    
                    $chunk_index++;
                    
                    // Force garbage collection to free memory
                    if ($chunk_index % 5 === 0) {
                        gc_collect_cycles();
                    }
                }
            }
            
            imagedestroy($source_image);
            
            if (empty($chunks)) {
                return new WP_Error('chunking_error', 'No valid chunks created');
            }
            
            error_log('MenuMatic: Successfully created ' . count($chunks) . ' image chunks');
            return $chunks;
            
        } catch (Exception $e) {
            error_log('MenuMatic: Exception in create_image_chunks: ' . $e->getMessage());
            return new WP_Error('chunking_error', $e->getMessage());
        }
    }
    
    /**
     * Estimate memory needed for image processing
     */
    private function estimate_memory_needed($width, $height) {
        // Each pixel uses 4 bytes (RGBA), plus overhead
        // We need memory for: original image + chunk + temporary operations
        $pixel_memory = $width * $height * 4;
        $chunk_memory = 2000 * 2000 * 4; // Maximum chunk size
        $overhead = 50 * 1024 * 1024; // 50MB overhead
        
        return $pixel_memory + $chunk_memory + $overhead;
    }
    
    /**
     * Get current memory limit in bytes
     */
    private function get_memory_limit() {
        $memory_limit = ini_get('memory_limit');
        if ($memory_limit == -1) {
            return PHP_INT_MAX;
        }
        
        $unit = strtolower(substr($memory_limit, -1));
        $memory_limit = (int) substr($memory_limit, 0, -1);
        
        switch ($unit) {
            case 'g':
                $memory_limit *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $memory_limit *= 1024 * 1024;
                break;
            case 'k':
                $memory_limit *= 1024;
                break;
        }
        
        return $memory_limit;
    }
    
    /**
     * Create image resource with error handling
     */
    private function create_image_resource($image_path, $mime_type) {
        try {
            switch ($mime_type) {
                case 'image/jpeg':
                    $source_image = imagecreatefromjpeg($image_path);
                    break;
                case 'image/png':
                    $source_image = imagecreatefrompng($image_path);
                    break;
                case 'image/gif':
                    $source_image = imagecreatefromgif($image_path);
                    break;
                default:
                    return new WP_Error('unsupported_format', 'Unsupported image format for chunking');
            }
            
            if (!$source_image) {
                return new WP_Error('image_error', 'Unable to create image resource - possibly out of memory');
            }
            
            return $source_image;
            
        } catch (Exception $e) {
            return new WP_Error('image_error', 'Failed to create image resource: ' . $e->getMessage());
        }
    }
    
    /**
     * Create a single chunk with memory monitoring
     */
    private function create_single_chunk($source_image, $x, $y, $width, $height, $mime_type, $temp_dir, $chunk_index) {
        try {
            // Create chunk image
            $chunk_image = imagecreatetruecolor($width, $height);
            if (!$chunk_image) {
                return new WP_Error('chunk_error', 'Failed to create chunk image - out of memory');
            }
            
            // Preserve transparency for PNG
            if ($mime_type === 'image/png') {
                imagealphablending($chunk_image, false);
                imagesavealpha($chunk_image, true);
                $transparent = imagecolorallocatealpha($chunk_image, 255, 255, 255, 127);
                imagefill($chunk_image, 0, 0, $transparent);
            }
            
            // Copy portion of original image to chunk
            if (!imagecopy($chunk_image, $source_image, 0, 0, $x, $y, $width, $height)) {
                imagedestroy($chunk_image);
                return new WP_Error('chunk_error', 'Failed to copy image data to chunk');
            }
            
            // Save chunk
            $chunk_filename = 'chunk_' . $chunk_index . '_' . uniqid() . '.jpg';
            $chunk_path = $temp_dir . '/' . $chunk_filename;
            
            if (imagejpeg($chunk_image, $chunk_path, 85)) { // Reduced quality to save memory
                imagedestroy($chunk_image);
                return $chunk_path;
            } else {
                imagedestroy($chunk_image);
                return new WP_Error('chunk_error', 'Failed to save chunk file');
            }
            
        } catch (Exception $e) {
            if (isset($chunk_image)) {
                imagedestroy($chunk_image);
            }
            return new WP_Error('chunk_error', 'Exception creating chunk: ' . $e->getMessage());
        }
    }
    
    /**
     * Alternative chunking method for very large images that can't fit in memory
     */
    private function create_image_chunks_alternative($image_path) {
        try {
            error_log('MenuMatic: Using alternative chunking method for memory-constrained processing');
            
            // For extremely large images, we'll use a different approach
            // Process the image in much smaller sections using ImageMagick if available
            if (extension_loaded('imagick')) {
                return $this->create_chunks_with_imagick($image_path);
            }
            
            // If ImageMagick is not available, fall back to processing the original image
            // with enhanced settings instead of chunking
            error_log('MenuMatic: Cannot chunk image due to memory constraints and no ImageMagick. Falling back to enhanced processing.');
            return new WP_Error('memory_error', 'Image too large for available memory. Please reduce image size or increase PHP memory limit.');
            
        } catch (Exception $e) {
            error_log('MenuMatic: Exception in create_image_chunks_alternative: ' . $e->getMessage());
            return new WP_Error('chunking_error', $e->getMessage());
        }
    }
    
    /**
     * Create chunks using ImageMagick for memory-efficient processing
     */
    private function create_chunks_with_imagick($image_path) {
        try {
            $chunks = [];
            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/menumatic';
            
            if (!is_dir($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }
            
            // Get image dimensions
            $imagick = new Imagick($image_path);
            $width = $imagick->getImageWidth();
            $height = $imagick->getImageHeight();
            
            // Use smaller chunks for ImageMagick processing
            $chunk_width = min(1500, $width);
            $chunk_height = min(1500, $height);
            $overlap_x = min(150, $chunk_width * 0.1);
            $overlap_y = min(150, $chunk_height * 0.1);
            
            error_log('MenuMatic: Using ImageMagick with chunk size ' . $chunk_width . 'x' . $chunk_height);
            
            $chunk_index = 0;
            
            // Create chunks with overlap
            for ($y = 0; $y < $height; $y += ($chunk_height - $overlap_y)) {
                for ($x = 0; $x < $width; $x += ($chunk_width - $overlap_x)) {
                    $actual_width = min($chunk_width, $width - $x);
                    $actual_height = min($chunk_height, $height - $y);
                    
                    // Skip very small chunks
                    if ($actual_width < 500 || $actual_height < 500) {
                        continue;
                    }
                    
                    // Create chunk with ImageMagick
                    $chunk_imagick = clone $imagick;
                    $chunk_imagick->cropImage($actual_width, $actual_height, $x, $y);
                    $chunk_imagick->setImageFormat('jpeg');
                    $chunk_imagick->setImageCompressionQuality(85);
                    
                    $chunk_filename = 'chunk_' . $chunk_index . '_' . uniqid() . '.jpg';
                    $chunk_path = $temp_dir . '/' . $chunk_filename;
                    
                    if ($chunk_imagick->writeImage($chunk_path)) {
                        $chunks[] = $chunk_path;
                        error_log('MenuMatic: Created ImageMagick chunk ' . $chunk_index . ' at position (' . $x . ',' . $y . ') with dimensions ' . $actual_width . 'x' . $actual_height);
                    }
                    
                    $chunk_imagick->destroy();
                    $chunk_index++;
                }
            }
            
            $imagick->destroy();
            
            if (empty($chunks)) {
                return new WP_Error('chunking_error', 'No valid chunks created with ImageMagick');
            }
            
            error_log('MenuMatic: Successfully created ' . count($chunks) . ' image chunks with ImageMagick');
            return $chunks;
            
        } catch (Exception $e) {
            error_log('MenuMatic: Exception in create_chunks_with_imagick: ' . $e->getMessage());
            return new WP_Error('chunking_error', $e->getMessage());
        }
    }
    
    /**
     * Combine results from multiple chunks intelligently
     */
    private function combine_chunk_results($chunk_results) {
        try {
            $combined_sections = [];
            $combined_other_content = [];
            $seen_items = [];
            
            foreach ($chunk_results as $chunk_index => $chunk_json) {
                $chunk_data = json_decode($chunk_json, true);
                
                if (!$chunk_data || !isset($chunk_data['sections'])) {
                    error_log('MenuMatic: Invalid chunk data for chunk ' . $chunk_index);
                    continue;
                }
                
                // Merge sections
                foreach ($chunk_data['sections'] as $section) {
                    $section_title = $section['title'];
                    
                    if (!isset($combined_sections[$section_title])) {
                        $combined_sections[$section_title] = [
                            'title' => $section_title,
                            'items' => []
                        ];
                    }
                    
                    // Merge items, avoiding duplicates
                    foreach ($section['items'] as $item) {
                        $item_key = $this->generate_item_key($item);
                        
                        if (!isset($seen_items[$item_key])) {
                            $combined_sections[$section_title]['items'][] = $item;
                            $seen_items[$item_key] = true;
                        }
                    }
                }
                
                // Merge other content
                if (isset($chunk_data['other_content'])) {
                    foreach ($chunk_data['other_content'] as $content) {
                        $content_key = $content['type'] . '|' . $content['content'];
                        
                        $is_duplicate = false;
                        foreach ($combined_other_content as $existing_content) {
                            $existing_key = $existing_content['type'] . '|' . $existing_content['content'];
                            if ($content_key === $existing_key) {
                                $is_duplicate = true;
                                break;
                            }
                        }
                        
                        if (!$is_duplicate) {
                            $combined_other_content[] = $content;
                        }
                    }
                }
            }
            
            // Convert back to structured format
            $combined_result = [
                'sections' => array_values($combined_sections),
                'other_content' => $combined_other_content
            ];
            
            error_log('MenuMatic: Combined ' . count($chunk_results) . ' chunks into ' . count($combined_result['sections']) . ' sections with ' . 
                     array_sum(array_map(function($s) { return count($s['items']); }, $combined_result['sections'])) . ' total items');
            
            return json_encode($combined_result);
            
        } catch (Exception $e) {
            error_log('MenuMatic: Exception in combine_chunk_results: ' . $e->getMessage());
            return new WP_Error('combination_error', $e->getMessage());
        }
    }
    
    /**
     * Generate a unique key for menu items to avoid duplicates
     */
    private function generate_item_key($item) {
        $name = trim(strtolower($item['name'] ?? ''));
        $price = trim($item['price'] ?? '');
        $description = trim(strtolower($item['description'] ?? ''));
        
        // Use name + price as primary key, fall back to name + description
        if (!empty($price)) {
            return $name . '|' . $price;
        } else {
            return $name . '|' . substr($description, 0, 50);
        }
    }
    
    /**
     * Perform secondary validation by comparing combined results with original image
     */
    private function perform_secondary_validation($image_path, $combined_result, $options) {
        try {
            error_log('MenuMatic: Starting secondary validation pass');
            
            // Use a focused validation prompt
            $validation_options = array_merge($options, [
                'validation_mode' => true,
                'combined_result' => $combined_result
            ]);
            
            $validation_result = $this->call_openai_isolated($image_path, $validation_options, false, 'validation');
            
            if (is_wp_error($validation_result)) {
                error_log('MenuMatic: Secondary validation failed: ' . $validation_result->get_error_message());
                return $validation_result;
            }
            
            // Extract the raw JSON from the validation result
            $validation_json = '';
            if (is_array($validation_result) && isset($validation_result['raw_json'])) {
                $validation_json = $validation_result['raw_json'];
            } else {
                error_log('MenuMatic: Validation result returned unexpected format: ' . print_r($validation_result, true));
                return $combined_result; // Return combined result without validation
            }
            
            // Compare validation result with combined result
            $enhanced_result = $this->merge_validation_results($combined_result, $validation_json);
            
            error_log('MenuMatic: Secondary validation completed successfully');
            return $enhanced_result;
            
        } catch (Exception $e) {
            error_log('MenuMatic: Exception in perform_secondary_validation: ' . $e->getMessage());
            return new WP_Error('validation_error', $e->getMessage());
        }
    }
    
    /**
     * Merge validation results with combined chunk results
     */
    private function merge_validation_results($combined_result, $validation_result) {
        try {
            $combined_data = json_decode($combined_result, true);
            $validation_data = json_decode($validation_result, true);
            
            if (!$combined_data || !$validation_data) {
                return $combined_result;
            }
            
            // Add any missing sections or items found in validation
            $existing_items = [];
            foreach ($combined_data['sections'] as $section) {
                foreach ($section['items'] as $item) {
                    $existing_items[$this->generate_item_key($item)] = true;
                }
            }
            
            foreach ($validation_data['sections'] as $val_section) {
                $section_found = false;
                foreach ($combined_data['sections'] as &$combined_section) {
                    if (strtolower($combined_section['title']) === strtolower($val_section['title'])) {
                        $section_found = true;
                        
                        // Add any missing items
                        foreach ($val_section['items'] as $val_item) {
                            $item_key = $this->generate_item_key($val_item);
                            if (!isset($existing_items[$item_key])) {
                                $combined_section['items'][] = $val_item;
                                $existing_items[$item_key] = true;
                                error_log('MenuMatic: Validation found missing item: ' . $val_item['name']);
                            }
                        }
                        break;
                    }
                }
                
                // Add completely new section if not found
                if (!$section_found) {
                    $combined_data['sections'][] = $val_section;
                    error_log('MenuMatic: Validation found missing section: ' . $val_section['title']);
                }
            }
            
            // Merge other content
            if (isset($validation_data['other_content'])) {
                foreach ($validation_data['other_content'] as $val_content) {
                    $content_key = $val_content['type'] . '|' . $val_content['content'];
                    
                    $is_duplicate = false;
                    foreach ($combined_data['other_content'] as $existing_content) {
                        $existing_key = $existing_content['type'] . '|' . $existing_content['content'];
                        if ($content_key === $existing_key) {
                            $is_duplicate = true;
                            break;
                        }
                    }
                    
                    if (!$is_duplicate) {
                        $combined_data['other_content'][] = $val_content;
                        error_log('MenuMatic: Validation found missing content: ' . $val_content['content']);
                    }
                }
            }
            
            return json_encode($combined_data);
            
        } catch (Exception $e) {
            error_log('MenuMatic: Exception in merge_validation_results: ' . $e->getMessage());
            return $combined_result;
        }
    }
    
    /**
     * Call the configured AI API in a completely isolated environment
     */
    private function call_openai_isolated($image_path, $options, $is_long_menu = false, $chunk_info = null) {
        // Build the prompt and tool schema for this request.
        list($prompt, $tools) = $this->build_ai_prompt_and_tools($options, $is_long_menu, $chunk_info);

        // Log the prompt being used for debugging
        error_log('MenuMatic: Using prompt: ' . $this->build_prompt($options));

        if (!file_exists($image_path)) {
            return new WP_Error('api_error', 'Image file not found: ' . $image_path);
        }

        $image_data = file_get_contents($image_path);
        if ($image_data === false) {
            return new WP_Error('api_error', 'Could not read image file.');
        }

        $base64_image = base64_encode($image_data);
        $mime_type = function_exists('mime_content_type') ? mime_content_type($image_path) : 'image/jpeg';
        if (!$mime_type) {
            $mime_type = 'image/jpeg';
        }

        $provider = $this->provider;
        $api_key = $this->api_key;
        $model = $this->model;
        $site_url = home_url('/');

        $prompt_for_request = $prompt;
        $use_responses_api = ($provider === 'openai' && in_array($model, array('gpt-5.4-mini', 'gpt-5.4-nano'), true));
        $use_tools = ($provider === 'openai' && !$use_responses_api);

        if (!$use_tools) {
            $prompt_for_request .= ' Return ONLY valid JSON (no markdown, no explanation) with this structure: ';
            $prompt_for_request .= '{"sections":[{"title":"string","items":[{"name":"string","description":"string","price":"string","price_options":[{"label":"string","price":"string"}],"additional_info":"string","dietary_indicators":["vegetarian|vegan|gluten-free|spicy|organic|local|seasonal|chef-recommended"]}]}],"other_content":[{"type":"string","content":"string"}]}';
            $prompt_for_request .= ' When an item lists multiple sizes/variants each with its own price (e.g. "20oz $9.00" and "16oz $8.00"), put each size in price_options as {"label","price"} and leave price empty. For single-price items, use price and leave price_options empty.';
        }

        if ($use_responses_api) {
            // GPT-5 mini is more reliable for this workflow via the Responses API.
            $request_data = [
                'model' => $model,
                'input' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'input_text', 'text' => $prompt_for_request],
                            ['type' => 'input_image', 'image_url' => 'data:' . $mime_type . ';base64,' . $base64_image]
                        ]
                    ]
                ],
                'text' => [
                    'format' => ['type' => 'json_object']
                ],
                'max_output_tokens' => $is_long_menu ? 16000 : 6000
            ];
        } else {
            // Chat Completions request body.
            $request_data = [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt_for_request],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => 'data:' . $mime_type . ';base64,' . $base64_image,
                                    'detail' => 'high'
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            if ($provider === 'openrouter') {
                $request_data['max_tokens'] = $is_long_menu ? 16000 : 6000;
            } else {
                $request_data['max_completion_tokens'] = $is_long_menu ? 16000 : 6000;
            }

            if ($use_tools) {
                $request_data['tools'] = $tools;
                $request_data['tool_choice'] = ['type' => 'function', 'function' => ['name' => 'create_menu']];
            }
        }

        $body = wp_json_encode($request_data);
        if (!$body) {
            return new WP_Error('api_error', 'Failed to encode request body.');
        }

        $api_url = $use_responses_api
            ? 'https://api.openai.com/v1/responses'
            : (($provider === 'openrouter') ? 'https://openrouter.ai/api/v1/chat/completions' : 'https://api.openai.com/v1/chat/completions');

        $headers = [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json'
        ];
        if ($provider === 'openrouter') {
            $headers['HTTP-Referer'] = $site_url;
            $headers['X-Title']      = 'MenuMatic';
        }

        // Use the WordPress HTTP API so this works on hosts where shell_exec is disabled.
        $response = wp_remote_post($api_url, [
            'headers'     => $headers,
            'body'        => $body,
            'timeout'     => $is_long_menu ? 180 : 120,
            'sslverify'   => true,
            'redirection' => 3,
        ]);

        if (is_wp_error($response)) {
            error_log('MenuMatic: HTTP request error: ' . $response->get_error_message());
            return new WP_Error('api_error', 'HTTP request error: ' . $response->get_error_message());
        }

        $http_code     = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        $data = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('MenuMatic: Invalid JSON response from API: ' . json_last_error_msg());
            return new WP_Error('api_error', 'Invalid JSON response from API: ' . json_last_error_msg());
        }

        if (intval($http_code) !== 200) {
            $error_message = 'HTTP ' . $http_code;
            if (isset($data['error']['message'])) {
                $error_message .= ': ' . $data['error']['message'];
            }
            error_log('MenuMatic: API error: ' . $error_message);
            return new WP_Error('api_error', $error_message);
        }

        $content = $this->extract_content_from_api_response($data, $use_responses_api);
        $json_content = $this->normalize_json_content($content);

        if (trim($json_content) === '') {
            $debug_source = $use_responses_api ? ($data['output'] ?? $data) : ($data['choices'][0]['message'] ?? $data);
            $debug = substr((string) wp_json_encode($debug_source), 0, 500);
            error_log('MenuMatic: Empty content from model. Response snippet: ' . $debug);
            return new WP_Error('api_error', 'Invalid response structure or empty content from the model.');
        }

        $structured_data = json_decode($json_content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('MenuMatic: Failed to decode JSON from AI: ' . json_last_error_msg() . '. Raw content: ' . $json_content);
            return new WP_Error('processing_error', 'Failed to parse structured data from the AI. The response was not valid JSON.');
        }

        // The data is valid JSON, now let's build the HTML from it.
        $menu_html = $this->build_html_from_structured_data($structured_data);

        if (empty($menu_html)) {
            return new WP_Error('processing_error', 'Failed to build menu HTML from the structured data provided by the AI.');
        }

        // Return both the HTML and the raw JSON data
        return [
            'html' => $menu_html,
            'json_data' => $structured_data,
            'raw_json' => $json_content
        ];
    }

    /**
     * Extract the structured-JSON text from either a Responses API or Chat
     * Completions response payload.
     */
    private function extract_content_from_api_response($data, $use_responses_api) {
        $content = '';

        if ($use_responses_api) {
            if (isset($data['output_text']) && is_string($data['output_text']) && trim($data['output_text']) !== '') {
                $content = trim($data['output_text']);
            }

            if ($content === '' && isset($data['output']) && is_array($data['output'])) {
                $parts = [];
                foreach ($data['output'] as $output_item) {
                    if (!is_array($output_item)) {
                        continue;
                    }
                    if (($output_item['type'] ?? '') === 'function_call' && isset($output_item['arguments'])) {
                        $args = (string) $output_item['arguments'];
                        if (trim($args) !== '') {
                            $parts[] = trim($args);
                        }
                    }
                    if (!isset($output_item['content']) || !is_array($output_item['content'])) {
                        continue;
                    }
                    foreach ($output_item['content'] as $part) {
                        if (!is_array($part)) {
                            continue;
                        }
                        $text = '';
                        if (isset($part['text']) && is_string($part['text'])) {
                            $text = $part['text'];
                        } else if (isset($part['content']) && is_string($part['content'])) {
                            $text = $part['content'];
                        }
                        if (trim($text) !== '') {
                            $parts[] = trim($text);
                        }
                    }
                }
                if (!empty($parts)) {
                    $content = implode(PHP_EOL, $parts);
                }
            }
        } else if (isset($data['choices'][0]['message']['tool_calls'][0]['function']['arguments'])) {
            $content = (string) $data['choices'][0]['message']['tool_calls'][0]['function']['arguments'];
        } else if (isset($data['choices'][0]['message']['function_call']['arguments'])) {
            $content = (string) $data['choices'][0]['message']['function_call']['arguments'];
        } else {
            $raw_content = $data['choices'][0]['message']['content'] ?? '';
            if (is_array($raw_content)) {
                $parts = [];
                foreach ($raw_content as $part) {
                    if (is_string($part) && trim($part) !== '') {
                        $parts[] = trim($part);
                        continue;
                    }
                    if (!is_array($part)) {
                        continue;
                    }
                    if (isset($part['text']) && is_string($part['text']) && trim($part['text']) !== '') {
                        $parts[] = trim($part['text']);
                    } else if (isset($part['content']) && is_string($part['content']) && trim($part['content']) !== '') {
                        $parts[] = trim($part['content']);
                    }
                }
                $raw_content = implode(PHP_EOL, $parts);
            }
            if (is_string($raw_content) && trim($raw_content) !== '') {
                $content = trim($raw_content);
            }
        }

        return is_string($content) ? $content : '';
    }

    /**
     * Clean and repair near-JSON model output so it can be decoded.
     */
    private function normalize_json_content($content) {
        if (!is_string($content) || trim($content) === '') {
            return '';
        }

        // Remove markdown fences if present.
        $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        $content = trim($content);

        // Strip UTF-8 BOM if present.
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // Strip control characters that commonly break JSON parsing.
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $content);

        // Normalize non-breaking spaces and common Unicode punctuation.
        $content = str_replace("\xC2\xA0", ' ', $content);
        $content = str_replace(
            ["\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x9E", "\xE2\x80\x9F", "\xE2\x80\x99", "\xE2\x80\x98", "\xE2\x80\x9A", "\xE2\x80\x9B", "\xEF\xBC\x8C", "\xE3\x80\x81", "\xEF\xBC\x9B", "\xEF\xBC\x9A"],
            ['"', '"', '"', '"', chr(39), chr(39), chr(39), chr(39), ',', ',', ';', ':'],
            $content
        );

        // If model returned an escaped JSON string, decode once first.
        if (strlen($content) > 1 && $content[0] === '"' && substr($content, -1) === '"') {
            $unescaped = json_decode($content, true);
            if (is_string($unescaped) && trim($unescaped) !== '') {
                $content = trim($unescaped);
            }
        }

        // If content has extra text, attempt to extract the first JSON object.
        json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $first_brace = strpos($content, '{');
            $last_brace  = strrpos($content, '}');
            if ($first_brace !== false && $last_brace !== false && $last_brace > $first_brace) {
                $candidate = substr($content, $first_brace, ($last_brace - $first_brace + 1));
                json_decode($candidate, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $content = $candidate;
                }
            }
        }

        // Common repair for trailing commas.
        if (json_last_error() !== JSON_ERROR_NONE) {
            $candidate = preg_replace('/,\s*([}\]])/', '$1', $content);
            if (is_string($candidate) && trim($candidate) !== '') {
                json_decode($candidate, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $content = $candidate;
                }
            }
        }

        // Repair a few common delimiter issues from cheaper vision models.
        if (json_last_error() !== JSON_ERROR_NONE) {
            $candidate = $content;
            $candidate = preg_replace('/,\s*,+(?=\s*")/u', ', ', $candidate);
            $candidate = preg_replace('/[,;]\s*[,;]+/u', ', ', $candidate);
            $candidate = preg_replace('/([}\]"0-9])\s*[,;]\s*(?=")/u', '$1, ', $candidate);
            $candidate = preg_replace('/"\s*[,;]\s*([}\]])/u', '"$1', $candidate);
            $candidate = preg_replace('/([}\]"0-9])\s+(?="[A-Za-z0-9_\- ]+"\s*:)/u', '$1, ', $candidate);
            $candidate = preg_replace('/([}\]])\s*(?=[{\[])/u', '$1, ', $candidate);
            if (is_string($candidate) && trim($candidate) !== '') {
                json_decode($candidate, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $content = $candidate;
                }
            }
        }

        // Canonicalize valid JSON before returning.
        $decoded_content = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $encoded_content = json_encode($decoded_content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded_content !== false) {
                $content = $encoded_content;
            }
        }

        return $content;
    }
    
         /**
      * Build the prompt text and tool schema for the configured AI API call.
      * Returns array($prompt, $tools).
     */
     private function build_ai_prompt_and_tools($options, $is_long_menu = false, $chunk_info = null) {
        $settings = MenuMatic::get_instance()->get_settings();
        $custom_prompt = $settings['ai_prompt_template'] ?? '';

        // Handle different processing modes
        if (isset($options['validation_mode']) && $options['validation_mode']) {
            // Secondary validation mode
            $prompt = "SECONDARY VALIDATION - I need you to perform a thorough secondary review of this menu image to ensure no content was missed. ";
            $prompt .= "This is a validation pass to catch any menu items, sections, or text that might have been overlooked. ";
            $prompt .= "Focus on finding: ";
            $prompt .= "- Any menu items that might be in small print or unusual formatting ";
            $prompt .= "- Sections that might be visually separated or formatted differently ";
            $prompt .= "- Special offers, daily specials, or seasonal items ";
            $prompt .= "- Restaurant information, hours, contact details ";
            $prompt .= "- Any text in corners, margins, or background areas ";
            $prompt .= "- Handwritten additions or modifications ";
            $prompt .= "Be especially thorough and don't assume any text is unimportant. ";
        } else if ($chunk_info && $chunk_info !== 'validation') {
            // Chunk processing mode
            $prompt = "CHUNK PROCESSING - This is chunk #" . $chunk_info . " of a large menu image. ";
            $prompt .= "Extract ALL text content from this specific portion of the menu. ";
            $prompt .= "Focus on: ";
            $prompt .= "- ALL menu items visible in this section ";
            $prompt .= "- Section headings and categories ";
            $prompt .= "- Any partial text at the edges (it will be captured in adjacent chunks) ";
            $prompt .= "- All prices, descriptions, and additional information ";
            $prompt .= "- Any special formatting or dietary indicators ";
            $prompt .= "Don't worry about incomplete items at the edges - focus on clearly visible content. ";
        } else if ($is_long_menu) {
            $prompt = "COMPREHENSIVE MENU EXTRACTION - This appears to be a very long/extensive menu. I need you to extract EVERY SINGLE piece of text from this entire image. This is critical - do not skip ANY content anywhere in the image, from top to bottom. ";
            $prompt .= "SCAN THE ENTIRE IMAGE systematically from top to bottom, left to right. Extract ALL text including: ";
            $prompt .= "- ALL menu sections and categories (appetizers, mains, desserts, beverages, etc.) ";
            $prompt .= "- EVERY menu item with its name, description, and price ";
            $prompt .= "- ALL specials, daily items, seasonal offerings ";
            $prompt .= "- EVERY promotional text, banners, headers ";
            $prompt .= "- ALL restaurant information (hours, contact, address, social media) ";
            $prompt .= "- ANY additional text in margins, corners, or special formatting ";
            $prompt .= "- Text in ANY font size, style, or color ";
            $prompt .= "- Handwritten notes, stamps, or added content ";
            $prompt .= "- Small print, fine details, or background text ";
            $prompt .= "IMPORTANT: This menu is extensive - make sure to scroll through mentally and capture content from the very top to the very bottom. Use all available tokens to provide a complete extraction. ";
        } else {
            $prompt = "Carefully analyze this menu image and capture ALL text content, including standard and non-standard items. Extract EVERY piece of text you see - don't skip anything. ";
            $prompt .= "This includes regular menu items, specials, seasonal items, combo meals, sides, beverages, desserts, appetizers, chef's recommendations, daily specials, promotional text, restaurant information, hours, phone numbers, addresses, social media handles, and any other text content. ";
            $prompt .= "Pay special attention to: ";
            $prompt .= "- Items written in different fonts, sizes, or styles ";
            $prompt .= "- Handwritten or script text ";
            $prompt .= "- Text in boxes, banners, or special formatting ";
            $prompt .= "- Small print text ";
            $prompt .= "- Text at angles or in creative layouts ";
        }
        $prompt .= "- Multiple language text ";
        $prompt .= "- Prices in different currencies or formats ";
        $prompt .= "- Any promotional offers or deals ";
        $prompt .= "If an item doesn't have a clear price, still include it with 'Price varies' or 'Market price' or leave price empty. ";
        $prompt .= "If an item lists multiple sizes/variants each with its own price (e.g. '20oz \$9.00' and '16oz \$8.00'), capture every size in 'price_options' as separate label/price pairs and leave the single 'price' empty. ";
        $prompt .= "If text doesn't fit into standard categories, use the 'other_content' section to capture it. ";
        if (!empty($options['detect_dietary'])) {
            $prompt .= 'Please identify and include all relevant dietary indicators for each item (e.g., vegan, vegetarian, gluten-free, spicy). ';
        }
        $prompt .= "Use the provided tool to structure ALL the data you find.";
        
        $tools = [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'create_menu',
                    'description' => 'Creates a structured menu from the provided data.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'sections' => [
                                'type' => 'array',
                                'description' => 'A list of menu sections with all items found.',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'title' => [
                                            'type' => 'string',
                                            'description' => 'The title of the menu section (e.g., "Appetizers", "Main Courses", "Daily Specials", "Chef\'s Recommendations").'
                                        ],
                                        'items' => [
                                            'type' => 'array',
                                            'description' => 'A list of ALL items within this section, including standard and non-standard items.',
                                            'items' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'name' => ['type' => 'string', 'description' => 'The name of the menu item, exactly as written.'],
                                                    'description' => ['type' => 'string', 'description' => 'Full description of the menu item, including all details.'],
                                                    'price' => ['type' => 'string', 'description' => 'The single price as written (can be empty). Use price_options instead when the item lists multiple sizes/variants.'],
                                                    'price_options' => [
                                                        'type' => 'array',
                                                        'description' => 'Use ONLY when the item lists multiple sizes/variants, each with its own price (e.g. a draft cider showing "20oz $9.00" and "16oz $8.00"). One entry per size/variant. Leave empty for single-price items.',
                                                        'items' => [
                                                            'type' => 'object',
                                                            'properties' => [
                                                                'label' => ['type' => 'string', 'description' => 'The size or variant label exactly as written (e.g. "20oz", "16oz", "Glass", "Bottle", "Large").'],
                                                                'price' => ['type' => 'string', 'description' => 'The price for this size/variant as written.']
                                                            ],
                                                            'required' => ['label', 'price']
                                                        ]
                                                    ],
                                                    'additional_info' => ['type' => 'string', 'description' => 'Any additional text, notes, or special formatting for this item.'],
                                                    'dietary_indicators' => [
                                                        'type' => 'array',
                                                        'description' => 'A list of dietary indicators for the item.',
                                                        'items' => [
                                                            'type' => 'string',
                                                            'enum' => ['vegetarian', 'vegan', 'gluten-free', 'spicy', 'organic', 'local', 'seasonal', 'chef-recommended']
                                                        ]
                                                    ]
                                                ],
                                                'required' => ['name']
                                            ]
                                        ]
                                    ],
                                    'required' => ['title', 'items']
                                ]
                            ],
                            'other_content' => [
                                'type' => 'array',
                                'description' => 'Any other text content that doesn\'t fit into menu sections (restaurant info, hours, phone, specials, etc.)',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'type' => ['type' => 'string', 'description' => 'Type of content (e.g., "restaurant_info", "hours", "special_offer", "contact", "other")'],
                                        'content' => ['type' => 'string', 'description' => 'The actual text content found']
                                    ],
                                    'required' => ['type', 'content']
                                ]
                            ]
                        ],
                        'required' => ['sections']
                    ]
                ]
            ]
        ];
         
         return array($prompt, $tools);

         // Legacy isolated-process script below is no longer used (kept for reference only).
         return '<?php
// Completely isolated AI API call
error_reporting(0);
ini_set("display_errors", 0);

$image_path = ' . var_export($image_path, true) . ';
$provider = ' . var_export($provider, true) . ';
$api_key = ' . var_export($api_key, true) . ';
$model = ' . var_export($model, true) . ';
$site_url = ' . var_export($site_url, true) . ';
$prompt = ' . var_export($prompt, true) . ';
$tools = ' . var_export($tools, true) . ';
$is_long_menu = ' . var_export($is_long_menu, true) . ';

try {
    if (!file_exists($image_path)) {
        echo "ERROR" . PHP_EOL . "Image file not found" . PHP_EOL;
        exit(1);
    }
    
    $image_data = file_get_contents($image_path);
    if ($image_data === false) {
        echo "ERROR" . PHP_EOL . "Could not read image file" . PHP_EOL;
        exit(1);
    }
    
    $base64_image = base64_encode($image_data);
    $mime_type = mime_content_type($image_path);
    
    $prompt_for_request = $prompt;
    $use_responses_api = ($provider === "openai" && $model === "gpt-5-mini");
    $use_tools = ($provider === "openai" && !$use_responses_api);

    if (!$use_tools) {
        $prompt_for_request .= " Return ONLY valid JSON (no markdown, no explanation) with this structure: ";
        $prompt_for_request .= "{\"sections\":[{\"title\":\"string\",\"items\":[{\"name\":\"string\",\"description\":\"string\",\"price\":\"string\",\"additional_info\":\"string\",\"dietary_indicators\":[\"vegetarian|vegan|gluten-free|spicy|organic|local|seasonal|chef-recommended\"]}]}],\"other_content\":[{\"type\":\"string\",\"content\":\"string\"}]}";
    }

    if ($use_responses_api) {
        // GPT-5 mini is more reliable for this workflow via the Responses API.
        $request_data = [
            "model" => $model,
            "input" => [
                [
                    "role" => "user",
                    "content" => [
                        ["type" => "input_text", "text" => $prompt_for_request],
                        ["type" => "input_image", "image_url" => "data:" . $mime_type . ";base64," . $base64_image]
                    ]
                ]
            ],
            "text" => [
                "format" => [
                    "type" => "json_object"
                ]
            ],
            "max_output_tokens" => $is_long_menu ? 16000 : 6000
        ];
    } else {
        // Construct the request body for chat completions.
        $request_data = [
            "model" => $model,
            "messages" => [
                [
                    "role" => "user",
                    "content" => [
                        ["type" => "text", "text" => $prompt_for_request],
                        [
                            "type" => "image_url",
                            "image_url" => [
                                "url" => "data:" . $mime_type . ";base64," . $base64_image,
                                "detail" => "high"
                            ]
                        ]
                    ]
                ]
            ]
        ];

        if ($provider === "openrouter") {
            $request_data["max_tokens"] = $is_long_menu ? 16000 : 6000;
        } else {
            $request_data["max_completion_tokens"] = $is_long_menu ? 16000 : 6000;
        }

        if ($use_tools) {
            $request_data["tools"] = $tools;
            $request_data["tool_choice"] = ["type" => "function", "function" => ["name" => "create_menu"]];
        }
    }
    
    $body = json_encode($request_data);
    
    if (!$body) {
        echo "ERROR" . PHP_EOL . "Failed to encode request body" . PHP_EOL;
        exit(1);
    }
    
    $ch = curl_init();
    
    if (!$ch) {
        echo "ERROR" . PHP_EOL . "Failed to initialize cURL" . PHP_EOL;
        exit(1);
    }
    
    $api_url = $use_responses_api ? "https://api.openai.com/v1/responses" : (($provider === "openrouter") ? "https://openrouter.ai/api/v1/chat/completions" : "https://api.openai.com/v1/chat/completions");
    $headers = [
        "Authorization: Bearer " . $api_key,
        "Content-Type: application/json"
    ];

    if ($provider === "openrouter") {
        $headers[] = "HTTP-Referer: " . $site_url;
        $headers[] = "X-Title: MenuMatic";
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $api_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => $is_long_menu ? 180 : 120,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($response === false || !empty($curl_error)) {
        echo "ERROR" . PHP_EOL . "cURL error: " . $curl_error . PHP_EOL;
        exit(1);
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "ERROR" . PHP_EOL . "Invalid JSON response: " . json_last_error_msg() . PHP_EOL;
        exit(1);
    }
    
    if ($http_code !== 200) {
        $error_message = "HTTP " . $http_code;
        if (isset($data["error"]["message"])) {
            $error_message .= ": " . $data["error"]["message"];
        }
        echo "ERROR" . PHP_EOL . $error_message . PHP_EOL;
        exit(1);
    }
    
    // Extract structured JSON from either Responses API output or Chat Completions output.
    $content = "";

    if ($use_responses_api) {
        if (isset($data["output_text"]) && is_string($data["output_text"]) && trim($data["output_text"]) !== "") {
            $content = trim($data["output_text"]);
        }

        if ($content === "" && isset($data["output"]) && is_array($data["output"])) {
            $parts = [];

            foreach ($data["output"] as $output_item) {
                if (!is_array($output_item)) {
                    continue;
                }

                if (($output_item["type"] ?? "") === "function_call" && isset($output_item["arguments"])) {
                    $args = (string) $output_item["arguments"];
                    if (trim($args) !== "") {
                        $parts[] = trim($args);
                    }
                }

                if (!isset($output_item["content"]) || !is_array($output_item["content"])) {
                    continue;
                }

                foreach ($output_item["content"] as $part) {
                    if (!is_array($part)) {
                        continue;
                    }

                    $text = "";
                    if (isset($part["text"]) && is_string($part["text"])) {
                        $text = $part["text"];
                    } else if (isset($part["content"]) && is_string($part["content"])) {
                        $text = $part["content"];
                    }

                    if (trim($text) !== "") {
                        $parts[] = trim($text);
                    }
                }
            }

            if (!empty($parts)) {
                $content = implode(PHP_EOL, $parts);
            }
        }
    } else if (isset($data["choices"][0]["message"]["tool_calls"][0]["function"]["arguments"])) {
        $content = (string) $data["choices"][0]["message"]["tool_calls"][0]["function"]["arguments"];
    } else if (isset($data["choices"][0]["message"]["function_call"]["arguments"])) {
        $content = (string) $data["choices"][0]["message"]["function_call"]["arguments"];
    } else {
        $raw_content = $data["choices"][0]["message"]["content"] ?? "";

        if (is_array($raw_content)) {
            $parts = [];
            foreach ($raw_content as $part) {
                if (is_string($part) && trim($part) !== "") {
                    $parts[] = trim($part);
                    continue;
                }

                if (!is_array($part)) {
                    continue;
                }

                if (isset($part["text"]) && is_string($part["text"]) && trim($part["text"]) !== "") {
                    $parts[] = trim($part["text"]);
                } else if (isset($part["content"]) && is_string($part["content"]) && trim($part["content"]) !== "") {
                    $parts[] = trim($part["content"]);
                }
            }
            $raw_content = implode(PHP_EOL, $parts);
        }

        if (is_string($raw_content) && trim($raw_content) !== "") {
            $content = trim($raw_content);
        }
    }

    if (is_string($content) && trim($content) !== "") {
        // Remove markdown fences if present.
        $content = preg_replace("/^```(?:json)?\\s*/i", "", $content);
        $content = preg_replace("/\\s*```$/", "", $content);
        $content = trim($content);

        // Strip UTF-8 BOM if present.
        $content = preg_replace("/^\\xEF\\xBB\\xBF/", "", $content);

        // Strip control characters that commonly break JSON parsing.
        $content = preg_replace("/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F\\x7F]/u", "", $content);

        // Normalize common Unicode punctuation that often appears in near-JSON model output.
        $content = str_replace(
            ["“", "”", "„", "‟", "’", "‘", "‚", "‛", "，", "、", "；", "：", " "],
            ["\"", "\"", "\"", "\"", chr(39), chr(39), chr(39), chr(39), ",", ",", ";", ":", " "],
            $content
        );

        // If model returned an escaped JSON string, decode once first.
        if (strlen($content) > 1 && $content[0] === "\"" && substr($content, -1) === "\"") {
            $unescaped = json_decode($content, true);
            if (is_string($unescaped) && trim($unescaped) !== "") {
                $content = trim($unescaped);
            }
        }

        // If content has extra text, attempt to extract the first JSON object.
        $decoded_content = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $first_brace = strpos($content, "{");
            $last_brace = strrpos($content, "}");
            if ($first_brace !== false && $last_brace !== false && $last_brace > $first_brace) {
                $candidate = substr($content, $first_brace, ($last_brace - $first_brace + 1));
                $decoded_candidate = json_decode($candidate, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $content = $candidate;
                    $decoded_content = $decoded_candidate;
                }
            }
        }

        // Common repair for trailing commas.
        if (json_last_error() !== JSON_ERROR_NONE) {
            $candidate = preg_replace("/,\\s*([}\\]])/", "$1", $content);
            if (is_string($candidate) && trim($candidate) !== "") {
                $decoded_candidate = json_decode($candidate, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $content = $candidate;
                    $decoded_content = $decoded_candidate;
                }
            }
        }

        // Repair a few common delimiter issues from cheaper vision models.
        if (json_last_error() !== JSON_ERROR_NONE) {
            $candidate = $content;
            $candidate = preg_replace("/,\\s*,+(?=\\s*\")/u", ", ", $candidate);
            $candidate = preg_replace("/[,;]\\s*[,;]+/u", ", ", $candidate);
            $candidate = preg_replace("/([}\\]\"0-9])\\s*[,;]\\s*(?=\")/u", "$1, ", $candidate);
            $candidate = preg_replace("/\"\\s*[,;]\\s*([}\\]])/u", "\"$1", $candidate);
            $candidate = preg_replace("/([}\\]\"0-9])\\s+(?=\"[A-Za-z0-9_\\- ]+\"\\s*:)/u", "$1, ", $candidate);
            $candidate = preg_replace("/([}\\]])\\s*(?=[{\\[])/u", "$1, ", $candidate);
            if (is_string($candidate) && trim($candidate) !== "") {
                $decoded_candidate = json_decode($candidate, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $content = $candidate;
                    $decoded_content = $decoded_candidate;
                }
            }
        }

        // Canonicalize valid JSON before returning.
        $decoded_content = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $encoded_content = json_encode($decoded_content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded_content !== false) {
                $content = $encoded_content;
            }
        }
    }
    
    if (trim($content) === "") {
        $debug_source = $use_responses_api ? ($data["output"] ?? $data) : ($data["choices"][0]["message"] ?? $data);
        $debug = json_encode($debug_source);
        if (!$debug) {
            $debug = "No message data available";
        }
        $debug = substr($debug, 0, 500);
        echo "ERROR" . PHP_EOL . "Invalid response structure or empty content from model. Response snippet: " . $debug . PHP_EOL;
        exit(1);
    }

    $json_check = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $snippet = substr($content, 0, 500);
        echo "ERROR" . PHP_EOL . "Model returned non-JSON content after normalization. Snippet: " . $snippet . PHP_EOL;
        exit(1);
    }
    
    echo "SUCCESS" . PHP_EOL . $content . PHP_EOL;
    
} catch (Exception $e) {
    echo "ERROR" . PHP_EOL . $e->getMessage() . PHP_EOL;
    exit(1);
} catch (Error $e) {
    echo "ERROR" . PHP_EOL . $e->getMessage() . PHP_EOL;
    exit(1);
}
?>';
     }
    
    /**
     * Build HTML from the structured data provided by the AI tool call.
     */
    /**
     * Render the price markup for a menu item. Supports either a single price
     * string or multiple size/variant options (price_options).
     */
    private function render_item_price_html($item) {
        // Multiple size/variant prices (e.g. 20oz $9.00 / 16oz $8.00).
        if (!empty($item['price_options']) && is_array($item['price_options'])) {
            $rows = '';
            foreach ($item['price_options'] as $option) {
                if (!is_array($option)) {
                    continue;
                }
                $label = esc_html(trim((string) ($option['label'] ?? '')));
                $opt_price = esc_html(trim((string) ($option['price'] ?? '')));
                if ($label === '' && $opt_price === '') {
                    continue;
                }
                $rows .= '<span class="price-option">'
                    . '<span class="price-option-label">' . $label . '</span>'
                    . '<span class="price-option-value">' . $opt_price . '</span>'
                    . '</span>';
            }
            if ($rows !== '') {
                return '<div class="item-price item-price-options">' . $rows . '</div>';
            }
        }

        // Single price fallback.
        $item_price = esc_html(trim((string) ($item['price'] ?? '')));
        if ($item_price !== '') {
            return '<div class="item-price">' . $item_price . '</div>';
        }

        return '';
    }

    /**
     * Render short text badges (e.g. "V", "GFP") shown beside the item name.
     * Accepts a comma-separated string or an array of labels.
     */
    private function render_item_badges_html($item) {
        $badges = $item['badges'] ?? array();
        if (is_string($badges)) {
            $badges = explode(',', $badges);
        }
        if (!is_array($badges)) {
            return '';
        }

        $html = '';
        foreach ($badges as $badge) {
            $badge = trim((string) $badge);
            if ($badge !== '') {
                $html .= ' <span class="dietary-indicator">' . esc_html($badge) . '</span>';
            }
        }

        return $html;
    }

    private function build_html_from_structured_data($data) {
        $html = '<div class="menu-container">';
        
        // Check if we have the new content structure with ordered items
        if (isset($data['content']) && is_array($data['content'])) {
            // New structure with ordered content (sections and inline other content)
            foreach ($data['content'] as $content) {
                if ($content['type'] === 'section') {
                    $section_title = esc_html($content['title'] ?? 'Menu Section');
                    $section_subtitle = esc_html(trim((string) ($content['subtitle'] ?? '')));
                    $html .= '<section class="menu-section">';
                    $html .= '<h2 class="menu-section-title">' . $section_title . '</h2>';
                    if ($section_subtitle !== '') {
                        $html .= '<p class="menu-subtitle">' . $section_subtitle . '</p>';
                    }

                    if (isset($content['items']) && is_array($content['items']) && !empty($content['items'])) {
                        $html .= '<div class="menu-grid">';
                        foreach ($content['items'] as $item) {
                            $item_name = esc_html($item['name'] ?? 'Menu Item');
                            $item_price = esc_html($item['price'] ?? '');
                            $item_description = esc_html($item['description'] ?? '');
                            $additional_info = esc_html($item['additional_info'] ?? '');
                            $dietary_indicators = $item['dietary_indicators'] ?? [];

                            // Check if item has an image (added through manual editing)
                            $has_image = !empty($item['image']);
                            $item_class = $has_image ? 'menu-item has-image' : 'menu-item';
                            
                            $html .= '<div class="' . $item_class . '">';
                            
                            // Add image if present (from manual editing)
                            if ($has_image) {
                                $image_url = esc_url($item['image']);
                                $image_alt = esc_attr($item_name);
                                $html .= '<img src="' . $image_url . '" alt="' . $image_alt . '" class="item-image">';
                            }
                            
                            // Wrap content in container for items with images
                            if ($has_image) {
                                $html .= '<div class="menu-item-content">';
                            }
                            
                            $html .= '<div class="item-name">' . $item_name . $this->render_item_badges_html($item) . '</div>';
                            $html .= $this->render_item_price_html($item);
                            if (!empty($item_description)) {
                                $html .= '<div class="item-description">' . $item_description . '</div>';
                            }
                            if (!empty($additional_info)) {
                                $html .= '<div class="item-additional-info">' . $additional_info . '</div>';
                            }

                            if (!empty($dietary_indicators) && is_array($dietary_indicators)) {
                                $html .= '<div class="dietary-info">';
                                foreach ($dietary_indicators as $indicator) {
                                    $class = 'dietary-indicator dietary-' . esc_attr(strtolower(trim($indicator)));
                                    $title = esc_attr(ucfirst(str_replace('-', ' ', $indicator)));
                                    $html .= '<span class="' . $class . '" title="' . $title . '"></span>';
                                }
                                $html .= '</div>';
                            }

                            // Close content wrapper for items with images
                            if ($has_image) {
                                $html .= '</div>'; // .menu-item-content
                            }

                            $html .= '</div>'; // .menu-item
                        }
                        $html .= '</div>'; // .menu-grid
                    }
                    $html .= '</section>'; // .menu-section
                } elseif ($content['type'] === 'other_content') {
                    // Inline other content - display in its correct position
                    $content_type = esc_html($content['content_type'] ?? 'other');
                    $content_text = esc_html($content['content'] ?? '');

                    if (!empty($content_text)) {
                        if ($content_type === 'footnote') {
                            $html .= '<p class="menu-footnote">' . $content_text . '</p>';
                        } elseif ($this->looks_like_title($content_text)) {
                            // Looks like a title/header
                            $html .= '<h4 class="menu-other-title">' . $content_text . '</h4>';
                        } else {
                            // Check if this contains price-like information
                            if ($this->contains_price($content_text)) {
                                // Treat like a menu item with name and price
                                $parts = $this->extract_name_and_price($content_text);
                                $html .= '<div class="menu-grid">';
                                $html .= '<div class="menu-item">';
                                $html .= '<div class="item-name">' . esc_html($parts['name']) . '</div>';
                                if (!empty($parts['price'])) {
                                    $html .= '<div class="item-price">' . esc_html($parts['price']) . '</div>';
                                }
                                if (!empty($parts['description'])) {
                                    $html .= '<div class="item-description">' . esc_html($parts['description']) . '</div>';
                                }
                                $html .= '</div>'; // .menu-item
                                $html .= '</div>'; // .menu-grid
                            } else {
                                // Regular text content
                                $html .= '<div class="menu-other-content">';
                                $html .= '<h4 class="other-content-type">' . esc_html(ucwords(str_replace(['_', '-'], ' ', $content_type))) . '</h4>';
                                $html .= '<p class="menu-other-text">' . $content_text . '</p>';
                                $html .= '</div>';
                            }
                        }
                    }
                }
            }
            
            // Footnotes always render last, as plain notes below the menu
            $sidebar_rows = array();
            $footnotes_html = '';
            if (isset($data['other_content']) && is_array($data['other_content'])) {
                foreach ($data['other_content'] as $content_item) {
                    if (($content_item['type'] ?? '') === 'footnote') {
                        $footnote_text = esc_html($content_item['content'] ?? '');
                        if ($footnote_text !== '') {
                            $footnotes_html .= '<p class="menu-footnote">' . $footnote_text . '</p>';
                        }
                    } else {
                        $sidebar_rows[] = $content_item;
                    }
                }
            }

            // Process remaining sidebar content if any
            if (!empty($sidebar_rows)) {
                $html .= '<hr class="menu-separator">';
                $in_grid = false;

                foreach ($sidebar_rows as $content_item) {
                    $content_type = esc_html($content_item['type'] ?? 'other');
                    $content_text = esc_html($content_item['content'] ?? '');
                    
                    if (!empty($content_text)) {
                        // Check if this looks like a title/header
                        if ($this->looks_like_title($content_text)) {
                            // Close any open grid first
                            if ($in_grid) {
                                $html .= '</div>'; // .menu-grid
                                $in_grid = false;
                            }
                            $html .= '<h4 class="menu-other-title">' . $content_text . '</h4>';
                        } else {
                            // Check if this contains price-like information
                            if ($this->contains_price($content_text)) {
                                // Start grid if not already started
                                if (!$in_grid) {
                                    $html .= '<div class="menu-grid">';
                                    $in_grid = true;
                                }
                                
                                // Treat like a menu item with name and price
                                $parts = $this->extract_name_and_price($content_text);
                                $html .= '<div class="menu-item">';
                                $html .= '<div class="item-name">' . esc_html($parts['name']) . '</div>';
                                if (!empty($parts['price'])) {
                                    $html .= '<div class="item-price">' . esc_html($parts['price']) . '</div>';
                                }
                                if (!empty($parts['description'])) {
                                    $html .= '<div class="item-description">' . esc_html($parts['description']) . '</div>';
                                }
                                $html .= '</div>'; // .menu-item
                            } else {
                                // Close any open grid first
                                if ($in_grid) {
                                    $html .= '</div>'; // .menu-grid
                                    $in_grid = false;
                                }
                                // Regular text content
                                $html .= '<div class="menu-other-content">';
                                $html .= '<h4 class="other-content-type">' . esc_html(ucwords(str_replace(['_', '-'], ' ', $content_type))) . '</h4>';
                                $html .= '<p class="menu-other-text">' . $content_text . '</p>';
                                $html .= '</div>';
                            }
                        }
                    }
                }
                
                // Close grid if still open
                if ($in_grid) {
                    $html .= '</div>'; // .menu-grid
                }
            }

            $html .= $footnotes_html;
        } else {
            // Legacy structure - fall back to old behavior
            if (!isset($data['sections']) || !is_array($data['sections'])) {
                $html .= '</div>'; // .menu-container
                return $html;
            }

            // Process menu sections
            foreach ($data['sections'] as $section) {
                $section_title = esc_html($section['title'] ?? 'Menu Section');
                $html .= '<h2 class="menu-section-title">' . $section_title . '</h2>';

                if (isset($section['items']) && is_array($section['items']) && !empty($section['items'])) {
                    $html .= '<div class="menu-grid">';
                    foreach ($section['items'] as $item) {
                        $item_name = esc_html($item['name'] ?? 'Menu Item');
                        $item_price = esc_html($item['price'] ?? '');
                        $item_description = esc_html($item['description'] ?? '');
                        $additional_info = esc_html($item['additional_info'] ?? '');
                        $dietary_indicators = $item['dietary_indicators'] ?? [];

                        // Check if item has an image (added through manual editing)
                        $has_image = !empty($item['image']);
                        $item_class = $has_image ? 'menu-item has-image' : 'menu-item';
                        
                        $html .= '<div class="' . $item_class . '">';
                        
                        // Add image if present (from manual editing)
                        if ($has_image) {
                            $image_url = esc_url($item['image']);
                            $image_alt = esc_attr($item_name);
                            $html .= '<img src="' . $image_url . '" alt="' . $image_alt . '" class="item-image">';
                        }
                        
                        // Wrap content in container for items with images
                        if ($has_image) {
                            $html .= '<div class="menu-item-content">';
                        }
                        
                        $html .= '<div class="item-name">' . $item_name . '</div>';
                        $html .= $this->render_item_price_html($item);
                        if (!empty($item_description)) {
                            $html .= '<div class="item-description">' . $item_description . '</div>';
                        }
                        if (!empty($additional_info)) {
                            $html .= '<div class="item-additional-info">' . $additional_info . '</div>';
                        }

                        if (!empty($dietary_indicators) && is_array($dietary_indicators)) {
                            $html .= '<div class="dietary-info">';
                            foreach ($dietary_indicators as $indicator) {
                                $class = 'dietary-indicator dietary-' . esc_attr(strtolower(trim($indicator)));
                                $title = esc_attr(ucfirst(str_replace('-', ' ', $indicator)));
                                $html .= '<span class="' . $class . '" title="' . $title . '"></span>';
                            }
                            $html .= '</div>';
                        }
                        
                        // Close content wrapper for items with images
                        if ($has_image) {
                            $html .= '</div>'; // .menu-item-content
                        }
                        
                        $html .= '</div>'; // .menu-item
                    }
                    $html .= '</div>'; // .menu-grid
                }
            }

            // Process other content (restaurant info, hours, specials, etc.) - integrate naturally
            if (isset($data['other_content']) && is_array($data['other_content']) && !empty($data['other_content'])) {
                $html .= '<hr class="menu-separator">';
                $in_grid = false;
                
                foreach ($data['other_content'] as $content_item) {
                    $content_type = esc_html($content_item['type'] ?? 'other');
                    $content_text = esc_html($content_item['content'] ?? '');
                    
                    if (!empty($content_text)) {
                        // Check if this looks like a title/header
                        if ($this->looks_like_title($content_text)) {
                            // Close any open grid first
                            if ($in_grid) {
                                $html .= '</div>'; // .menu-grid
                                $in_grid = false;
                            }
                            $html .= '<h4 class="menu-other-title">' . $content_text . '</h4>';
                        } else {
                            // Check if this contains price-like information
                            if ($this->contains_price($content_text)) {
                                // Start grid if not already started
                                if (!$in_grid) {
                                    $html .= '<div class="menu-grid">';
                                    $in_grid = true;
                                }
                                
                                // Treat like a menu item with name and price
                                $parts = $this->extract_name_and_price($content_text);
                                $html .= '<div class="menu-item">';
                                $html .= '<div class="item-name">' . esc_html($parts['name']) . '</div>';
                                if (!empty($parts['price'])) {
                                    $html .= '<div class="item-price">' . esc_html($parts['price']) . '</div>';
                                }
                                if (!empty($parts['description'])) {
                                    $html .= '<div class="item-description">' . esc_html($parts['description']) . '</div>';
                                }
                                $html .= '</div>'; // .menu-item
                            } else {
                                // Close any open grid first
                                if ($in_grid) {
                                    $html .= '</div>'; // .menu-grid
                                    $in_grid = false;
                                }
                                // Regular text content
                                $html .= '<div class="menu-other-content">';
                                $html .= '<h4 class="other-content-type">' . esc_html(ucwords(str_replace(['_', '-'], ' ', $content_type))) . '</h4>';
                                $html .= '<p class="menu-other-text">' . $content_text . '</p>';
                                $html .= '</div>';
                            }
                        }
                    }
                }
                
                // Close grid if still open
                if ($in_grid) {
                    $html .= '</div>'; // .menu-grid
                }
            }
        }

        $html .= '</div>'; // .menu-container

        return $html;
    }
    
    /**
     * Build the AI prompt based on options
     */
    private function build_prompt($options = array(), $custom_prompt_template = '') {
        // Use custom template if provided and not empty
        if (!empty($custom_prompt_template)) {
            return $custom_prompt_template;
        }
        
        // Default prompt - simplified and more direct
        $prompt = "Please analyze this menu image and convert it into clean HTML. Follow these requirements:\n\n";
        
        $prompt .= "1. Create a complete HTML structure\n";
        $prompt .= "2. Use these CSS classes:\n";
        $prompt .= "   - menu-container: main wrapper\n";
        $prompt .= "   - menu-section: each menu category\n";
        $prompt .= "   - menu-item: individual menu items\n";
        $prompt .= "   - item-name: item name\n";
        $prompt .= "   - item-description: item description\n";
        $prompt .= "   - item-price: price\n";
        $prompt .= "3. Include headings for each menu section\n";
        $prompt .= "4. List each menu item with name, description, and price\n";
        
        if (!empty($options['detect_dietary'])) {
            $prompt .= "6. Include dietary indicators (🌱 vegetarian, 🌿 vegan, 🌾 gluten-free, etc.)\n";
        }
        
        if (!empty($options['extract_allergens'])) {
            $prompt .= "7. Extract and highlight allergen information\n";
        }
        
        if (!empty($options['preserve_formatting'])) {
            $prompt .= "8. Preserve the original menu structure and groupings as much as possible\n";
        }
        
        $prompt .= "\nReturn ONLY the HTML code without any explanations, markdown formatting, or code blocks. Start directly with the HTML content. Do not include any error messages, warnings, or non-HTML text.";
        
        return $prompt;
    }
    
    /**
     * Clean the HTML response from the AI provider
     */
    private function clean_html_response($html) {
        // Remove markdown code blocks if present
        $html = preg_replace('/```html\s*/i', '', $html);
        $html = preg_replace('/```\s*$/i', '', $html);
        $html = preg_replace('/```/i', '', $html);
        
        // Remove extra whitespace
        $html = trim($html);
        
        // Basic HTML validation and cleanup
        if (strpos($html, '<') === false) {
            error_log('MenuMatic: No HTML tags found in response');
            return ''; // Not HTML
        }
        
        // More permissive HTML tags and attributes for menu content
        $allowed_tags = array(
            'div' => array('class' => array(), 'id' => array(), 'style' => array()),
            'section' => array('class' => array(), 'id' => array()),
            'article' => array('class' => array(), 'id' => array()),
            'header' => array('class' => array(), 'id' => array()),
            'main' => array('class' => array(), 'id' => array()),
            'h1' => array('class' => array(), 'id' => array()),
            'h2' => array('class' => array(), 'id' => array()),
            'h3' => array('class' => array(), 'id' => array()),
            'h4' => array('class' => array(), 'id' => array()),
            'h5' => array('class' => array(), 'id' => array()),
            'h6' => array('class' => array(), 'id' => array()),
            'p' => array('class' => array(), 'id' => array()),
            'span' => array('class' => array(), 'id' => array()),
            'ul' => array('class' => array(), 'id' => array()),
            'ol' => array('class' => array(), 'id' => array()),
            'li' => array('class' => array(), 'id' => array()),
            'dl' => array('class' => array(), 'id' => array()),
            'dt' => array('class' => array(), 'id' => array()),
            'dd' => array('class' => array(), 'id' => array()),
            'strong' => array('class' => array()),
            'b' => array('class' => array()),
            'em' => array('class' => array()),
            'i' => array('class' => array()),
            'br' => array(),
            'hr' => array('class' => array()),
            'table' => array('class' => array(), 'id' => array()),
            'thead' => array('class' => array()),
            'tbody' => array('class' => array()),
            'tr' => array('class' => array()),
            'th' => array('class' => array()),
            'td' => array('class' => array()),
            'a' => array('class' => array(), 'href' => array(), 'title' => array()),
            'img' => array('class' => array(), 'src' => array(), 'alt' => array()),
        );
        
        $cleaned_html = wp_kses($html, $allowed_tags);
        
        // If wp_kses stripped everything, return the original with basic sanitization
        if (empty($cleaned_html) && !empty($html)) {
            error_log('MenuMatic: wp_kses stripped all content, using basic sanitization');
            // Just remove script and style tags for basic safety
            $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
            $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);
            return $html;
        }
        
        return $cleaned_html;
    }
    
    /**
     * Build HTML from raw JSON string (for editing)
     */
    public function build_html_from_json($json_string) {
        $data = json_decode($json_string, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Invalid JSON: ' . json_last_error_msg());
        }
        
        return $this->build_html_from_structured_data($data);
    }

    /**
     * Check if text looks like a title/header
     */
    private function looks_like_title($text) {
        $text = trim($text);
        
        // Short text that might be a title
        if (strlen($text) < 50) {
            // All caps might be a title
            if (strtoupper($text) === $text && strlen($text) > 3) {
                return true;
            }
            
            // Title case patterns
            if (preg_match('/^[A-Z][a-z]+(\s+[A-Z][a-z]+)*$/', $text)) {
                return true;
            }
            
            // Common title words
            $title_words = ['hours', 'contact', 'information', 'special', 'today', 'daily', 'chef', 'featured'];
            foreach ($title_words as $word) {
                if (stripos($text, $word) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Check if text contains price information
     */
    private function contains_price($text) {
        // Look for common price patterns
        $price_patterns = [
            '/\$\d+\.?\d*/',  // $5, $5.99
            '/\d+\.?\d*\s*\$/', // 5$, 5.99$
            '/\d+\.?\d*\s*dollars?/i', // 5 dollars
            '/\d+\.?\d*\s*EUR?/i', // 5 EUR
            '/€\d+\.?\d*/', // €5
            '/£\d+\.?\d*/', // £5
            '/\d+\.?\d*\s*cents?/i', // 50 cents
        ];
        
        foreach ($price_patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Extract name and price from text
     */
    private function extract_name_and_price($text) {
        $result = [
            'name' => $text,
            'price' => '',
            'description' => ''
        ];
        
        // Try to extract price patterns
        $price_patterns = [
            '/(.+?)\s+(\$\d+\.?\d*)(.*)/',  // Name $5.99 description
            '/(.+?)\s+(\d+\.?\d*\s*\$)(.*)/', // Name 5.99$ description
            '/(.+?)\s+(\d+\.?\d*\s*dollars?)(.*)/', // Name 5 dollars description
            '/(.+?)\s+(€\d+\.?\d*)(.*)/', // Name €5.99 description
            '/(.+?)\s+(£\d+\.?\d*)(.*)/', // Name £5.99 description
        ];
        
        foreach ($price_patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $result['name'] = trim($matches[1]);
                $result['price'] = trim($matches[2]);
                if (isset($matches[3]) && !empty(trim($matches[3]))) {
                    $result['description'] = trim($matches[3]);
                }
                break;
            }
        }
        
        return $result;
    }

    /**
     * Estimate API cost for processing
     */
    public function estimate_cost($image_size_mb) {
        // Approximate input token pricing by selected model.
        $cost_per_1k_tokens = ($this->model === 'gpt-5.4-nano') ? 0.00015 : 0.00025;
        $estimated_tokens = 1000 + ($image_size_mb * 200); // Base + image tokens
        
        return ($estimated_tokens / 1000) * $cost_per_1k_tokens;
    }
}
