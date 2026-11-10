<?php
/**
 * Shared helpers for the public site and the admin panel.
 * Requires PHP 7.4 or newer.
 */

define('ROOT_DIR', dirname(__DIR__));
define('DATA_DIR', ROOT_DIR . '/data');
define('UPLOAD_DIR', ROOT_DIR . '/uploads');
define('CONTENT_FILE', DATA_DIR . '/content.json');
define('AUTH_FILE', DATA_DIR . '/auth.php');
define('ATTEMPTS_FILE', DATA_DIR . '/login-attempts.php');

define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);   // 5 MB per image
define('SESSION_IDLE_SECONDS', 2 * 60 * 60);    // log out after 2 hours idle
define('LOGIN_MAX_ATTEMPTS', 5);                // failed logins allowed…
define('LOGIN_LOCK_SECONDS', 15 * 60);          // …before a 15 minute lock

// Image paths a product may use: the bundled photos or admin uploads.
define('IMAGE_PATH_PATTERN', '~^(assets/products|uploads)/[A-Za-z0-9._-]+\.(jpe?g|png|webp|gif)$~i');

// Production error handling: never show PHP errors to visitors; log them privately instead.
// (data/ is blocked from the web, and .log files are blocked by .htaccess as well.)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (is_dir(DATA_DIR) && is_writable(DATA_DIR)) {
    ini_set('error_log', DATA_DIR . '/php-errors.log');
}
date_default_timezone_set('Asia/Kolkata');

// Fallbacks in case the host's PHP lacks the mbstring extension.
if (!function_exists('mb_substr')) {
    function mb_substr($s, $start, $length = null, $enc = null) {
        preg_match_all('/./us', (string) $s, $m);
        return implode('', array_slice($m[0], $start, $length));
    }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower($s, $enc = null) { return strtolower((string) $s); }
}

/** Largest image upload this server accepts, in bytes (the smaller of our limit and PHP's limits). */
function upload_limit_bytes(): int
{
    $toBytes = function ($v): int {
        $v = trim((string) $v);
        if ($v === '' || $v === '-1' || $v === '0') {
            return PHP_INT_MAX;
        }
        $n = (int) $v;
        switch (strtolower(substr($v, -1))) {
            case 'g': $n *= 1024;
            // no break
            case 'm': $n *= 1024;
            // no break
            case 'k': $n *= 1024;
        }
        return $n;
    };
    // Leave room in post_max_size for the rest of the request.
    $post = $toBytes(ini_get('post_max_size'));
    return (int) min(MAX_UPLOAD_BYTES, $toBytes(ini_get('upload_max_filesize')), $post === PHP_INT_MAX ? PHP_INT_MAX : $post - 64 * 1024);
}

function format_bytes(int $bytes): string
{
    return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : round($bytes / 1024) . ' KB';
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ---------- File storage ---------- */

function write_file_atomic(string $path, string $data): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Cannot create folder ' . basename($dir) . '.');
    }
    $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (file_put_contents($tmp, $data, LOCK_EX) === false || !rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Cannot write ' . basename($path) . '. Check that the data folder is writable (permissions 755).');
    }
}

/** Private data is stored inside a .php file that exits immediately if requested over the web. */
const PRIVATE_HEADER = "<?php http_response_code(404); exit; ?>\n";

function read_private(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }
    $raw = (string) file_get_contents($path);
    if (strpos($raw, PRIVATE_HEADER) === 0) {
        $raw = substr($raw, strlen(PRIVATE_HEADER));
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function write_private(string $path, array $data): void
{
    write_file_atomic($path, PRIVATE_HEADER . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/* ---------- Site content ---------- */

function default_content(): array
{
    return [
        'panel' => [
            'eyebrow' => 'Equipment range',
            'title' => 'Dust collection equipment for industry.',
            'intro' => '',
            'cardLabel' => 'RANGE',
            'showFilters' => true,
            'showCategoryOnCards' => true,
            'spotlight' => [
                'show' => false, 'kicker' => '', 'title' => '', 'text' => '',
                'linkText' => 'Explore the equipment range', 'image' => '', 'alt' => '',
            ],
        ],
        'categories' => [],
        'products' => [],
    ];
}

function load_content(): array
{
    $data = is_file(CONTENT_FILE) ? json_decode((string) file_get_contents(CONTENT_FILE), true) : null;
    if (!is_array($data)) {
        return default_content();
    }
    try {
        return sanitize_content($data, false);
    } catch (InvalidArgumentException $ex) {
        return default_content();
    }
}

function save_content(array $content): void
{
    write_file_atomic(CONTENT_FILE, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
}

/* ---------- Validation ---------- */

function clean_text($value, int $max, bool $multiline = false): string
{
    $text = is_scalar($value) ? (string) $value : '';
    $text = $multiline ? str_replace("\r\n", "\n", $text) : preg_replace('/\s+/u', ' ', $text);
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $text);
    $text = trim((string) $text);
    return mb_substr($text, 0, $max, 'UTF-8');
}

function clean_bool($value): bool
{
    return $value === true || $value === 1 || $value === '1' || $value === 'true';
}

function clean_image($value, bool $mustExist): string
{
    $path = clean_text($value, 200);
    if ($path === '') {
        return '';
    }
    if (!preg_match(IMAGE_PATH_PATTERN, $path)) {
        throw new InvalidArgumentException('Invalid image path: ' . $path);
    }
    if ($mustExist && !is_file(ROOT_DIR . '/' . $path)) {
        throw new InvalidArgumentException('Image file not found: ' . $path);
    }
    return $path;
}

function clean_link($value): string
{
    $link = clean_text($value, 500);
    if ($link === '') {
        return '';
    }
    // Allow web, email and phone links, or a relative path on this site. Blocks javascript: and similar.
    if (preg_match('~^(https?://|mailto:|tel:)~i', $link) || !preg_match('~^[^/?#]*:~', $link)) {
        return $link;
    }
    throw new InvalidArgumentException('Links must start with https://, http://, mailto: or tel:');
}

function clean_id($value, string $prefix): string
{
    $id = is_string($value) ? strtolower($value) : '';
    if (!preg_match('/^[a-z0-9_-]{1,40}$/', $id)) {
        $id = $prefix . bin2hex(random_bytes(5));
    }
    return $id;
}

/**
 * Validate and normalise a full content document.
 * @param bool $strict  true when saving from the admin (also checks that image files exist)
 */
function sanitize_content(array $in, bool $strict = true): array
{
    $p = is_array($in['panel'] ?? null) ? $in['panel'] : [];
    $s = is_array($p['spotlight'] ?? null) ? $p['spotlight'] : [];

    $out = ['panel' => [
        'eyebrow' => clean_text($p['eyebrow'] ?? '', 80),
        'title' => clean_text($p['title'] ?? '', 160),
        'intro' => clean_text($p['intro'] ?? '', 600, true),
        'cardLabel' => clean_text($p['cardLabel'] ?? '', 20),
        'showFilters' => clean_bool($p['showFilters'] ?? false),
        'showCategoryOnCards' => clean_bool($p['showCategoryOnCards'] ?? false),
        'spotlight' => [
            'show' => clean_bool($s['show'] ?? false),
            'kicker' => clean_text($s['kicker'] ?? '', 80),
            'title' => clean_text($s['title'] ?? '', 160),
            'text' => clean_text($s['text'] ?? '', 600, true),
            'linkText' => clean_text($s['linkText'] ?? '', 80),
            'image' => clean_image($s['image'] ?? '', $strict),
            'alt' => clean_text($s['alt'] ?? '', 200),
        ],
    ], 'categories' => [], 'products' => []];

    $categoryIds = [];
    foreach (array_slice(is_array($in['categories'] ?? null) ? $in['categories'] : [], 0, 100) as $c) {
        if (!is_array($c)) {
            continue;
        }
        $name = clean_text($c['name'] ?? '', 80);
        if ($name === '') {
            throw new InvalidArgumentException('Every category needs a name.');
        }
        $id = clean_id($c['id'] ?? '', 'c');
        if (isset($categoryIds[$id])) {
            $id = clean_id('', 'c');
        }
        $categoryIds[$id] = true;
        $out['categories'][] = ['id' => $id, 'name' => $name];
    }

    $productIds = [];
    $slugs = [];
    foreach (array_slice(is_array($in['products'] ?? null) ? $in['products'] : [], 0, 1000) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = clean_text($item['name'] ?? '', 150);
        if ($name === '') {
            throw new InvalidArgumentException('Every product needs a name.');
        }
        $id = clean_id($item['id'] ?? '', 'p');
        if (isset($productIds[$id])) {
            $id = clean_id('', 'p');
        }
        $productIds[$id] = true;
        $category = is_string($item['category'] ?? null) ? $item['category'] : '';

        // Page address: from the admin's slug, or from the product name. Must be unique.
        $slug = slugify(clean_text($item['slug'] ?? '', 80)) ?: slugify($name) ?: $id;
        $base = $slug;
        for ($n = 2; isset($slugs[$slug]); $n++) {
            $slug = $base . '-' . $n;
        }
        $slugs[$slug] = true;

        $gallery = [];
        foreach (array_slice(is_array($item['gallery'] ?? null) ? $item['gallery'] : [], 0, 12) as $img) {
            $img = clean_image($img, $strict);
            if ($img !== '' && !in_array($img, $gallery, true)) {
                $gallery[] = $img;
            }
        }

        $specs = [];
        foreach (array_slice(is_array($item['specs'] ?? null) ? $item['specs'] : [], 0, 40) as $row) {
            $label = clean_text($row['label'] ?? '', 60);
            $value = clean_text($row['value'] ?? '', 200);
            if ($label !== '' || $value !== '') {
                $specs[] = ['label' => $label, 'value' => $value, 'highlight' => clean_bool($row['highlight'] ?? false)];
            }
        }

        $features = [];
        foreach (array_slice(is_array($item['features'] ?? null) ? $item['features'] : [], 0, 12) as $row) {
            $title = clean_text($row['title'] ?? '', 100);
            $text = clean_text($row['text'] ?? '', 400, true);
            if ($title !== '' || $text !== '') {
                $features[] = ['title' => $title, 'text' => $text];
            }
        }

        $apps = $item['applications'] ?? [];
        if (is_string($apps)) {
            $apps = explode(',', $apps);
        }
        $applications = [];
        foreach (array_slice(is_array($apps) ? $apps : [], 0, 20) as $tag) {
            $tag = clean_text($tag, 60);
            if ($tag !== '' && !in_array($tag, $applications, true)) {
                $applications[] = $tag;
            }
        }

        $out['products'][] = [
            'id' => $id,
            'slug' => $slug,
            'name' => $name,
            'category' => isset($categoryIds[$category]) ? $category : '',
            'description' => clean_text($item['description'] ?? '', 1000, true),
            'overview' => clean_text($item['overview'] ?? '', 5000, true),
            'image' => clean_image($item['image'] ?? '', $strict),
            'alt' => clean_text($item['alt'] ?? '', 200),
            'gallery' => $gallery,
            'specs' => $specs,
            'features' => $features,
            'applications' => $applications,
            'link' => clean_link($item['link'] ?? ''),
            'visible' => clean_bool($item['visible'] ?? true),
        ];
    }
    return $out;
}

function slugify(string $text): string
{
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }
    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($text)), '-');
    return substr($slug, 0, 80);
}

/* ---------- URLs ---------- */

/** Base path of the site, e.g. "/" or "/subfolder/". Works for scripts in the site root. */
function base_url(): string
{
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    return rtrim($dir, '/') . '/';
}

function url(string $path = ''): string
{
    return base_url() . ltrim($path, '/');
}

/** True when the server rewrites /products/<slug> (set in .htaccess, or by router.php locally). */
function pretty_urls(): bool
{
    return defined('PRETTY_URLS')
        || getenv('PRETTY_URLS') || getenv('REDIRECT_PRETTY_URLS')
        || !empty($_SERVER['PRETTY_URLS']) || !empty($_SERVER['REDIRECT_PRETTY_URLS']);
}

/** Path (relative to the site root) of a product's page. */
function product_path(array $product): string
{
    return pretty_urls() ? 'products/' . $product['slug'] : 'product.php?p=' . rawurlencode($product['slug']);
}

function absolute_url(string $path): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return (is_https() ? 'https://' : 'http://') . $host . url($path);
}

/** Remove uploaded images no longer used anywhere (older than 1 hour, so in-progress edits are safe). */
function cleanup_uploads(array $content): void
{
    $used = [$content['panel']['spotlight']['image'] => true];
    foreach ($content['products'] as $item) {
        $used[$item['image']] = true;
        foreach ($item['gallery'] as $img) {
            $used[$img] = true;
        }
    }
    foreach (glob(UPLOAD_DIR . '/*') ?: [] as $file) {
        if (!preg_match('/\.(jpe?g|png|webp|gif)$/i', $file)) {
            continue;   // never touch .htaccess or anything else
        }
        if (!isset($used['uploads/' . basename($file)]) && filemtime($file) < time() - 3600) {
            @unlink($file);
        }
    }
}

/* ---------- Sessions & authentication ---------- */

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

function start_admin_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('dc_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();

    if (!empty($_SESSION['admin']) && (time() - ($_SESSION['last_seen'] ?? 0)) > SESSION_IDLE_SECONDS) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_seen'] = time();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ?? '';
}

function check_csrf(?string $token): bool
{
    return is_string($token) && csrf_token() !== '' && hash_equals(csrf_token(), $token);
}

function admin_account(): ?array
{
    $auth = read_private(AUTH_FILE);
    return ($auth && !empty($auth['email']) && !empty($auth['hash'])) ? $auth : null;
}

function save_admin_account(string $email, string $password): void
{
    write_private(AUTH_FILE, [
        'email' => $email,
        'hash' => password_hash($password, PASSWORD_DEFAULT),
        'updated' => date('c'),
    ]);
}

function is_logged_in(): bool
{
    $account = admin_account();
    return $account && !empty($_SESSION['admin'])
        && hash_equals((string) ($_SESSION['admin_key'] ?? ''), substr($account['hash'], -16));
}

/** The visitor's IP address. Behind a tunnel/proxy on this same machine, use the forwarded address. */
function client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (in_array($ip, ['127.0.0.1', '::1'], true)) {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR'] as $header) {
            $forwarded = trim(explode(',', (string) ($_SERVER[$header] ?? ''))[0]);
            if (filter_var($forwarded, FILTER_VALIDATE_IP)) {
                return $forwarded;
            }
        }
    }
    return $ip !== '' ? $ip : 'unknown';
}

function client_key(): string
{
    return hash('sha256', client_ip());
}

function login_locked_for(): int
{
    $attempts = read_private(ATTEMPTS_FILE) ?? [];
    $entry = $attempts[client_key()] ?? null;
    if (!$entry || $entry['count'] < LOGIN_MAX_ATTEMPTS) {
        return 0;
    }
    return max(0, ($entry['time'] + LOGIN_LOCK_SECONDS) - time());
}

function record_login_failure(): void
{
    $attempts = read_private(ATTEMPTS_FILE) ?? [];
    $now = time();
    foreach ($attempts as $key => $entry) {   // forget old entries
        if ($entry['time'] + LOGIN_LOCK_SECONDS < $now) {
            unset($attempts[$key]);
        }
    }
    $key = client_key();
    $attempts[$key] = ['count' => ($attempts[$key]['count'] ?? 0) + 1, 'time' => $now];
    write_private(ATTEMPTS_FILE, $attempts);
}

function clear_login_failures(): void
{
    $attempts = read_private(ATTEMPTS_FILE) ?? [];
    unset($attempts[client_key()]);
    write_private(ATTEMPTS_FILE, $attempts);
}

function attempt_login(string $email, string $password): bool
{
    $account = admin_account();
    $ok = $account
        && hash_equals(strtolower($account['email']), strtolower($email))
        && password_verify($password, $account['hash']);
    if (!$ok) {
        return false;
    }
    if (password_needs_rehash($account['hash'], PASSWORD_DEFAULT)) {
        save_admin_account($account['email'], $password);
        $account = admin_account();
    }
    session_regenerate_id(true);
    $_SESSION['admin'] = true;
    $_SESSION['admin_key'] = substr($account['hash'], -16);   // a password change logs out other sessions
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function send_admin_headers(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
}
