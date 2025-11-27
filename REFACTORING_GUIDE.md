# O365 Sync Dashboard - Modular Refactoring

## Overview

The O365 Sync Dashboard has been refactored from a monolithic 1700+ line `index.php` into a clean, modular architecture with separated concerns.

## Directory Structure

```
/msdd
  ├── index.php                      # Main entry point (refactored)
  ├── index.php.backup              # Original backup
  ├── exceptions.json               # Exception rules data
  ├── mapping.json                  # Product mapping configuration
  ├── helpers.php                   # Utility functions (monacoEditor, dd)
  ├── debug_exceptions.php          # Exception debugging tool
  │
  ├── /includes                     # Core initialization and utilities
  │   ├── config.php               # Environment, DB, API initialization
  │   ├── api_handlers.php         # POST request handlers (save_mapping, exceptions)
  │   ├── header.php               # HTML head, theme toggle, navigation
  │   └── footer.php               # Footer, Monaco editor initialization
  │
  ├── /pages                        # Page components
  │   ├── problems.php             # WHMCS missing custom fields table
  │   ├── mapping.php              # Dicker-to-WHMCS product mapping UI
  │   ├── dashboard.php            # Main O365 client sync status table
  │   └── dashboard_details.php    # Expanded row details for dashboard
  │
  ├── /assets                       # Frontend resources
  │   ├── app.js                   # Main JavaScript (exceptions, mapping, utilities)
  │   └── style.css                # Custom styles
  │
  └── /classes                      # Backend PHP classes
      ├── DB.php                   # Database connection
      ├── WHMCS.php                # WHMCS data operations
      ├── WHMCSAPI.php             # WHMCS API client
      ├── DickerAPI.php            # Dicker Data API client
      ├── Mail.php                 # Email functionality
      └── Report.php               # Report generation
```

## Architecture

### 1. Configuration Layer (`includes/config.php`)

**Responsibilities:**

- Load environment variables from `.env`
- Configure error reporting and sessions
- Initialize database connection
- Initialize API clients (WHMCS, Dicker)
- Fetch initial data

**Key Variables Exported:**

- `$db` - Database connection
- `$whmcs` - WHMCS API client
- `$dicker` - Dicker API client
- `$whmcs_data` - WHMCS O365 client data
- `$dicker_data` - Dicker subscription data
- `$problems` - Problematic clients (missing fields)

### 2. API Handlers (`includes/api_handlers.php`)

**Responsibilities:**

- Handle POST requests early in request lifecycle
- Process and return JSON responses

**Endpoints:**

- `save_mapping` - Save product mapping configuration
- `add_exception` - Create billing exceptions
- `remove_exception` - Delete billing exceptions

### 3. Header (`includes/header.php`)

**Features:**

- HTML5 doctype and meta tags
- TailwindCSS CDN
- Prism.js for syntax highlighting
- Dark/light theme toggle with localStorage
- Theme preference detection

### 4. Footer (`includes/footer.php`)

**Features:**

- Monaco Editor initialization for JSON viewing
- App.js inclusion
- Folding configuration for JSON display

### 5. Page Components (`pages/`)

#### `problems.php`

Displays WHMCS clients with missing custom fields:

- Tenant ID status
- Expiry date status
- Client ID and company name

#### `mapping.php`

Drag-and-drop interface for mapping Dicker products to WHMCS:

- Unassigned Dicker packages pool
- WHMCS product dropzones
- SortableJS integration
- Save and rematch functionality

#### `dashboard.php`

Main sync status dashboard:

- Client-by-client comparison
- WHMCS vs Dicker license counts
- Charging status indicators (undercharging/overcharging)
- Trial subscription badges
- Expandable row details
- Summary statistics

#### `dashboard_details.php`

Expanded row content (included by dashboard.php):

- Issue list
- Product matrix table
- Unmatched subscriptions
- Client-specific exceptions
- Raw JSON data viewer (Monaco editor)

### 6. Assets

#### `app.js`

**Functions:**

- `addException(clientId, msc, whmcsQty, dickerQty, reason, applyTo, subscriptionId)` - Add billing exception
- `removeException(clientId, msc, subscriptionId)` - Remove exception
- `initMappingUI()` - Initialize drag-and-drop mapping interface
- `escapeHtml(text)` - HTML escaping utility

#### `style.css`

Custom styles for the dashboard (TailwindCSS is primary framework)

## Main Entry Point (`index.php`)

The refactored `index.php` now follows a clean flow:

```php
1. Include config.php       → Environment, DB, APIs
2. Include api_handlers.php → Handle POST requests
3. Load mapping.json        → Product mapping data
4. Fetch data               → WHMCS + Dicker subscriptions
5. Process mappings         → Match products
6. Generate report          → Create sync status report
7. Check cron mode          → Email report if ?cron=1
8. Include header.php       → Start HTML output
9. Include page components  → Render UI sections
10. Include footer.php      → Close HTML, init Monaco
11. Disconnect DB           → Clean up
```

## Key Features

### Theme Management

- Automatic dark/light mode detection
- Manual toggle button
- Persisted in localStorage
- System preference awareness

### Exception Management

- Client-specific exceptions
- Global/unmatched subscription exceptions
- Duplicate detection
- JSON file-based storage

### Product Mapping

- Visual drag-and-drop interface
- Real-time updates
- Backup on save (timestamped)
- JSON validation

### Sync Status

- Quantity mismatch detection
- Undercharging/overcharging indicators
- Trial subscription tracking
- Tenant ID validation

### Cron Integration

- `?cron=1` parameter for automated runs
- Email reports for issues
- JSON response output

## Usage

### Normal Web Access

Navigate to: `https://your-domain.com/tools/msdd/`

### Cron Job

```bash
curl "https://your-domain.com/tools/msdd/?cron=1"
```

### Adding Exceptions

1. Click on a client row to expand details
2. Find mismatched product
3. Click "Add Exception" button
4. Page reloads with exception applied

### Mapping Products

1. Scroll to "Dicker to WHMCS Product Mapping" section
2. Drag Dicker packages from left pool
3. Drop into corresponding WHMCS product box
4. Click "Save Mapping"
5. Page reloads with new mappings

## Development

### Adding a New Page

1. Create `pages/your_page.php`
2. Include in `index.php`: `require_once __DIR__ . '/pages/your_page.php';`
3. Access shared variables: `$report`, `$whmcs_data`, `$dicker_data`, `$problems`

### Adding New API Endpoint

1. Open `includes/api_handlers.php`
2. Add POST action handler
3. Return JSON response and exit

### Modifying Theme

- Edit `includes/header.php` for theme toggle logic
- TailwindCSS dark mode uses `dark:` prefix classes
- Theme state stored in `localStorage.theme`

## Dependencies

### PHP (7.4+)

- No composer packages required
- Built-in PHP functions only

### JavaScript

- **TailwindCSS** (CDN) - UI framework
- **Monaco Editor** (CDN) - JSON viewer/editor
- **SortableJS** (CDN) - Drag-and-drop
- **Prism.js** (CDN) - Syntax highlighting

### External APIs

- WHMCS API
- Dicker Data API

## Configuration

Environment variables in `.env`:

```
DB_HOST=localhost
DB_DBNAME=database_name
DB_USER=db_user
DB_PASS=db_password

WHMCS_Url=https://whmcs.example.com
WHMCS_Identifier=api_identifier
WHMCS_Secret=api_secret
WHMCS_api_access_key=access_key

DICKER_url=https://api.dickerdata.com.au
DICKER_access_token=your_token

MAIL_TO=admin@example.com
MAIL_SUBJECT=O365 Sync Issues
```

## Benefits of Refactoring

1. **Maintainability** - Each component has a single responsibility
2. **Reusability** - Page components can be reused in different contexts
3. **Testability** - Isolated components easier to test
4. **Scalability** - Easy to add new pages or features
5. **Readability** - Clear separation of concerns
6. **Performance** - API handlers exit early, no unnecessary HTML rendering for AJAX requests

## Future Enhancements

- Add routing for multi-page navigation
- Implement user authentication
- Create separate exceptions management page
- Add API documentation
- Implement automated testing
- Add logging system
- Create admin settings page

## Changelog

### Version 2.0 (Modular Refactor)

- ✅ Split 1700-line index.php into modular components
- ✅ Created `/includes`, `/pages`, `/assets` directory structure
- ✅ Extracted configuration to `config.php`
- ✅ Separated API handlers to `api_handlers.php`
- ✅ Created reusable header and footer
- ✅ Modularized page components (problems, mapping, dashboard)
- ✅ Extracted JavaScript to `app.js`
- ✅ Improved code organization and maintainability

---

**Last Updated:** November 28, 2025
