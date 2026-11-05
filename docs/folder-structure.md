# Folder Structure

CONTROLLERS:

```bash
ORIGINAL/
├── HITUAM/
│   ├── HITUAM01/ # Master data module
│   │   ├── HITUAMF001.php  # Application Controller
│   │   ├── HITUAMF002.php  # Menu Controller
│   │   ├── HITUAMF003.php  # Service Controller
│   │   ├── HITUAMF004.php  # Role Controller
│   │   ├── HITUAMF005.php  # User Controller
│   │   ├── HITUAMF006.php  # User Role Controller
│   │   ├── HITUAMF007.php  # Role Access Controller
│   │   ├── HITUAMF008.php  # Role Service Controller
│   │   └── HITUAMF010.php  # Other Controller
│   └── HITUAM02/ # Auth module
│       └── HITUAMF009.php  # Auth Controller
│
├── FACTWM
│   └── FACTWM01/  # Dashboard Modules
│   │   └── FACTWMC001.php  # Dashboard Controller
│   ├── FACTWM02/  # Master Data Modules
│   │   ├── FACTWMC001.php  # Master Vendor Controller
│   │   ├── FACTWMC002.php  # Master Information Controller
│   │   └── FACTWMC003.php  # Master News Controller
│   ├── FACTWM03/  # Transaction Modules
│   │   └── FACTWMC001.php  # GRN Controller
│   ├── FACTWM04/  # Report Modules
│   │
└   └── FACTWM05/  # Setting Modules
        ├── FACTWMC001.php  # Configuration Controller
        └── FACTWMC002.php  # Log Activity Controller

```

RESOURCES:

```bash
modules/
├── HITUAM01/  # User Access Management
│   ├── HITUAMF001/
│   │   ├── partials/
│   │   │   ├── HITUAMF001-01.blade.php  # Form modal user - edit & add user
│   │   │   ├── HITUAMF001-02.blade.php  # Form modal import user
│   │   │   ├── HITUAMF001-03.blade.php  # Modal - error display during import
│   │   │   └── HITUAMF001_04.blade.php  # Form modal role - edit & add role
│   │   └── HITUAMF001.blade.php  # User role view
│   │
│   ├── HITUAMF002/
│   │   └── HITUAMF002.blade.php  # Login view
│   │
│   ├── HITUAMF003/
│   │   ├── partials/
│   │   │   └── HITUAMF003_01.blade.php  # Form modal menu - edit & add menu
│   │   └── HITUAMF003.blade.php  # List menu view
│   │
│   └── HITUAMF004/
│       ├── partials/
│       │   └── HITUAMF004_01.blade.php  # Form modal service - edit & add service
│       └── HITUAMF004.blade.php  # List service view
│
├── FACTWM01/  # Dashboard Module
│
├── FACTWM02/  # Master Data Module
│
├── FACTWM03/  # Transaction Module
│
├── FACTWM04/  # Report Module
│
└── FACTWM05/  # Setting Module
```
