# OSWP News Portal Plugin

A comprehensive WordPress plugin for news portal functionality with built-in auto-updater system.

## Features

- **News Portal Management**: Full-featured news portal with custom post types and management tools
- **Auto-Updater System**: Automatic plugin updates from a remote server
- **User Authentication**: Registration, login, email verification, and password reset
- **Dashboard Management**: Admin dashboard for content and user management
- **Email System**: Integrated email logging and notification system
- **Block Editor Support**: Gutenberg blocks for post carousels and other content
- **Role Management**: Custom user roles and permissions

## Installation

1. Download the plugin zip file
2. Upload it to your WordPress plugins directory (`wp-content/plugins/`)
3. Activate the plugin through the WordPress admin dashboard
4. Configure settings in the OSWP News Portal menu

## Auto-Updater Setup

The plugin includes an automatic update system that checks for new versions from a remote server.

### Server Requirements

- Remote update server (configured in plugin settings)
- Plugin metadata JSON file
- Plugin zip files hosted on the server

### Configuration

1. Set the update server URL in plugin settings
2. Ensure the server has `plugin-info.php` endpoint
3. Host plugin zip files at the specified download URLs

### Update Process

- Plugin automatically checks for updates every 12 hours
- Updates appear in WordPress admin under Plugins > Installed Plugins
- Click "Update Now" to install new versions

## File Structure

```
oswp-news-portal/
├── oswp-news-portal.php          # Main plugin file
├── includes/                     # Core plugin classes
│   ├── Plugin.php               # Main plugin class
│   ├── Updates/                 # Auto-updater classes
│   ├── Auth/                    # Authentication system
│   ├── Admin/                   # Admin interface
│   └── ...
├── assets/                      # CSS and JS files
├── blocks/                      # Gutenberg blocks
├── templates/                   # Frontend templates
├── languages/                   # Translation files
└── README.md                    # This file
```

## Development

### Requirements

- WordPress 5.0+
- PHP 7.4+
- MySQL 5.6+

### Building Blocks

For Gutenberg blocks, navigate to each block directory and run:

```bash
npm install
npm run build
```

## Changelog

### Version 1.0.1
- Added auto-updater system
- Improved authentication flow
- Enhanced admin dashboard

### Version 1.0.0
- Initial release
- Basic news portal functionality
- User management system

## Support

For support and bug reports, please contact the development team.

## License

This plugin is licensed under the GPL v2 or later.