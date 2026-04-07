# Context Module - AI Agent Integration

This document explains how AI coding agents (Claude Code, Cursor, Windsurf, etc.) can work with the ProcessWire Context module.

## Overview

The Context module provides **two ways** for AI agents to access ProcessWire site information:

1. **CLI Commands** - Query and export site data from command line
2. **File-based Context** - Read exported JSON/TOON files

## Quick Start

### 1. Export site context
```bash
php index.php --context-export
```

This creates a complete snapshot of your ProcessWire site in the configured export directory (default: `site/assets/cache/context/`).

### 2. Query specific information
```bash
# List all templates
php index.php --context-query templates

# List all fields
php index.php --context-query fields

# List pages
php index.php --context-query pages "template=product, limit=10"

# Show statistics
php index.php --context-stats
```

## CLI Commands Reference

### Export Commands

| Command | Description |
|---------|-------------|
| `php index.php --context-export` | Full export (JSON + TOON formats) |
| `php index.php --context-export --toon-only` | Export only TOON format (30-60% fewer tokens) |
| `php index.php --context-export --json-only` | Export only JSON format |

### Query Commands

| Command | Description |
|---------|-------------|
| `php index.php --context-query templates` | List all templates with field counts |
| `php index.php --context-query fields` | List all fields with types |
| `php index.php --context-query pages [selector]` | List pages (with optional PW selector) |

### Stats Commands

| Command | Description |
|---------|-------------|
| `php index.php --context-stats` | Show module statistics and configuration |
| `php index.php --context-help` | Display CLI help |

## File-based Context

After running `--context-export`, you'll have these files available:

### Core Files (Always Generated)

- **structure.json / structure.toon** - Complete page tree
- **structure.txt** - ASCII tree visualization
- **templates.json / templates.toon** - All templates with field definitions
- **templates.csv** - Templates in CSV format
- **tree.json / tree.toon** - Combined structure + templates + fields
- **config.json / config.toon** - Site configuration
- **modules.json / modules.toon** - Installed modules
- **README.md** - Documentation
- **SKILL.md** - Skill definition for AI agents

### Optional Files (Based on Settings)

- **samples/** - Real page content examples
- **api/** - REST API schemas and endpoints
- **snippets/** - Code examples and patterns
- **prompts/** - Prompt templates for manual use
- **metadata/** - Technical metadata (field definitions, routes, performance)

### TOON Format

TOON (Token-Oriented Object Notation) is **highly recommended** for AI agents:
- 30-60% fewer tokens than JSON
- Same data, more compact format
- Faster to process
- Lower API costs

**Always prefer .toon files over .json when available!**

## Workflow Examples

### Scenario 1: Understanding Site Structure

```bash
# Get quick overview
php index.php --context-stats

# See all templates
php index.php --context-query templates

# Export full context for deeper analysis
php index.php --context-export --toon-only

# Then read: structure.toon, templates.toon, tree.toon
```

### Scenario 2: Working with Specific Template

```bash
# Query pages of specific template
php index.php --context-query pages "template=product"

# Export samples for real data
php index.php --context-export

# Then read: samples/product-samples.toon
```

### Scenario 3: Building New Feature

```bash
# Export full context
php index.php --context-export

# Read SKILL.md first (tells you what files are available)
# Then read relevant files based on task:
# - templates.toon for field structure
# - structure.toon for page hierarchy
# - snippets/ for code patterns
```

## API Variable Access

In ProcessWire code, you can access the Context module via the `$context` API variable:

```php
// Get Context module instance
$context = wire('context');

// Programmatic export
$context->executeExport();

// Get export path
$path = $context->getContextPath();
```

## Best Practices

### For AI Agents

1. **Start with stats** - Run `--context-stats` to understand the site
2. **Use TOON format** - Always prefer .toon over .json files
3. **Read SKILL.md first** - It lists all available files
4. **Query before full export** - Use `--context-query` for quick lookups
5. **Export once per session** - Reuse files unless structure changes

### For Developers

1. **Re-export after changes** - Run `--context-export` after modifying templates/fields
2. **Use custom export path** - Set path outside webroot for production (e.g., `/home/user/context/`)
3. **Enable auto-update** - Module can auto-export on template/field changes
4. **Version control migrations** - Commit context exports to track structure changes

## Integration with Other Tools

### Cline (PHPStorm/VSCode)

Export to `.agents/skills/context/` and SKILL.md will be auto-discovered.

### Junie (PHPStorm)

Export to `.junie/skills/docs/` for automatic integration.

### Claude Code

Point Claude to your export directory and it can read all files directly.

### Session Continuity

The module creates `prompts/project-summary.md` template. Ask your AI agent to update this file at the end of each session to maintain context between sessions.

## Troubleshooting

### "command not found: php"

Make sure PHP is in your PATH, or use full path:
```bash
/usr/bin/php index.php --context-export
```

### "Permission denied"

Ensure export directory is writable:
```bash
chmod 755 site/assets/cache/context/
```

### "Module not found"

Make sure you're running from ProcessWire root directory (where index.php is located).

## Security Notes

- CLI commands require shell access to the server
- Export directory is protected with .htaccess (Apache) or should be outside webroot (Nginx)
- Never commit sensitive data (passwords, API keys) in exported files
- For production, use absolute export path outside document root

## More Information

- **Module Settings**: Admin → Setup → Modules → Context → Configure
- **GitHub**: https://github.com/mxmsmnv/Context
- **ProcessWire Forum**: Search for "Context Module"

---

**Generated for**: Context Module v1.3.0  
**Compatible with**: Claude Code, Cursor, Windsurf, Copilot, and other AI coding agents
