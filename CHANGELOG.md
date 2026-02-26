# Changelog

All notable changes to the Context module will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
