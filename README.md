# Required Fields Manager
**Osclass / Osclass Enterprise Plugin**

**Author:** Van Isle Web Solutions  
**Current Version:** 1.4.0  
**License:** GPL-2.0-or-later  

---

## Overview

Required Fields Manager is a server-side enforcement plugin for Osclass and Osclass Enterprise that allows administrators to:

- Define which registration fields are mandatory
- Define which listing publish/edit fields are mandatory
- Persist registration and listing form data into the user profile
- Enforce and store Seller Type (Individual / Business) consistently
- Maintain compatibility across Osclass forks (8.x, Enterprise 3.x)

The plugin exists to ensure users fill in the important parts of their profile for tax purposes and to allow better monatization control over individual/company registrations without infringing on PIPEDA and PIPA in British Columbia, Canada. You may want to check with your local privacy act to ensure this plugin complies with it before using.

---

## Core Features

### 1. Admin-Configurable Required Fields

Accessible via:

Admin → Plugins → Required Fields Manager → Configure

**Registration fields (optional enforcement):**
- Name
- Username
- Email
- Phone
- Country
- Region
- City
- City area
- Zip code
- Address
- Seller Type

**Listing fields (optional enforcement):**
- Title
- Description
- Price
- Category
- Region
- City
- Contact name & email
- Seller Type

All validation is enforced server-side and cannot be bypassed by disabling JavaScript.

---

### 2. Seller Type Enforcement (Individual / Business)

- Uses Osclass core support where available (`b_company`)
- Stores seller type redundantly in `t_user_meta` as `seller_type`
- Can be required during registration and/or listing submission
- Automatically persisted to the user profile

This enables future features such as:
- Charging businesses to post listings
- Business-only listing plans
- Filtering listings by seller type

---

---

### 3. Automatic Profile Population

Data entered during registration or listing submission is automatically written back to the user profile, including:

- Address
- City / Region / Country
- Zip
- Phone
- Seller Type

This prevents empty or partially completed profiles.

---

### 4. Fork-Safe Design

Designed to work across Osclass variants:

- Osclass 8.x
- Osclass Enterprise 3.x

Compatibility techniques:
- Multiple registration completion hooks
- Best-effort User model updates
- Direct SQL fallback for user meta
- Graceful failure (no fatal errors)

---

## File Structure

required_fields_manager/
- index.php   (plugin bootstrap, validation, hooks)
- admin.php   (admin configuration UI)
- README.md   (this file)

---

## Validation Flow

### Registration
1. Required fields validated server-side
2. Extra fields stored in session
3. Osclass creates user
4. Plugin applies stored fields to user profile
5. Seller type saved to user meta

### Listing Publish/Edit
1. Required fields validated
2. Seller type validated if enabled
3. Listing allowed or blocked accordingly

---

## Admin UI Notes

- Configuration UI lives in admin.php only
- Install, uninstall, and hooks are defined in index.php

---

## Version History

### v1.0.0
- Initial required field validation
- Registration and listing enforcement

### v1.1.x
- Improved redirects and session handling
- Better fork compatibility

### v1.2.1
- Centralized admin configuration
- Flash message handling
- Safer defaults

### v1.3.0
- Seller Type enforcement
- Automatic profile population
- Profile completeness blocking
- User meta storage for seller type
- Clean uninstall logic
- Fixed invisible admin configuration page
- Removed duplicate uninstall hooks

---

### v1.4.0 (Current)
- Removed Profile completeness blocking to comply with privacy laws

---

## Known Limitations

- No front-end field injection (theme must provide fields)
- No pricing logic yet unless used with a payment plugin or similiar
- No database schema changes (uses core tables only)

---

## Planned Enhancements

- Front-end helper for Seller Type dropdown
- Admin filtering by Seller Type
- Business-only listing plans
- Category-based required field presets

---

## Support Notes

Developed and tested on production sites including:
- nootkasoundclassifieds.com
- vancouverislandclassifieds.com

This plugin is designed to solve structural Osclass issues safely without modifying core files.
