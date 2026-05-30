<?php namespace ProcessWire;

/**
 * Generates prompt template content for exported Context files.
 */
class ContextPromptTemplates {

    /** @var Context */
    protected $module;

    /** @var callable */
    protected $call;

    public function __construct(Context $module) {
        $this->module = $module;
        $this->call = \Closure::bind(function($method, ...$args) {
            return $this->$method(...$args);
        }, $module, get_class($module));
    }

    public function generateProjectContext() {
        $m = $this->module;
        $homePage = $m->pages->get('/');
        $contextPath = rtrim($m->getContextPath(), '/');
        $structureFile = $this->invoke('contextFileName', 'structure');
        $templatesFile = $this->invoke('contextFileName', 'templates');
        $samplesPattern = $this->invoke('contextFileName', 'samples/*-samples');
        $stack = $this->invoke('detectFrontendStack');
        $routes = $this->invoke('getRouteMap');
        $access = $this->invoke('getAccessMap');

        $stats = [];
        foreach($m->templates as $template) {
            if($template->flags & Template::flagSystem) continue;
            $count = $m->pages->count("template={$template->name}");
            if($count > 100) {
                $stats[] = "- **{$template->label}**: {$count} " . strtolower($template->label ?: $template->name);
            }
        }

        $phpVersion = phpversion();

        $toonInfo = '';
        if($m->export_toon_format && !$m->isJsonExportEnabled()) {
            $toonInfo = <<<TOON

## 📊 Export Format

This site's context was generated in **TOON-only mode**. Prefer `.toon` resources; JSON/CSV artifacts are not present in this export.

TOON;
        } elseif($m->export_toon_format) {
            $toonInfo = <<<TOON

## 📊 Export Formats

This site's context is available in two formats:

**TOON Format (.toon files) - RECOMMENDED FOR AI**
- Token-Oriented Object Notation
- 30-60% fewer tokens than JSON
- Optimized for AI assistants (Claude, ChatGPT, etc.)
- Same data, significantly smaller size
- Example: `templates.toon`, `structure.toon`, `samples/*.toon`

**JSON Format (.json files) - For Development**
- Standard JSON for APIs, tools, IDEs
- Example: `templates.json`, `structure.json`

**💡 Use .toon files for AI interactions to save tokens and reduce API costs!**

TOON;
        }

        $md = <<<MD
# SYSTEM PROMPT: ProcessWire Expert Mode

You are an expert developer for this specific ProcessWire instance.
{$toonInfo}

## Project Overview
**Site**: {$homePage->title}
**URL**: {$m->config->httpHost}
**ProcessWire Version**: {$m->config->version}
**PHP Version**: {$phpVersion}

## 🎨 Frontend & Design
**Tech Stack**: {$stack}

MD;

        if(!empty($routes)) {
            $md .= "\n## 🛣 Route Map (URL Segments)\n";
            foreach($routes as $name => $info) {
                $md .= "- **Template '{$name}':** Allows {$info['max_segments']} segments ({$info['segments_allowed']})\n";
            }
        }

        if(!empty($access)) {
            $md .= "\n## 🔐 Access Control (Roles & Permissions)\n";
            foreach($access as $role => $info) {
                $perms = implode(', ', $info['permissions']);
                $md .= "- **Role '{$role}':** [{$perms}]\n";
            }
        }

        $md .= "\n## Key Statistics\n";
        $md .= implode("\n", $stats);

        $md .= <<<MD


## Technical Stack
- **CMS**: ProcessWire {$m->config->version}
- **PHP**: {$phpVersion}
- **Database**: MySQL
- **Admin**: {$m->config->urls->admin}

## Content Organization
This site uses ProcessWire's flexible template system. See `{$contextPath}/structure.txt` for the complete page tree.

## Important Patterns

### Getting Pages
```php
// Single page
\$product = \$pages->get("template=product, name=product-slug");

// Multiple pages
\$products = \$pages->find("template=product, limit=20");

// With relationships
\$products = \$pages->find("template=product, brand=\$brandId");
```

### Working with Fields
```php
// Text fields
echo \$page->title;
echo \$page->summary;

// Page references
\$brand = \$page->brand; // Returns Page object
echo \$brand->title;

// Images
foreach(\$page->images as \$img) {
    echo "<img src='{\$img->url}' alt='{\$img->description}'>";
}
```

MD;

        $md .= <<<MD

## Common Tasks

1. **Listing Pages**: Use `\$pages->find()` with appropriate selectors
2. **Creating Pages**: Instantiate `new Page()`, set template and parent
3. **Search**: Use `title|summary%=\$query` for text search
4. **Pagination**: Use `limit` and `start` in selectors

## File References

**Core Files (TOON format recommended for AI):**
- **Structure**: 
  - `{$contextPath}/{$structureFile}` - Complete page tree
  - `{$contextPath}/structure.txt` - ASCII visualization
- **Templates**: 
  - `{$contextPath}/{$templatesFile}` - All templates with fields
- **Samples**: 
  - `{$contextPath}/{$samplesPattern}` - Real content examples
- **Snippets**: `{$contextPath}/snippets/` - Code examples

MD;

        if($m->isJsonExportEnabled() && $m->export_api_docs) {
            $md .= "- **API Docs**: `{$contextPath}/api/` - API schemas and endpoints\n";
        }

        $md .= <<<MD

**💡 Pro Tip**: Always prefer .toon files over .json when available - they contain the same data but use significantly fewer tokens!

## Notes
- Always sanitize user input using `\$sanitizer`
- Use ProcessWire's built-in URL functions
- Implement caching for heavy queries
- Keep selectors efficient

For detailed information, explore the files in `{$contextPath}/` directory.

MD;

        if($m->custom_ai_instructions) {
            $md .= "\n## 📝 Custom Project Instructions\n\n";
            $md .= $m->custom_ai_instructions . "\n";
        }

        return $md;
    }

    public function generateCreateTemplatePrompt() {
        $contextPath = rtrim($this->module->getContextPath(), '/');
        $templatesFile = $this->invoke('contextFileName', 'templates');

        return <<<MD
# Create ProcessWire Template - AI Prompt

I need help creating a new ProcessWire template.

## Template Details

**Template Name**: [e.g., "winery", "event", "award"]

**Purpose**: [Describe what this template is for]

**Label**: [Human-readable label]

## Fields Required

### Basic Fields
- title (FieldtypePageTitle) - required
- [Add other fields...]

### Custom Fields
List any special fields needed:
1. [field_name] - [field_type] - [description]
2. ...

## Relationships
- Parent template: [which template contains these pages?]
- Child templates: [can this have children? which templates?]
- Page references: [connects to which other templates?]

## URL Structure
- Example URL: `/section/page-name/`
- Parent path: [e.g., /events/]

## Example Data
Provide 1-2 examples of pages that would use this template.

---

## Files to Reference
When generating the template, review:
- `{$contextPath}/{$templatesFile}` - existing field patterns
- `{$contextPath}/structure.txt` - site structure
- `{$contextPath}/snippets/selectors.php` - query examples

## Expected Output
Please generate:
1. Template file code (`templates/template-name.php`)
2. Field creation code or instructions
3. Example page creation code
4. Common selectors for querying these pages
MD;
    }

    public function generateCreateApiPrompt() {
        $contextPath = rtrim($this->module->getContextPath(), '/');

        return <<<MD
# Create ProcessWire API Endpoint - AI Prompt

I need to create a REST API endpoint for ProcessWire.

## API Endpoint Details

**Endpoint**: `/api/[resource]/[action]`

**Method**: GET / POST / PUT / DELETE

**Purpose**: [What this endpoint does]

## Request

### URL Parameters
- [param1]: [description]
- [param2]: [description]

### Query Parameters
- limit: [number of results]
- page: [page number]
- [custom params...]

### POST Body (if applicable)
```json
{
  "field1": "value",
  "field2": "value"
}
```

## Response

### Success Response (200)
```json
{
  "success": true,
  "data": {}
}
```

### Error Response (4xx/5xx)
```json
{
  "error": "Error message"
}
```

## Authentication
- Required: Yes / No
- Method: [Session, API Key, etc.]

## Example Use Cases
1. [Use case 1]
2. [Use case 2]

---

## Files to Reference
- `{$contextPath}/api/endpoints.json` - existing API endpoints
- `{$contextPath}/api/schemas/` - data schemas
- `{$contextPath}/snippets/api-examples.php` - code patterns

## Expected Output
Please generate:
1. Complete PHP endpoint code
2. Example request/response
3. Error handling
4. Authentication checks (if needed)
MD;
    }

    public function generateDebugPrompt() {
        $contextPath = rtrim($this->module->getContextPath(), '/');
        $configFile = $this->invoke('contextFileName', 'config');
        $templatesFile = $this->invoke('contextFileName', 'templates');

        return <<<MD
# Debug ProcessWire Issue - AI Prompt

I'm experiencing an issue with my ProcessWire site.

## Problem Description
[Describe the issue in detail]

## What I'm Trying to Do
[What are you trying to accomplish?]

## Current Code
```php
// Paste your code here
```

## Error Messages
```
[Paste any error messages here]
```

## Expected Behavior
[What should happen?]

## Actual Behavior
[What is actually happening?]

## Environment
- ProcessWire Version: [check `{$contextPath}/{$configFile}`]
- PHP Version: [check `{$contextPath}/{$configFile}`]
- Template: [which template is affected?]

## What I've Tried
1. [Thing 1]
2. [Thing 2]

---

## Files to Reference
- `{$contextPath}/{$templatesFile}` - template/field structure
- `{$contextPath}/snippets/` - code examples
- `{$contextPath}/structure.txt` - page tree

## Expected Help
Please provide:
1. Explanation of the issue
2. Fixed code
3. Alternative approaches
4. Best practices to avoid this in future
MD;
    }

    public function generateProjectSummaryTemplate() {
        return <<<'TEMPLATE'
# Project Summary

This file helps AI agents remember context between sessions. Update this file at the end of each coding session so AI can continue where you left off.

---

## Rules

- Be concise and factual
- Do not explain reasoning unless critical
- Do not invent anything not discussed
- Prefer clarity over completeness
- Keep bullet points short and actionable
- Remove duplication when updating
- Preserve existing headings unless a new one is clearly needed

## File Instructions

- Update this file in place (do not overwrite)
- Do not add any commentary outside the file contents
- Only modify content below the boundary line
- Do not change any text above the boundary line

---

#### DO NOT UPDATE ANYTHING ABOVE THIS LINE ####

---

## Project
- (One line description of what you're building)

## Current State
- 

## Decisions Made
- 

## Known Issues
- 

## What We Tried
- 

## Constraints
- 

## Next Steps
1. 
2. 
3. 

## Do NOT Do
- 

---

**Last Updated:** (AI will update this automatically)

TEMPLATE;
    }

    protected function invoke($method, ...$args) {
        return ($this->call)($method, ...$args);
    }
}
