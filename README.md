# Context - ProcessWire Module

> Export your ProcessWire site structure as AI-optimized context for ChatGPT, Claude, and other AI assistants.

## 🎯 What It Does

Context automatically generates comprehensive documentation of your ProcessWire site in formats optimized for AI assistants. Instead of manually explaining your site structure every time, upload the exported files and AI immediately understands your templates, fields, page hierarchy, and code patterns.

**Perfect for:**
- 🤖 Working with AI coding assistants (Claude, ChatGPT, Copilot)
- 📚 Onboarding new developers
- 🔄 Site migrations and documentation
- 🚀 Rapid development with AI pair programming
- 📖 Maintaining consistent code standards

## ✨ Features

### Core Exports (Always Generated)

- **📊 Site Structure** - JSON and ASCII tree visualization of entire page hierarchy
- **📋 Templates & Fields** - Complete template definitions with field types, options, and configurations
- **🔧 Configuration** - ProcessWire version, PHP settings, installed modules
- **📦 Custom Classes** - Automatic detection of custom Page classes from `/site/classes/`
- **🎨 Frontend Stack** - Auto-detects Alpine.js, Tailwind CSS, UIkit, and other frameworks

### Optional Features

- **📝 Content Samples** - Export real page examples for each template
- **🔌 API Documentation** - Generate JSON schemas for REST API development
- **💾 Code Snippets** - Customized selector patterns and helper functions
- **🤖 AI Prompts** - Ready-to-use context prompts for AI assistants
- **📈 Performance Metrics** - Site statistics and performance data
- **🛠️ IDE Integration** - Generate `.cursorrules` and `.claudecode.json` files

### Site Type Customization

Code snippets are automatically customized for your site type:

- **Generic / Mixed Content** - General purpose patterns
- **Blog / News / Magazine** - Posts, authors, categories, archives
- **E-commerce / Online Store** - Products, cart, orders, inventory
- **Business / Portfolio / Agency** - Services, team, projects, testimonials
- **Catalog / Directory / Listings** - Brands, categories, hierarchical data

## 📦 Installation

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

## 🚀 Quick Start

### 1. Configure the Module

**Setup → Modules → Context → Configure**

1. **Choose your site type** (Blog, E-commerce, Business, Catalog, or Generic)
2. **Enable optional features** you need:
   - ✅ Export Content Samples
   - ✅ Generate API Documentation
   - ✅ Create Code Snippets
   - ✅ Create AI Prompts
3. **Set samples per template** (1-10)
4. **Enable auto-update** if you want automatic exports on template/field changes

### 2. Export Your Site

Click **"Re-Export Context for AI"** or visit:
```
/processwire/module/?name=Context
```

Files are generated in: `/site/assets/context/`

### 3. Use with AI

Upload these files to your AI assistant:

**Essential files:**
- `prompts/project-context.md` - Complete site overview
- `templates.json` - All templates and fields
- `structure.txt` - Page hierarchy

**For development:**
- `snippets/selectors.php` - Customized code examples
- `snippets/helpers.php` - Utility functions
- `classes.json` - Custom Page classes

**Example prompt:**
```
I've uploaded my ProcessWire site context. Please help me create 
a new template for blog posts with title, body, author, and 
categories fields. Follow the existing patterns in templates.json.
```

## 📂 Generated Files

### Directory Structure

```
/site/assets/context/
├── README.md                      # Documentation
├── structure.json                 # Complete page tree (JSON)
├── structure.txt                  # ASCII tree visualization
├── templates.json                 # Templates with fields
├── templates.csv                  # Templates in CSV
├── config.json                    # Site configuration
├── modules.json                   # Installed modules
├── classes.json                   # Custom page classes
│
├── samples/                       # Content examples (optional)
├── api/                           # API schemas (optional)
├── snippets/                      # Code library (optional)
├── prompts/                       # AI prompts (optional)
└── metadata/                      # Technical data (optional)
```

### File Descriptions

| File | Contains | Use For |
|------|----------|---------|
| `structure.json` | Complete page hierarchy with IDs, URLs | Navigation, finding pages |
| `structure.txt` | ASCII tree visualization | Quick overview |
| `templates.json` | All templates with field definitions | Template development |
| `config.json` | PW version, PHP, modules, frontend stack | Environment setup |
| `classes.json` | Custom Page classes from `/site/classes/` | OOP development |
| `snippets/selectors.php` | Site-type customized code examples | Learning patterns |
| `prompts/project-context.md` | Complete AI system prompt | AI onboarding |

## 🎨 Customizing Code Snippets

Code snippets are generated from templates in `ContextSnippets.php`. To add your own patterns:

1. Open `/site/modules/Context/ContextSnippets.php`
2. Edit the appropriate method:
   - `getBlogSelectors()` - Blog-specific examples
   - `getEcommerceSelectors()` - E-commerce examples
   - `getBusinessSelectors()` - Business examples
   - `getCatalogSelectors()` - Catalog examples
   - `getUniversalSelectors()` - Always included
3. Re-export context

**Example - Adding a custom pattern:**

```php
protected static function getBlogSelectors($t1) {
    return '// ... existing code ...

// YOUR CUSTOM PATTERN
// Get trending posts from last 7 days
$trending = $pages->find("template=post, created>=-7 days, views>50, sort=-views");

';
}
```

## ⚙️ Module Settings

### Site Type Selection

Choose your site type to get customized code snippets:
- Generic / Mixed Content
- Blog / News / Magazine  
- E-commerce / Online Store
- Business / Portfolio / Agency
- Catalog / Directory / Listings

### Content Features

- **Export Content Samples** - Include real page examples
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

## 💡 Use Cases

### 1. AI-Assisted Development

```
# Upload to Claude/ChatGPT:
- prompts/project-context.md
- templates.json
- snippets/selectors.php

# Then ask:
"Create a product filter with price range, category, and brand using 
the patterns from selectors.php"
```

### 2. Onboarding New Developers

Share the `/site/assets/context/` folder with new team members. They get:
- Complete site structure overview
- All templates and field definitions
- Code examples and patterns
- Custom Page classes documentation

### 3. Building APIs

```
# Upload:
- api/schemas/product-schema.json
- snippets/api-examples.php

# Ask:
"Create a REST API endpoint for products with search and filtering"
```

### 4. Site Documentation

Export generates human-readable documentation:
- `structure.txt` - Visual page hierarchy
- `templates.csv` - Spreadsheet of all templates
- `README.md` - Complete documentation

### 5. Code Review & Refactoring

```
# Upload:
- classes.json
- templates.json
- snippets/selectors.php

# Ask:
"Review my custom Page classes and suggest improvements following 
ProcessWire best practices"
```

## 🔧 Auto-Update Feature

Enable **Auto-Update on Changes** to automatically regenerate context when you:
- Create or modify templates
- Add or modify fields
- Change template-field assignments

Hooks into:
- `Templates::saved`
- `Fields::saved`
- `Fieldgroups::saveReady`

## 🎯 Best Practices

### Working with AI Assistants

1. **Always include** `prompts/project-context.md` - contains system instructions
2. **For field questions** - include `templates.json`
3. **For site structure** - include `structure.txt`
4. **For coding** - include `snippets/selectors.php`
5. **For debugging** - include relevant `samples/[template]-samples.json`

### File Upload Strategy

- **Small tasks** (1-3 files): Upload to chat directly
- **Medium tasks** (3-10 files): Core files + specific sections
- **Large projects**: Use Claude Projects with entire `/context/` folder

### When to Re-Export

- After adding/modifying templates
- After adding/modifying fields
- After changing site structure
- After changing Site Type setting
- Before major development sessions

## 🛠️ Technical Details

### Requirements

- ProcessWire 3.x
- PHP 8.1 or higher
- Write permissions for `/site/assets/context/`

### Performance

- Export typically takes 1-3 seconds
- Uses ProcessWire's caching where possible
- Auto-update hooks are lightweight (< 50ms)

### Security

- Exports are stored in `/site/assets/` (protected by ProcessWire)
- No sensitive data (passwords, API keys) is exported
- Only accessible to logged-in superusers by default

### File Locations

- **Module**: `/site/modules/Context/`
- **Exports**: `/site/assets/context/`
- **Snippets Library**: `/site/modules/Context/ContextSnippets.php`

## 🤝 Contributing

Contributions are welcome! Areas for improvement:

- Additional site type templates
- More code snippet examples
- Translations
- Integration with other AI tools
- Documentation improvements

## 📝 License

MIT License - see LICENSE file for details

## 👨‍💻 Author

**Maxim Alex**
- Website: [smnv.org](https://smnv.org)
- Email: maxim@smnv.org
- GitHub: [@mxmsmnv](https://github.com/mxmsmnv)