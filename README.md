# MenuMatic WordPress Plugin

Transform menu photos into beautiful, responsive HTML menus using OpenAI GPT-5.4 vision AI.

## Description

MenuMatic is a powerful WordPress plugin that allows you to take a photo of any menu and automatically convert it into a professionally styled, mobile-responsive HTML menu using advanced AI technology. Perfect for restaurants, cafes, bars, and any business that needs to digitize their menus quickly and efficiently.

## Features

- 📱 **AI-Powered Menu Recognition** - Uses OpenAI GPT-5.4 Vision models to read and transcribe menu images
- 🎨 **Multiple Style Templates** - Choose from Modern, Classic, Minimal, and Elegant designs
- 📱 **Mobile-First Responsive Design** - Looks great on all devices
- 🔍 **Menu Search Functionality** - Built-in search for large menus
- 🍃 **Dietary Indicator Detection** - Automatically identifies vegan, vegetarian, gluten-free items
- 🖨️ **Print-Friendly** - Generate printable versions of your menus
- ♿ **Accessibility Features** - WCAG compliant with proper ARIA labels
- 🎯 **Easy Shortcode Integration** - Display menus anywhere with simple shortcodes

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- OpenAI API key (for GPT-5.4 mini or GPT-5.4 nano)
- Active internet connection for AI processing

## Installation

1. **Download the Plugin**
   - Download the MenuMatic plugin files
   - Upload to your WordPress `/wp-content/plugins/menumatic/` directory
   - Or install via WordPress admin: Plugins → Add New → Upload Plugin

2. **Activate the Plugin**
   - Go to Plugins in your WordPress admin
   - Find "MenuMatic" and click Activate

3. **Configure OpenAI API**
   - Get your API key from [OpenAI Platform](https://platform.openai.com/api-keys)
   - Go to MenuMatic → Settings in your WordPress admin
   - Enter your OpenAI API key and save

## Usage

### Upload and Process a Menu

1. **Navigate to MenuMatic → Upload Menu**
2. **Upload your menu image:**
   - Drag and drop or click to select
   - Supported formats: JPG, PNG, WEBP
   - Maximum file size: 5MB (configurable)

3. **Configure processing options:**
   - Enter a menu title
   - Choose a style (Modern, Classic, Minimal, Elegant)
   - Select processing options (allergen detection, dietary indicators, etc.)

4. **Process the menu:**
   - Click "Process Menu with AI"
   - Wait for AI processing (usually 30-60 seconds)
   - Review the generated HTML menu
   - Save or make adjustments as needed

### Display Your Menu

Use the shortcode to display your menu anywhere on your site:

```
[menumatic_display id="1"]
```

**Shortcode Parameters:**
- `id` - Required. The menu ID from the manage page
- `style` - Optional. Override the menu style (modern, classic, minimal, elegant)

**Examples:**
```
[menumatic_display id="1" style="modern"]
[menumatic_display id="2" style="classic"]
```

### Manage Your Menus

1. **Go to MenuMatic → Manage Menus**
2. **View all your processed menus**
3. **Actions available:**
   - Preview menu
   - Copy shortcode
   - Delete menu

## Settings

### OpenAI Configuration
- **API Key** - Your OpenAI API key for GPT-5.4 models
- **Model Selection** - Choose `GPT-5.4 mini (Recommended)` or `GPT-5.4 nano (Fastest, lowest cost)`
- **API Connection Test** - Verify your API key is working

### File Upload Settings
- **Maximum File Size** - Set upload limit (1-50MB)
- **Allowed File Types** - Choose supported image formats

### Menu Display Settings
- **Default Menu Style** - Set the default style for new menus
- **Enable Caching** - Cache processed menus for better performance

### Advanced Settings
- **Custom AI Prompt Template** - Customize the AI processing prompt
- **Processing Options** - Default settings for allergen detection, etc.

## Menu Styles

### Modern Style
- Clean, contemporary design
- Gradient backgrounds
- Bold typography
- Perfect for trendy restaurants

### Classic Style
- Traditional serif fonts
- Warm color palette
- Elegant borders
- Great for fine dining establishments

### Minimal Style
- Clean, simple design
- Plenty of white space
- Subtle styling
- Ideal for casual dining

### Elegant Style
- Sophisticated typography
- Refined color scheme
- Professional appearance
- Perfect for upscale venues

## Tips for Best Results

### Photography Tips
- **Lighting:** Ensure good, even lighting without shadows
- **Angle:** Take photos straight-on to avoid distortion
- **Focus:** Make sure all text is sharp and clearly readable
- **Coverage:** Include all menu sections in the image
- **Quality:** Use high-resolution images when possible

### Menu Processing
- **Clear Text:** Ensure menu text is not obscured by glare or shadows
- **Complete Sections:** Include full menu sections for better organization
- **Allergen Info:** If present on original menu, AI will detect and include it
- **Prices:** Make sure prices are clearly visible and readable

## Troubleshooting

### Common Issues

**"OpenAI API key not configured"**
- Go to MenuMatic → Settings
- Enter your valid OpenAI API key
- Test the connection

**"File upload failed"**
- Check file size limits
- Ensure file format is supported
- Verify WordPress upload permissions

**"AI processing failed"**
- Check your OpenAI API key balance
- Ensure image is clear and readable
- Try a smaller file size

**Menu not displaying**
- Verify the shortcode ID is correct
- Check that the menu status is "active"
- Ensure the plugin is activated

### Getting Help

1. **Check the plugin settings** - Verify all configurations
2. **Test API connection** - Use the built-in connection test
3. **Review error messages** - Note any specific error details
4. **Check WordPress logs** - Look for PHP errors or warnings

## API Usage and Costs

MenuMatic uses OpenAI GPT-5.4 vision-capable models for image processing:

- **Approximate cost:** $0.01-0.05 per menu image
- **Processing time:** 30-90 seconds per image
- **Image requirements:** Up to 20MB, high resolution recommended

**Cost-saving tips:**
- Batch process multiple menus when possible
- Use clear, high-contrast images to reduce processing retries
- Consider processing during off-peak hours

## Security and Privacy

- All uploaded images are processed securely
- Images are deleted after processing (configurable)
- OpenAI API calls are made over HTTPS
- No menu data is stored by OpenAI after processing
- All generated content is stored in your WordPress database

## Changelog

### Version 1.0.0
- Initial release
- OpenAI GPT-5.4 model integration
- Four menu style templates
- Responsive design
- Admin interface
- Shortcode functionality
- File upload system
- Menu management system

## Support

For support, feature requests, or bug reports:

1. Check this README for common solutions
2. Review the plugin settings and configuration
3. Test with different menu images
4. Contact support with specific error details

## License

GPL v2 or later

---

**MenuMatic** - Transform your menu photos into beautiful digital menus with AI! 🍕✨ 
