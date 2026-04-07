# Mailchimp User Registration Integration

[![WordPress Plugin](https://img.shields.io/badge/WordPress-Plugin-0073AA?style=for-the-badge&logo=wordpress&logoColor=white)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net/)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg?style=for-the-badge)](https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html)

A powerful and lightweight WordPress plugin that automatically syncs user registration details with **Mailchimp**. Designed specifically to bridge the gap between registration forms and email marketing, it ensures every new user is instantly added to your Mailchimp audience with the appropriate tags.

## 🚀 Key Features

- **Automated Sync**: Seamlessly transmits user details (Email, First Name, Last Name) to Mailchimp upon registration.
- **Dynamic Tagging**: Automatically applies the `User Registration` tag to new subscribers for easy segmentation.
- **Smart Data Extraction**: Uses multiple methods (Hooks, AJAX, and JavaScript) to ensure data is captured even during redirects.
- **Easy Configuration**: Dedicated settings page for API Key and List ID management.
- **Error Logging**: Comprehensive logging for troubleshooting connection or data issues.

## 🛠️ Installation

1.  **Download** the repository as a ZIP file.
2.  **Upload** the plugin to your WordPress site (`Plugins > Add New > Upload Plugin`).
3.  **Activate** the plugin through the 'Plugins' menu in WordPress.
4.  **Configure** your settings in `Settings > Mailchimp User Reg`.

## ⚙️ Configuration

To start syncing users, you'll need:
1.  **Mailchimp API Key**: Found in your Mailchimp account under `Account > Extras > API Keys`.
2.  **Audience ID**: Found in your Mailchimp account under `Audience > Settings > Audience name and campaign defaults`.

Enter these details in the plugin settings page, and you're ready to go!

## 🧪 How It Works

The plugin employs a robust multi-layered approach:
1.  **Primary Hook**: Listens for the `user_registration_after_register_user_action` from the popular User Registration plugin.
2.  **JavaScript Fallback**: Captures data on the registration success page and sends it via AJAX if the primary hook is bypassed.
3.  **Secure API**: Uses the Mailchimp V3.0 API via `wp_remote_request` for secure and reliable data transmission.

## 📄 License

This project is licensed under the GPL v2 or later.

---

*Developed by A.DEV for seamless WordPress-Mailchimp integration.*
