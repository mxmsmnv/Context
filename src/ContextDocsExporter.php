<?php namespace ProcessWire;

/**
 * Generates exported README and SKILL documentation.
 */
class ContextDocsExporter {

    /** @var Context */
    protected $module;

    public function __construct(Context $module) {
        $this->module = $module;
    }

    public function createReadme() {
        return (function() {
        $moduleVersion = self::VERSION;
        $toonEnabled = $this->export_toon_format ? 'enabled' : 'disabled';
        $toonSection = '';
        $exportPath = rtrim($this->getContextPath(), '/') . '/';
        $jsonEnabled = $this->_exportJsonFormat;
        $csvEnabled = $this->_exportCsvFormat;
        $structureResource = $this->export_toon_format ? 'structure.toon' : 'structure.json';
        $templatesResource = $this->export_toon_format ? 'templates.toon' : 'templates.json';
        $samplesResource = $this->export_toon_format ? 'samples/[template]-samples.toon' : 'samples/[template]-samples.json';
        $classesResource = $this->export_toon_format ? 'classes.toon' : 'classes.json';
        $configResource = $this->export_toon_format ? 'config.toon' : 'config.json';
        $modulesResource = $this->export_toon_format ? 'modules.toon' : 'modules.json';
        $matrixResource = $this->export_toon_format ? 'matrix-templates.toon' : 'matrix-templates.json';
        $apiQuickLine = ($jsonEnabled && $this->export_api_docs) ? "   - **For API work**: `api/schemas/`, `snippets/api-examples.php`\n" : "";
        $apiWorkflowSection = ($jsonEnabled && $this->export_api_docs) ? <<<README

#### Building an API Endpoint
```
Upload: api/schemas/, snippets/api-examples.php
Ask: "Create a REST API endpoint for [template] with CRUD operations"
```
README : "";
        $apiDescriptionSection = ($jsonEnabled && $this->export_api_docs) ? <<<README

**api/** (if API Documentation enabled)
- JSON schemas for each template
- Endpoint documentation and examples
- **Use for**: Building REST APIs, headless CMS integration, external applications
README : "";
        $metadataDescriptionSection = $jsonEnabled ? <<<README

**metadata/** (if enabled)
- **routes.json**: URL segment configurations
- **field-definitions.json**: Deep field type information (Repeater, Matrix, Table)
- **performance.json**: Site metrics, page counts, database size
- **Use for**: Advanced development, optimization, troubleshooting
README : "";
        $csvDescriptionSection = $csvEnabled ? <<<README

**templates.csv**
- Template and field inventory in CSV format
- Easy to import into Excel, Google Sheets
- **Use for**: Analysis, planning, sharing with stakeholders
README : "";

        if($this->export_toon_format && !$jsonEnabled) {
            $toonSection = <<<'TOON'

## 🎯 TOON Format (AI-Optimized)

This export was generated without JSON artifacts. Use the selected TOON/CSV files from this folder.

**What is TOON?**
- Compact, human-readable format designed for AI assistants
- Uses 30-60% fewer tokens than JSON
- Same data, smaller size = lower API costs

**Viewing TOON files:**
- VS Code/Cursor: Install "TOON Language Support" extension
- PhpStorm: Use YAML syntax highlighting
- AI Assistants: Upload directly - they understand TOON natively

TOON;
        } elseif($this->export_toon_format) {
            $toonSection = <<<'TOON'

## 🎯 TOON Format (AI-Optimized)

This export includes files in **TOON (Token-Oriented Object Notation)** format alongside standard JSON files.

**What is TOON?**
- Compact, human-readable format designed for AI assistants
- Uses 30-60% fewer tokens than JSON
- Same data, smaller size = lower API costs

**File Formats:**
- `.json` files - Standard JSON for development, APIs, tools
- `.toon` files - AI-optimized format for Claude, ChatGPT, etc.

**When to use which:**
- ✅ **Use .toon for AI**: Upload to Claude, ChatGPT to save tokens and costs
- ✅ **Use .json for dev**: Use with IDEs, APIs, standard tools

**Example savings:**
```
structure.json  (15,000 tokens)  →  structure.toon  (8,500 tokens)  = 43% savings
templates.json  (8,000 tokens)   →  templates.toon  (4,000 tokens)  = 50% savings
samples/*.json  (12,000 tokens)  →  samples/*.toon  (6,500 tokens)  = 46% savings
```

**Viewing TOON files:**
- VS Code/Cursor: Install "TOON Language Support" extension
- PhpStorm: Use YAML syntax highlighting
- AI Assistants: Upload directly - they understand TOON natively

TOON;
        }
        
        // Directory structure - разный в зависимости от TOON
        $directoryStructure = '';
        if($this->export_toon_format && !$jsonEnabled) {
            $directoryStructure = <<<'STRUCTURE'
```
/site/assets/context/
├── README.md                      # This file
├── structure.toon                 # Complete page tree (TOON - AI optimized)
├── structure.txt                  # Page tree visualization (ASCII)
├── templates.toon                 # All templates with field definitions (TOON)
├── matrix-templates.toon          # Repeater Matrix field types (TOON) - if ProFields installed
├── config.toon                    # Site configuration (TOON)
├── modules.toon                   # Installed modules with versions (TOON)
├── classes.toon                   # Custom page classes (TOON)
│
├── samples/                       # Real content examples (optional)
│   ├── [template]-samples.toon    # Sample pages per template (TOON)
│   └── _all-samples.toon          # All samples combined (TOON)
STRUCTURE;
        } elseif($this->export_toon_format) {
            $directoryStructure = <<<'STRUCTURE'
```
/site/assets/context/
├── README.md                      # This file
├── structure.json                 # Complete page tree (JSON)
├── structure.toon                 # Complete page tree (TOON - AI optimized)
├── structure.txt                  # Page tree visualization (ASCII)
├── templates.json                 # All templates with field definitions (JSON)
├── templates.toon                 # All templates with field definitions (TOON)
├── templates.csv                  # Templates export in CSV format
├── matrix-templates.json          # Repeater Matrix field types (JSON) - if ProFields installed
├── matrix-templates.toon          # Repeater Matrix field types (TOON) - if ProFields installed
├── config.json                    # Site configuration (JSON)
├── config.toon                    # Site configuration (TOON)
├── modules.json                   # Installed modules with versions (JSON)
├── modules.toon                   # Installed modules with versions (TOON)
├── classes.json                   # Custom page classes (JSON)
├── classes.toon                   # Custom page classes (TOON)
│
├── samples/                       # Real content examples (optional)
│   ├── [template]-samples.json    # Sample pages per template (JSON)
│   ├── [template]-samples.toon    # Sample pages per template (TOON)
│   ├── _all-samples.json          # All samples combined (JSON)
│   └── _all-samples.toon          # All samples combined (TOON)
STRUCTURE;
        } else {
            $directoryStructure = <<<'STRUCTURE'
```
/site/assets/context/
├── README.md                      # This file
├── structure.json                 # Complete page tree (JSON)
├── structure.txt                  # Page tree visualization (ASCII)
├── templates.json                 # All templates with field definitions
├── templates.csv                  # Templates export in CSV format
├── matrix-templates.json          # Repeater Matrix field types (if ProFields installed)
├── config.json                    # Site configuration
├── modules.json                   # Installed modules with versions
├── classes.json                   # Custom page classes (/site/classes/)
│
├── samples/                       # Real content examples (optional)
│   ├── [template]-samples.json    # Sample pages per template
│   └── _all-samples.json          # All samples combined
STRUCTURE;
        }

        if($this->export_toon_format && !$jsonEnabled) {
            $optionalDirectoryStructure = <<<'STRUCTURE'
│
├── snippets/                      # Code library (optional)
│   ├── selectors.php              # Selector patterns for your site type
│   ├── helpers.php                # Utility functions
│   └── api-examples.php           # API implementation examples
│
└── prompts/                       # Ready-to-use AI prompts (optional)
    └── project-context.md         # Overall project context & instructions
STRUCTURE;
        } else {
            $optionalDirectoryStructure = <<<'STRUCTURE'
│
├── api/                           # API documentation (optional)
│   ├── endpoints.json             # Available API endpoints
│   ├── all-schemas.json           # All JSON schemas
│   └── schemas/
│       └── [template]-schema.json # JSON Schema per template
│
├── snippets/                      # Code library (optional)
│   ├── selectors.php              # Selector patterns for your site type
│   ├── helpers.php                # Utility functions
│   └── api-examples.php           # API implementation examples
│
├── prompts/                       # Ready-to-use AI prompts (optional)
│   └── project-context.md         # Overall project context & instructions
│
└── metadata/                      # Technical metadata (optional)
    ├── routes.json                # URL routing structure
    ├── field-definitions.json     # Detailed field information
    └── performance.json           # Performance metrics
STRUCTURE;
        }

        if(!$csvEnabled) {
            $directoryStructure = preg_replace('/^.*templates\.csv.*\R/m', '', $directoryStructure);
        } elseif($this->export_toon_format && !$jsonEnabled && strpos($directoryStructure, 'templates.csv') === false) {
            $directoryStructure = str_replace(
                "├── templates.toon                 # All templates with field definitions (TOON)\n",
                "├── templates.toon                 # All templates with field definitions (TOON)\n├── templates.csv                  # Templates export in CSV format\n",
                $directoryStructure
            );
        }

        $readme = <<<README
# ProcessWire AI Context Documentation

This directory contains a comprehensive export of your ProcessWire site structure, optimized for use with AI development assistants (ChatGPT, Claude, Copilot, etc.).

**Generated by Context Module v{$moduleVersion}**
**TOON Format: {$toonEnabled}**
{$toonSection}

## 📁 Directory Structure

{$directoryStructure}
{$optionalDirectoryStructure}

```

**Note:** Folders marked (optional) are created only if enabled in module settings.

## 🎯 Site Type Configuration

The snippets in this export are customized for your site type. You can change this in:  
**Setup → Modules → Context → Configure → Site Type**

**Available site types:**
- **Generic / Mixed Content** - General purpose with various content types
- **Blog / News / Magazine** - Articles, posts, authors, categories
- **E-commerce / Online Store** - Products, cart, orders, inventory
- **Business / Portfolio / Agency** - Services, team, projects, case studies
- **Catalog / Directory / Listings** - Brands, categories, hierarchical data

Changing the site type will regenerate `snippets/selectors.php` with relevant examples.

## 🚀 How to Use with AI

### Quick Start

README;
        
        // Условный Quick Start в зависимости от TOON
        $readme .= $this->export_toon_format ? <<<TOON_QUICK

1. **Upload core files** to your AI assistant:
   - **For AI (TOON - Recommended)**: `prompts/project-context.md`, `{$templatesResource}`, `{$structureResource}`
   - **Always useful**: `structure.txt`, `README.md`
   - **For coding**: `snippets/selectors.php`, `snippets/helpers.php`
{$apiQuickLine}

2. **Describe your task** clearly to the AI

3. **Reference specific files** when asking questions

**💡 Pro Tip**: Use `.toon` files instead of `.json` when uploading to AI assistants - you'll save 30-60% on tokens and API costs!

### Common Workflows

#### Understanding Site Structure
```
Upload: {$structureResource}, {$templatesResource}, README.md  (TOON format saves ~45% tokens!)
Ask: "Explain the site structure and main content types"
```

#### Creating a New Template
```
Upload: {$templatesResource}, prompts/project-context.md  (50% fewer tokens than JSON!)
Ask: "Create a template for [purpose] following existing patterns"
```

#### Building Features with Selectors
```
Upload: snippets/selectors.php, {$templatesResource}
Ask: "Show me how to get the 10 most recent [items] with images"
```
TOON_QUICK
 : <<<JSON_QUICK

1. **Upload core files** to your AI assistant:
   - **Always**: `prompts/project-context.md`, `{$templatesResource}`
   - **Recommended**: `structure.txt`, `README.md`
   - **For coding**: `snippets/selectors.php`, `snippets/helpers.php`
{$apiQuickLine}

2. **Describe your task** clearly to the AI

3. **Reference specific files** when asking questions

### Common Workflows

#### Understanding Site Structure
```
Upload: structure.txt, {$templatesResource}, README.md
Ask: "Explain the site structure and main content types"
```

#### Creating a New Template
```
Upload: {$templatesResource}, prompts/project-context.md
Ask: "Create a template for [purpose] following existing patterns"
```

#### Building Features with Selectors
```
Upload: snippets/selectors.php, {$templatesResource}
Ask: "Show me how to get the 10 most recent [items] with images"
```
JSON_QUICK;

        $readme .= <<<README

{$apiWorkflowSection}

#### Debugging an Issue
```
Upload: {$templatesResource}, {$samplesResource}
Ask: "Why is [field] not working on [template]? Here's sample data."
```

#### Working with Custom Page Classes
```
Upload: {$classesResource}, {$templatesResource}
Ask: "Create a custom Page class for [template] with methods to [purpose]"
```

## 📊 File Descriptions

### Core Files (Always Generated)

**{$structureResource}**
- Complete hierarchical page tree
- Includes page IDs, titles, templates, URLs, parent-child relationships
- **Use for**: Understanding site architecture, building navigation, finding pages programmatically

**structure.txt**
- Human-readable ASCII tree visualization
- Shows site structure at a glance with indentation
- **Use for**: Quick overview, documentation, sharing with non-technical team members

**{$templatesResource}**
- All templates with complete field definitions
- Field types, labels, requirements, options, default values
- Includes Repeater Matrix, Table field structures
- **Use for**: Template development, understanding field configurations, building forms

{$csvDescriptionSection}

**{$matrixResource}** (if ProFields Repeater Matrix installed)
- Detailed structure of all Repeater Matrix field types
- Each matrix type with complete field definitions
- Includes parent field information, labels, sort order
- All field options, settings, and relationships
- **Use for**: Understanding complex Matrix structures, AI-assisted Matrix development, documentation

**{$configResource}**
- ProcessWire version, PHP version, database info
- Site configuration, timezone, installed language
- Frontend stack detection (Alpine.js, Tailwind, UIkit, etc.)
- **Use for**: Environment setup, compatibility checks, deployment planning

**{$modulesResource}**
- All installed modules with versions, summaries, authors
- Sorted alphabetically for easy reference
- **Use for**: Module compatibility checks, understanding available functionality

**{$classesResource}**
- Custom Page classes from `/site/classes/` directory
- Class names, namespaces, extends, methods, descriptions
- Shows which templates use custom classes
- **Use for**: Understanding OOP structure, custom page behaviors, available methods

### Optional Directories

**snippets/** (if Code Snippets enabled)
- **selectors.php**: Customized selector examples for your site type
  - Basic queries, search, sorting, filtering
  - Type-specific patterns (blog posts, products, services, etc.)
  - Advanced selectors, pagination, counting
  - Real template names from your site
- **helpers.php**: Universal helper functions
  - Page helpers (getPageTitle, getBreadcrumbs)
  - Text helpers (getExcerpt, timeAgo)
  - URL helpers (isCurrentPage, getCurrentUrl)
  - Image helpers (getResponsiveImage)
  - Form helpers (sanitizeInput, getInput)
- **api-examples.php**: REST API implementation examples
  - List items, get single item, search
  - Customized for your site type
- **Note**: Snippets are generated based on your Site Type setting
- **To customize**: Edit `/site/modules/Context/src/ContextSnippets.php`
- **TOON serializer**: `/site/modules/Context/src/ContextToon.php`
- **Sample serializer**: `/site/modules/Context/src/ContextSampleSerializer.php`

**samples/** (if Content Samples enabled)
- Real content examples exported from live pages
- Shows actual data formats and field usage patterns
- Helps AI understand how data is structured in practice
- **Use for**: Data migration, understanding content patterns, training AI on your data

**prompts/** (if AI Prompts enabled)
- **project-context.md**: Complete system-level instructions for AI
  - Site overview, technical stack, templates, fields
  - Best practices, code standards, common patterns
  - Custom AI instructions (if configured)
- **Use for**: Consistent AI interactions, onboarding, complex workflows

{$apiDescriptionSection}
{$metadataDescriptionSection}

## 🎯 Best Practices

### When Working with AI

1. **Always start with project-context.md** - it contains system instructions
2. **Upload {$templatesResource}** for any field-related questions
3. **Use structure.txt** for quick site overview
4. **Include snippets/selectors.php** when writing queries
5. **Reference samples/** when asking about data patterns

### File Upload Strategy

- **Small tasks** (< 3 files): Upload directly to chat
- **Medium tasks** (3-10 files): Upload core files + specific sections
- **Large tasks** (10+ files): Use Claude Projects or upload entire `/context/` folder

### Updating Context

The context exports automatically when you change templates or fields if **Auto-Update on Changes** is enabled in module settings.

Otherwise, click **Re-Export Context for AI** in the module when you:
- Add or modify templates
- Add or modify fields
- Make structural changes to the site
- Change the Site Type setting

## 🔧 Module Settings

Configure what gets exported in **Setup → Modules → Context → Configure**

### Site Type Selection
Choose your site type to customize code snippets:
- Generic / Mixed Content
- Blog / News / Magazine
- E-commerce / Online Store
- Business / Portfolio / Agency
- Catalog / Directory / Listings

### Content Features
- **Export Content Samples**: Include real page examples
- **Samples Per Template**: Number of examples (1-10)
- **Generate API Documentation**: Create JSON schemas
- **Export URL Routes**: URL segment configurations
- **Export Performance Metrics**: Site statistics
- **Create Code Snippets**: PHP code examples (customized per site type)
- **Create AI Prompts**: Ready-to-use prompt templates

### Advanced Settings
- **Maximum Tree Depth**: How deep to export page tree (3-20)
- **JSON Children Limit**: Max children per page in JSON (5-100)
- **Compact Mode**: Collapse large lists in structure.txt
- **Auto-Update on Changes**: Auto-regenerate on template/field save
- **Create IDE Integration Files**: Generate `.cursorrules`, `.claudecode.json`
- **Custom AI Instructions**: Project-specific instructions for AI

## 💡 Tips & Tricks

### Customizing Code Snippets

The code snippets in `snippets/selectors.php` are generated from templates in:
`/site/modules/Context/src/ContextSnippets.php`

To add your own patterns:
1. Edit `src/ContextSnippets.php`
2. Add examples to the appropriate method (getBlogSelectors, getEcommerceSelectors, etc.)
3. Re-export context

### Working with Multiple Projects

If you use Claude Projects:
1. Create a project for each ProcessWire site
2. Upload the entire `/site/assets/context/` folder to Project Knowledge
3. AI will have permanent access to your site structure

### IDE Integration

If **Create IDE Integration Files** is enabled:
- `.cursorrules` - Rules for Cursor AI editor
- `.claudecode.json` - Configuration for Claude Code CLI

These files help AI editors understand your ProcessWire project structure.

## 📖 Additional Resources

- **ProcessWire Documentation**: https://processwire.com/docs/
- **API Reference**: https://processwire.com/api/ref/
- **Selectors Guide**: https://processwire.com/docs/selectors/
- **Module Repository**: https://modules.processwire.com/
- **ProcessWire Forums**: https://processwire.com/talk/

## 🔄 Version History

**v{$moduleVersion}** - Current version
- Site type selection (5 types)
- Customized code snippets per site type
- External snippets library (`src/ContextSnippets.php`)
- Custom page classes export
- Frontend stack detection
- IDE integration files
- Auto-update on changes
- Comprehensive documentation

---

**Export location**: `/site/assets/context/`  
**Module**: Context v{$moduleVersion}
**Website**: https://processwire.com  

Use AI assistants effectively with complete site context! 🚀

README;

        return str_replace('/site/assets/context/', $exportPath, $readme);
        })->call($this->module);
    }

    public function createSkillMd() {
        return (function() {
        $exportPath = $this->getContextPath();

        // Collect available files
        $files = [];
        $structureResource = $this->export_toon_format ? 'structure.toon' : 'structure.json';
        $templatesResource = $this->export_toon_format ? 'templates.toon' : 'templates.json';
        $treeResource = $this->export_toon_format ? 'tree.toon' : 'tree.json';
        $modulesResource = $this->export_toon_format ? 'modules.toon' : 'modules.json';
        $apiResourceNote = $this->_exportJsonFormat ? "  - API schemas → `api/` directory\n" : "";

        // Core files
        if($this->_exportJsonFormat && file_exists($exportPath . 'config.json')) $files[] = '- **[config.json](./config.json)**: Site configuration';
        if($this->_exportJsonFormat && file_exists($exportPath . 'templates.json')) $files[] = '- **[templates.json](./templates.json)**: All templates with field definitions';
        if($this->_exportJsonFormat && file_exists($exportPath . 'templates.csv')) $files[] = '- **[templates.csv](./templates.csv)**: Templates export in CSV format';
        if($this->_exportJsonFormat && file_exists($exportPath . 'structure.json')) $files[] = '- **[structure.json](./structure.json)**: Complete page tree (JSON)';
        if(file_exists($exportPath . 'structure.txt')) $files[] = '- **[structure.txt](./structure.txt)**: Page tree visualization (ASCII)';
        if($this->_exportJsonFormat && file_exists($exportPath . 'tree.json')) $files[] = '- **[tree.json](./tree.json)**: Combined structure with templates and fields';
        if($this->_exportJsonFormat && file_exists($exportPath . 'modules.json')) $files[] = '- **[modules.json](./modules.json)**: Installed modules with versions';
        if($this->_exportJsonFormat && file_exists($exportPath . 'matrix-templates.json')) $files[] = '- **[matrix-templates.json](./matrix-templates.json)**: Repeater Matrix field types';
        if(file_exists($exportPath . 'README.md')) $files[] = '- **[README.md](./README.md)**: Source documentation and directory structure';
        
        // TOON files
        if($this->export_toon_format) {
            $toonFiles = [];
            if(file_exists($exportPath . 'config.toon')) $toonFiles[] = '  - **[config.toon](./config.toon)**';
            if(file_exists($exportPath . 'templates.toon')) $toonFiles[] = '  - **[templates.toon](./templates.toon)**';
            if(file_exists($exportPath . 'structure.toon')) $toonFiles[] = '  - **[structure.toon](./structure.toon)**';
            if(file_exists($exportPath . 'tree.toon')) $toonFiles[] = '  - **[tree.toon](./tree.toon)**';
            if(file_exists($exportPath . 'modules.toon')) $toonFiles[] = '  - **[modules.toon](./modules.toon)**';
            if(file_exists($exportPath . 'matrix-templates.toon')) $toonFiles[] = '  - **[matrix-templates.toon](./matrix-templates.toon)**';
            
            if(!empty($toonFiles)) {
                $files[] = "\n- **TOON Format** (30-60% fewer tokens than JSON):";
                $files = array_merge($files, $toonFiles);
            }
        }
        
        // Subdirectories
        if($this->_exportJsonFormat && is_dir($exportPath . 'metadata/')) {
            $files[] = "\n- **[metadata/](metadata/)**: Technical metadata";
            if(file_exists($exportPath . 'metadata/field-definitions.json'))
                $files[] = '  - **[field-definitions.json](metadata/field-definitions.json)**: Detailed field information';
            if(file_exists($exportPath . 'metadata/routes.json'))
                $files[] = '  - **[routes.json](metadata/routes.json)**: URL routing structure';
        }
        
        if($this->_exportJsonFormat && is_dir($exportPath . 'api/')) {
            $files[] = "\n- **[api/](api/)**: REST API schemas and examples";
        }
        
        if(is_dir($exportPath . 'snippets/')) {
            $files[] = "\n- **[snippets/](snippets/)**: Code library";
            if(file_exists($exportPath . 'snippets/selectors.php'))
                $files[] = '  - **[selectors.php](snippets/selectors.php)**: Selector patterns for your site type';
            if(file_exists($exportPath . 'snippets/helpers.php'))
                $files[] = '  - **[helpers.php](snippets/helpers.php)**: Utility functions';
            if(file_exists($exportPath . 'snippets/api-examples.php'))
                $files[] = '  - **[api-examples.php](snippets/api-examples.php)**: API implementation examples';
        }
        
        if(is_dir($exportPath . 'prompts/')) {
            $files[] = "\n- **[prompts/](prompts/)**: Prompt templates for manual LLM/agent use (not auto-loaded by agents)";
        }
        
        if(is_dir($exportPath . 'samples/')) {
            $files[] = "\n- **[samples/](samples/)**: Real content examples from live pages";
        }
        
        $filesList = implode("\n", $files);
        
        return <<<SKILL
---
name: context
description: Provides comprehensive context about the current ProcessWire project structure, fields, modules, and API snippets. Use this skill when the user asks about project structure, available fields, templates, or specific implementation details of the current site.
---

# ProcessWire Context

This skill provides a structured snapshot of the current ProcessWire project configuration. Use the provided resources to answer user queries accurately.

## When to use this skill

- User asks about project structure, templates, or fields
- User needs to know available modules or their versions
- User requests code examples or API usage patterns
- User wants to understand the page tree or URL routing
- User needs site configuration details

## Steps

1. **Analyze the request**: Determine what type of context is needed (templates, fields, routes, etc.)
2. **Locate the resource**: Find the relevant file in the Resources section below
3. **Read the content**: Extract the necessary data from the resource file
4. **Formulate the answer**: Provide accurate information based strictly on the context

## Resources

The following files contain the project context:

{$filesList}

## Important Notes

- **TOON format** files use 30-60% fewer tokens than JSON equivalents
- All data is auto-generated from the live ProcessWire installation
- Files are updated when templates, fields, or structure changes
- Use specific files based on the query type:
  - Structure questions → `{$structureResource}` or `structure.txt`
  - Template/field details → `{$templatesResource}` or `{$treeResource}`
  - Module information → `{$modulesResource}`
  - Code examples → `snippets/` directory
{$apiResourceNote}

## Examples

**Q: "What templates are available in this project?"**  
→ Read `{$templatesResource}`

**Q: "Show me the page tree structure"**  
→ Read `structure.txt` for ASCII visualization or `{$structureResource}` for detailed data

**Q: "What fields does the 'product' template have?"**  
→ Read `{$templatesResource}` and find the 'product' template entry

**Q: "Give me an example of using selectors in ProcessWire"**  
→ Read `snippets/selectors.php`

---

**Generated by**: Context module for ProcessWire
SKILL;
        })->call($this->module);
    }

}
