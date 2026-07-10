---
agent: agent
---
You are a senior WordPress plugin developer.

Create a highly configurable, production-ready WordPress plugin using:
- PHP (OOP only)
- WordPress Coding Standards (WPCS)
- Secure and scalable architecture
- Reusable classes and services
- Hooks (actions & filters)
- Nonces, sanitization, validation, and escaping

PLUGIN GOAL
Build a complete frontend user registration, login, and dashboard system with email verification and admin controls.

--------------------------------------------------
CORE FEATURES
--------------------------------------------------

1. USER REGISTRATION & LOGIN
- Frontend user registration form
- Frontend login form
- Forgot password page
- Reset password flow
- Email verification system
- User cannot access dashboard until email is verified and account is active
- Store users in WordPress users table
- Assign custom role: `os_author`

2. EMAIL VERIFICATION
- Send verification email on registration
- Verification link with secure token
- Admin can enable/disable verification requirement
- Admin can manually mark user as verified/unverified
- Verification status stored in user meta

3. USER DASHBOARD (FRONTEND)
Create a frontend dashboard accessible only to:
- Logged-in users
- Verified and active accounts

Dashboard sections:
- Overview
- Profile management
- Manage posts
- Add new post
- Change password

4. USER PROFILE FIELDS
Profile fields (editable from frontend):
- First Name
- Last Name
- Email
- Phone
- Profile Picture (media upload)
- Country
- Organization (optional)
- Website
- Social accounts (Facebook, Twitter/X, LinkedIn, etc.)
- Verification status (read-only for user)

Store all custom fields using user meta.

5. POST SUBMISSION SYSTEM
- Frontend "Add Post" form (only after login)
- Fields:
  - Post Title
  - Content (editor)
  - Category (select)
  - Tags
  - Featured Image upload
  - SEO Title (Yoast SEO meta)
  - SEO Description (Yoast SEO meta)
- Posts created as standard WordPress posts
- Compatible with Yoast SEO meta keys

6. POST PERMISSION & LIMITS
- Admin can:
  - Enable/disable post creation per user role
  - Set monthly post limit per user
- Enforce monthly post count restriction
- Show remaining post count in dashboard

7. POST APPROVAL SETTINGS
Admin settings:
- Auto-approve posts
- Manual approval (pending review)
- Per role configuration (optional)

8. ADMIN SETTINGS PANEL
Create a clean admin settings page:
- Enable/disable email verification
- Email templates:
  - New user registration (user)
  - New user notification (admin)
  - Verification email
  - Password reset email
- Dashboard page selector
- Login page selector
- Registration page selector
- Forgot password page selector
- Toggle menu visibility based on login status
- Post limits and approval settings

All pages must be generated automatically OR selectable via admin settings.

9. MENU VISIBILITY SYSTEM
- Show/hide menu items based on:
  - Logged-in status
  - User role
  - Verification status

10. SECURITY REQUIREMENTS
- Nonce verification on all forms
- Sanitize and validate all inputs
- Escape all outputs
- Secure file uploads
- Prevent unauthorized access
- Prevent direct file access

11. ARCHITECTURE & STRUCTURE
Use a clean, modular structure like:

- plugin-root/
  - assets/
  - includes/
    - Admin/
    - Frontend/
    - Auth/
    - Dashboard/
    - Emails/
    - Posts/
    - Roles/
    - Settings/
  - templates/
  - languages/
  - plugin-main-file.php

Use:
- Singleton or service container pattern where appropriate
- Separate logic from templates
- Custom helper and utility classes

12. EXTENSIBILITY
- Provide action hooks and filters
- Allow future addons
- Follow WordPress best practices

13. FINAL OUTPUT EXPECTATION
- Complete plugin code
- Well-commented
- Ready to install
- No hardcoded values
- Fully configurable from admin panel

--------------------------------------------------
IMPORTANT
--------------------------------------------------
Do NOT use procedural code.
Do NOT rely on third-party frameworks.
Do NOT break WordPress core standards.

Build this as a professional, reusable WordPress plugin suitable for real-world production use. Managable from admin.
