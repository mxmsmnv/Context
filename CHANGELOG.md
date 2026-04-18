# Changelog

All notable changes to the Context module will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.4.0] - 2026-04-18

### Changed

#### Complete UIkit Design System Compliance

Redesigned entire admin dashboard to follow ProcessWire's AdminThemeUikit design system standards:

**Removed Custom CSS:**
- Eliminated all custom CSS classes and inline styles
- Replaced with native UIkit classes and ProcessWire CSS variables
- Dashboard now respects light/dark theme switching automatically

**Cards:**
- `uk-card uk-card-default uk-margin` - consistent card styling
- `uk-card-header` / `uk-card-body` / `uk-card-footer` - proper structure
- Uses `var(--pw-blocks-background)` and `var(--pw-border-color)`

**Tables:**
- `uk-table uk-table-divider uk-table-small uk-table-hover` - native UIkit tables
- Automatic ProcessWire theming via CSS variables
- Removed custom `.context-config-table` class

**Status Badges:**
- `.context-status-badge.enabled` - uses `var(--pw-alert-success)`
- `.context-status-badge.disabled` - uses `var(--pw-alert-danger)`
- Automatic theme adaptation

**TOON Banner:**
- Converted to `uk-alert uk-alert-success`
- `uk-flex uk-flex-middle` layout
- Removed inline styles

**Buttons:**
- `uk-button-group` for button grouping
- `uk-button uk-button-primary` / `uk-button-default`
- Native ProcessWire button styling

**Metrics Cards:**
- Pure UIkit grid: `uk-grid-small uk-child-width-1-6@m`
- Success color: `var(--pw-alert-success)`
- Primary color: `var(--pw-main-color)`

**Quick Tips:**
- Clean `uk-card` structure
- `uk-text-success` for icons
- No custom styling needed

### Fixed

#### Duplicate formatBytes() Method
- **Fixed:** Removed duplicate `formatBytes()` method definition at line 4791 (reported by @matjazp)
- **Impact:** Cleaner code, no functional changes
- **Credit:** Thank you @matjazp for the bug report!

### Technical Details

**CSS Variables Used:**
- `--pw-text-color` - Main text color (light/dark adaptive)
- `--pw-muted-color` - Muted text
- `--pw-main-color` - Primary brand color
- `--pw-border-color` - Borders
- `--pw-blocks-background` - Card backgrounds
- `--pw-alert-success` - Success states
- `--pw-alert-danger` - Error states
- `--pw-alert-warning` - Warning states

**Why This Matters:**
- Dashboard now follows ProcessWire design standards
- Automatic light/dark theme support
- Consistent with other ProcessWire admin interfaces
- Easier maintenance - no custom CSS to update
- Better accessibility through native UIkit components

---

## [1.3.5] - 2026-04-18

### Added

#### Full ProcessWire API Access via CLI

**New Commands:**
- `--context-eval 'CODE'` - Execute single-line PHP code with full ProcessWire API access
- `--context-stdin` - Execute multi-line PHP code from stdin

**Why this is a game changer:**
- AI agents can now **create, read, update, and delete** anything in ProcessWire
- Full access to `$pages`, `$templates`, `$fields`, `$modules`, `$config`, etc.
- Same power as AgentTools module, but integrated with Context
- Perfect for Claude Code, Cursor, Windsurf automation

**Examples:**

Single-line commands:
```bash
# Count pages
php index.php --context-eval 'echo $pages->count() . " pages\n";'

# Get specific page
php index.php --context-eval '$p = $pages->get(1); echo $p->title;'
```

Multi-line scripts:
```bash
echo '
$p = new Page();
$p->template = "basic-page";
$p->parent = $pages->get("/");
$p->title = "New Page";
$p->save();
echo "Created: " . $p->url . "\n";
' | php index.php --context-stdin
```

**Available API variables in eval/stdin:**
- `$pages`, `$templates`, `$fields`, `$modules`
- `$config`, `$users`, `$session`, `$input`
- `$sanitizer`, `$database`, `$cache`, `$log`, `$files`
- `$context` (Context module instance)

**Security Note:**
These commands execute with full ProcessWire privileges. Use only in development environments or with proper security measures.

### Fixed

#### Duplicate formatBytes() Method
- **Fixed:** Removed duplicate `formatBytes()` method definition (reported by @matjazp)
- **Impact:** No functional change, just cleaner code

### Changed

#### Documentation Updates
- **AGENTS.md** - Added Scenario 4 with comprehensive API access examples
- **CHANGELOG** - Enhanced with detailed eval/stdin usage examples

### Technical Details

**New Methods:**
- `cliEval($argv)` - Execute PHP code with full PW API access
  - Extracts all ProcessWire API variables
  - Wraps code in ProcessWire namespace
  - Comprehensive error handling with line numbers
- `cliStdin()` - Execute multi-line code from stdin
  - Reads from `php://stdin`
  - Same API access as eval
  - Perfect for complex scripts

**Modified Methods:**
- `handleCLI()` - Added `eval` and `stdin` cases
- `cliHelp()` - Updated with API Access Commands section and examples

**Pattern:**
Both methods use identical approach:
1. Make all PW API variables available via `$this->pages`, `$this->templates`, etc.
2. Add ProcessWire namespace wrapper: `namespace ProcessWire;`
3. Execute with `eval()`
4. Catch and display errors with context

---

## [1.3.0] - 2026-04-06

### Added

#### CLI Commands for AI Agents
- **New feature:** Complete command-line interface for AI coding agents
- **Export command:** `php index.php --context-export` - Full export from CLI
- **Query commands:** Interactive querying of templates, fields, and pages
- **Stats command:** `php index.php --context-stats` - Quick site statistics
- **Help command:** `php index.php --context-help` - CLI documentation

**Available CLI Commands:**

**Export:**
```bash
php index.php --context-export              # Full export (JSON + TOON)
php index.php --context-export --toon-only  # TOON format only
php index.php --context-export --json-only  # JSON format only
```

**Query:**
```bash
php index.php --context-query templates           # List all templates
php index.php --context-query fields              # List all fields  
php index.php --context-query pages [selector]    # List pages with optional selector
```

**API Access:**
```bash
php index.php --context-eval 'CODE'               # Execute PHP code with PW API
echo 'CODE' | php index.php --context-stdin       # Multi-line code from stdin
```

**Stats:**
```bash
php index.php --context-stats                     # Show statistics
```

**Why CLI?**
- AI agents (Claude Code, Cursor, Windsurf) can export context without admin access
- **Full API access** - AI can query, create, modify pages/templates/fields directly
- Query specific data instead of loading all files (saves tokens)
- Automation via cron jobs or CI/CD pipelines
- Faster than navigating admin interface

#### API Variable Registration
- **New:** `$context` API variable available throughout ProcessWire
- **Usage:** `$context = wire('context');` instead of `$modules->get('Context')`
- **Methods:** `$context->executeExport()`, `$context->getContextPath()`

#### AGENTS.md Documentation
- **New file:** Complete documentation for AI coding agents
- **Content:** CLI commands reference, workflow examples, best practices
- **Location:** `/site/modules/Context/AGENTS.md`
- **Purpose:** Tell Claude Code/agents to read this file for instructions

### Changed

#### Module Autoload
- **Changed:** `autoload` set to `true` (was `false`)
- **Reason:** Required for CLI command handling
- **Impact:** Module now loads on every request (minimal performance impact)

### Technical Details

**New Methods:**
- `ready()` - Handles CLI command routing
- `handleCLI($action, $argv)` - CLI command dispatcher
- `cliExport($argv)` - Export via CLI with progress indicators
- `cliStats()` - Display site statistics
- `cliQuery($argv)` - Query templates/fields/pages
- `cliQueryTemplates()` - List all templates
- `cliQueryFields()` - List all fields
- `cliQueryPages($argv)` - Query pages with selectors
- `cliEval($argv)` - **Execute PHP code with full PW API access**
- `cliStdin()` - **Execute multi-line code from stdin**
- `cliHelp()` - Display CLI help
- `exportAll($aiPath)` - Unified export method for CLI and admin
- `getDirectorySize($path)` - Calculate export directory size
- `formatBytes($bytes)` - Human-readable file sizes

**Modified Methods:**
- `init()` - Now registers `$context` API variable

**Files Added:**
- `AGENTS.md` - AI agent integration guide

---

## [1.2.0] - 2026-04-06

### Fixed

#### Hardcoded Export Paths in Prompts
- **Fixed:** All hardcoded `/site/assets/context/` paths replaced with dynamic `export_path` (reported by @szabesz)
- **Affected files:** All prompt templates (project-context.md, create-template.md, create-api.md)
- **Why it matters:** Users with custom export paths (e.g., `/home/user/context-exports/`) now get correct file references
- **Changed methods:**
  - `generateProjectContext()` - Added dynamic `$contextPath` variable
  - `generateCreateTemplatePrompt()` - Replaced hardcoded paths
  - `generateCreateApiPrompt()` - Replaced hardcoded paths

**Before:**
```markdown
- `/site/assets/context/templates.json` - existing field patterns
```

**After:**
```markdown
- `/home/user/context-exports/templates.json` - existing field patterns
```

#### Prompts Description Clarity
- **Improved:** Clarified that `prompts/` contains templates for manual use, not auto-loaded by agents (feedback from @szabesz)
- **SKILL.md:** Changed description to "Prompt templates for manual LLM/agent use (not auto-loaded by agents)"
- **README.md:** Changed to "Prompt templates for manual use (optional)"
- **Why:** Prevents AI agents from mistakenly treating prompt templates as project data

### Added

#### "Go to Module's Settings" Button
- **New button** on dashboard for quick access to module configuration (requested by @szabesz)
- **Location:** Next to "Re-Export Context for AI" button
- **Why:** Eliminates need to navigate through admin menus when settings tab is closed

### Changed

#### Project Summary Preservation
- **Changed:** `project-summary.md` is no longer overwritten on re-export (feedback from @psy)
- **Behavior:** File only created if it doesn't exist
- **Why:** Preserves user's session history and change tracking
- **Implementation:** Added `file_exists()` check before writing template

**What this means:**
- First export: Creates template
- Subsequent exports: Preserves your updated content
- To reset: Manually delete the file

---

## [1.1.9] - 2026-03-29

### Fixed

#### Cline Skill Name Compatibility
- **Fixed:** SKILL.md name must match folder name for Cline (reported by @szabesz)
- **Changed:** `name: ProcessWire Context - example.com` → `name: context`
- **Reason:** Cline requires exact folder name match to activate skill
- **Now works:** Cline automatically discovers and loads the context skill

### Changed

#### Improved Project Summary Template
- **Updated:** Better template format (improved by @psy)
- **Rules embedded in file** - AI sees them every update (not separate "How to Use" section)
- **"Update in place" mode** - Preserves history instead of overwriting
- **Boundary protection** - `#### DO NOT UPDATE ABOVE THIS LINE ####` prevents AI from modifying instructions
- **"Remove duplication"** - AI automatically cleans up redundant entries
- **Shorter, more actionable** - Concise bullet points

**What improved:**
- Rules are now at the top of the file → AI sees them every time
- File Instructions section guides AI behavior
- Boundary line protects template structure
- Update mode preserves session history
- Cleaner, more focused format

---

## [1.1.8] - 2026-03-21

### Added

#### SKILL.md Auto-Generation for AI Agents
- **New feature:** Automatic SKILL.md generation for AI coding agents (requested by @szabesz)
- **Supported agents:** Cline (PHPStorm/VSCode), Junie, and other MCP-compatible agents
- **Format:** Structured markdown following Cline's skill format specification
- **Content:** Lists all exported files with descriptions, usage examples, and integration notes
- **Configuration:** Enable/disable via checkbox in module settings (enabled by default)

**What is SKILL.md?**
AI coding agents like Cline and Junie use SKILL.md files to understand how to use external knowledge sources. This file:
- Describes when to use the skill
- Lists all available resource files
- Provides usage examples
- Explains the TOON format benefits
- Shows best practices for querying the context

**Configuration:**
```
Setup → Modules → Context → Configure → Export Formats → Generate SKILL.md for AI Agents
```

**Use case:**
- Export path: `.agents/skills/context/` (for Cline in PHPStorm)
- Export path: `.junie/skills/docs/` (for Junie)
- SKILL.md auto-generated in export directory
- AI agents can now discover and use ProcessWire context automatically

#### Project Summary Template
- **New file:** `prompts/project-summary.md` template auto-generated
- **Purpose:** Helps AI agents maintain context between coding sessions
- **Format:** Structured markdown with project state, decisions, issues, next steps
- **Usage:** Ask AI to update this file at end of each session for session continuity

**How it works:**
1. Template is auto-created in `prompts/project-summary.md`
2. At end of coding session: Ask AI to update the file with current state
3. Next session: AI reads Context exports + project-summary.md
4. AI understands exactly where you left off

**Template includes:**
- Project description
- Current state
- Decisions made
- Known issues
- What was tried
- Constraints
- Next steps
- What NOT to do

### Fixed

#### FieldtypeQRCode Compatibility
- **Fixed:** Error when exporting field definitions for FieldtypeQRCode (reported by @psy)
- **Root cause:** Some fieldtypes use `.info.php` instead of `getModuleInfo()` method
- **Solution:** Added `method_exists()` check before calling `getModuleInfo()`
- **Affected location:** Line ~1928 in `exportFieldDefinitions()`

**Before (ERROR):**
```php
'label' => $field->type->getModuleInfo()['title'] ?? $className,
// Fatal error if getModuleInfo() doesn't exist
```

**After (FIXED):**
```php
$label = $className;
if(method_exists($field->type, 'getModuleInfo')) {
    $moduleInfo = $field->type->getModuleInfo();
    $label = $moduleInfo['title'] ?? $className;
}
```

### Technical Details

**New Configuration:**
- `generate_skill_md` setting (default: `1` - enabled)
- Checkbox in "Export Formats" section

**New Methods:**
- `createSkillMd()` - Generates SKILL.md content with:
  - Dynamic file list based on enabled exports
  - TOON format files detection
  - Subdirectory scanning (metadata/, api/, snippets/, prompts/, samples/)
  - Usage examples and best practices
  - Site-specific metadata (hostname, etc.)
- `generateProjectSummaryTemplate()` - Creates project-summary.md template with:
  - Structured format for session continuity
  - Instructions on how to use
  - Sections for state, decisions, issues, next steps

**Modified Methods:**
- `createPrompts()` - Now generates project-summary.md template
- `executeExport()` - Calls `createSkillMd()` if enabled
- `exportFieldDefinitions()` - Added `method_exists()` safety check

**Files Created:**
- `SKILL.md` in export directory (if enabled)
- `prompts/project-summary.md` template (if AI Prompts enabled)

**Documentation:**
- Added "Best Practices" section to README
- Session continuity workflow with project-summary.md template (suggested by @psy)
- AI coding agents setup guide (Cline, Junie)
- File upload strategy for optimal token usage

---

## [1.1.7] - 2026-03-21

### Added

#### CSS Framework Selection
- **New setting:** Manual CSS framework selection (requested by @psy)
- **Options:** Auto-detect, Tailwind CSS, Bootstrap, UIkit, Vanilla CSS, None
- **Default:** Auto-detect (scans templates and package.json)
- **Use case:** Override auto-detection for custom/nested CSS or when detection is incorrect

**Configuration:**
```
Setup → Modules → Context → Configure → CSS Framework
```

**Why this matters:**
- Affects code examples and snippets generated for AI
- Custom CSS users can now select "Vanilla CSS" instead of auto-detected Tailwind
- More accurate context for AI when generating code

### Changed

#### Frontend Stack Detection
- `detectFrontendStack()` now checks manual CSS setting before auto-detection
- New helper method `detectJavaScriptFrameworks()` for detecting JS frameworks when CSS is manual
- JavaScript frameworks (Alpine.js, HTMX, jQuery) are still auto-detected even when CSS is manually set

### Technical Details

**New Configuration:**
- `css_framework` setting (default: `auto`)
  - Options: auto, tailwind, bootstrap, uikit, vanilla, none
  - Accessible via module configuration UI

**Modified Methods:**
- `detectFrontendStack()` - Now checks manual CSS setting before auto-detection
- `detectJavaScriptFrameworks()` - New helper method for detecting JS frameworks when CSS is manual

**How it works:**
- When `css_framework` is set to anything other than `auto`:
  - Uses the manually selected CSS framework
  - Still auto-detects JavaScript frameworks (Alpine.js, HTMX, jQuery, etc.)
  - Combines both in the final stack string
- When `css_framework` is `auto` (default):
  - Original behavior - detects everything automatically

---

## [1.1.6] - 2026-03-14

### Added

#### Configurable Export Path
- **New setting:** Export Path configuration in module settings (requested by @szabesz)
- **Default path:** `site/assets/cache/context/` (protected by ProcessWire root .htaccess)
- **Absolute paths supported:** `/home/user/context-exports/` (outside web root - most secure)
- **Relative paths supported:** 
  - `site/assets/cache/context/` (default, ProcessWire protected)
  - `.junie/skills/docs` (for Junie AI agent in PHPStorm)
  - `../../context-exports/` (relative to PW root)

**Configuration:**
```
Setup → Modules → Context → Configure → Export Path
```

**Examples:**
- `site/assets/cache/context/` (default, ProcessWire .htaccess protected)
- `/home/user/context-exports/` (absolute path, outside web root - recommended)
- `.junie/skills/docs` (for Junie AI integration)
- `../../context-exports/` (two levels up from PW root)

### Security

#### Automatic .htaccess Protection
- **Critical fix:** Export directory now protected by default (reported by @csaggo in GitHub issue #1)
- **Default path changed:** From `site/assets/context/` to `site/assets/cache/context/`
- **Triple protection strategy:**
  1. **ProcessWire native:** `/site/assets/cache/` blocked in root .htaccess
  2. **Local .htaccess:** Auto-created in export directory as fallback
  3. **Outside web root:** Absolute paths like `/home/user/context-exports/` (recommended for Nginx)

**.htaccess content:**
```apache
# Deny access to Context exports
# Remove this file if you need public access
Deny from all
```

**Important for Nginx users:**
- `.htaccess` files don't work on Nginx servers
- **Recommended solution:** Use absolute path outside web root (e.g. `/home/user/context-exports/`)
- **Alternative:** Add to nginx config:
  ```nginx
  location ~ ^/site/assets/cache/context/ {
      deny all;
      return 403;
  }
  ```

**Security notes:**
- Versions < 1.1.6 used `site/assets/context/` which was NOT protected
- Version 1.1.6+ uses `site/assets/cache/context/` (ProcessWire protected)
- For maximum security on Nginx: use absolute path outside web root
- `.htaccess` created as backup protection for Apache servers

### Changed

#### Path Handling
- `getContextPath()` now supports absolute paths (starting with `/`)
- Absolute paths: Used as-is without modification
- Relative paths: Added to ProcessWire root directory
- Path normalization: Handles `../` correctly for relative paths

**Path Resolution Examples:**
- `/home/user/exports/` → `/home/user/exports/` (absolute, used as-is)
- `site/assets/cache/context/` → `/var/www/site/assets/cache/context/` (relative to PW root)
- `../../exports/` → `/var/www/../exports/` → `/var/exports/` (relative, normalized)

#### Directory Creation
- `ensureFolder()` creates `.htaccess` automatically in ALL directories
- Protection applied even if directory already exists
- Subdirectories (samples/, api/, prompts/) get individual .htaccess files

### Fixed

#### Absolute Path Support
- Fixed: Absolute paths were incorrectly treated as relative
- Fixed: Leading `/` was stripped, causing paths to be relative to PW root
- Solution: Check for leading `/` before path normalization

### Technical Details

**New Configuration:**
- `export_path` setting (default: `site/assets/cache/context/`)
- Supports both absolute and relative paths
- Accessible via module configuration UI

**Modified Methods:**
- `getContextPath()` - Detects absolute vs relative paths, handles both correctly
- `ensureFolder()` - Creates .htaccess even if folder exists

**Files Created:**
- `.htaccess` in export directory (automatic, Apache only)
- `.htaccess` in all subdirectories (samples/, api/, etc.)

**Migration for Existing Users:**
1. Update module to v1.1.6
2. **Apache servers:** Change path to `site/assets/cache/context/` in settings (protected by ProcessWire)
3. **Nginx servers:** Use absolute path like `/home/user/context-exports/` (outside web root)
4. Re-export to create new directory structure
5. Delete old `/site/assets/context/` directory if it exists

**Server-Specific Recommendations:**
- **Apache:** `site/assets/cache/context/` (default, works out of box)
- **Nginx:** `/home/user/context-exports/` (absolute path outside htdocs)
- **CloudPanel/Similar:** Absolute path recommended as .htaccess may not work

---

## [1.1.5] - 2026-03-10

### Fixed

#### Critical Bug: getMatrixTypes() Array Handling
- **Fixed:** "Attempt to read property 'name' on int" error (reported by @szabesz)
- **Root Cause:** `getMatrixTypes()` returns associative array `['matrix_name' => id]`, not array of objects
- **Solution:** Changed all iterations from `foreach($matrixTypes as $mt)` to `foreach($matrixTypes as $matrixName => $matrixId)`
- **Affected Lines:** 414, 604, 643, 1352, 1748, 1957

**Before (WRONG):**
```php
$matrixTypes = $field->type->getMatrixTypes($field);
foreach($matrixTypes as $mt) {
    if($mt->name === $name) { // ERROR: $mt is integer!
```

**After (CORRECT):**
```php
$matrixTypes = $field->type->getMatrixTypes($field);
// $matrixTypes is ['matrix_name' => id] array
foreach($matrixTypes as $matrixName => $matrixId) {
    if($matrixName === $name) { // ✓ Correct
```

#### Module Configuration Defaults Not Applied
- **Fixed:** Default settings not applied on first install (reported by @szabesz)
- **Root Cause:** `$configDefaults` only used in config form, not on module instantiation
- **Solution:** Added `__construct()` method to apply defaults
- **Affected Settings:** All checkboxes (export_samples, export_api_docs, export_toon_format, etc.)

**Before:** User had to click "Submit" after install to activate default settings  
**After:** Defaults applied immediately on module install

### Changed

#### Error Handling Improvements
- Added comments explaining `getMatrixTypes()` return structure in all locations
- Improved label retrieval: fetch template object to get label instead of trying to access non-existent property
- More robust error handling with try-catch blocks around matrix type operations

### Technical Details

**Files Modified:**
- 6 locations with `getMatrixTypes()` calls fixed
- Added `__construct()` method for default configuration
- Added inline documentation for array structure

**Locations Fixed:**
1. Line ~414: exportTemplates() - Matrix types in template export
2. Line ~604: exportMatrixTemplates() - Pattern 4 detection
3. Line ~643: exportMatrixTemplates() - Getting matrix type labels
4. Line ~1352: exportSamples() - Matrix type labels in samples
5. Line ~1748: exportApiDocs() - API schema generation
6. Line ~1957: exportFieldDefinitions() - Field definitions export

---

## [1.1.4] - 2026-02-26

### Fixed

#### CRITICAL: TOON Format Data Loss
- **Fixed data loss in TOON export** - Previous version was losing field data in templates
- **Complete data preservation** - TOON now contains ALL data from JSON, just in different format
- **Proper array handling** - Non-uniform arrays now properly exported with all their data
- **Field details preserved** - All field properties (type, label, options, etc.) now in TOON

**Before (WRONG):**
```toon
templates[60]{name,id,label,fields,fieldCount}:
product,48,Product,[22],22
```

**After (CORRECT):**
```toon
templates[60]{name,id,label,fields,fieldCount}:
- # item 0
  name: product
  id: 48
  label: Product
  fields:
    - # item 0
      name: brand
      type: FieldtypePage
      label: Brand
    - # item 1
      name: abv
      type: FieldtypeText
      label: "ABV, %"
```

### Changed

#### TOON Export Logic
- Rewrote `toToonRecursive()` method to handle indexed arrays correctly
- Added `formatTableData()` method for table format without key
- Split `formatValue()` into `formatSimpleValue()` for non-array values
- Arrays in table cells now use JSON encoding to preserve nested data
- Improved handling of non-uniform object arrays

### Technical Details

**Root Cause:**
Original `formatValue()` was returning `[count]` for arrays instead of actual data, causing complete data loss for complex structures like template fields.

**Solution:**
1. Indexed arrays (lists) now properly iterate through items
2. Each item's properties fully exported
3. Table format only used when ALL items have identical keys
4. Non-uniform arrays use list notation with `- # item N`

**Files Affected:**
- `toToonRecursive()` - Complete rewrite for array handling
- `formatSimpleValue()` - New method for scalar values only
- `formatTableData()` - New method for keyless table format
- `formatAsTable()` - Updated to handle nested arrays in cells

---

## [1.1.3] - 2026-02-26

### Added

#### Nested Matrix Support
- **Recursive Matrix processing** - Full support for Matrix fields inside Matrix fields
- **Deep nesting** - Matrix → Matrix → Table/Combo/Page references all properly exported
- **Complete field structure** - Nested matrix types include all their subfields with types

#### Example Structure
```json
{
  "name": "menu",
  "type": "FieldtypeRepeaterMatrix",
  "matrix_types": [
    {
      "name": "repeater_menu",
      "subfields": [
        {
          "name": "menu_section",
          "type": "FieldtypeRepeaterMatrix",
          "matrix_types": [
            {
              "name": "repeater_menu_section",
              "subfields": [
                {
                  "name": "menu_food",
                  "type": "FieldtypeTable",
                  "columns": [...]
                }
              ]
            }
          ]
        }
      ]
    }
  ]
}
```

### Technical
- Added nested Matrix detection in exportTree() method
- Processes Matrix fields at any depth level
- Supports Page references, Table, and Combo inside nested Matrix
- Uses same template detection logic for nested structures

---

## [1.1.2] - 2026-02-26

### Added

#### Complete Site Tree Export
- **New file: tree.json** - Complete technical site overview combining structure + templates + fields
- **New file: tree.toon** - TOON format version for AI assistants
- **Architecture visualization** - All templates with their fields, subfields, and relationships
- **Page structure** - Complete page tree showing which template each page uses
- **Field relationships** - Shows page references, repeater subfields, matrix types, table columns, combo subfields

#### Tree Structure Features
- Template information: name, label, ID, page count
- Field types with nested structures:
  - **Page references** - Shows which template is referenced
  - **Repeaters** - Lists all subfields with types
  - **Matrix** - All matrix types with their subfields
  - **Table** - Column names and types
  - **Combo** - Subfield names and types
- Page hierarchy with template assignments
- No data values - purely technical architecture

**Use Cases:**
- Upload single `tree.toon` file to AI for complete site understanding
- Quick architecture overview without navigating multiple files
- Perfect for AI assistants to understand site structure instantly
- Combines best of `structure.json` and `templates.json` without redundancy

### Changed
- README.md: Removed Screenshots section
- README.md: Added tree.json/tree.toon to file structure
- Export flow: tree.json generated immediately after templates.json

---

## [1.1.1] - 2026-02-26

### Fixed

#### ProFields Repeater Matrix
- **Matrix templates not found** - Fixed detection to support `repeater_FIELDNAME` naming (old PW style)
  - Previous: Only looked for `repeater_matrix_*` prefix
  - Now: Checks all Matrix fields first, supports multiple naming patterns
  - Patterns supported: `repeater_*`, `repeater_matrix_*`, `repeatermatrix_*`

- **Empty Combo subfields** - Fixed parsing of `order` array
  - Previous: Incorrectly searched by field names
  - Now: Correctly uses indices (`['1', '2', '3']`)
  - Reads via `$field->data["i{$index}_name"]`

- **Empty Table columns** - Fixed column data extraction
  - Previous: Looked for non-existent `$field->data['columns']` array
  - Now: Reads via `col{i}name`, `col{i}label`, `col{i}type` pattern
  - Includes options and selectors

- **Missing Matrix samples** - No sample data was exported for Matrix templates
  - Added: New `exportMatrixSamples()` method
  - Creates: `repeater_*-samples.json` and `.toon` files in `/samples/`

- **Simplified repeater data in samples** - All values converted to strings
  - Previous: `"image": "1"`, `"page": "5678"`
  - Now: Complete objects with full metadata
  - Fixed: Page refs, Images, Files, Options, Datetime fields

#### Technical Fixes
- **Method call error** - Fixed `$template->getFields()` (doesn't exist) → `$template->fields`
- **Array access** - Added proper isset() checks for all array operations
- **Error handling** - Wrapped Matrix operations in try-catch blocks

### Added

#### Matrix Samples Export
- New `exportMatrixSamples()` method (line 1234)
- Exports real content examples from Matrix fields
- Creates separate sample files per Matrix template type
- Handles all field types (Page, Image, File, Table, Combo, Options, Datetime)
- Respects `samples_count` configuration setting
- Generates both JSON and TOON formats

#### Enhanced Matrix Data in Samples
- **Type labels** - Added `type_label` field to Matrix items
- **Page references** - Full objects: `{id, title, url}`
- **Images** - Complete data: `{url, width, height, description}`
- **Files** - Full details: `{url, basename, filesize}`
- **Options** - Array format: `[{id, value, title}]`
- **Datetime** - Formatted: `Y-m-d H:i:s`

#### Complete Field Definitions
- **Table columns** - Now exports with:
  - Column options (parsed as key=value)
  - Selectors for page autocomplete columns
  - Notes and descriptions
  
- **Combo subfields** - Now exports with:
  - All subfield types and options
  - Notes and descriptions
  - Proper order preservation

### Changed

#### Matrix Template Detection
- Previous approach: String pattern matching only
- New approach: Multi-stage detection
  1. Find all Matrix fields by type
  2. Locate their templates using multiple patterns
  3. Fallback to API check via `getMatrixTypes()`

#### Data Export Quality
- Matrix field data now matches quality of regular fields
- No more string conversion for complex types
- Proper type preservation across all field types

### Technical Details

#### New Methods
- `exportMatrixSamples()` - Export Matrix template samples

#### Modified Methods
- `exportMatrixTemplates()` - Enhanced detection logic
- `exportSamples()` - Improved Matrix/Repeater data handling

#### New Files Generated
```
/site/assets/context/samples/
├── repeater_menu-samples.json
├── repeater_menu-samples.toon
├── repeater_menu_section-samples.json
├── repeater_menu_section-samples.toon
├── repeater_options-samples.json
├── repeater_options-samples.toon
```

#### Integration Points
- Main export flow (line ~3017): Calls `exportMatrixTemplates()`
- Samples flow (line ~3053): Calls `exportMatrixSamples()`

#### Logging Added
```
Context: Starting Matrix templates export...
Context: Found 2 Matrix fields: menu, options
Context: Found 5 potential Matrix templates
Context: Processing Matrix template: repeater_menu (field: menu)
Context: Created samples for Matrix template: repeater_menu (3 samples)
```

---

## [1.1.0] - 2026-02-16

### Added

#### TOON Format Support
- **Dual format export** - JSON and TOON formats generated simultaneously
- **Token efficiency** - 30-60% fewer tokens than JSON for AI prompts
- **No dependencies** - Pure PHP TOON implementation
- **Conditional docs** - Documentation adapts based on TOON setting
- **Format comparison** - Admin UI shows token savings per file

#### ProFields Repeater Matrix Support
- **Matrix templates export** - Dedicated `matrix-templates.json`/`.toon` files
- **Complete field definitions** - All matrix types with full field configurations
- **Deep inspection** - Captures all field options and settings
- **Nested matrix support** - Handles Matrix fields within Matrix fields

#### New Export Methods
- `convertToToon()` - Convert data structures to TOON format
- `convertArrayToToon()` - Handle arrays in TOON
- `convertObjectToToon()` - Handle objects in TOON
- `exportMatrixTemplates()` - Export ProFields Matrix structures

### Changed

#### Admin Interface
- Added TOON status banner when enabled
- Format comparison table showing token savings
- Updated "What will be exported?" section with TOON files
- Quick tips section with TOON-specific guidance
- Module configuration table shows TOON status

#### Documentation
- README.md - Added TOON format section
- project-context.md - Conditional TOON documentation
- Directory structure - Shows both formats when TOON enabled
- File references - Updated with TOON examples

#### Export Process
- All core exports now generate both JSON and TOON
- Templates, structure, config, modules, classes - dual format
- Samples directory - Both formats for each template
- Conditional export based on `export_toon_format` setting

### Technical

#### New Configuration Options
- `export_toon_format` (boolean) - Enable/disable TOON export

#### Files Generated (when TOON enabled)
```
/site/assets/context/
├── structure.json + structure.toon
├── templates.json + templates.toon
├── config.json + config.toon
├── modules.json + modules.toon
├── classes.json + classes.toon
├── matrix-templates.json + matrix-templates.toon
└── samples/
    ├── *-samples.json + *-samples.toon
```

#### Performance
- TOON conversion adds < 0.5s to export time
- No external dependencies or libraries required
- Memory efficient - processes data in chunks

---

## [1.0.0] - 2026-02-14

### Added

#### Core Features
- **Site structure export** - Complete page hierarchy in JSON
- **ASCII tree visualization** - Human-readable site structure
- **Template definitions** - All templates with field configurations
- **Field types support** - Page, Image, File, Options, Table, Combo, Repeater
- **Content samples** - Real page examples per template
- **Configuration export** - PW version, PHP settings, modules

#### Optional Features
- **API documentation** - JSON schemas for REST APIs
- **Code snippets** - Customized selector patterns
- **AI prompts** - Ready-to-use context prompts
- **Performance metrics** - Site statistics
- **Frontend detection** - Alpine.js, Tailwind, UIkit auto-detection
- **Custom classes** - Automatic detection from `/site/classes/`

#### Admin Interface
- Configuration page with all settings
- Module status overview
- Export statistics
- Quick tips section
- File list preview

#### Site Type Customization
- Generic (default)
- Blog
- E-commerce
- Directory/Listings
- Agency/Portfolio

#### Export Options
- Configurable tree depth
- Children limit per node
- Sample count per template
- Compact mode for smaller files

### Initial Release
- ProcessWire 3.x compatible
- MIT License
- No external dependencies
- Full documentation

---

## Version Format

- **Major.Minor.Patch** (Semantic Versioning)
- **Major**: Breaking changes
- **Minor**: New features, backward compatible
- **Patch**: Bug fixes, backward compatible

## Links

- **Repository**: https://github.com/mxmsmnv/Context
- **Documentation**: https://github.com/mxmsmnv/Context/blob/main/README.md
- **Issues**: https://github.com/mxmsmnv/Context/issues
- **TOON Format**: https://toonformat.dev
