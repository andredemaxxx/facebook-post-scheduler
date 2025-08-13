# Facebook Post Scheduler - Professional WordPress Plugin v2.0

A comprehensive, production-ready WordPress plugin for scheduling and automating Facebook page posts using Meta's Graph API with OAuth 2.0 authentication, secure token management, and professional scheduling features.

## 🚨 **IMPORTANT: JavaScript Error Prevention**

This plugin has been specifically designed to prevent common JavaScript recursion errors ("too much recursion") that can occur with WordPress media uploaders and event handlers. The following measures have been implemented:

### **Separated JavaScript Files**
- `admin.js` - Handles normal post scheduling
- `multi-scheduler.js` - Handles multi-post scheduling  
- `calendar.js` - Handles calendar functionality

### **Event Handler Safety**
- All event handlers use `.off()` before `.on()` to prevent multiple bindings
- Namespaced events (`.fps-admin`, `.fps-multi`, `.fps-calendar`) prevent conflicts
- Proper event delegation to avoid recursion
- Console logging for debugging event flows

### **Media Uploader Best Practices**
- Single-use media frame instances
- Proper cleanup after media selection
- Fallback file input handlers
- Prevention of multiple simultaneous uploads

### **Conditional Script Loading**
- Scripts are loaded only on their specific pages
- Separate nonces for each functionality
- Independent initialization checks
- No cross-functionality dependencies

## 🚀 Features

### Core Functionality
- **OAuth 2.0 Authentication**: Secure Facebook login with automatic token refresh
- **Multi-Page Management**: Connect and manage multiple Facebook pages
- **Advanced Scheduling**: Schedule individual posts with text, images, videos, and links
- **Multi Post Scheduling**: Upload and schedule multiple posts in bulk with three pairing modes:
  - Pair by filename (image1.jpg + image1.txt)
  - Pair by upload order (first image + first text)
  - Images only with manual text input
- **Calendar Post Management**: Create recurring schedules for specific days and times
- **Real-time Preview**: See how your posts will look before publishing
- **Comprehensive Analytics**: Track post performance and page insights
- **Secure Token Storage**: Encrypted token storage with automatic refresh
- **Professional Admin Interface**: Clean, responsive WordPress admin integration
- **Visual Calendar**: Monthly calendar view with recurring schedules and occupied time slots
- **Timezone Management**: All operations use São Paulo timezone (America/Sao_Paulo GMT-3)

### Security & Performance
- **Encrypted Token Storage**: All Facebook tokens are encrypted in the database
- **Automatic Token Refresh**: Long-lived tokens are automatically refreshed and validated
- **Token Validation**: Real-time token validation using Facebook's debug_token endpoint
- **Comprehensive Logging**: Detailed activity logs for debugging and monitoring
- **Input Validation**: All user inputs are sanitized and validated
- **WordPress Standards**: Follows WordPress coding standards and best practices
- **Error Handling**: Robust error handling with user-friendly messages

### Technical Features
- **Dual Scheduling**: Uses Facebook's native scheduling with WordPress cron backup for recurring posts
- **Long-lived Tokens**: Automatic exchange of short-lived tokens for long-lived page tokens
- **File Upload Support**: Handle images and videos with proper validation
- **Bulk Operations**: Manage multiple posts efficiently
- **Smart File Pairing**: Automatically pair images with text files by name or order
- **Database Optimization**: Efficient database structure with proper indexing
- **Internationalization**: Ready for translation (i18n)
- **Responsive Design**: Works perfectly on all devices
- **Modern UI**: Tailwind CSS-inspired design with enhanced user experience
- **Real-time Notifications**: Toast notifications for better user feedback

## 📋 Requirements

### System Requirements
- **WordPress**: 6.8 or higher
- **PHP**: 8.3 or higher
- **MySQL**: 5.7 or higher
- **SSL Certificate**: HTTPS is required for Facebook API
- **PHP Extensions**: cURL, JSON, OpenSSL

### Facebook Requirements
- Facebook Developer Account
- Facebook App with Business verification (for production)
- Facebook Graph API v23.0 compatibility
- Admin access to Facebook pages you want to manage
- Valid SSL certificate on your WordPress site

## 🔧 Installation

### 1. Download and Install
1. Download the plugin files
2. Upload the `facebook-post-scheduler` folder to `/wp-content/plugins/`
3. Activate the plugin through WordPress admin
4. You'll be redirected to the dashboard automatically

### 2. Facebook App Setup

#### Step 1: Create Facebook App
1. Go to [Facebook Developers](https://developers.facebook.com/apps/)
2. Click **"Create App"**
3. Select **"Business"** as app type
4. Fill in app details:
   - **App Name**: Your website name + "Post Scheduler"
   - **Contact Email**: Your email address
   - **Business Account**: Select if you have one

#### Step 2: Configure Basic Settings
1. In App Dashboard → **Settings** → **Basic**
2. Note your **App ID** and **App Secret**
3. Add your domain to **App Domains**
4. Set **Privacy Policy URL** and **Terms of Service URL**
5. Save changes

#### Step 3: Add Facebook Login Product
1. In left sidebar → **Add Product**
2. Find **Facebook Login** → **Set Up**
3. Choose **Web** platform
4. In **Valid OAuth Redirect URIs**, add:
   ```
   https://yourdomain.com/wp-admin/admin.php?page=fps-settings
   ```
5. Save changes

#### Step 4: Request Permissions
Your app needs these permissions:
- `pages_manage_posts` - Publish posts to pages
- `pages_read_engagement` - Read engagement metrics
- `pages_show_list` - List user's pages
- `pages_manage_metadata` - Manage page metadata

For production, submit for App Review in **App Review** → **Permissions and Features**

### 3. Plugin Configuration

#### Method 1: WordPress Admin Setup (Recommended)
1. Go to **Facebook Scheduler** → **Settings**
2. Enter your **App ID** and **App Secret**
3. Click **"Connect Facebook Account"**
4. Authorize the app and select pages
5. Save settings

#### Method 2: Manual Configuration
If OAuth doesn't work due to network restrictions:
1. Get a long-lived page access token from [Graph API Explorer](https://developers.facebook.com/tools/explorer/)
2. Use the manual configuration section in settings
3. Test connection before saving

### 4. Timezone Configuration

The plugin uses São Paulo timezone (America/Sao_Paulo GMT-3) for all scheduling operations. To ensure proper functionality:

1. **WordPress Timezone**: Set your WordPress timezone to match your preference in **Settings** → **General**
2. **Server Timezone**: The plugin automatically handles timezone conversion
3. **Facebook API**: All timestamps are converted to UTC when sending to Facebook

**Important**: All scheduling times are displayed and processed in São Paulo timezone regardless of your WordPress timezone setting. This ensures consistency for Brazilian users.

### 5. Cron Configuration

The plugin uses WordPress cron for recurring posts and token refresh:

1. **WordPress Cron**: Enabled by default, no additional configuration needed
2. **Server Cron**: For high-traffic sites, consider setting up server-level cron:
   ```bash
   */5 * * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
   ```
3. Test connection before saving

## 📖 Usage Guide

### Basic Post Scheduling


### Scheduling Your First Post

1. **Go to Schedule Post**
   - Navigate to **Facebook Scheduler** → **Schedule Post**

2. **Select Facebook Page**
   - Choose from your connected pages

3. **Create Content**
   - Write your message (supports emojis and hashtags)
   - Add links for automatic preview generation
   - Upload images or videos (optional)

4. **Set Schedule**
   - Pick date and time (must be in future)
   - Preview shows in your WordPress timezone

5. **Preview & Schedule**
   - Review the Facebook-style preview
   - Click **"Schedule Post"** to confirm

### Multi Post Scheduling

The Multi Post Scheduler has been completely redesigned with three distinct modes:

The Multi Post Scheduler allows you to upload multiple images and text files, pair them automatically, and schedule them across multiple time slots.

1. **Go to Schedule Post Multi**
   - Navigate to **Facebook Scheduler** → **Schedule Post Multi**

2. **Select Facebook Page**
   - Choose the page where you want to publish all posts

3. **Choose Upload Mode**
   - **Pair by filename**: Pairs files with matching names (image1.jpg + image1.txt)
   - **Pair by upload order**: Pairs files in upload order (first image + first text)
   - **Images only + manual text**: Upload images and manually enter text for each

4. **Upload Files**
   - Upload multiple images (JPG, PNG, GIF)
   - For filename/order modes: Upload corresponding text files (.txt)
   - For manual mode: Enter text directly in the interface

5. **Process Files**
   - Click "Process Files" to pair images with text
   - Review the preview of all paired posts

6. **Select Schedule Options**
   - Choose a date for posting
   - Select available time slots from the recurring schedules
   - Time slots are automatically loaded based on your Calendar Post settings

7. **Schedule Posts**
   - Review all posts in the preview
   - Click "Schedule All Posts" to confirm
   - Posts will be distributed automatically across selected times

### Calendar Post Management

The Calendar Post feature allows you to create recurring schedules:

6. **Schedule Posts**
   - Select a date for posting
   - Choose available time slots for your posts
   - Posts will be distributed automatically across selected times
   - Click **"Schedule All Posts"** to confirm

1. **Go to Calendar Post**
   - Navigate to **Facebook Scheduler** → **Calendar Post**

2. **Create Recurring Schedule**
   - Click "Add Recurring Schedule"
   - Select Facebook page, day of week, and time
   - Enter the message and optional link
   - Save the schedule

3. **Manage Schedules**
   - View all recurring schedules in the sidebar
   - Edit, pause, or delete schedules as needed
   - View calendar with scheduled posts and recurring times

### Managing Time Slots

1. **Add Time Slots**
   - Use the time picker to add new posting times
   - Times are automatically sorted and saved
   - Common times: 06:00, 09:00, 12:00, 15:00, 18:00, 21:00

2. **Remove Time Slots**
   - Click the × button next to any saved time
   - Removed times won't appear in future scheduling

3. **View Occupied Slots**
   - Red slots are already occupied by scheduled posts
   - Blue slots are available for new posts
   - Green slots are selected for current scheduling

### Managing Scheduled Posts

1. **View All Posts**
   - Go to **Facebook Scheduler** → **Scheduled Posts**
   - Filter by status: Scheduled, Published, Failed
   - Search posts by content

2. **Edit Scheduled Posts**
   - Click edit icon on pending posts
   - Modify message, links, or schedule time
   - Changes sync with Facebook automatically

3. **Monitor Performance**
   - Check **Analytics** for insights
   - View engagement metrics
   - Track posting success rates

### Analytics & Insights

1. **Page Analytics**
   - Go to **Facebook Scheduler** → **Analytics**
   - Select page to view insights
   - See reach, impressions, and engagement

2. **Post Performance**
   - View individual post metrics
   - Track clicks, likes, shares, comments
   - Export data for reporting

## 🛠️ Advanced Configuration

### Timezone Settings

The plugin automatically uses São Paulo timezone (America/Sao_Paulo GMT-3). To verify correct operation:

```php
// Check current plugin timezone
$timezone_info = FPS_Timezone_Manager::get_timezone_info();
echo $timezone_info['display_name']; // São Paulo (GMT-3)
echo $timezone_info['current_time']; // Current time in São Paulo
```

### Cron Configuration

For optimal performance, especially on high-traffic sites:

1. **Disable WordPress Cron** (optional, for server cron):
   ```php
   // Add to wp-config.php
   define('DISABLE_WP_CRON', true);
   ```

2. **Set up server cron**:
   ```bash
   # Every 5 minutes
   */5 * * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
   ```

### Custom Scheduling Options

```php
// Add to your theme's functions.php
add_filter('fps_post_settings', function($settings) {
    $settings['timezone'] = 'America/Sao_Paulo';     // Plugin timezone
    $settings['use_facebook_scheduling'] = true; // Use Facebook's native scheduling
    $settings['auto_link_preview'] = true;       // Generate link previews
    $settings['image_quality'] = 'high';         // Image quality setting
    return $settings;
});
```

### Calendar Post Hooks

```php
// Modify recurring post data before publishing
add_filter('fps_before_recurring_post', function($post_data, $schedule_id) {
    // Add custom modifications
    $post_data['message'] .= ' #AutoPost';
    return $post_data;
}, 10, 2);

// After recurring post is published
add_action('fps_after_recurring_post', function($post_id, $schedule_id, $facebook_response) {
    // Custom logging or notifications
    FPS_Logger::info("Recurring post published: {$post_id}");
}, 10, 3);
```

### Custom Post Processing

```php
// Modify post data before scheduling
add_filter('fps_before_schedule_post', function($post_data) {
    // Add custom hashtags
    $post_data['message'] .= ' #YourHashtag';
    
    // Add UTM parameters to links
    if (!empty($post_data['link'])) {
        $post_data['link'] = add_query_arg([
            'utm_source' => 'facebook',
            'utm_medium' => 'social',
            'utm_campaign' => 'scheduled_post'
        ], $post_data['link']);
    }
    
    return $post_data;
});
```

### Webhook Integration

```php
// Handle Facebook webhooks for real-time updates
add_action('fps_facebook_webhook', function($data) {
    if ($data['object'] === 'page') {
        foreach ($data['entry'] as $entry) {
            // Process page events
            FPS_Logger::info('Webhook received for page: ' . $entry['id']);
        }
    }
});
```

## 🔍 Troubleshooting

### Timezone Issues

**"Posts are being scheduled at wrong times"**
**Solution**:
1. Verify your server timezone: `date_default_timezone_get()`
2. Check WordPress timezone in **Settings** → **General**
3. The plugin uses São Paulo timezone regardless of WordPress settings
4. All times in the interface are displayed in São Paulo timezone

**"Recurring posts not publishing"**
**Solution**:
1. Check if WordPress cron is working: `wp_next_scheduled('fps_process_recurring_posts')`
2. Verify recurring schedules are active in Calendar Post
3. Check error logs for cron execution issues
4. Test with server-level cron if WordPress cron is unreliable

### Common Issues

#### "App ID not configured"
**Solution**: Enter your Facebook App ID in Settings → Facebook App

#### "No pages found"
**Causes**:
- Facebook API version mismatch (ensure v23.0)
- Not admin of any Facebook pages
- App permissions not granted
- Token expired

**Solution**:
1. Ensure you're admin of at least one Facebook page
2. Reconnect your account with proper permissions
3. Check App Review status for production apps
4. Verify Facebook Graph API v23.0 compatibility

#### "Failed to publish post"
**Causes**:
- Token expired
- Page permissions changed
- Facebook API limits
- Network connectivity

**Solution**:
1. Check connection in Settings
2. Refresh page tokens
3. Verify page admin status
4. Check error logs

#### "Token validation failed"
**Causes**:
- Page access token has expired
- Facebook app permissions changed
- Page admin status revoked
- Network connectivity issues

**Solution**:
1. Go to Settings → Account → Test Connection
2. If connection fails, disconnect and reconnect your Facebook account
3. Ensure you have admin access to all selected pages
4. Check Facebook app permissions in Facebook Developer Console
5. Review error logs for detailed information

#### "Multi posts not scheduling"
**Causes**:
- No time slots configured
- Selected date is in the past
- Insufficient available time slots
- File pairing failed

**Solution**:
1. Add time slots in the Time Slot Management section
2. Ensure selected date is today or in the future
3. Select enough available time slots for all posts
4. Check that images and text files are properly paired
5. Verify file formats (images: JPG/PNG/GIF, texts: .txt)

#### "Calendar posts not working"
**Causes**:
- WordPress cron disabled or not working
- Incorrect timezone configuration
- Recurring schedule inactive
- Facebook token expired

**Solution**:
1. Enable WordPress cron or set up server cron
2. Verify timezone settings (should be America/Sao_Paulo)
3. Check recurring schedule status in Calendar Post
4. Test Facebook connection in Settings

#### "WordPress Cron not working"
**Causes**:
- `DISABLE_WP_CRON` is true
- Server doesn't support cron
- High traffic blocking cron

**Solution**:
1. Enable WordPress cron in wp-config.php
2. Set up server-level cron job
3. Check cron execution: `wp cron event list` (if WP-CLI available)
3. Use Facebook's native scheduling

### Debug Mode

Enable debug logging:

**WordPress Debug**:
```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

**Plugin Debug**:
```php
// Add to wp-config.php
define('FPS_DEBUG', true);
define('FPS_DEBUG_TIMEZONE', true); // Extra timezone debugging
```

Check logs in:
- WordPress: `/wp-content/debug.log`
- Plugin: **Facebook Scheduler** → **Logs** (when WP_DEBUG is enabled)

### API Testing

Test your setup:
- **Graph API Explorer**: https://developers.facebook.com/tools/explorer/ (use v23.0)

2. **Access Token Debugger**: https://developers.facebook.com/tools/debug/accesstoken/
3. **App Dashboard**: https://developers.facebook.com/apps/

### Performance Optimization

For high-volume sites:

**Timezone Optimization**:
```php
// Optimize timezone operations
add_filter('fps_timezone_cache', function() {
    return true; // Enable timezone caching
});
```

```php
// Optimize database queries
add_filter('fps_posts_per_page', function() {
    return 50; // Increase posts per page
});

// Reduce log retention
add_filter('fps_log_retention_days', function() {
    return 7; // Keep logs for 7 days only
});
```

**Cron Optimization**:
```php
// Reduce cron frequency for recurring posts (default: every minute)
add_filter('fps_recurring_cron_interval', function() {
    return 'fps_every_five_minutes'; // Check every 5 minutes instead
});
```

## 🔒 Security Best Practices

### Token Security

#### Automatic Token Management
- All tokens use Facebook Graph API v23.0
- Page tokens are automatically exchanged for long-lived tokens
- Tokens are validated before each use using Facebook's debug_token endpoint
- Invalid or expired tokens are automatically refreshed
- Failed token operations are logged with detailed error messages

#### Encryption and Storage
- Compatible with PHP 8.3+ encryption standards
- Tokens are encrypted using OpenSSL AES-256-CBC
- Encryption keys are unique per installation
- Tokens are automatically refreshed before expiration
- Failed tokens are logged and removed

### Data Protection
- PHP 8.3+ compatibility with enhanced type safety
- All user inputs are sanitized
- SQL queries use prepared statements
- File uploads are validated and restricted
- CSRF protection on all forms

### Access Control
- WordPress 6.8+ capability system integration
- Only users with `manage_options` capability can access
- All AJAX requests verify nonces
- IP addresses and user agents are logged
- Failed attempts are monitored

## 📊 Database Schema

### New Tables (v2.0)
- `wp_fps_recurring_posts_log` - Recurring post execution log

### Tables Created
- `wp_fps_scheduled_posts` - Scheduled post data
- `wp_fps_page_tokens` - Encrypted page tokens
- `wp_fps_logs` - Activity logs
- `wp_fps_page_insights` - Analytics data
- `wp_fps_recurring_posts_log` - Recurring post execution tracking

### Data Retention
- Published posts: 90 days (configurable)
- Activity logs: 30 days (configurable)
- Failed posts: Kept until manually deleted
- Analytics: 1 year (configurable)
- Recurring logs: 90 days (configurable)

## 🌐 Internationalization

The plugin is translation-ready. To add your language:

1. Create translation files in `/languages/` folder
2. Use WordPress translation tools
3. All timezone displays support localization
3. Submit translations to WordPress.org

Current translations:
- English (default)
- Portuguese (included)

## 🔄 Updates & Maintenance

### Version 2.0 Changes
- **PHP 8.3+ Compatibility**: Full compatibility with PHP 8.3 and newer
- **WordPress 6.8+ Support**: Updated for latest WordPress features
- **Facebook Graph API v23.0**: Updated to latest API version
- **Timezone Management**: Dedicated São Paulo timezone handling
- **Calendar Post System**: New recurring post management
- **Enhanced Multi Scheduler**: Three pairing modes with improved UI
- **Modern UI**: Tailwind CSS-inspired design
- **Better Error Handling**: Enhanced user feedback and notifications
- **JavaScript Separation**: Complete separation of JS functionality to prevent conflicts
- **Recursion Prevention**: Built-in safeguards against JavaScript recursion errors

### Automatic Updates
- Database schema updates automatically
- Settings migrate between versions
- Backward compatibility maintained

### Manual Maintenance
- **Timezone Verification**: Check timezone settings in Calendar Post
- Clean old logs: **Settings** → **Advanced** → **Clean Logs**
- Refresh tokens: **Settings** → **Account** → **Test Connection**
- Database cleanup: Runs automatically weekly

## 📞 Support

### Getting Help
1. **Timezone Issues**: Verify all times are in São Paulo timezone (GMT-3)
2. **Cron Problems**: Check WordPress cron status and recurring schedules
3. **JavaScript Errors**: Check browser console for specific error messages
4. **Upload Issues**: Ensure media uploader is not being called multiple times
1. Check this README for solutions
2. Enable debug logging and check logs
3. Test recurring posts with Calendar Post feature
3. Test with Facebook's Graph API Explorer
4. Verify app permissions and review status

### **Common JavaScript Issues**

**"Too much recursion" Error**
- **Cause**: Event handlers being bound multiple times
- **Solution**: The plugin now uses namespaced events and proper cleanup
- **Prevention**: Each page loads only its specific JavaScript file

**Upload Button Not Working**
- **Cause**: Multiple media frame instances or conflicting event handlers
- **Solution**: Plugin now creates single-use media frames with proper cleanup
- **Debug**: Check browser console for specific error messages

**Calendar Events Not Firing**
- **Cause**: Event delegation conflicts or multiple bindings
- **Solution**: Calendar now uses dedicated event namespace `.fps-calendar`
- **Debug**: Enable debug mode to see event flow in console

### Reporting Issues
When reporting issues, include:
- Plugin version (v2.0+)
- WordPress version
- PHP version
- Timezone configuration
- Browser console errors (if JavaScript related)
- Steps to reproduce the issue
- Plugin version
- Error messages from logs
- Steps to reproduce

### **Testing Each Functionality**

**Normal Post Scheduling**
1. Go to **Facebook Scheduler** → **Schedule Post**
2. Select Facebook page
3. Enter message and optional link
4. Upload media:
   - **Carousel**: Multiple images for carousel post
   - **Single Image**: One image only
   - **Video**: Video file
5. Select date/time (HTML5 date picker)
6. Optionally enable "Share to Story"
7. Click "Schedule Post"

**Link Preview Testing**
1. Enter a URL in the link field
2. Preview should automatically load title, description, and image
3. Refresh preview button should update the preview

**Carousel Testing**
1. Click on "Carousel (Multiple)" tab
2. Select multiple images from media library
3. Images should appear in a grid with remove buttons
4. Preview should show carousel format
5. Remove individual images using X button

**Multi Post Scheduling**
1. Go to **Facebook Scheduler** → **Schedule Post Multi**
2. Select Facebook page
3. Choose upload mode:
   - **Pair by filename**: Upload image1.jpg, image1a.jpg, image1b.jpg + text1.txt
   - **Order pairing**: Upload images and texts in order
   - **Manual text**: Upload images and enter text manually
4. Drag/drop or click to upload files
5. Click "Process Files" to pair content
6. Review preview (shows single posts and carousels)
7. Select date and available time slots
8. Optionally enable "Share to Story"
9. Click "Schedule All Posts"

**Calendar Post Management**
1. Go to **Facebook Scheduler** → **Calendar Post**
2. Click "Create Recurring Times"
3. Enter time (HH:MM format)
4. Select days of week (checkboxes)
5. Save (modal should close properly)
6. View calendar with recurring times marked
7. Edit/delete/toggle times using action buttons

**Settings Tabs Testing**
1. Go to **Facebook Scheduler** → **Settings**
2. Click each tab: Facebook App, Account, Pages, Posting, Advanced
3. All tabs should open and display content properly
4. No JavaScript errors in console

### Contributing
1. Ensure PHP 8.3+ and WordPress 6.8+ compatibility
2. Follow JavaScript best practices to prevent recursion
3. Test all upload and calendar functionality
4. Use proper event namespacing
1. Fork the repository
2. Create feature branch
3. Follow WordPress coding standards
4. Add tests for new features
5. Submit pull request

## 📄 License

This plugin is licensed under GPL v2 or later.

## 🙏 Credits

- **Facebook Graph API v23.0**: Meta Platforms, Inc.
- **Facebook Graph API**: Meta Platforms, Inc.
- **WordPress**: WordPress Foundation
- **Icons**: WordPress Dashicons

---

## 📚 Developer Documentation

### Version 2.0 New Classes

#### FPS_Calendar_Manager
```php
// Create recurring schedule
$calendar_manager = new FPS_Calendar_Manager();
$schedule_id = $calendar_manager->create_recurring_schedule($schedule_data);

// Get calendar data
$calendar_data = $calendar_manager->get_calendar_data('2024-01');

// Get available time slots
$available_slots = $calendar_manager->get_available_time_slots('2024-01-15');
```

#### FPS_Multi_Scheduler
```php
// Process uploaded files
$multi_scheduler = new FPS_Multi_Scheduler($facebook_api, $calendar_manager);
$processed_files = $multi_scheduler->process_uploaded_files($files, 'images');

// Pair files
$pairs = $multi_scheduler->pair_files($images, $texts, 'filename');

// Schedule multiple posts
$result = $multi_scheduler->schedule_multiple_posts($page_id, $pairs, $date, $time_slots);
```

#### FPS_Timezone_Manager
```php
// Get current time in plugin timezone
$current_time = FPS_Timezone_Manager::get_current_time();

// Convert datetime to plugin timezone
$sp_datetime = FPS_Timezone_Manager::convert_to_plugin_timezone($datetime, 'UTC');

// Format for Facebook API
$timestamp = FPS_Timezone_Manager::format_for_facebook($datetime);

// Check if datetime is in future
$is_future = FPS_Timezone_Manager::is_future($datetime);
```
### Hooks and Filters

#### New Actions (v2.0)
```php
// Before recurring post is published
do_action('fps_before_recurring_post', $post_data, $schedule_id);

// After recurring post is published
do_action('fps_after_recurring_post', $post_id, $schedule_id, $facebook_response);

// When recurring schedule is created
do_action('fps_recurring_schedule_created', $schedule_id, $schedule_data);

// When calendar data is loaded
do_action('fps_calendar_data_loaded', $calendar_data, $month);
```

#### New Filters (v2.0)
```php
// Modify recurring post data
apply_filters('fps_recurring_post_data', $post_data, $schedule_id);

// Customize timezone handling
apply_filters('fps_timezone_settings', $timezone_settings);

// Modify multi scheduler settings
apply_filters('fps_multi_scheduler_settings', $settings);

// Customize calendar display
apply_filters('fps_calendar_display_options', $options);
```

#### Actions
```php
// Before post is scheduled
do_action('fps_before_schedule_post', $post_data);

// After post is published
do_action('fps_after_publish_post', $post_id, $facebook_response);

// When token is refreshed
do_action('fps_token_refreshed', $page_id, $new_token);
```

#### Enhanced Filters (v2.0)
```php
// Modify post data with timezone awareness
apply_filters('fps_post_data', $post_data, $timezone);

// Customize Facebook API parameters for v23.0
apply_filters('fps_api_params', $params, $endpoint, $api_version);

// Modify scheduling intervals with timezone
apply_filters('fps_cron_schedules', $schedules, $timezone);

// Customize recurring post processing
apply_filters('fps_recurring_post_interval', $interval);
```

// When token is refreshed
do_action('fps_token_refreshed', $page_id, $new_token);
```

#### Filters
```php
// Modify post data before scheduling
apply_filters('fps_post_data', $post_data, $timezone);
apply_filters('fps_post_data', $post_data);

// Customize Facebook API parameters
apply_filters('fps_api_params', $params, $endpoint);

// Modify scheduling intervals
apply_filters('fps_cron_schedules', $schedules, $timezone);
apply_filters('fps_cron_schedules', $schedules);
```

### API Reference

#### FPS_Facebook_API
```php
// Create a post (v23.0 compatible)
// Create a post
$api = new FPS_Facebook_API($token_manager);
$result = $api->create_post($page_id, $post_data);

// Get page insights
$insights = $api->get_page_insights($page_id, $metrics, $period);
$insights = $api->get_page_insights($page_id, $metrics);

// Test connection
$test = $api->test_connection($access_token);
```

#### FPS_Scheduler
```php
// Schedule a post with timezone handling
// Schedule a post
$scheduler = new FPS_Scheduler($facebook_api);
$post_id = $scheduler->schedule_post($post_data);

// Get scheduled posts
$posts = $scheduler->get_scheduled_posts($args, $timezone);
$posts = $scheduler->get_scheduled_posts($args);

// Update scheduled post
$scheduler->update_scheduled_post($post_id, $update_data);
```

#### FPS_Logger
```php
// Enhanced logging with timezone
// Log messages
FPS_Logger::info('Post scheduled successfully');
FPS_Logger::error('Failed to publish post', $context);
FPS_Logger::warning('Token expires soon');

// Get logs
$logs = FPS_Logger::get_logs($args, $timezone);
$logs = FPS_Logger::get_logs($args);
```

### Database Schema

#### fps_scheduled_posts
```sql
-- Updated schema for v2.0
CREATE TABLE wp_fps_scheduled_posts (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    page_id varchar(50) NOT NULL,
    message longtext NOT NULL,
    link varchar(500) DEFAULT '',
    image_url varchar(500) DEFAULT '',
    image_path varchar(500) DEFAULT '',
    video_url varchar(500) DEFAULT '',
    video_path varchar(500) DEFAULT '',
    scheduled_time datetime NOT NULL,
    status varchar(20) DEFAULT 'scheduled',
    timezone varchar(50) DEFAULT 'America/Sao_Paulo',
    facebook_post_id varchar(100) DEFAULT '',
    permalink varchar(500) DEFAULT '',
    error_message text DEFAULT '',
    retry_count int(11) DEFAULT 0,
    created_by bigint(20) unsigned DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at datetime DEFAULT NULL,
    PRIMARY KEY (id),
    KEY page_id (page_id),
    KEY scheduled_time (scheduled_time),
    KEY status (status),
    KEY timezone (timezone)
);
```

#### fps_recurring_posts_log (New in v2.0)
```sql
CREATE TABLE wp_fps_recurring_posts_log (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    schedule_id bigint(20) unsigned NOT NULL,
    post_date date NOT NULL,
    status varchar(20) NOT NULL,
    facebook_post_id varchar(100) DEFAULT '',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_schedule_date (schedule_id, post_date),
    KEY schedule_id (schedule_id),
    KEY post_date (post_date)
);
```

    published_at datetime DEFAULT NULL,
    PRIMARY KEY (id),
    KEY page_id (page_id),
    KEY scheduled_time (scheduled_time),
    KEY status (status)
);
```

## 🚀 Version 2.0 Migration Guide

### Automatic Migration
- Database schema updates automatically
- Existing scheduled posts are preserved
- Timezone data is added to existing posts
- No manual intervention required

### New Features Available After Update
1. **Calendar Post**: Create recurring schedules
2. **Enhanced Multi Scheduler**: Three pairing modes
3. **Timezone Management**: São Paulo timezone handling
4. **Modern UI**: Improved user interface
5. **Better Error Handling**: Enhanced notifications

### Breaking Changes
- **Minimum PHP Version**: Now requires PHP 8.3+
- **Minimum WordPress Version**: Now requires WordPress 6.8+
- **Facebook API Version**: Updated to v23.0
- **Timezone Handling**: All times now use São Paulo timezone

### Recommended Actions After Update
1. Test Facebook connection in Settings
2. Verify existing scheduled posts
3. Set up recurring schedules in Calendar Post
4. Review timezone settings
5. Test multi post scheduling with new modes

## 🔐 Security Considerations

### Version 2.0 Security Enhancements
- **PHP 8.3+ Security**: Enhanced type safety and security features
- **WordPress 6.8+ Integration**: Latest security standards
- **Facebook API v23.0**: Updated security protocols
- **Enhanced Token Validation**: Improved token security checks

### Token Management
- All tokens are encrypted using PHP 8.3+ compatible encryption
- All tokens are encrypted using AES-256-CBC
- Encryption keys are unique per installation
- Tokens are stored separately from main options
- Automatic cleanup of expired tokens

### Data Validation
- PHP 8.3+ type declarations for enhanced security
- All inputs sanitized using WordPress functions
- File uploads validated for type and size
- SQL queries use prepared statements
- CSRF protection on all forms

### Access Control
- WordPress 6.8+ capability system
- Capability checks on all admin functions
- Nonce verification on AJAX requests
- User activity logging
- IP address tracking

## 🚀 Production Deployment

### Version 2.0 Requirements
- **PHP 8.3+**: Ensure server supports PHP 8.3 or newer
- **WordPress 6.8+**: Update WordPress to 6.8 or newer
- **Facebook App**: Update to Graph API v23.0
- **SSL Certificate**: Required for Facebook API
- **Timezone Configuration**: Server should support America/Sao_Paulo timezone

### Pre-deployment Checklist
- [ ] PHP 8.3+ installed and configured
- [ ] WordPress 6.8+ installed
- [ ] Facebook App updated to Graph API v23.0
- [ ] Facebook App reviewed and approved
- [ ] SSL certificate installed and working
- [ ] WordPress and PHP versions meet requirements
- [ ] Database backups configured
- [ ] Error logging enabled
- [ ] Cron jobs working properly
- [ ] Timezone properly configured (America/Sao_Paulo)

### Performance Optimization
- Enable PHP 8.3+ OPcache for better performance
- Enable object caching (Redis/Memcached)
- Use CDN for media files
- Configure proper cron intervals
- Monitor database performance
- Set up log rotation
- Monitor timezone-related operations

### Monitoring
- Monitor PHP 8.3+ compatibility issues
- Check Facebook Graph API v23.0 responses
- Verify timezone operations
- Monitor recurring post execution
- Monitor error logs regularly
- Check token expiration dates
- Verify cron job execution
- Track API rate limits
- Monitor post success rates

---

**Version 2.0 Notes**: This plugin now requires PHP 8.3+ and WordPress 6.8+ for optimal performance and security. All scheduling operations use São Paulo timezone (America/Sao_Paulo GMT-3) for consistency. The Facebook Graph API has been updated to v23.0 for the latest features and security improvements.

**Note**: This plugin requires a Facebook Developer account and proper app configuration. Make sure to follow Facebook's Platform Policies and Terms of Service when using this plugin.

For the most up-to-date documentation and support, visit the [plugin repository](https://github.com/yourname/facebook-post-scheduler).