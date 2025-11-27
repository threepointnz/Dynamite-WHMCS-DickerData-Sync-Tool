# O365 Sync Dashboard - Architecture Diagram

## Request Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                          Browser / Cron Job                          │
└────────────────────────────────┬────────────────────────────────────┘
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────────┐
│                            index.php                                 │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │  1. Load Environment & Configuration                          │ │
│  │     ├─ includes/config.php                                    │ │
│  │     │   ├─ Load .env variables                                │ │
│  │     │   ├─ Initialize DB connection                           │ │
│  │     │   ├─ Initialize WHMCS API                               │ │
│  │     │   └─ Initialize Dicker API                              │ │
│  │     │                                                          │ │
│  │  2. Handle API Requests (if POST)                             │ │
│  │     ├─ includes/api_handlers.php                              │ │
│  │     │   ├─ save_mapping → JSON response → EXIT                │ │
│  │     │   ├─ add_exception → JSON response → EXIT               │ │
│  │     │   └─ remove_exception → JSON response → EXIT            │ │
│  │     │                                                          │ │
│  │  3. Fetch Data                                                │ │
│  │     ├─ $dicker_data = $dicker->getSubscriptionDetails()       │ │
│  │     ├─ $whmcs_data = $whmcs->getO365Clients()                 │ │
│  │     └─ $problems = $whmcs->getProblematicClients()            │ │
│  │                                                                │ │
│  │  4. Process Mappings & Generate Report                        │ │
│  │     ├─ Load mapping.json                                      │ │
│  │     ├─ Match products                                         │ │
│  │     └─ $report = Report->generate()                           │ │
│  │                                                                │ │
│  │  5. Check Cron Mode                                           │ │
│  │     └─ if (?cron=1) → Send Email → JSON response → EXIT       │ │
│  │                                                                │ │
│  │  6. Render HTML Pages                                         │ │
│  │     ├─ includes/header.php (Theme, Navigation)                │ │
│  │     ├─ pages/problems.php (Custom Fields)                     │ │
│  │     ├─ pages/mapping.php (Product Mapping)                    │ │
│  │     ├─ pages/dashboard.php (Sync Status)                      │ │
│  │     │   └─ pages/dashboard_details.php (Expanded Rows)        │ │
│  │     └─ includes/footer.php (Scripts, Monaco)                  │ │
│  └───────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────┘
                                 │
                                 ▼
                   ┌─────────────────────────────┐
                   │   Browser Renders Response   │
                   │   - Dark/Light Theme         │
                   │   - Interactive Dashboard    │
                   │   - Drag & Drop Mapping      │
                   │   - Exception Management     │
                   └─────────────────────────────┘
```

## Component Architecture

```
┌────────────────────────────────────────────────────────────────────┐
│                          PRESENTATION LAYER                         │
├────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐            │
│  │ header.php   │  │ problems.php │  │ mapping.php  │            │
│  │              │  │              │  │              │            │
│  │ - HTML Head  │  │ - WHMCS      │  │ - Drag &     │            │
│  │ - Theme      │  │   Custom     │  │   Drop       │            │
│  │   Toggle     │  │   Fields     │  │ - Product    │            │
│  │ - Navigation │  │ - Validation │  │   Matching   │            │
│  └──────────────┘  └──────────────┘  └──────────────┘            │
│                                                                     │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐            │
│  │dashboard.php │  │dashboard_    │  │ footer.php   │            │
│  │              │  │details.php   │  │              │            │
│  │ - Sync       │  │              │  │ - Monaco     │            │
│  │   Status     │  │ - Matrix     │  │   Editor     │            │
│  │ - Client     │  │ - Exceptions │  │ - Scripts    │            │
│  │   Table      │  │ - Details    │  │ - App.js     │            │
│  └──────────────┘  └──────────────┘  └──────────────┘            │
│                                                                     │
└────────────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────────────┐
│                         APPLICATION LAYER                           │
├────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │ index.php - Main Controller                                   │ │
│  │                                                                │ │
│  │  - Orchestrates request flow                                  │ │
│  │  - Loads configuration                                        │ │
│  │  - Delegates to handlers                                      │ │
│  │  - Includes page components                                   │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                                                                     │
│  ┌─────────────────────┐  ┌──────────────────────────────────┐   │
│  │ config.php          │  │ api_handlers.php                  │   │
│  │                     │  │                                    │   │
│  │ - Environment       │  │ - save_mapping (POST)             │   │
│  │ - Database          │  │ - add_exception (POST)            │   │
│  │ - API Clients       │  │ - remove_exception (POST)         │   │
│  │ - Initialization    │  │                                    │   │
│  └─────────────────────┘  └──────────────────────────────────┘   │
│                                                                     │
└────────────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────────────┐
│                         BUSINESS LOGIC LAYER                        │
├────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌────────────┐  ┌────────────┐  ┌────────────┐  ┌────────────┐ │
│  │ WHMCS.php  │  │DickerAPI   │  │Report.php  │  │ Mail.php   │ │
│  │            │  │.php        │  │            │  │            │ │
│  │ - Get O365 │  │            │  │ - Generate │  │ - Send     │ │
│  │   Clients  │  │ - Get      │  │   Sync     │  │   Alerts   │ │
│  │ - Get      │  │   Subs     │  │   Report   │  │ - Format   │ │
│  │   Problems │  │ - Match    │  │ - Compare  │  │   Email    │ │
│  │ - Products │  │   Products │  │   Data     │  │            │ │
│  └────────────┘  └────────────┘  └────────────┘  └────────────┘ │
│                                                                     │
└────────────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────────────┐
│                           DATA ACCESS LAYER                         │
├────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌────────────┐  ┌────────────┐  ┌────────────┐  ┌────────────┐ │
│  │  DB.php    │  │ WHMCSAPI   │  │mapping.json│  │exceptions  │ │
│  │            │  │ .php       │  │            │  │.json       │ │
│  │ - MySQL    │  │            │  │ - Product  │  │            │ │
│  │   Connect  │  │ - REST API │  │   Maps     │  │ - Billing  │ │
│  │ - Query    │  │   Client   │  │ - Configs  │  │   Rules    │ │
│  │ - Fetch    │  │            │  │            │  │            │ │
│  └────────────┘  └────────────┘  └────────────┘  └────────────┘ │
│                                                                     │
└────────────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────────────┐
│                          FRONTEND ASSETS                            │
├────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ app.js - JavaScript Application                             │  │
│  │                                                              │  │
│  │  ┌─────────────────┐  ┌─────────────────┐  ┌────────────┐ │  │
│  │  │ addException()  │  │ initMappingUI() │  │escapeHtml()│ │  │
│  │  │ removeException │  │ collectMapping()│  │            │ │  │
│  │  │                 │  │ SortableJS      │  │            │ │  │
│  │  └─────────────────┘  └─────────────────┘  └────────────┘ │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ External Libraries (CDN)                                     │  │
│  │                                                              │  │
│  │  - TailwindCSS (Styling)                                    │  │
│  │  - Monaco Editor (JSON Viewer)                              │  │
│  │  - SortableJS (Drag & Drop)                                 │  │
│  │  - Prism.js (Syntax Highlighting)                           │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                     │
└────────────────────────────────────────────────────────────────────┘
```

## Data Flow

```
┌─────────────┐
│   Browser   │
└──────┬──────┘
       │
       │ HTTP Request
       │
       ▼
┌─────────────────────────────────────────────────────────────┐
│                       index.php                              │
│                                                              │
│  ┌──────────────┐         ┌──────────────┐                 │
│  │  config.php  │────────▶│  Database    │                 │
│  └──────────────┘         └──────────────┘                 │
│         │                         │                          │
│         │                         │                          │
│         ▼                         ▼                          │
│  ┌──────────────┐         ┌──────────────┐                 │
│  │ WHMCS API    │◀───────▶│  WHMCS.php   │                 │
│  └──────────────┘         └──────────────┘                 │
│         │                         │                          │
│         │                         ▼                          │
│         │                  ┌──────────────┐                 │
│         │                  │  Report.php  │                 │
│         │                  └──────────────┘                 │
│         │                         │                          │
│         ▼                         ▼                          │
│  ┌──────────────┐         ┌──────────────┐                 │
│  │ Dicker API   │◀───────▶│DickerAPI.php │                 │
│  └──────────────┘         └──────────────┘                 │
│         │                         │                          │
│         │                         ▼                          │
│         │                  ┌──────────────┐                 │
│         └─────────────────▶│    $report   │                 │
│                            └──────────────┘                 │
│                                    │                         │
│                                    ▼                         │
│                            ┌──────────────┐                 │
│                            │ Page         │                 │
│                            │ Components   │                 │
│                            │              │                 │
│                            │ - problems   │                 │
│                            │ - mapping    │                 │
│                            │ - dashboard  │                 │
│                            └──────────────┘                 │
│                                    │                         │
└────────────────────────────────────┼─────────────────────────┘
                                     │
                                     │ HTML Response
                                     │
                                     ▼
                              ┌─────────────┐
                              │   Browser   │
                              │  (Rendered) │
                              └─────────────┘
```

## File Dependencies

```
index.php
├── includes/config.php
│   ├── .env (environment variables)
│   ├── classes/DB.php
│   ├── classes/WHMCS.php
│   ├── classes/WHMCSAPI.php
│   ├── classes/DickerAPI.php
│   ├── classes/Mail.php
│   └── classes/Report.php
│
├── includes/api_handlers.php
│   ├── mapping.json (read/write)
│   └── exceptions.json (read/write)
│
├── includes/header.php
│   ├── TailwindCSS (CDN)
│   └── Prism.js (CDN)
│
├── pages/problems.php
│   └── $problems (from config)
│
├── pages/mapping.php
│   ├── $mappingContent (from index)
│   └── SortableJS (CDN)
│
├── pages/dashboard.php
│   ├── $report (from index)
│   ├── pages/dashboard_details.php
│   └── helpers.php (monacoEditor function)
│
└── includes/footer.php
    ├── Monaco Editor (CDN)
    └── assets/app.js
```

---

**Architecture Type:** Modular MVC-inspired  
**Pattern:** Front Controller with Component Inclusion  
**Separation:** Presentation / Application / Business / Data Access
