# Context - ProcessWire Module

Export your ProcessWire site structure as AI-optimized context for ChatGPT, Claude, and other AI assistants.

## What It Does

Context automatically generates comprehensive documentation of your ProcessWire site in formats optimized for AI assistants. Export in both **JSON** (standard) and **TOON** (AI-optimized) formats. 

**Perfect for:**
- Working with AI coding assistants (Claude, ChatGPT, Copilot)
- Reducing AI API costs with token-efficient TOON format
- Onboarding new developers
- Site migrations and documentation
- Rapid development with AI pair programming
- Maintaining consistent code standards

## Features

### Dual Format Export

- **JSON Format** - Standard format for APIs, development tools, and compatibility
- **TOON Format** - Token-Oriented Object Notation for AI assistants
  - 30-60% fewer tokens than JSON
  - Significantly reduces API costs for Claude, ChatGPT, etc.
  - Better for large datasets in AI prompts
  - Lossless conversion - same data, smaller size

### Core Exports (Always Generated)

- **Site Structure** - JSON/TOON and ASCII tree visualization of entire page hierarchy
- **Templates & Fields** - Complete template definitions with field types, options, and configurations
- **Configuration** - ProcessWire version, PHP settings, installed modules
- **Custom Classes** - Automatic detection of custom Page classes from `/site/classes/`
- **Frontend Stack** - Auto-detects Alpine.js, Tailwind CSS, UIkit, and other frameworks
- **ProFields Support** - Special handling for Repeater Matrix field types (if installed)

### Optional Features

- **Content Samples** - Export real page examples for each template (JSON + TOON)
- **API Documentation** - Generate JSON schemas for REST API development
- **Code Snippets** - Customized selector patterns and helper functions
- **AI Prompts** - Ready-to-use context prompts for AI assistants
- **Performance Metrics** - Site statistics and performance data
- **IDE Integration** - Generate `.cursorrules` and `.claudecode.json` files

### Site Type Customization

Code snippets are automatically customized for your site type:

- **Generic / Mixed Content** - General purpose patterns
- **Blog / News / Magazine** - Posts, authors, categories, archives
- **E-commerce / Online Store** - Products, cart, orders, inventory
- **Business / Portfolio / Agency** - Services, team, projects, testimonials
- **Catalog / Directory / Listings** - Brands, categories, hierarchical data

## Requirements

- ProcessWire 3.0 or higher
- PHP 8.2 or higher
- Write permissions for `/site/assets/context/` directory
- No external dependencies required - pure PHP implementation

## Installation

### Method 1: Manual Installation

```bash
cd /site/modules/
git clone https://github.com/mxmsmnv/Context.git
```

Then refresh modules in admin and install.

### Method 2: Modules Directory

1. Download the module from the [ProcessWire Modules Directory](https://modules.processwire.com/)
2. Place files in `/site/modules/Context/`
3. In ProcessWire admin: **Modules → Refresh**
4. Click **Install** next to "Context"

## Quick Start

### 1. Configure the Module

**Setup → Modules → Context → Configure**

1. Choose your site type (Blog, E-commerce, Business, Catalog, or Generic)
2. Enable TOON format - Export TOON Format (AI-Optimized) - Recommended!
3. Enable optional features you need:
   - Export Content Samples
   - Generate API Documentation
   - Create Code Snippets
   - Create AI Prompts
4. **Set samples per template** (1-10)
5. **Enable auto-update** if you want automatic exports on template/field changes

### 2. Export Your Site

Click **"Re-Export Context for AI"** or visit:
```
/processwire/module/?name=Context
```

Files are generated in: `/site/assets/context/`

### 3. Use with AI

Upload these files to your AI assistant:

**For AI Development (Recommended - Use TOON):**
- `structure.toon` - Complete page hierarchy (30-60% smaller!)
- `templates.toon` - All templates and fields (optimized)
- `config.toon` - Site configuration
- `prompts/project-context.md` - Complete project overview

**For Development Tools (Use JSON):**
- `structure.json` - Standard JSON format
- `templates.json` - For IDE plugins
- `classes.json` - Custom Page classes

**Example prompt:**
```
I've uploaded my ProcessWire site context in TOON format. 
Please help me create a new template for blog posts with 
title, body, author, and categories fields. Follow the 
existing patterns in templates.toon.
```

## Generated Files

### Directory Structure

```
/site/assets/context/
├── README.md                      # Documentation with format guide
├── tree.json                      # Complete site structure (templates + fields + pages)
├── tree.toon                      # Complete site structure (TOON - AI optimized!)
├── structure.json                 # Page tree (JSON)
├── structure.toon                 # Page tree (TOON - AI optimized!)
├── structure.txt                  # ASCII tree visualization
├── templates.json                 # Templates (JSON)
├── templates.toon                 # Templates (TOON - AI optimized!)
├── templates.csv                  # Templates in CSV
├── matrix-templates.json          # Repeater Matrix types (ProFields) - if installed
├── matrix-templates.toon          # Repeater Matrix types (TOON) - if installed
├── config.json                    # Configuration (JSON)
├── config.toon                    # Configuration (TOON)
├── modules.json                   # Installed modules (JSON)
├── modules.toon                   # Installed modules (TOON)
├── classes.json                   # Custom page classes (JSON)
├── classes.toon                   # Custom page classes (TOON)
│
├── samples/                       # Content examples (optional)
│   ├── product-samples.json
│   └── product-samples.toon       # AI-optimized samples!
├── api/                           # API schemas (optional)
├── snippets/                      # Code library (optional)
├── prompts/                       # AI prompts (optional)
└── metadata/                      # Technical data (optional)
```

### File Format Comparison

| File | JSON Size | TOON Size | Savings | Use For |
|------|-----------|-----------|---------|---------|
| `structure.*` | 45 KB | 28 KB | ~38% | Page hierarchy |
| `templates.*` | 12 KB | 6 KB | ~50% | Template definitions |
| `samples/*` | 8 KB | 4 KB | ~50% | Content examples |

**Tip:** When uploading to AI, use `.toon` files to save tokens and reduce API costs!

## Understanding TOON Format

### What is TOON?

TOON (Token-Oriented Object Notation) is a compact, human-readable format designed specifically for AI prompts. It represents the same data as JSON but uses 30-60% fewer tokens.

### Example Comparison

**JSON (120 tokens):**
```json
{
  "products": [
    {
      "id": 1045,
      "title": "Dark Chocolate 70%",
      "price": 12.99,
      "category": "Dark"
    },
    {
      "id": 1046,
      "title": "Milk Hazelnut",
      "price": 9.99,
      "category": "Milk"
    }
  ]
}
```

**TOON (65 tokens - 46% savings!):**
```toon
products[2]{id,title,price,category}:
1045,Dark Chocolate 70%,12.99,Dark
1046,Milk Hazelnut,9.99,Milk
```

### When to Use Which Format

**Use TOON (.toon) for:**
- Uploading to Claude, ChatGPT, or other AI assistants
- Large datasets in AI prompts
- Reducing API costs
- AI code generation

**Use JSON (.json) for:**
- API endpoints
- Development tools (PhpStorm, VSCode plugins)
- Third-party integrations
- Standard ProcessWire development

### Viewing TOON Files

- **VS Code / Cursor**: Install "TOON Language Support" extension for syntax highlighting
- **PhpStorm**: Use YAML syntax highlighting (similar appearance)
- **AI Assistants**: Upload directly - they understand TOON natively
- **Text Editors**: Plain text - fully readable by humans

## Use Cases

### 1. AI-Assisted Development (Save 30-60% on API Costs!)

```
# Upload to Claude/ChatGPT (TOON format):
- structure.toon
- templates.toon
- samples/product-samples.toon

# Then ask:
"Create a product filter with price range, category, and brand 
using the patterns from my site structure"

Result: Same quality response, but using 40% fewer tokens!
```

### 2. Onboarding New Developers

Share the `/site/assets/context/` folder with new team members. They get:
- Complete site structure overview (both formats)
- All templates and field definitions
- Code examples and patterns
- Custom Page classes documentation

### 3. Building APIs

```
# Upload (JSON format for technical work):
- api/schemas/product-schema.json
- snippets/api-examples.php

# Ask:
"Create a REST API endpoint for products with search and filtering"
```

### 4. Large-Scale AI Tasks

```
# For big projects with lots of data:
- Use TOON format to fit more context in AI's window
- 100 products in JSON = ~15,000 tokens
- 100 products in TOON = ~7,000 tokens
- Difference: You can include 2x more examples!
```

### 5. Cost Optimization

**Real savings example:**

```
Your site: 50 templates, 500 pages to document

JSON export: ~85,000 tokens
TOON export: ~42,000 tokens
Savings: 43,000 tokens per prompt

With Claude Sonnet ($3 per million input tokens):
- JSON cost: $0.255 per prompt
- TOON cost: $0.126 per prompt
- Savings: $0.129 per prompt

If you use 100 prompts/month: Save ~$13/month
If you use 1000 prompts/month: Save ~$130/month
```

## Auto-Update Feature

Enable **Auto-Update on Changes** to automatically regenerate context when you:
- Create or modify templates
- Add or modify fields
- Change template-field assignments

Hooks into:
- `Templates::saved`
- `Fields::saved`
- `Fieldgroups::saveReady`

Auto-update exports both JSON and TOON formats if TOON export is enabled.

## Best Practices

### Working with AI Assistants

1. **Always include** `prompts/project-context.md` - contains system instructions
2. **For token efficiency** - use `.toon` files instead of `.json`
3. **For field questions** - include `templates.toon`
4. **For site structure** - include `structure.toon` or `structure.txt`
5. **For coding** - include `snippets/selectors.php`
6. **For debugging** - include relevant `samples/*-samples.toon`

### File Upload Strategy

- **Small tasks** (1-3 files): Upload `.toon` files to chat directly
- **Medium tasks** (3-10 files): Core `.toon` files + specific sections
- **Large projects**: Use Claude Projects with entire `/context/` folder
- **Development tools**: Use `.json` files with IDEs and APIs

### When to Re-Export

- After adding/modifying templates
- After adding/modifying fields
- After changing site structure
- After changing Site Type setting
- Before major development sessions
- When you want to update TOON files with latest data

## Module Settings

### Export Formats

- **Export TOON Format (AI-Optimized)** - Generate `.toon` files alongside `.json`
  - Enabled by default
  - Saves 30-60% tokens
  - Perfect for AI development

### Site Type Selection

Choose your site type to get customized code snippets:
- Generic / Mixed Content
- Blog / News / Magazine  
- E-commerce / Online Store
- Business / Portfolio / Agency
- Catalog / Directory / Listings

### Content Features

- **Export Content Samples** - Include real page examples (JSON + TOON)
- **Samples Per Template** - How many examples (1-10)
- **Generate API Documentation** - Create JSON schemas
- **Export URL Routes** - URL segment configurations
- **Export Performance Metrics** - Site statistics
- **Create Code Snippets** - PHP examples customized for site type
- **Create AI Prompts** - Ready-to-use prompts

### Advanced Settings

- **Maximum Tree Depth** - Page tree depth limit (3-20)
- **JSON Children Limit** - Max children per page (5-100)
- **Compact Mode** - Collapse large lists
- **Auto-Update on Changes** - Auto-export on template/field save
- **Create IDE Integration Files** - `.cursorrules`, `.claudecode.json`
- **Custom AI Instructions** - Project-specific AI instructions

## Technical Details

### Requirements

- ProcessWire 3.x
- PHP 8.1 or higher
- Write permissions for `/site/assets/context/`

### Performance

- Export typically takes 1-3 seconds
- TOON conversion adds < 0.5s overhead
- Uses ProcessWire's caching where possible
- Auto-update hooks are lightweight (< 50ms)

### Security

- Exports are stored in `/site/assets/` (protected by ProcessWire)
- No sensitive data (passwords, API keys) is exported
- Only accessible to logged-in superusers by default

### TOON Format Details

- Pure PHP implementation - no external dependencies
- Lossless conversion - no data loss
- Deterministic output - same input = same output
- Handles all JSON data types
- Special optimization for uniform arrays (tables)

### File Locations

- **Module**: `/site/modules/Context/`
- **Exports**: `/site/assets/context/`
- **Snippets Library**: `/site/modules/Context/ContextSnippets.php`

## Format Comparison in Module Admin

When TOON export is enabled, the module admin page shows a comparison table:

| File Type | JSON Size | TOON Size | Savings |
|-----------|-----------|-----------|---------|
| structure | 45.2 KB | 27.8 KB | -38.5% |
| templates | 12.1 KB | 6.3 KB | -47.9% |
| config | 2.4 KB | 1.6 KB | -33.3% |

This helps you see the actual token/cost savings for your specific site!

## FAQ

### General Questions

**Q: Do I need to install any external libraries for TOON support?**  
A: No! TOON conversion is built-in with pure PHP. No Composer packages or external dependencies required.

**Q: Is TOON format lossless?**  
A: Yes! TOON contains exactly the same data as JSON, just in a more compact format. You can convert back and forth without any data loss.

**Q: Which AI assistants support TOON?**  
A: Claude, ChatGPT, and most modern LLMs understand TOON natively. Just upload the `.toon` file as you would a `.json` file.

**Q: Can I use both JSON and TOON formats?**  
A: Absolutely! Both formats are generated simultaneously. Use JSON for development tools and APIs, TOON for AI assistants.

**Q: How much does TOON actually save?**  
A: Typically 30-60% fewer tokens. The exact savings depend on your data structure - uniform arrays see the biggest gains (up to 60%).

### Technical Questions

**Q: Does TOON export slow down my site?**  
A: No. Export happens on-demand when you click the button, not on every page load. The TOON conversion adds < 0.5s to the export time.

**Q: What if I disable TOON format later?**  
A: No problem! Simply uncheck "Export TOON Format" in settings. Your next export will only generate JSON files.

**Q: Can I edit TOON files manually?**  
A: Yes, TOON files are plain text and human-readable. However, it's easier to make changes in ProcessWire and re-export.

**Q: Are there any file size limits?**  
A: TOON files follow the same limits as JSON. Both are text files with no artificial size restrictions.

**Q: How do I view TOON files?**  
A: TOON files are plain text. Use any text editor. For syntax highlighting: VS Code/Cursor (install "TOON Language Support" extension) or PhpStorm (use YAML highlighting).

### Troubleshooting

**Q: Export failed with "permission denied"**  
A: Ensure `/site/assets/` directory is writable by your web server user. Check file permissions (755 or 775).

**Q: TOON files not being created**  
A: Check that "Export TOON Format" is enabled in module settings and you've clicked "Re-Export Context for AI" after enabling it.

**Q: AI assistant doesn't understand my TOON file**  
A: TOON is plain text - just upload it as you would any text file. Make sure the file has a `.toon` extension.

## License

MIT License - see LICENSE file for details

## Author

**Maxim Alex**
- Website: [smnv.org](https://smnv.org)
- Email: maxim@smnv.org
- GitHub: [@mxmsmnv](https://github.com/mxmsmnv)

