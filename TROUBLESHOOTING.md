# MenuMatic Troubleshooting Guide

## File Upload Issues

If you're having trouble uploading files, follow these steps:

### 1. Check Browser Console

Open your browser's developer tools (F12) and look for any error messages:

1. Go to MenuMatic → Upload Menu
2. Press F12 to open developer tools
3. Click on "Console" tab
4. Try clicking "Drop your menu image here"
5. Look for any red error messages

You should see these messages if everything is working:
```
MenuMatic: Initializing upload functionality
MenuMatic: Dropzone found: 1
MenuMatic: File input found: 1
MenuMatic: Dropzone clicked
MenuMatic: Triggering file input click
```

### 2. Try the Select File Button

If the dropzone doesn't work, try clicking the "Select File" button instead.

### 3. Check File Requirements

Make sure your file meets these requirements:
- **File types:** JPG, JPEG, PNG, WEBP
- **File size:** Under 5MB (or your configured limit)
- **File quality:** Clear, readable text

### 4. WordPress Issues

#### Check Plugin Activation
1. Go to Plugins → Installed Plugins
2. Make sure "MenuMatic" is activated

#### Check File Permissions
Your WordPress uploads directory needs write permissions:
- `/wp-content/uploads/` should be writable

#### Check for Plugin Conflicts
1. Deactivate other plugins temporarily
2. Try uploading again
3. If it works, reactivate plugins one by one to find the conflict

### 5. Server Issues

#### PHP Upload Limits
Check these PHP settings in your hosting control panel:
- `upload_max_filesize` should be at least 5MB
- `post_max_size` should be at least 8MB
- `max_execution_time` should be at least 120 seconds

#### WordPress Upload Limits
Check WordPress Settings → Media for upload limits.

### 6. OpenAI API Issues

#### API Key Problems
1. Go to MenuMatic → Settings
2. Click "Test Connection"
3. If it fails, check your API key

#### API Key Requirements
- Valid OpenAI API key from https://platform.openai.com/api-keys
- Sufficient API credits/quota
- GPT-5.2 or GPT-5 mini model access

### 7. Quick Fixes

#### Clear Browser Cache
1. Clear your browser cache and cookies
2. Refresh the page and try again

#### Try Different Browser
Test with Chrome, Firefox, or Safari to isolate browser issues.

#### Check JavaScript
Make sure JavaScript is enabled in your browser.

### 8. Get Debug Information

If none of the above works, collect this information:

1. **Browser Console Errors** (from step 1)
2. **WordPress Version**
3. **PHP Version** (Tools → Site Health)
4. **Active Plugins List**
5. **Theme Name**
6. **Error Messages** (exact text)

### 9. Common Error Messages

#### "Invalid file type"
- File is not JPG, JPEG, PNG, or WEBP
- File extension doesn't match content

#### "File size too large"
- File exceeds size limit
- Check Settings → Max File Size

#### "OpenAI API key not configured"
- Go to Settings and add your API key
- Test the connection

#### "File upload failed"
- Server permission issues
- PHP upload limits exceeded
- Disk space full

### 10. Alternative Upload Method

If drag & drop doesn't work, you can:

1. Click the "Select File" button directly
2. Use the browser's file picker
3. Ensure the file meets all requirements

### Still Having Issues?

If you've tried all these steps and still can't upload files:

1. Check WordPress error logs (in cPanel or hosting control panel)
2. Contact your hosting provider about upload issues
3. Try with a smaller, simpler image file first
4. Ensure your OpenAI API key has sufficient credits

### Testing File

Try uploading a simple test image:
- Small JPG file (under 1MB)
- Clear text on white background
- Simple menu layout

This will help isolate whether the issue is with the upload system or specific file requirements.

## AJAX and Error Debugging

If you're getting "no menu content generated" errors, follow these debugging steps:

### 1. Enable WordPress Debug Logging

Add these lines to your `wp-config.php` file (before the "/* That's all, stop editing!" line):

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### 2. Check Error Logs

After enabling debugging, check these locations for error logs:
- `/wp-content/debug.log` (main WordPress log)
- Your hosting provider's error log (check hosting control panel)

Look for entries starting with "MenuMatic:" to see detailed debugging information.

### 3. Test AJAX Endpoints

Open your browser's developer console (F12) and try uploading a menu image. Check for:
- JavaScript errors in the Console tab
- Network requests in the Network tab
- Look for failed AJAX calls to `admin-ajax.php`

### 4. Check API Keys

1. Go to MenuMatic Settings
2. Click "Test Connection" 
3. If it fails, verify your OpenAI API key is correct

### 5. File Upload Issues

If upload fails before reaching AI processing:
- Check file size (must be under 5MB)
- Check file type (JPG, PNG, or WEBP only)  
- Verify WordPress upload permissions

### 6. Common Error Messages

**"Invalid response from OpenAI API"**
- Check your API key
- Verify you have credits in your OpenAI account
- Check if OpenAI API is down

**"No menu content generated"**
- Check the error logs for the actual OpenAI response
- The image might not be clear enough for AI to read
- Try a different image with clearer text

**"File upload failed"**
- Check WordPress upload directory permissions
- Verify file size and type restrictions

### 7. Manual Testing

If all else fails, you can test the upload process manually:

1. Enable debugging (step 1 above)
2. Try uploading a clear, high-quality menu image
3. Check `/wp-content/debug.log` for detailed error messages
4. Look for the actual OpenAI response in the logs

The debug logs will show exactly what OpenAI returns, which helps identify if:
- The API call is working but returning unexpected content
- The HTML cleaning is too aggressive
- There's a network or authentication issue 
