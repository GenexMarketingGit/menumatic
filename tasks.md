# MenuMatic WordPress Plugin - Task Plan

## Project Overview
Create a WordPress plugin that allows users to upload menu images (phone pictures) and uses OpenAI GPT-4o to transcribe them into responsive HTML menus.

## Tasks

### Phase 1: Plugin Foundation
- [x] Create main plugin file (menumatic.php)
- [x] Set up plugin header and activation/deactivation hooks
- [x] Create plugin directory structure
- [x] Add admin menu and settings page
- [x] Create database table for storing menu data

### Phase 2: File Upload System
- [x] Create upload form interface
- [x] Implement secure file upload handling
- [x] Add image validation (file type, size limits)
- [x] Create file storage system
- [x] Add upload progress indicator

### Phase 3: OpenAI Integration
- [x] Create OpenAI API configuration settings
- [x] Implement GPT-4o vision API integration
- [x] Create prompt engineering for menu transcription
- [x] Add error handling for API calls
- [ ] Implement rate limiting and usage tracking

### Phase 4: Menu Processing & Display
- [x] Parse GPT-4o response into structured data
- [x] Create responsive HTML/CSS templates
- [x] Implement mobile-first design
- [x] Add customization options (colors, fonts, layout)
- [x] Create shortcode for menu display
- [x] Fix CSS layout to match menu-example.html structure exactly

### Phase 5: Admin Interface
- [x] Create menu management dashboard
- [x] Add edit/delete functionality for menus
- [x] Implement preview system
- [ ] Add bulk operations
- [ ] Create export functionality

### Phase 6: User Experience
- [x] Add loading states and progress indicators
- [x] Implement error messaging system
- [x] Create help documentation
- [x] Add tooltips and guidance
- [x] Implement accessibility features
- [x] Create frontend management shortcode for public menu management
- [x] Update frontend upload to allow selecting existing menus for replacement
- [x] Fix frontend AJAX localization for proper script functionality
- [x] Remove item search functionality from frontend menus
- [x] Improve AI text extraction to capture ALL text including non-standard items
- [x] Enhanced AI prompt to identify different text formats and styles
- [x] Added support for additional item information and other content
- [x] Improved CSS styling for additional content sections
- [x] Implement comprehensive processing for very long menus
  - [x] Automatic detection of tall images (aspect ratio > 3:1)
  - [x] Enhanced token limits (16,000 for long menus vs 6,000 standard)
  - [x] Aggressive prompting for complete content extraction
  - [x] Extended processing timeout for complex menus
  - [x] Fallback mechanism to ensure processing success
  - [x] Implement true image chunking for large menus
    - [x] Detect large/complex menus (size, aspect ratio, content density)
    - [x] Split large images into overlapping chunks
    - [x] Process each chunk separately with OpenAI
    - [x] Combine results from all chunks intelligently
    - [x] Implement secondary validation pass
    - [x] Cross-reference chunks to ensure no content is missed
    - [x] Provide detailed processing report with confidence scores
    - [x] **Fixed all data type mismatch bugs** (2025-01-03)
      - Fixed `json_decode()` error where array was passed instead of string
      - Modified chunking system to extract `raw_json` from chunk results
      - Fixed same issue in secondary validation step (line 798)
      - Fixed standard processing path (line 107)
      - Fixed enhanced processing path (process_long_menu_image)
      - All processing methods now work correctly end-to-end
- [x] Implement memory-efficient processing for extremely large images
  - [x] Automatic memory requirement estimation and optimization
  - [x] Dynamic PHP memory limit adjustment when possible
  - [x] ImageMagick fallback for memory-constrained environments
  - [x] Intelligent chunk size adjustment based on image size
  - [x] Memory cleanup and garbage collection between chunks
  - [x] Graceful fallback hierarchy for processing methods
  - [x] User-friendly error messages for memory-related issues
- [x] Implement JSON storage for all generated menu data
- [x] Create visual JSON editor interface in WordPress backend
- [x] Add functionality to manually edit menu JSON structure
- [x] Implement regenerate HTML from edited JSON feature
- [x] Add JSON validation and real-time preview in editor
- [x] Convert JSON editor to visual menu editor
  - [x] Replace raw JSON editing with visual menu representation matching menu-example.html
  - [x] Enable inline editing of menu items, sections, and content
  - [x] Add visual controls for adding/removing sections and items
  - [x] Include dietary indicators with checkboxes
  - [x] Provide sidebar for other content management
  - [x] Maintain save/regenerate functionality with visual interface
- [x] Add frontend visual editor functionality
  - [x] Integrate "Edit Manually" button into frontend management shortcode
  - [x] Create frontend visual editor interface with same capabilities as admin
  - [x] Implement secure nonce handling for frontend access
  - [x] Enable direct frontend menu editing without admin access
  - [x] Maintain responsive design and user experience consistency
- [x] **Implement drag-and-drop reordering functionality**
  - [x] Add drag-and-drop reordering for menu sections in admin editor
  - [x] Add drag-and-drop reordering for menu items within sections in admin editor
  - [x] Add drag-and-drop reordering for other content items in admin editor
  - [x] Add drag-and-drop reordering for menu sections in frontend editor
  - [x] Add drag-and-drop reordering for menu items within sections in frontend editor
  - [x] Add drag-and-drop reordering for other content items in frontend editor
  - [x] Ensure save functionality preserves reordered structure
  - [x] Add visual feedback during drag operations
  - [x] **Add cross-container dragging for other content items**
    - [x] Allow dragging other content from sidebar into main menu area
    - [x] Position other content between menu sections
    - [x] Support bi-directional movement (main area ↔ sidebar)
    - [x] Maintain content editing capabilities when inline
    - [x] Apply distinctive styling for inline other content
  - [x] **Fix drag-and-drop persistence issues** (2025-01-03)
    - [x] Update data structure to use unified `content` array for ordered sections and inline content
    - [x] Modify admin editor PHP to handle new structure for loading saved content
    - [x] Update JavaScript save functionality to preserve inline content positions
    - [x] Fix OpenAI API class to generate HTML from new mixed content structure
    - [x] Maintain backward compatibility with legacy data structure
    - [x] Ensure inline content persists after save and appears on frontend
    - [x] Fix editor reload to show inline content in correct positions
    - [x] **Fix frontend editor data handling** (2025-01-03)
      - [x] Update frontend `getMenuDataFromFrontendEditor` to use new unified structure
      - [x] Fix frontend `populateVisualEditor` to load inline content correctly
      - [x] Add `createInlineOtherContentHtml` function for frontend editor
      - [x] Update drag-and-drop handler to use proper inline content creation
      - [x] Add event handlers for inline content editing changes
      - [x] Ensure frontend and admin editors use identical data structures
  - [ ] Test reordering functionality across different browsers and devices

### Phase 7: Security & Performance
- [x] Implement proper nonce verification
- [x] Add input sanitization and validation
- [x] Create file cleanup system
- [x] Add caching for processed menus
- [x] Implement proper user permissions

### Phase 8: Testing & Polish
- [ ] Test with various menu image types
- [ ] Cross-browser compatibility testing
- [ ] Mobile responsiveness testing
- [ ] Performance optimization
- [ ] Code cleanup and documentation

## Technical Requirements
- WordPress 5.0+
- PHP 7.4+
- OpenAI API key
- Image processing capabilities
- Responsive design framework 