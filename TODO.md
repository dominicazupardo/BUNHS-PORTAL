# Settings Implementation Plan

## Overview

Make settings.php fully functional with persistence in JSON config.

## Steps

1. ✅ Create `admin_account/config/settings.json` with defaults
2. ✅ Create `admin_account/settings_api.php` (load/save/reset API)
3. ✅ Update `admin_account/settings.php`:
   - PHP: Load settings, output as data attr/JSON
   - JS: AJAX to API, populate forms, save on change
4. Integrate global settings (theme CSS vars)
5. Test save/load across tabs
6. Dynamic data (logs, stats from DB)
