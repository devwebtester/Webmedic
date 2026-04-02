<?php

function loadStoreConfig($storeId) {
    $envFile = __DIR__ . "/.env.{$storeId}";

    if (!file_exists($envFile)) {
        return null;
    }

    $config = [];
    $lines  = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $config[trim($parts[0])] = trim($parts[1]);
        }
    }

    return $config;
}

function getStoreIdFromRequest() {
    // Priority 1: explicit query param
    if (!empty($_GET['store'])) {
        return $_GET['store'];
    }

    // Priority 2: environment variable (single-store deployments)
    if ($id = getenv('STORE_ID')) {
        return $id;
    }

    // Priority 3: path prefix (/tpt/webhook) — checked before subdomain
    // because hosting platforms like onrender.com look like valid subdomains
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (preg_match('#^/([^/]+)/?#', $path, $m)) {
        $skip = ['webhook', 'settings', 'health', 'logs', 'api', 'admin', 'check-now', 'test-email', 'debug-config'];
        if (!in_array($m[1], $skip)) {
            return $m[1];
        }
    }

    // Priority 4: subdomain (tpt.yourdomain.com)
    // Only use this if the subdomain actually matches a configured store,
    // so hosting domains (product-brand-php.onrender.com) are never mistaken
    // for a store ID.
    if (isset($_SERVER['HTTP_HOST'])) {
        $parts = explode('.', $_SERVER['HTTP_HOST']);
        if (count($parts) >= 3 && !in_array($parts[0], ['www', 'api', 'admin'])) {
            $subdomain      = $parts[0];
            $knownStores    = getAvailableStores();
            if (in_array($subdomain, $knownStores)) {
                return $subdomain;
            }
        }
    }

    return null;
}

function getAvailableStores() {
    $stores = [];
    foreach (glob(__DIR__ . '/.env.*') as $f) {
        $stores[] = str_replace('.env.', '', basename($f));
    }
    return $stores;
}

// ── Bootstrap ────────────────────────────────────────────────
$storeId = getStoreIdFromRequest();

if (!$storeId) {
    http_response_code(400);
    die(json_encode([
        'error' => 'Store identifier required',
        'hint'  => 'Use ?store=STORE_ID',
        'examples' => ['query' => '?store=tpt', 'path' => '/tpt/webhook']
    ]));
}

$envConfig = loadStoreConfig($storeId);

if (!$envConfig) {
    http_response_code(404);
    die(json_encode([
        'error'     => "No config found for store: $storeId",
        'available' => getAvailableStores()
    ]));
}

$BRANDS_FILE = __DIR__ . "/brands_{$storeId}.json";
$STATE_FILE  = __DIR__ . "/notification_state_{$storeId}.json";
$LOG_FILE    = __DIR__ . "/inventory_monitor_{$storeId}.log";
$LOCK_DIR    = __DIR__ . "/locks";

if (!is_dir($LOCK_DIR)) {
    mkdir($LOCK_DIR, 0755, true);
}

$CONFIG = [
    'STORE_ID'               => $storeId,
    'SHOPIFY_SHOP'           => $envConfig['SHOPIFY_SHOP']            ?? null,
    'SHOPIFY_SHOP_NAME'      => $envConfig['SHOPIFY_SHOP_NAME']       ?? null,
    'SHOPIFY_ACCESS_TOKEN'   => $envConfig['SHOPIFY_ACCESS_TOKEN']    ?? null,
    'SHOPIFY_WEBHOOK_SECRET' => $envConfig['SHOPIFY_WEBHOOK_SECRET']  ?? null,
    'EMAIL_FROM'             => $envConfig['EMAIL_FROM']              ?? null,
    'EMAIL_TO'               => $envConfig['EMAIL_TO']                ?? null,
    'SENDGRID_API_KEY'       => $envConfig['SENDGRID_API_KEY']        ?? null,
    'SETTINGS_PASSWORD'      => $envConfig['SETTINGS_PASSWORD']       ?? 'admin123',
    'BRANDS_TO_MONITOR'      => loadBrands(),
];

foreach (['SHOPIFY_SHOP', 'SHOPIFY_ACCESS_TOKEN', 'SHOPIFY_WEBHOOK_SECRET', 'EMAIL_FROM', 'EMAIL_TO'] as $v) {
    if (empty($CONFIG[$v])) {
        http_response_code(500);
        die(json_encode(['error' => "Missing required config: $v"]));
    }
}

// ── Brands ───────────────────────────────────────────────────
function loadBrands() {
    global $BRANDS_FILE;
    if (file_exists($BRANDS_FILE)) {
        $data = json_decode(file_get_contents($BRANDS_FILE), true);
        return $data['brands'] ?? getDefaultBrands();
    }
    return getDefaultBrands();
}

function saveBrands($brands) {
    global $BRANDS_FILE;
    file_put_contents($BRANDS_FILE, json_encode(['brands' => $brands], JSON_PRETTY_PRINT), LOCK_EX);
}

function getDefaultBrands() {
    return [
        '24 Bottles',
        '360 Degrees Water Bottles',
        'ALIFEDESIGN',
        'Alpaka',
        'Anello',
        'Bagsmart',
        'Bellroy',
        'Black Blaze',
        'Black Ember',
        'Boarding Gate Singapore',
        'BoardingG',
        'Bobby Backpack by XD Design',
        'Bric\'s',
        'Briggs & Riley',
        'C-Secure',
        'Cabeau',
        'CabinZero',
        'Case Logic',
        'Cilocala',
        'Conwood',
        'Crossing',
        'Crossing Wallet',
        'Doughnut',
        'Eagle Creek',
        'Eastpak',
        'Easynap',
        'Echolac',
        'ELECOM',
        'Ember',
        'FLEXTAIL',
        'Fulton Umbrellas',
        'GASTON LUGA',
        'Gift Voucher',
        'Go Girl',
        'Go Travel',
        'Haan Hand Sanitisers',
        'Hellolulu',
        'Heroclip',
        'HEYS',
        'Human Gear',
        'Jansport',
        'July',
        'KeepCup',
        'King Jim',
        'Kinto',
        'KiU',
        'Klean Kanteen',
        'Klipsta',
        'Knirps Umbrellas',
        'Legato Largo',
        'LOQI',
        'Mack\'s Ear Plugs',
        'Made By Fressko',
        'MAH',
        'Miamily',
        'Nalgene Water Bottles',
        'Nifteen',
        'Notabag',
        'O2COOL',
        'Oasis Bottles',
        'Orbitkey Key Organizers',
        'Osprey',
        'Outgear',
        'Pacsafe',
        'Paire',
        'Pitas',
        'Porsche Design',
        'RAWROW',
        'READEREST',
        'Retrokitchen',
        'SACHI',
        'Sandqvist',
        'Sea to Summit',
        'Secrid',
        'Shupatto',
        'SKROSS',
        'Skross',
        'Status Anxiety',
        'Stratic',
        'STTOKE',
        'The Coopedia',
        'THULE',
        'Thule',
        'Travel Smart',
        'Tropicfeel',
        'True Utility',
        'Ubiqua',
        'Varigrip Hand Exerciser',
        'Wacaco',
        'WPC',
    ];
}

// ── Logging ───────────────────────────────────────────────────
// Concise: one meaningful line per event, not per product.
function logMessage($message, $level = 'INFO') {
    global $LOG_FILE, $CONFIG;
    $line = sprintf("[%s] [%s] [%s] %s\n", date('Y-m-d H:i:s'), $CONFIG['STORE_ID'], $level, $message);
    file_put_contents($LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

// ── State ────────────────────────────────────────────────────
// ── State file locking strategy ──────────────────────────────
// Problem: simultaneous webhooks for DIFFERENT vendors both read the state
// file, each add their own vendor's state, then both write back — whichever
// writes last silently overwrites the other's update. This causes state loss.
//
// Solution: a single global state lock per store. Each webhook process:
//   1. Does the slow Shopify API call FIRST (no lock held yet)
//   2. Acquires the global state lock (fast, just file I/O)
//   3. Re-reads state (gets the freshest copy)
//   4. Writes updated state
//   5. Releases lock
//
// A separate per-vendor dedup lock (with TTL) prevents the OOS storm problem
// where 50 webhooks for the SAME vendor all try to run at once.

function acquireStateLock() {
    global $LOCK_DIR, $storeId;
    $lockFile = $LOCK_DIR . '/' . $storeId . '_state.lock';
    $fp = fopen($lockFile, 'c');
    if (!$fp) return false;
    // Blocking lock — wait up to ~5s for other processes to finish their write
    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return false;
    }
    return $fp;
}

function releaseStateLock($fp) {
    if ($fp) {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function loadState() {
    global $STATE_FILE;
    if (file_exists($STATE_FILE)) {
        $raw   = file_get_contents($STATE_FILE);
        $state = json_decode($raw, true);
        if ($state === null) {
            logMessage("State file JSON decode failed: $STATE_FILE", 'ERROR');
            return [];
        }
        return $state;
    }
    return [];
}

function saveState($state) {
    global $STATE_FILE;
    file_put_contents($STATE_FILE, json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);
}

// ── Per-vendor dedup lock (OOS storm protection) ──────────────
// When a vendor goes fully OOS, Shopify fires one webhook per variant —
// potentially 50-100 near-simultaneous requests for the same vendor.
// The first one processes; the rest bail instantly within the TTL window.
// TTL of 10s is enough to collapse the storm without blocking real changes.
function acquireVendorLock($vendor, $ttlSeconds = 10) {
    global $LOCK_DIR, $storeId;
    $lockFile = $LOCK_DIR . '/' . $storeId . '_' . md5($vendor) . '.lock';

    $fp = fopen($lockFile, 'c');
    if (!$fp) return false;

    // Non-blocking: if another process already holds it, skip immediately
    if (!flock($fp, LOCK_EX | LOCK_NB)) {
        fclose($fp);
        return false;
    }

    // If this vendor was processed recently (within TTL), skip
    if (filesize($lockFile) > 0 && (time() - filemtime($lockFile)) < $ttlSeconds) {
        flock($fp, LOCK_UN);
        fclose($fp);
        return false;
    }

    fwrite($fp, date('c'));
    fflush($fp);
    return $fp;
}

function releaseVendorLock($fp) {
    if ($fp) {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

// ── HTTP helper ───────────────────────────────────────────────
function makeRequest($url, $method = 'GET', $headers = [], $data = null) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);

    if ($method === 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }

    $response   = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr    = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        logMessage("cURL error [$method $url]: $curlErr", 'ERROR');
    }

    return [
        'code'    => $httpCode,
        'headers' => substr($response, 0, $headerSize),
        'body'    => substr($response, $headerSize),
    ];
}

// ── GraphQL: resolve vendor from inventory item ──────────────
function getVendorFromInventoryItem($inventoryItemId) {
    global $CONFIG;

    $query = 'query($id: ID!) {
        inventoryItem(id: $id) {
            variant {
                product {
                    vendor
                    title
                }
            }
        }
    }';

    $resp = makeRequest(
        "{$CONFIG['SHOPIFY_SHOP']}/admin/api/2025-10/graphql.json",
        'POST',
        [
            "X-Shopify-Access-Token: {$CONFIG['SHOPIFY_ACCESS_TOKEN']}",
            "Content-Type: application/json"
        ],
        json_encode([
            'query'     => $query,
            'variables' => ['id' => "gid://shopify/InventoryItem/$inventoryItemId"]
        ])
    );

    if ($resp['code'] !== 200) {
        logMessage("GraphQL vendor lookup failed (HTTP {$resp['code']}) for inventory_item_id=$inventoryItemId", 'ERROR');
        return null;
    }

    $data = json_decode($resp['body'], true);

    if (!empty($data['errors'])) {
        logMessage("GraphQL errors for inventory_item_id=$inventoryItemId: " . json_encode($data['errors']), 'ERROR');
        return null;
    }

    return [
        'vendor' => $data['data']['inventoryItem']['variant']['product']['vendor'] ?? null,
        'title'  => $data['data']['inventoryItem']['variant']['product']['title']  ?? null,
    ];
}

// ── GraphQL: efficient vendor stock check ────────────────────
// Uses Shopify's `totalInventory` field — a pre-computed sum across
// ALL variants and locations per product. No variant looping needed.
// A vendor with 500 products takes 2 GraphQL pages instead of
// 500+ REST product fetches + thousands of variant iterations.
function checkVendorStockGraphQL($vendor) {
    global $CONFIG;

    $query = 'query($q: String!, $cursor: String) {
        products(first: 250, after: $cursor, query: $q) {
            pageInfo {
                hasNextPage
                endCursor
            }
            edges {
                node {
                    totalInventory
                }
            }
        }
    }';

    $vendorQuery   = "vendor:'" . addslashes($vendor) . "'";
    $totalProducts = 0;
    $oosProducts   = 0;
    $cursor        = null;
    $pages         = 0;

    do {
        $pages++;
        $resp = makeRequest(
            "{$CONFIG['SHOPIFY_SHOP']}/admin/api/2025-10/graphql.json",
            'POST',
            [
                "X-Shopify-Access-Token: {$CONFIG['SHOPIFY_ACCESS_TOKEN']}",
                "Content-Type: application/json"
            ],
            json_encode([
                'query'     => $query,
                'variables' => ['q' => $vendorQuery, 'cursor' => $cursor]
            ])
        );

        if ($resp['code'] !== 200) {
            logMessage("Stock check GraphQL failed (HTTP {$resp['code']}) for '$vendor' page $pages", 'ERROR');
            break;
        }

        $data = json_decode($resp['body'], true);

        if (!empty($data['errors'])) {
            logMessage("Stock check GraphQL errors for '$vendor': " . json_encode($data['errors']), 'ERROR');
            break;
        }

        $products = $data['data']['products'];

        foreach ($products['edges'] as $edge) {
            $totalProducts++;
            if (($edge['node']['totalInventory'] ?? 0) <= 0) {
                $oosProducts++;
            }
        }

        $cursor = $products['pageInfo']['hasNextPage'] ? $products['pageInfo']['endCursor'] : null;

    } while ($cursor !== null);

    $inStockProducts = $totalProducts - $oosProducts;
    $allOOS          = $totalProducts > 0 && $oosProducts === $totalProducts;

    logMessage("Stock check '$vendor': total=$totalProducts inStock=$inStockProducts oos=$oosProducts pages=$pages" . ($allOOS ? ' [ALL OOS]' : ''));

    return [
        'allOOS'          => $allOOS,
        'totalProducts'   => $totalProducts,
        'oosProducts'     => $oosProducts,
        'inStockProducts' => $inStockProducts,
        'pages'           => $pages,
    ];
}

// ── Email ─────────────────────────────────────────────────────
function sendEmail($subject, $message) {
    global $CONFIG;

    if (empty($CONFIG['SENDGRID_API_KEY'])) {
        logMessage("No SendGrid API key configured", 'ERROR');
        return ['success' => false, 'error' => 'No SendGrid key'];
    }

    $recipients = [];
    foreach (array_map('trim', explode(',', $CONFIG['EMAIL_TO'])) as $email) {
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = ['email' => $email];
        }
    }

    if (empty($recipients)) {
        logMessage("No valid email recipients configured", 'ERROR');
        return ['success' => false, 'error' => 'No valid recipients'];
    }

    $safeSubject = htmlspecialchars(preg_replace('/[\x{1F000}-\x{1FFFF}]/u', '', $subject));
    $safeMessage = htmlspecialchars($message);

    $htmlMessage = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h2 style="margin: 0; color: #212529;">{$safeSubject}</h2>
    </div>
    <div style="background: white; padding: 20px; border-radius: 8px; border: 1px solid #dee2e6;">
        <pre style="font-family: 'Courier New', monospace; white-space: pre-wrap; margin: 0; font-size: 14px;">{$safeMessage}</pre>
    </div>
    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d;">
        <p>Automated notification — please do not reply to this email.</p>
    </div>
</body>
</html>
HTML;

    $payload = [
        'personalizations' => [['to' => $recipients]],
        'from'    => ['email' => $CONFIG['EMAIL_FROM'], 'name' => 'Shopify Inventory Monitor'],
        'subject' => $subject,
        'content' => [['type' => 'text/html', 'value' => $htmlMessage]],
        'tracking_settings' => [
            'click_tracking' => ['enable' => false],
            'open_tracking'  => ['enable' => false],
        ],
        'categories' => ['inventory-alert'],
    ];

    $resp = makeRequest(
        'https://api.sendgrid.com/v3/mail/send',
        'POST',
        [
            "Authorization: Bearer {$CONFIG['SENDGRID_API_KEY']}",
            "Content-Type: application/json"
        ],
        json_encode($payload)
    );

    if ($resp['code'] === 202) {
        logMessage("Email sent: $subject");
        return ['success' => true, 'method' => 'sendgrid', 'recipients' => count($recipients)];
    }

    logMessage("SendGrid error (HTTP {$resp['code']}): " . substr($resp['body'], 0, 300), 'ERROR');
    return ['success' => false, 'error' => $resp['body'], 'http_code' => $resp['code']];
}

// ── HMAC verification ─────────────────────────────────────────
function verifyWebhook($data, $hmac) {
    global $CONFIG;
    $calculated = base64_encode(hash_hmac('sha256', $data, $CONFIG['SHOPIFY_WEBHOOK_SECRET'], true));
    return hash_equals($calculated, $hmac);
}

// ── Core: check vendor stock and notify if state changed ──────
// Shared by both /webhook (single vendor) and /check-now (all vendors)
function processVendorStock($vendor) {
    global $CONFIG, $STATE_FILE;

    // ── Step 1: Fetch stock from Shopify BEFORE acquiring the lock ──
    // This is the slow part (~1s). We don't want to hold the state lock
    // while waiting on a network call.
    $stock = checkVendorStockGraphQL($vendor);

    if ($stock['totalProducts'] === 0) {
        logMessage("'$vendor' has 0 products in Shopify — skipping", 'WARNING');
        return ['action' => 'skipped', 'reason' => 'no_products'];
    }

    $newState = $stock['allOOS'] ? 'OOS' : 'IN_STOCK';

    // ── Step 2: Acquire global state lock, then re-read freshest state ──
    $stateLock = acquireStateLock();
    if (!$stateLock) {
        logMessage("Could not acquire state lock for '$vendor' — skipping", 'ERROR');
        return ['action' => 'skipped', 'reason' => 'lock_failed'];
    }

    $state     = loadState();
    $lastState = $state[$vendor] ?? null;
    $action    = 'no_change';

    logMessage("'$vendor' lastState=" . ($lastState ?? 'null') . " newState=$newState | total={$stock['totalProducts']} inStock={$stock['inStockProducts']} oos={$stock['oosProducts']}");

    // ── Step 3: Decide whether to notify ─────────────────────────
    // lastState===null means this vendor has never been seen before.
    // We treat unknown as UNKNOWN — not OOS, not IN_STOCK.
    // Rules:
    //   null  -> OOS      : alert (vendor went OOS, we need to know)
    //   null  -> IN_STOCK : silent (vendor is healthy on first sight, no action needed)
    //   IN_STOCK -> OOS   : alert
    //   OOS -> IN_STOCK   : alert
    //   same -> same      : silent

    $shouldAlertOOS     = ($newState === 'OOS'      && $lastState !== 'OOS');
    $shouldAlertInStock = ($newState === 'IN_STOCK' && $lastState === 'OOS');

    // Always save state first, release lock, THEN send email.
    // This keeps the lock window tiny (just file I/O, not email latency).
    $state[$vendor] = $newState;
    $written = file_put_contents($STATE_FILE, json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);
    releaseStateLock($stateLock);
    $stateLock = null;

    if ($written === false) {
        logMessage("STATE FILE WRITE FAILED: $STATE_FILE — check permissions!", 'ERROR');
    } else {
        logMessage("State saved: '$vendor'=$newState ($written bytes)");
    }

    // ── Step 4: Send notification if state changed ────────────────
    if ($shouldAlertOOS) {
        $isFirst = ($lastState === null);
        logMessage("'$vendor' " . ($isFirst ? "FIRST CHECK — ALL OOS" : "NEWLY ALL OOS") . " — sending alert", 'ALERT');
        sendEmail(
            "ALL {$vendor} Products OUT OF STOCK",
            "Store: {$CONFIG['SHOPIFY_SHOP_NAME']}\n\n" .
            "All {$stock['totalProducts']} products for \"{$vendor}\" are now out of stock.\n\n" .
            "ACTION REQUIRED: Hide this brand from your brand page.\n\n" .
            "Brand          : $vendor\n" .
            "Total Products : {$stock['totalProducts']}\n" .
            "Out of Stock   : {$stock['oosProducts']}\n" .
            "Timestamp      : " . date('c')
        );
        $action = 'oos_alert';

    } elseif ($shouldAlertInStock) {
        logMessage("'$vendor' BACK IN STOCK — sending alert", 'ALERT');
        sendEmail(
            "{$vendor} Products BACK IN STOCK",
            "Store: {$CONFIG['SHOPIFY_SHOP_NAME']}\n\n" .
            "{$stock['inStockProducts']} of {$stock['totalProducts']} products for \"{$vendor}\" are back in stock.\n\n" .
            "ACTION REQUIRED: Show this brand on your brand page.\n\n" .
            "Brand        : $vendor\n" .
            "In Stock     : {$stock['inStockProducts']}\n" .
            "Out of Stock : {$stock['oosProducts']}\n" .
            "Total        : {$stock['totalProducts']}\n" .
            "Timestamp    : " . date('c')
        );
        $action = 'back_in_stock_alert';

    } else {
        $reason = ($lastState === null) ? "first seen as IN_STOCK — no action needed" : "no change ($lastState => $newState)";
        logMessage("'$vendor' $reason — no email");
    }

    return array_merge(['action' => $action, 'state' => $newState], $stock);
}

// ============================================================
// ROUTING
// ============================================================
$requestUri = $_SERVER['REQUEST_URI'];
$method     = $_SERVER['REQUEST_METHOD'];
$rawPath    = parse_url($requestUri, PHP_URL_PATH);

// Strip store ID prefix from path if it was used (/tpt/webhook → /webhook)
$path = $rawPath;
if (preg_match('#^/([^/]+)(/.*)?$#', $rawPath, $matches) && $matches[1] === $storeId) {
    $path = ($matches[2] ?? '') ?: '/';
}

// ── WEBHOOK ──────────────────────────────────────────────────
if ($path === '/webhook' && $method === 'POST') {

    // ── Log every incoming delivery so we can diagnose Shopify removals ──
    // These lines run BEFORE any processing, so even if the code crashes
    // below, we have a record that Shopify reached us.
    $deliveryId  = $_SERVER['HTTP_X_SHOPIFY_WEBHOOK_ID']        ?? 'no-id';
    $shopDomain  = $_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN']       ?? 'unknown';
    $topic       = $_SERVER['HTTP_X_SHOPIFY_TOPIC']             ?? 'unknown';
    $hmac        = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256']       ?? '';
    $contentType = $_SERVER['CONTENT_TYPE']                     ?? 'none';
    $bodyLen     = (int)($_SERVER['CONTENT_LENGTH']             ?? 0);
    $proto       = $_SERVER['SERVER_PROTOCOL']                  ?? 'unknown';
    $scheme      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

    logMessage("WEBHOOK RECEIVED | delivery=$deliveryId shop=$shopDomain topic=$topic method=$method proto=$proto scheme=$scheme content-type=$contentType body-len=$bodyLen has-hmac=" . (!empty($hmac) ? 'yes' : 'NO'));

    $rawBody = file_get_contents('php://input');
    $actualLen = strlen($rawBody);

    logMessage("WEBHOOK BODY READ | declared=$bodyLen actual=$actualLen");

    // ── HMAC verification ────────────────────────────────────
    if (empty($hmac)) {
        // No HMAC at all — log and reject. This happens if URL is hit directly
        // or if a redirect stripped the Shopify headers.
        http_response_code(401);
        logMessage("WEBHOOK REJECTED | reason=missing-hmac-header | This usually means the request went through a redirect that stripped headers. Check for HTTP->HTTPS or trailing-slash redirects on this URL.", 'ERROR');
        echo "Unauthorized";
        exit;
    }

    if (!verifyWebhook($rawBody, $hmac)) {
        http_response_code(401);
        logMessage("WEBHOOK REJECTED | reason=invalid-hmac | Possible causes: wrong webhook secret in .env, or body was modified in transit (e.g. by a proxy).", 'ERROR');
        echo "Unauthorized";
        exit;
    }

    logMessage("WEBHOOK VERIFIED | delivery=$deliveryId sending 200 OK now");

    // ── Respond 200 to Shopify IMMEDIATELY ──────────────────
    // Must happen within 5 seconds or Shopify retries (5min, 10min, 30min).
    // After 19 failures over 48 hours, Shopify removes the webhook entirely.
    // fastcgi_finish_request() closes the HTTP connection while PHP keeps running.
    http_response_code(200);
    header('Content-Type: text/plain');
    echo "OK";

    $respondedAt = microtime(true);

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
        logMessage("WEBHOOK 200 SENT | method=fastcgi_finish_request delivery=$deliveryId");
    } else {
        if (ob_get_level()) ob_end_flush();
        flush();
        logMessage("WEBHOOK 200 SENT | method=flush delivery=$deliveryId | WARNING: fastcgi_finish_request not available — async processing may block");
    }

    // ── Async processing from here ───────────────────────────
    if (empty($rawBody)) {
        logMessage("WEBHOOK EMPTY BODY | delivery=$deliveryId — Shopify test ping, nothing to process");
        exit;
    }

    $data = json_decode($rawBody, true);
    if (!$data || json_last_error() !== JSON_ERROR_NONE) {
        logMessage("WEBHOOK JSON ERROR | " . json_last_error_msg() . " | delivery=$deliveryId", 'ERROR');
        exit;
    }

    $inventoryItemId = $data['inventory_item_id'] ?? null;
    if (!$inventoryItemId) {
        logMessage("WEBHOOK NO INVENTORY_ITEM_ID | delivery=$deliveryId | keys=" . implode(',', array_keys($data)), 'WARNING');
        exit;
    }

    logMessage("WEBHOOK PROCESSING | delivery=$deliveryId inventory_item_id=$inventoryItemId location_id=" . ($data['location_id'] ?? '?') . " available=" . ($data['available'] ?? '?'));

    // Resolve which vendor this inventory item belongs to
    $productInfo = getVendorFromInventoryItem($inventoryItemId);
    if (!$productInfo || !$productInfo['vendor']) {
        logMessage("WEBHOOK VENDOR LOOKUP FAILED | delivery=$deliveryId inventory_item_id=$inventoryItemId", 'ERROR');
        exit;
    }

    $vendor = $productInfo['vendor'];

    // Check if this vendor is on our monitoring list
    if (!in_array($vendor, $CONFIG['BRANDS_TO_MONITOR'], true)) {
        foreach ($CONFIG['BRANDS_TO_MONITOR'] as $b) {
            if (strtolower($b) === strtolower($vendor)) {
                logMessage("WEBHOOK CASE MISMATCH | Shopify='$vendor' config='$b' — fix one of them", 'WARNING');
                break;
            }
        }
        logMessage("WEBHOOK UNMONITORED | vendor='$vendor' delivery=$deliveryId — skipping");
        exit;
    }

    logMessage("Webhook: inventory change for '$vendor' (product: '{$productInfo['title']}')");

    // Dedup: collapse OOS storm (50+ webhooks for same vendor at once)
    $lock = acquireVendorLock($vendor, 10);
    if (!$lock) {
        logMessage("'$vendor' dedup lock active — skipping duplicate webhook");
        exit;
    }

    try {
        processVendorStock($vendor);
    } finally {
        releaseVendorLock($lock);
    }

    $elapsed = round(microtime(true) - $respondedAt, 2);
    logMessage("WEBHOOK DONE | delivery=$deliveryId vendor='$vendor' elapsed={$elapsed}s");
    exit;
}

// ── CHECK NOW (manual inventory check) ───────────────────────
if ($path === '/check-now' && $method === 'GET') {
    header('Content-Type: application/json');

    logMessage("=== MANUAL CHECK STARTED ===");

    $results = [];
    $oosList = [];

    foreach ($CONFIG['BRANDS_TO_MONITOR'] as $brand) {
        $result          = processVendorStock($brand);
        $results[$brand] = $result;
        if (($result['state'] ?? null) === 'OOS') {
            $oosList[] = $brand;
        }
    }

    logMessage("=== MANUAL CHECK DONE: " . count($oosList) . " OOS brand(s) ===");

    echo json_encode([
        'summary' => [
            'totalBrands'      => count($CONFIG['BRANDS_TO_MONITOR']),
            'brandsOutOfStock' => count($oosList),
            'brandsInStock'    => count($CONFIG['BRANDS_TO_MONITOR']) - count($oosList),
            'oosBrands'        => $oosList,
        ],
        'details' => $results,
    ], JSON_PRETTY_PRINT);
    exit;
}

// ── SETTINGS PAGE ────────────────────────────────────────────
if ($path === '/settings' && $method === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brand Monitor Settings — <?php echo htmlspecialchars(strtoupper($storeId)); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
        }
        .container { max-width: 900px; margin: 0 auto; }
        .card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1 { color: #333; margin-bottom: 10px; }
        .subtitle { color: #666; margin-bottom: 30px; }
        .password-form { max-width: 400px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: #333; font-weight: 500; }
        input, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }
        textarea { min-height: 400px; font-family: 'Courier New', monospace; }
        .btn {
            background: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        .btn:hover { background: #0056b3; }
        .btn-secondary { background: #6c757d; margin-left: 10px; }
        .btn-secondary:hover { background: #545b62; }
        .alert { padding: 12px 16px; border-radius: 4px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error   { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info-text { color: #666; font-size: 14px; margin-top: 8px; }
        .brand-count {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 500;
        }
        #brands-container { display: none; }
        .help-text {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Brand Monitor Settings — <?php echo htmlspecialchars(strtoupper($storeId)); ?></h1>
            <p class="subtitle">Manage the brands being monitored for inventory changes</p>

            <div id="password-container">
                <form class="password-form" onsubmit="checkPassword(event)">
                    <div class="form-group">
                        <label for="password">Enter Password:</label>
                        <input type="password" id="password" required autofocus>
                        <p class="info-text">Set via SETTINGS_PASSWORD in your .env file</p>
                    </div>
                    <button type="submit" class="btn">Access Settings</button>
                </form>
            </div>

            <div id="brands-container">
                <div class="help-text">
                    <strong>Instructions:</strong><br>
                    • One brand name per line<br>
                    • Case-sensitive — must match exactly as it appears in Shopify<br>
                    • Remove empty lines before saving<br>
                    • Current count: <span class="brand-count" id="brand-count">0</span>
                </div>

                <div id="message-container"></div>

                <form onsubmit="saveBrands(event)">
                    <div class="form-group">
                        <label for="brands">Monitored Brands (one per line):</label>
                        <textarea id="brands" required></textarea>
                    </div>
                    <button type="submit" class="btn">Save Brands</button>
                    <button type="button" class="btn btn-secondary" onclick="loadBrands()">Reset</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const storeId = <?php echo json_encode($CONFIG['STORE_ID']); ?>;
        let currentPassword = '';

        function checkPassword(e) {
            e.preventDefault();
            currentPassword = document.getElementById('password').value;

            fetch(`/api/verify-password?store=${storeId}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ password: currentPassword })
            })
            .then(r => r.json())
            .then(data => {
                if (data.valid) {
                    document.getElementById('password-container').style.display = 'none';
                    document.getElementById('brands-container').style.display = 'block';
                    loadBrands();
                } else {
                    alert('Invalid password. Please try again.');
                }
            })
            .catch(err => alert('Error: ' + err.message));
        }

        function loadBrands() {
            fetch(`/api/get-brands?store=${storeId}`, {
                headers: { 'X-Password': currentPassword }
            })
            .then(r => r.json())
            .then(data => {
                if (data.brands) {
                    document.getElementById('brands').value = data.brands.join('\n');
                    updateBrandCount();
                }
            })
            .catch(err => alert('Error loading brands: ' + err.message));
        }

        function saveBrands(e) {
            e.preventDefault();
            const brands = document.getElementById('brands').value
                .split('\n')
                .map(b => b.trim())
                .filter(b => b.length > 0);

            if (brands.length === 0) {
                alert('Please enter at least one brand name.');
                return;
            }

            fetch(`/api/save-brands?store=${storeId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Password': currentPassword
                },
                body: JSON.stringify({ brands: brands })
            })
            .then(r => r.json())
            .then(data => {
                const container = document.getElementById('message-container');
                if (data.success) {
                    container.innerHTML = `<div class="alert alert-success">✅ Saved ${data.count} brands successfully.</div>`;
                    updateBrandCount();
                } else {
                    container.innerHTML = `<div class="alert alert-error">❌ Error: ${data.error || 'Unknown error'}</div>`;
                }
                setTimeout(() => container.innerHTML = '', 5000);
            })
            .catch(err => {
                document.getElementById('message-container').innerHTML =
                    `<div class="alert alert-error">❌ Error: ${err.message}</div>`;
            });
        }

        function updateBrandCount() {
            const count = document.getElementById('brands').value
                .split('\n').filter(b => b.trim().length > 0).length;
            document.getElementById('brand-count').textContent = count;
        }

        document.getElementById('brands')?.addEventListener('input', updateBrandCount);
        document.getElementById('password').addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); checkPassword(e); }
        });
    </script>
</body>
</html>
    <?php
    exit;
}

// ── API: verify password ──────────────────────────────────────
if ($path === '/api/verify-password' && $method === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $valid = ($input['password'] ?? '') === $CONFIG['SETTINGS_PASSWORD'];
    echo json_encode(['valid' => $valid]);
    exit;
}

// ── API: get brands ───────────────────────────────────────────
if ($path === '/api/get-brands' && $method === 'GET') {
    header('Content-Type: application/json');
    if (($_SERVER['HTTP_X_PASSWORD'] ?? '') !== $CONFIG['SETTINGS_PASSWORD']) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    echo json_encode(['brands' => $CONFIG['BRANDS_TO_MONITOR']]);
    exit;
}

// ── API: save brands ──────────────────────────────────────────
if ($path === '/api/save-brands' && $method === 'POST') {
    header('Content-Type: application/json');
    if (($_SERVER['HTTP_X_PASSWORD'] ?? '') !== $CONFIG['SETTINGS_PASSWORD']) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $input  = json_decode(file_get_contents('php://input'), true);
    $brands = $input['brands'] ?? [];

    if (empty($brands)) {
        echo json_encode(['success' => false, 'error' => 'No brands provided']);
        exit;
    }

    saveBrands($brands);
    $CONFIG['BRANDS_TO_MONITOR'] = loadBrands();
    logMessage("Brands updated via settings page. New count: " . count($brands));

    echo json_encode(['success' => true, 'count' => count($brands)]);
    exit;
}

// ── HEALTH ────────────────────────────────────────────────────
if ($path === '/health' && $method === 'GET') {
    header('Content-Type: application/json');
    echo json_encode([
        'status'    => 'ok',
        'store'     => $storeId,
        'brands'    => count($CONFIG['BRANDS_TO_MONITOR']),
        'timestamp' => date('c'),
    ]);
    exit;
}

// ── LOGS ──────────────────────────────────────────────────────
if ($path === '/logs' && $method === 'GET') {
    header('Content-Type: text/plain; charset=utf-8');

    if (!file_exists($LOG_FILE)) {
        echo "No log file yet. Logs will appear here once the system starts processing.\n";
        echo "Log file: $LOG_FILE\n";
        exit;
    }

    $lines      = isset($_GET['lines']) ? (int)$_GET['lines'] : 150;
    $allLines   = file($LOG_FILE);
    $totalLines = count($allLines);
    $slice      = array_slice($allLines, max(0, $totalLines - $lines));

    echo "=== [{$storeId}] Last $lines of $totalLines log lines ===\n\n";
    echo implode('', $slice);
    exit;
}

// ── DEBUG CONFIG ──────────────────────────────────────────────
if ($path === '/debug-config' && $method === 'GET') {
    header('Content-Type: application/json');

    echo json_encode([
        'store'   => $storeId,
        'shopify' => [
            'shop'           => $CONFIG['SHOPIFY_SHOP'],
            'access_token'   => substr($CONFIG['SHOPIFY_ACCESS_TOKEN'] ?? '', 0, 10) . '...',
            'webhook_secret' => $CONFIG['SHOPIFY_WEBHOOK_SECRET'] ? 'SET (hidden)' : 'MISSING',
        ],
        'email' => [
            'from'         => $CONFIG['EMAIL_FROM'],
            'to'           => $CONFIG['EMAIL_TO'],
            'sendgrid_key' => $CONFIG['SENDGRID_API_KEY'] ? substr($CONFIG['SENDGRID_API_KEY'], 0, 10) . '...' : 'MISSING',
        ],
        'monitoring' => [
            'brands'       => $CONFIG['BRANDS_TO_MONITOR'],
            'total_brands' => count($CONFIG['BRANDS_TO_MONITOR']),
        ],
        'state' => loadState(),
        'files' => [
            'state_file'  => ['path' => $STATE_FILE,  'exists' => file_exists($STATE_FILE)],
            'brands_file' => ['path' => $BRANDS_FILE, 'exists' => file_exists($BRANDS_FILE)],
            'log_file'    => [
                'path'   => $LOG_FILE,
                'exists' => file_exists($LOG_FILE),
                'size'   => file_exists($LOG_FILE) ? filesize($LOG_FILE) . ' bytes' : 'N/A',
            ],
        ],
        'timestamp'  => date('c'),
        'php_version'=> PHP_VERSION,
        'server'     => [
            'host'    => $_SERVER['HTTP_HOST'] ?? 'unknown',
            'request' => $requestUri,
        ],
    ], JSON_PRETTY_PRINT);
    exit;
}

// ── TEST EMAIL ────────────────────────────────────────────────
if ($path === '/test-email' && $method === 'GET') {
    header('Content-Type: application/json');

    logMessage("Test email requested");

    $result = sendEmail(
        '🧪 Test Email from Shopify Monitor',
        "This is a test email to verify your email configuration is working.\n\n" .
        "Store     : {$CONFIG['SHOPIFY_SHOP_NAME']}\n" .
        "From      : {$CONFIG['EMAIL_FROM']}\n" .
        "To        : {$CONFIG['EMAIL_TO']}\n" .
        "Timestamp : " . date('c') . "\n\n" .
        "If you're seeing this, your email setup is working correctly!"
    );

    echo json_encode($result);
    exit;
}

// ── ADMIN: register webhook ───────────────────────────────────
if ($path === '/admin/register-webhook' && ($method === 'POST' || $method === 'GET')) {
    header('Content-Type: application/json');

    $webhookUrl = "https://{$_SERVER['HTTP_HOST']}/webhook?store={$CONFIG['STORE_ID']}";
    logMessage("Registering webhook: $webhookUrl");

    $resp = makeRequest(
        "{$CONFIG['SHOPIFY_SHOP']}/admin/api/2025-10/webhooks.json",
        'POST',
        [
            "X-Shopify-Access-Token: {$CONFIG['SHOPIFY_ACCESS_TOKEN']}",
            "Content-Type: application/json"
        ],
        json_encode([
            'webhook' => [
                'topic'   => 'inventory_levels/update',
                'address' => $webhookUrl,
                'format'  => 'json'
            ]
        ])
    );

    if ($resp['code'] === 201) {
        logMessage("Webhook registered successfully: $webhookUrl", 'SUCCESS');
        echo json_encode(['success' => true, 'url' => $webhookUrl, 'webhook' => json_decode($resp['body'], true)]);
    } else {
        logMessage("Webhook registration failed: " . $resp['body'], 'ERROR');
        echo json_encode(['success' => false, 'error' => $resp['body']]);
    }
    exit;
}

// ── ADMIN: list webhooks ──────────────────────────────────────
if ($path === '/admin/webhooks' && $method === 'GET') {
    header('Content-Type: application/json');
    $resp = makeRequest(
        "{$CONFIG['SHOPIFY_SHOP']}/admin/api/2025-10/webhooks.json",
        'GET',
        [
            "X-Shopify-Access-Token: {$CONFIG['SHOPIFY_ACCESS_TOKEN']}",
            "Content-Type: application/json"
        ]
    );
    echo $resp['body'];
    exit;
}

// ── ADMIN: delete webhook ─────────────────────────────────────
if ($path === '/admin/delete-webhook' && $method === 'GET') {
    header('Content-Type: application/json');

    $webhookId = $_GET['id'] ?? null;
    if (!$webhookId) {
        echo json_encode(['error' => 'Missing ?id=WEBHOOK_ID']);
        exit;
    }

    logMessage("Deleting webhook ID: $webhookId");

    $resp = makeRequest(
        "{$CONFIG['SHOPIFY_SHOP']}/admin/api/2025-10/webhooks/{$webhookId}.json",
        'DELETE',
        [
            "X-Shopify-Access-Token: {$CONFIG['SHOPIFY_ACCESS_TOKEN']}",
            "Content-Type: application/json"
        ]
    );

    if ($resp['code'] === 200 || $resp['code'] === 204) {
        logMessage("Webhook $webhookId deleted", 'SUCCESS');
        echo json_encode(['success' => true, 'message' => 'Webhook deleted']);
    } else {
        logMessage("Webhook deletion failed: " . $resp['body'], 'ERROR');
        echo json_encode(['success' => false, 'error' => $resp['body']]);
    }
    exit;
}

// ── HOME PAGE ─────────────────────────────────────────────────
if ($path === '/' && $method === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    $availableStores = getAvailableStores();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopify Brand Inventory Monitor</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 640px;
            width: 100%;
        }
        h1 { color: #333; margin-bottom: 10px; font-size: 28px; }
        .subtitle { color: #666; margin-bottom: 30px; font-size: 16px; }
        .store-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .store-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .store-card:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        .store-card h3 {
            font-size: 20px;
            margin-bottom: 15px;
            text-transform: uppercase;
        }
        .store-card a {
            display: block;
            padding: 8px;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 4px;
            margin: 4px 0;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.2s ease;
        }
        .store-card:hover a { background: rgba(255,255,255,0.9); }
        .store-card a:hover { transform: scale(1.03); }
        .info-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .info-section h3 { color: #333; margin-bottom: 10px; font-size: 16px; }
        .endpoint-list { list-style: none; font-size: 13px; color: #666; font-family: 'Courier New', monospace; }
        .endpoint-list li { padding: 4px 0; }
        .status-badge {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏪 Shopify Inventory Monitor <span class="status-badge">ONLINE</span></h1>
        <p class="subtitle">Select a store to manage</p>

        <?php if (empty($availableStores)): ?>
            <div class="info-section" style="background: #fff3cd; border-left: 4px solid #ffc107;">
                <h3 style="color: #856404;">⚠️ No Stores Configured</h3>
                <p style="color: #856404; margin-top: 10px;">
                    No .env files found. Create <code>.env.tpt</code>, <code>.env.bg</code>, etc.
                </p>
            </div>
        <?php else: ?>
            <div class="store-grid">
                <?php foreach ($availableStores as $store): ?>
                    <div class="store-card">
                        <h3><?php echo strtoupper(htmlspecialchars($store)); ?></h3>
                        <a href="/settings?store=<?php echo htmlspecialchars($store); ?>">⚙️ Settings</a>
                        <a href="/health?store=<?php echo htmlspecialchars($store); ?>">💚 Health Check</a>
                        <a href="/check-now?store=<?php echo htmlspecialchars($store); ?>">🔍 Check Now</a>
                        <a href="/test-email?store=<?php echo htmlspecialchars($store); ?>">📧 Test Email</a>
                        <a href="/logs?store=<?php echo htmlspecialchars($store); ?>">📋 Logs</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="info-section">
            <h3>📚 API Endpoints</h3>
            <ul class="endpoint-list">
                <li>POST /webhook?store={store}          — Shopify webhook receiver</li>
                <li>GET  /check-now?store={store}         — Manual check all brands</li>
                <li>GET  /health?store={store}</li>
                <li>GET  /logs?store={store}&amp;lines=150</li>
                <li>GET  /debug-config?store={store}</li>
                <li>GET  /test-email?store={store}</li>
                <li>GET  /settings?store={store}</li>
                <li>GET  /admin/register-webhook?store={store}</li>
                <li>GET  /admin/webhooks?store={store}</li>
                <li>GET  /admin/delete-webhook?store={store}&amp;id={id}</li>
            </ul>
        </div>

        <div class="info-section" style="margin-top: 16px;">
            <h3>🔗 Webhook URLs</h3>
            <p style="font-size: 13px; color: #666; margin-top: 10px;">Register in Shopify → Settings → Notifications → Webhooks:</p>
            <code style="display: block; background: #e9ecef; padding: 10px; border-radius: 4px; font-size: 12px; margin-top: 8px;">
                <?php foreach ($availableStores as $store): ?>
                    <?php echo strtoupper($store); ?>: https://<?php echo htmlspecialchars($_SERVER['HTTP_HOST']); ?>/webhook?store=<?php echo $store; ?><br>
                <?php endforeach; ?>
            </code>
        </div>
    </div>
</body>
</html>
    <?php
    exit;
}

// ── KEEP-ALIVE ───────────────────────────────────────────────
// Set up a free cron at cron-job.org to hit this every 4 minutes:
//   https://your-server.com/ping
// Keeps Render free tier awake so webhooks don't hit a cold-start.
if ($path === '/ping' && $method === 'GET') {
    http_response_code(200);
    header('Content-Type: text/plain');
    echo 'pong ' . date('c');
    exit;
}

// ── DIAGNOSTICS ───────────────────────────────────────────────
// Use this to verify your webhook URL is reachable and configured correctly
// BEFORE registering it in Shopify. Checks everything Shopify cares about.
// Usage: GET /diagnostics?store=tpt
if ($path === '/diagnostics' && $method === 'GET') {
    header('Content-Type: application/json');

    $checks  = [];
    $passed  = 0;
    $failed  = 0;

    // 1. HTTPS check — Shopify requires https://
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ($_SERVER['SERVER_PORT'] ?? '') === '443'
                || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $checks['https'] = [
        'pass'   => $isHttps,
        'detail' => $isHttps ? 'Request arrived over HTTPS' : 'Request arrived over HTTP — Shopify will reject this webhook URL',
    ];

    // 2. Redirect check — test if /webhook path gets a redirect
    // We can't self-test redirects from inside the app, so we explain what to check
    $checks['redirect_note'] = [
        'pass'   => null,
        'detail' => 'Run: curl -sI ' . ($isHttps ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'your-server') . '/webhook?store=' . $storeId . ' | grep -i "HTTP/\|location"' .
                     ' — You should see "HTTP/2 200" or "HTTP/1.1 200", NOT a 301/302. Any redirect will cause Shopify to fail.',
    ];

    // 3. fastcgi_finish_request — critical for async processing
    $hasFcgi = function_exists('fastcgi_finish_request');
    $checks['fastcgi_finish_request'] = [
        'pass'   => $hasFcgi,
        'detail' => $hasFcgi
            ? 'Available — 200 OK will be sent to Shopify before processing starts'
            : 'NOT available — using flush() fallback. If your server doesnt support this, processing may block the response and cause Shopify timeouts. Consider switching to PHP-FPM.',
    ];

    // 4. State file writable
    $stateDir      = dirname($STATE_FILE);
    $stateWritable = is_writable($stateDir);
    $stateExists   = file_exists($STATE_FILE);
    $checks['state_file'] = [
        'pass'   => $stateWritable,
        'detail' => $stateWritable
            ? "State directory writable. File: $STATE_FILE (exists=" . ($stateExists ? 'yes' : 'no, will be created on first webhook') . ")"
            : "State directory NOT writable: $stateDir — state cannot be saved, no emails will ever be sent",
    ];

    // 5. Lock directory writable
    $lockWritable = is_writable($LOCK_DIR);
    $checks['lock_dir'] = [
        'pass'   => $lockWritable,
        'detail' => $lockWritable ? "Lock directory writable: $LOCK_DIR" : "Lock dir NOT writable: $LOCK_DIR — dedup locks will fail",
    ];

    // 6. Webhook secret configured
    $hasSecret = !empty($CONFIG['SHOPIFY_WEBHOOK_SECRET']);
    $checks['webhook_secret'] = [
        'pass'   => $hasSecret,
        'detail' => $hasSecret ? 'Webhook secret is set' : 'SHOPIFY_WEBHOOK_SECRET is empty — all webhooks will be rejected with 401',
    ];

    // 7. Shopify API reachable
    $apiResp = makeRequest(
        "{$CONFIG['SHOPIFY_SHOP']}/admin/api/2025-10/shop.json",
        'GET',
        ["X-Shopify-Access-Token: {$CONFIG['SHOPIFY_ACCESS_TOKEN']}","Content-Type: application/json"]
    );
    $apiOk = $apiResp['code'] === 200;
    $checks['shopify_api'] = [
        'pass'   => $apiOk,
        'detail' => $apiOk ? "Shopify API reachable (HTTP 200)" : "Shopify API returned HTTP {$apiResp['code']} — check your access token",
    ];

    // 8. SendGrid reachable
    $sgResp = makeRequest('https://api.sendgrid.com/v3/user/profile', 'GET', ["Authorization: Bearer {$CONFIG['SENDGRID_API_KEY']}"]);
    $sgOk   = $sgResp['code'] === 200;
    $checks['sendgrid_api'] = [
        'pass'   => $sgOk,
        'detail' => $sgOk ? "SendGrid API reachable (HTTP 200)" : "SendGrid returned HTTP {$sgResp['code']} — check your API key",
    ];

    // 9. Registered webhooks — confirm the URL is correct
    $whResp    = makeRequest("{$CONFIG['SHOPIFY_SHOP']}/admin/api/2025-10/webhooks.json", 'GET',
        ["X-Shopify-Access-Token: {$CONFIG['SHOPIFY_ACCESS_TOKEN']}","Content-Type: application/json"]);
    $whData    = json_decode($whResp['body'], true);
    $webhooks  = $whData['webhooks'] ?? [];
    $myWebhook = null;
    foreach ($webhooks as $wh) {
        if (($wh['topic'] ?? '') === 'inventory_levels/update') {
            $myWebhook = $wh;
            break;
        }
    }
    $checks['registered_webhook'] = [
        'pass'   => $myWebhook !== null,
        'detail' => $myWebhook !== null
            ? "Found: id={$myWebhook['id']} url={$myWebhook['address']} created={$myWebhook['created_at']}"
            : "No inventory_levels/update webhook registered for this store. Run /admin/register-webhook?store=$storeId to create one.",
    ];

    // 10. Redirect self-test — simulate what Shopify sees
    // Do a HEAD request to our own webhook URL to check for redirects
    $webhookUrl  = ($isHttps ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . '/webhook?store=' . $storeId;
    $ch = curl_init($webhookUrl);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY         => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false, // Don't follow — we want to see the redirect
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CUSTOMREQUEST  => 'HEAD',
    ]);
    $headResp = curl_exec($ch);
    $headCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $isRedirect = in_array($headCode, [301, 302, 303, 307, 308]);
    $checks['no_redirect'] = [
        'pass'   => !$isRedirect && $headCode !== 0,
        'detail' => $isRedirect
            ? "HEAD $webhookUrl returned HTTP $headCode (REDIRECT!) — Shopify POSTs do not follow redirects. This is likely why your webhook was removed. Fix: register the final URL, or configure your server to not redirect POST requests."
            : ($headCode === 0
                ? "Could not self-test (curl error) — test manually: curl -sI $webhookUrl"
                : "HEAD returned HTTP $headCode — no redirect detected on GET/HEAD. Note: some servers redirect GET but not POST, so also test with: curl -sI -X POST $webhookUrl"),
    ];

    foreach ($checks as $k => $c) {
        if ($c['pass'] === true)  $passed++;
        if ($c['pass'] === false) $failed++;
    }

    logMessage("Diagnostics run: $passed passed, $failed failed");

    http_response_code($failed > 0 ? 200 : 200); // always 200, let consumer check 'failed'
    echo json_encode([
        'store'   => $storeId,
        'summary' => ['passed' => $passed, 'failed' => $failed, 'warnings' => count($checks) - $passed - $failed],
        'webhook_url_to_register' => "https://{$_SERVER['HTTP_HOST']}/webhook?store=$storeId",
        'checks'  => $checks,
        'timestamp' => date('c'),
    ], JSON_PRETTY_PRINT);
    exit;
}

// ── 404 ───────────────────────────────────────────────────────
http_response_code(404);
echo json_encode(['error' => 'Not Found', 'path' => $path, 'store' => $storeId]);
