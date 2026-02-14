<?php namespace ProcessWire;

/**
 * Context Module - Snippets Templates Library
 * 
 * This file contains all code snippet templates for different site types.
 * Edit this file to customize or add new snippet patterns.
 * 
 * @version 1.0.0
 */

class ContextSnippets {
    
    /**
     * Get selectors snippet for site type
     * 
     * @param string $siteType Site type (blog, ecommerce, business, catalog, generic)
     * @param array $templates Available templates from the site
     * @return string PHP code snippet
     */
    public static function getSelectorsSnippet($siteType, $templates) {
        // Use first 3 real templates
        $t1 = $templates[0] ?? 'page';
        $t2 = $templates[1] ?? 'article';
        $t3 = $templates[2] ?? 'category';
        
        $allTemplates = implode(', ', array_slice($templates, 0, 5));
        
        $snippet = '<?php namespace ProcessWire;

/**
 * ProcessWire Selectors Library
 * Site type: ' . $siteType . '
 * Available templates: ' . $allTemplates . '
 */

// ==================== BASIC QUERIES ====================

// Get all published pages
$items = $pages->find("template=' . $t1 . ', status!=hidden");

// Get single page by name
$page = $pages->get("template=' . $t1 . ', name=page-name");

// Get multiple templates
$items = $pages->find("template=' . $t1 . '|' . $t2 . '");

// ==================== SEARCH ====================

$query = $sanitizer->text($input->get->q);
$results = $pages->find("template=' . $t1 . ', title|summary%=$query");

';
        
        // Add type-specific sections
        $snippet .= self::getTypeSpecificSelectors($siteType, $t1, $t2, $t3);
        
        // Add universal examples
        $snippet .= self::getUniversalSelectors($t1);
        
        return $snippet;
    }
    
    /**
     * Get type-specific selectors
     */
    protected static function getTypeSpecificSelectors($siteType, $t1, $t2, $t3) {
        $snippets = [
            'blog' => self::getBlogSelectors($t1),
            'ecommerce' => self::getEcommerceSelectors($t1),
            'business' => self::getBusinessSelectors($t1),
            'catalog' => self::getCatalogSelectors($t1, $t3)
        ];
        
        return $snippets[$siteType] ?? '';
    }
    
    /**
     * Blog-specific selectors
     */
    protected static function getBlogSelectors($t1) {
        return '// ==================== BLOG SPECIFIC ====================

// Get recent posts
$recent = $pages->find("template=post|article, sort=-created, limit=10");

// Get posts by author
$author = $pages->get("template=author, id=$authorId");
$posts = $pages->find("template=post, author=$author, sort=-created");

// Get posts by category
$category = $pages->get("template=category, id=$catId");
$posts = $pages->find("template=post, categories=$category");

// Get posts by tag
$posts = $pages->find("template=post, tags=$tagId");

// Get posts by date range
$posts = $pages->find("template=post, created>=2024-01-01, created<2024-12-31");

// Get posts by year and month
$posts = $pages->find("template=post, created>=2024-03-01, created<2024-04-01");

// Get featured/sticky posts
$featured = $pages->find("template=post, featured=1, sort=-created, limit=5");

// Get popular posts (by views/comments)
$popular = $pages->find("template=post, views>100, sort=-views, limit=10");

// Get posts with specific status
$drafts = $pages->find("template=post, status=unpublished");
$scheduled = $pages->find("template=post, publish_date>=' . time() . '");

// Search in posts
$query = $sanitizer->text($input->get->q);
$results = $pages->find("template=post, title|body|summary~=$query, limit=20");

// Related posts (same category, exclude current)
$post = $pages->get($id);
$related = $pages->find("template=post, categories=$post->categories, id!=$id, limit=6");

// Archives - get unique years
$years = $pages->find("template=post")->explode(function($p) {
    return date("Y", $p->created);
});
$years = array_unique($years);

';
    }
    
    /**
     * E-commerce specific selectors
     */
    protected static function getEcommerceSelectors($t1) {
        return '// ==================== E-COMMERCE SPECIFIC ====================

// Get products in price range
$products = $pages->find("template=product, price>=$minPrice, price<=$maxPrice");

// Get products in stock
$inStock = $pages->find("template=product, stock>0, status=1");

// Get out of stock products
$outOfStock = $pages->find("template=product, stock=0");

// Get products on sale
$onSale = $pages->find("template=product, sale_price>0, sort=-sale_percentage");

// Get featured/bestseller products
$featured = $pages->find("template=product, featured=1, limit=8");
$bestsellers = $pages->find("template=product, sort=-sales_count, limit=12");

// Get new arrivals (last 30 days)
$newProducts = $pages->find("template=product, created>=-30 days, sort=-created");

// Get products by category
$category = $pages->get("template=category, id=$catId");
$products = $pages->find("template=product, categories=$category");

// Get products by brand
$brand = $pages->get("template=brand, id=$brandId");
$products = $pages->find("template=product, brand=$brand");

// Get products by attributes
$products = $pages->find("template=product, color=$colorId, size=$sizeId");

// Search products
$query = $sanitizer->text($input->get->q);
$results = $pages->find("template=product, title|sku|description~=$query");

// Filter products by rating
$topRated = $pages->find("template=product, rating>=4, sort=-rating");

// Get related/similar products
$product = $pages->get($id);
$related = $pages->find("template=product, categories=$product->categories, id!=$id, limit=6");
$similarPrice = $pages->find("template=product, price>=$product->price*0.8, price<=$product->price*1.2, id!=$id");

// Get cart items for user
$cart = $pages->find("template=cart_item, user=$user->id");

// Get user orders
$orders = $pages->find("template=order, user=$user->id, sort=-created");
$pendingOrders = $pages->find("template=order, user=$user->id, status=pending");

// Get products with reviews
$reviewed = $pages->find("template=product, reviews.count>0");

';
    }
    
    /**
     * Business specific selectors
     */
    protected static function getBusinessSelectors($t1) {
        return '// ==================== BUSINESS SPECIFIC ====================

// Get active services
$services = $pages->find("template=service, status=1, sort=sort");

// Get services by category
$category = $pages->get("template=service-category, id=$catId");
$services = $pages->find("template=service, category=$category");

// Get portfolio projects
$portfolio = $pages->find("template=project, status=1, sort=-created");
$featured = $pages->find("template=project, featured=1, limit=6");

// Get projects by category/industry
$category = $pages->get("template=category, id=$catId");
$projects = $pages->find("template=project, category=$category");

// Get projects by client
$client = $pages->get("template=client, id=$clientId");
$projects = $pages->find("template=project, client=$client");

// Get team members
$team = $pages->find("template=team, status=1, sort=sort");
$leadership = $pages->find("template=team, role=leadership, sort=sort");
$department = $pages->find("template=team, department=$deptId");

// Get testimonials/reviews
$testimonials = $pages->find("template=testimonial, status=1, sort=-created, limit=10");
$featured = $pages->find("template=testimonial, featured=1, limit=3");

// Get case studies
$cases = $pages->find("template=case-study, status=1, sort=-created");
$byIndustry = $pages->find("template=case-study, industry=$industryId");

// Get clients/partners
$clients = $pages->find("template=client, status=1, sort=title");
$partners = $pages->find("template=partner, featured=1");

// Get blog posts (if business has blog)
$posts = $pages->find("template=post, sort=-created, limit=5");

// Get office locations
$locations = $pages->find("template=location, status=1, sort=sort");
$country = $pages->find("template=location, country=$countryCode");

// Get events/webinars
$events = $pages->find("template=event, event_date>=' . time() . ', sort=event_date");
$upcoming = $pages->find("template=event, event_date>=' . time() . ', limit=5");

// Get job openings
$jobs = $pages->find("template=job, status=open, sort=-created");
$byDepartment = $pages->find("template=job, department=$deptId, status=open");

';
    }
    
    /**
     * Catalog specific selectors
     */
    protected static function getCatalogSelectors($t1, $t3) {
        return '// ==================== CATALOG SPECIFIC ====================

// Get items by parent/category
$parent = $pages->get("template=category|brand, id=$parentId");
$items = $pages->find("template=' . $t1 . ', parent=$parent");

// Get all items under category (including subcategories)
$category = $pages->get("template=category, id=$catId");
$items = $pages->find("template=' . $t1 . ', has_parent=$category");

// Get items by brand
$brand = $pages->get("template=brand, id=$brandId");
$items = $pages->find("template=' . $t1 . ', brand=$brand, sort=title");

// Get items by country/region
$country = $pages->get("template=country, id=$countryId");
$items = $pages->find("template=' . $t1 . ', country=$country");
$region = $pages->get("template=region, id=$regionId");
$items = $pages->find("template=' . $t1 . ', region=$region");

// Get items by type/category
$type = $pages->get("template=type, id=$typeId");
$items = $pages->find("template=' . $t1 . ', type=$type");

// Get items alphabetically
$items = $pages->find("template=' . $t1 . ', sort=title");
$startWithA = $pages->find("template=' . $t1 . ', title^=A, sort=title");

// Get featured items
$featured = $pages->find("template=' . $t1 . ', featured=1, limit=10");

// Get recently added items
$recent = $pages->find("template=' . $t1 . ', sort=-created, limit=20");

// Get items with specific attributes
$items = $pages->find("template=' . $t1 . ', vintage=$year");
$items = $pages->find("template=' . $t1 . ', rating>=4");

// Search across catalog
$query = $sanitizer->text($input->get->q);
$results = $pages->find("template=' . $t1 . ', title|description~=$query");

// Get related items
$item = $pages->get($id);
$related = $pages->find("template=' . $t1 . ', brand=$item->brand, id!=$id, limit=6");
$sameCat = $pages->find("template=' . $t1 . ', parent=$item->parent, id!=$id");

// Get all brands
$brands = $pages->find("template=brand, sort=title");
$popularBrands = $pages->find("template=brand, items.count>0, sort=-items.count");

// Get all categories
$categories = $pages->find("template=category, sort=title");
$topLevel = $pages->find("template=category, parent.template=home");

// Get filtering options
$countries = $pages->find("template=country, sort=title");
$types = $pages->find("template=type, sort=title");
$regions = $pages->find("template=region, sort=title");

';
    }
    
    /**
     * Universal selectors (always included)
     */
    protected static function getUniversalSelectors($t1) {
        return '// ==================== SORTING & FILTERING ====================

// Sort by date
$items = $pages->find("template=' . $t1 . ', sort=-created, limit=10");
$oldest = $pages->find("template=' . $t1 . ', sort=created");

// Sort alphabetically
$items = $pages->find("template=' . $t1 . ', sort=title");

// Sort by custom field
$items = $pages->find("template=' . $t1 . ', sort=sort");
$items = $pages->find("template=' . $t1 . ', sort=-views");

// Multiple categories (OR)
$items = $pages->find("template=' . $t1 . ', category=$cat1|$cat2");

// Items with images
$items = $pages->find("template=' . $t1 . ', images.count>0");

// Items without images
$items = $pages->find("template=' . $t1 . ', images.count=0");

// Date range filtering
$items = $pages->find("template=' . $t1 . ', created>=2024-01-01, created<2024-12-31");

// Exclude items
$items = $pages->find("template=' . $t1 . ', id!=$excludeId1|$excludeId2");

// Field is not empty
$items = $pages->find("template=' . $t1 . ', summary!=\'\'");

// ==================== PAGINATION ====================

// Basic pagination
$items = $pages->find("template=' . $t1 . ', limit=20");
$pagination = $items->renderPager();

// Manual pagination
$limit = 20;
$start = ($input->pageNum - 1) * $limit;
$items = $pages->find("template=' . $t1 . ', limit=$limit, start=$start");

// Get total count
$total = $items->getTotal();
$totalPages = ceil($total / $limit);

// ==================== RELATIONSHIPS ====================

// Get children
$children = $page->children("template=' . $t1 . ', limit=10");
$allChildren = $page->children("template=' . $t1 . '");

// Get specific child
$child = $page->child("name=about");

// Get siblings
$siblings = $page->siblings("template=' . $t1 . '");

// Get next/previous
$next = $page->next();
$prev = $page->prev();

// Get parent
$parent = $page->parent();

// Get all parents
$parents = $page->parents();

// Get descendants (all children recursively)
$descendants = $page->find("template=' . $t1 . '");

// ==================== ADVANCED ====================

// Array syntax
$items = $pages->find([
    \'template\' => \'' . $t1 . '\',
    \'status\' => 1,
    \'sort\' => \'title\',
    \'limit\' => 20
]);

// Subselectors
$items = $pages->find("template=' . $t1 . ', parent=[template=category, name=news]");

// Has parent check
$items = $pages->find("template=' . $t1 . ', has_parent=$categoryPage");

// Get by IDs
$ids = [1, 2, 3, 4];
$items = $pages->find("id=" . implode("|", $ids));

// Full-text search
$query = $sanitizer->text($input->get->q);
$results = $pages->find("template=' . $t1 . ', title|body~=$query");

// ==================== COUNTING ====================

// Count without loading
$count = $pages->count("template=' . $t1 . '");
$activeCount = $pages->count("template=' . $t1 . ', status=1");

// Check if exists
$exists = $pages->count("template=' . $t1 . ', name=$name") > 0;

// Count from PageArray
$items = $pages->find("template=' . $t1 . ', limit=20");
$loaded = $items->count();  // Items in memory
$total = $items->getTotal(); // Total matching

// ==================== FINDING SINGLE ITEMS ====================

// Get by ID
$page = $pages->get($id);

// Get by name
$page = $pages->get("template=' . $t1 . ', name=about");

// Get by path
$page = $pages->get("/about/team/");

// Find one (more efficient than get for selectors)
$page = $pages->findOne("template=' . $t1 . ', featured=1");

// Get or create
$page = $pages->get("template=' . $t1 . ', name=$name");
if(!$page->id) {
    $page = new Page();
    $page->template = "' . $t1 . '";
    $page->parent = $parentPage;
    $page->name = $name;
    $page->save();
}
';
    }
    
    /**
     * Get helpers snippet (universal for all site types)
     */
    public static function getHelpersSnippet() {
        return '<?php namespace ProcessWire;

/**
 * Helper Functions Library
 * Common utility functions
 */

// ==================== PAGE HELPERS ====================

/**
 * Safely get page title with fallback
 */
function getPageTitle(Page $page, $default = \'Untitled\') {
    return $page->title ?: $default;
}

/**
 * Get first image from a page
 */
function getFirstImage(Page $page, $fieldName = \'images\', $width = null) {
    $images = $page->get($fieldName);
    if(!$images || !$images->count()) return null;
    
    $image = $images->first();
    return $width ? $image->width($width) : $image;
}

/**
 * Get breadcrumbs array
 */
function getBreadcrumbs(Page $page, $includeHome = false) {
    $breadcrumbs = [];
    
    foreach($page->parents() as $parent) {
        if($parent->id === 1 && !$includeHome) continue;
        $breadcrumbs[] = [
            \'title\' => $parent->title,
            \'url\' => $parent->url
        ];
    }
    
    $breadcrumbs[] = [
        \'title\' => $page->title,
        \'url\' => $page->url,
        \'current\' => true
    ];
    
    return $breadcrumbs;
}

// ==================== TEXT HELPERS ====================

/**
 * Create excerpt from text
 */
function getExcerpt($text, $length = 150, $suffix = \'...\') {
    $text = strip_tags($text);
    $text = preg_replace(\'/\s+/\', \' \', $text);
    $text = trim($text);
    
    if(mb_strlen($text) <= $length) return $text;
    
    $text = mb_substr($text, 0, $length);
    $lastSpace = mb_strrpos($text, \' \');
    if($lastSpace !== false) {
        $text = mb_substr($text, 0, $lastSpace);
    }
    
    return $text . $suffix;
}

/**
 * Format date in human readable format
 */
function formatDate($timestamp, $format = \'F j, Y\') {
    return date($format, $timestamp);
}

/**
 * Time ago format
 */
function timeAgo($timestamp) {
    $diff = time() - $timestamp;
    
    if($diff < 60) return \'just now\';
    if($diff < 3600) return floor($diff / 60) . \' minutes ago\';
    if($diff < 86400) return floor($diff / 3600) . \' hours ago\';
    if($diff < 604800) return floor($diff / 86400) . \' days ago\';
    
    return date(\'M j, Y\', $timestamp);
}

// ==================== URL HELPERS ====================

/**
 * Build query string
 */
function buildQueryString($params) {
    return http_build_query($params);
}

/**
 * Get current URL with query string
 */
function getCurrentUrl() {
    return wire(\'page\')->httpUrl . ($_SERVER[\'QUERY_STRING\'] ? \'?\' . $_SERVER[\'QUERY_STRING\'] : \'\');
}

/**
 * Check if current page
 */
function isCurrentPage(Page $page) {
    return wire(\'page\')->id === $page->id;
}

/**
 * Check if in current section
 */
function isCurrentSection(Page $page) {
    $current = wire(\'page\');
    return $current->id === $page->id || $current->parents->has($page);
}

// ==================== IMAGE HELPERS ====================

/**
 * Get responsive image sizes
 */
function getResponsiveImage(Pageimage $image, $sizes = []) {
    $srcset = [];
    foreach($sizes as $width) {
        $resized = $image->width($width);
        $srcset[] = "{$resized->url} {$width}w";
    }
    return implode(\', \', $srcset);
}

/**
 * Get image with fallback
 */
function getImageOrPlaceholder(Page $page, $fieldName = \'images\', $width = null) {
    $image = getFirstImage($page, $fieldName, $width);
    if($image) return $image;
    
    return wire(\'config\')->urls->templates . \'assets/placeholder.jpg\';
}

// ==================== FORM HELPERS ====================

/**
 * Sanitize input
 */
function sanitizeInput($value, $type = \'text\') {
    $sanitizer = wire(\'sanitizer\');
    
    switch($type) {
        case \'email\': return $sanitizer->email($value);
        case \'url\': return $sanitizer->url($value);
        case \'int\': return $sanitizer->int($value);
        case \'text\':
        default: return $sanitizer->text($value);
    }
}

/**
 * Get sanitized input
 */
function getInput($name, $type = \'text\', $default = \'\') {
    $input = wire(\'input\');
    $value = $input->get($name) ?: $input->post($name);
    return $value ? sanitizeInput($value, $type) : $default;
}

// ==================== ARRAY HELPERS ====================

/**
 * Pluck field from PageArray
 */
function pluck($pageArray, $field) {
    $result = [];
    foreach($pageArray as $p) {
        $result[] = $p->get($field);
    }
    return $result;
}

/**
 * Group pages by field
 */
function groupBy($pageArray, $field) {
    $result = [];
    foreach($pageArray as $p) {
        $key = $p->get($field);
        if(!isset($result[$key])) $result[$key] = [];
        $result[$key][] = $p;
    }
    return $result;
}
';
    }
    
    /**
     * Get API examples snippet for site type
     */
    public static function getApiExamplesSnippet($siteType, $templates) {
        $t1 = $templates[0] ?? 'page';
        
        return '<?php namespace ProcessWire;

/**
 * API Examples for ' . $siteType . ' site
 */

// GET /api/' . $t1 . '/
function listItems() {
    $items = pages()->find("template=' . $t1 . ', limit=50");
    
    $data = [];
    foreach($items as $item) {
        $data[] = [
            \'id\' => $item->id,
            \'title\' => $item->title,
            \'url\' => $item->httpUrl
        ];
    }
    
    header(\'Content-Type: application/json\');
    echo json_encode([\'success\' => true, \'data\' => $data]);
}

// GET /api/' . $t1 . '/{id}
function getItem($id) {
    $item = pages()->get("template=' . $t1 . ', id=$id");
    
    if(!$item->id) {
        http_response_code(404);
        echo json_encode([\'error\' => \'Not found\']);
        return;
    }
    
    $data = [
        \'id\' => $item->id,
        \'title\' => $item->title,
        \'url\' => $item->httpUrl
    ];
    
    header(\'Content-Type: application/json\');
    echo json_encode([\'success\' => true, \'data\' => $data]);
}

// Search API
function search() {
    $query = input()->get->text(\'q\');
    $results = pages()->find("template=' . $t1 . ', title%=$query, limit=20");
    
    header(\'Content-Type: application/json\');
    echo json_encode([\'success\' => true, \'data\' => $results]);
}
';
    }
}