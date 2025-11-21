<?php
/**
 * SendGrid Test Script
 * Bu dosyayı çalıştırarak SendGrid kurulumunuzu test edebilirsiniz
 */

// Test email adresinizi buraya yazın veya komut satırından verin
// Kullanım: php test_sendgrid.php email@example.com
$testEmail = isset($argv[1]) && !empty($argv[1]) ? $argv[1] : 'okanvatanci@gmail.com';

echo "========================================\n";
echo "SendGrid Test Script\n";
echo "========================================\n\n";

// 1. Composer kontrolü
echo "1. Composer kontrolü...\n";
$vendorPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($vendorPath)) {
    echo "   ✓ vendor/autoload.php bulundu\n";
    require_once $vendorPath;
} else {
    echo "   ✗ vendor/autoload.php bulunamadı\n";
    echo "   Çözüm: composer install komutunu çalıştırın\n";
    exit(1);
}

// 2. SendGrid kütüphanesi kontrolü
echo "\n2. SendGrid kütüphanesi kontrolü...\n";
if (class_exists('\SendGrid\Mail\Mail')) {
    echo "   ✓ SendGrid kütüphanesi yüklü\n";
} else {
    echo "   ✗ SendGrid kütüphanesi bulunamadı\n";
    echo "   Çözüm: composer install komutunu çalıştırın\n";
    exit(1);
}

// 3. Config dosyası kontrolü
echo "\n3. Config dosyası kontrolü...\n";

// Load .env file manually (without database connection)
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Skip comments
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!empty($key) && !getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
    echo "   ✓ .env dosyası yüklendi\n";
} else {
    echo "   ⚠ .env dosyası bulunamadı\n";
}

// Check for SendGrid API key
$sendgridApiKey = getenv('SENDGRID_API_KEY');
if (empty($sendgridApiKey)) {
    // Try to load from c.php if available
    if (file_exists(__DIR__ . '/__cs/c.php')) {
        // Define WEB_URL if not defined
        if (!defined('WEB_URL')) {
            define('WEB_URL', 'http://localhost:8888/cstar');
        }
        // Try to include but catch database errors
        try {
            require_once __DIR__ . '/__cs/c.php';
            if (defined('SENDGRID_API_KEY') && !empty(SENDGRID_API_KEY)) {
                $sendgridApiKey = SENDGRID_API_KEY;
            }
        } catch (Exception $e) {
            // Ignore database connection errors
        }
    }
}

if (!empty($sendgridApiKey)) {
    echo "   ✓ SENDGRID_API_KEY bulundu\n";
} else {
    echo "   ✗ SENDGRID_API_KEY bulunamadı\n";
    echo "   Çözüm: .env dosyasına SENDGRID_API_KEY ekleyin\n";
    echo "   Örnek: SENDGRID_API_KEY=your_api_key_here\n";
    exit(1);
}

// 4. Email helper kontrolü
echo "\n4. Email helper fonksiyonları kontrolü...\n";

// Define WEB_URL if not defined
if (!defined('WEB_URL')) {
    define('WEB_URL', 'http://localhost:8888/cstar');
}

// Try to load email.php (may need database, but we'll handle it)
if (file_exists(__DIR__ . '/__cs/email.php')) {
    // Create a dummy $conn variable to avoid errors
    $conn = null;
    require_once __DIR__ . '/__cs/email.php';
    
    if (function_exists('sendEmail')) {
        echo "   ✓ sendEmail() fonksiyonu mevcut\n";
    } else {
        echo "   ✗ sendEmail() fonksiyonu bulunamadı\n";
        exit(1);
    }
} else {
    echo "   ✗ __cs/email.php dosyası bulunamadı\n";
    exit(1);
}

// 5. Test email gönderme
echo "\n5. Test email gönderme...\n";
if ($testEmail === 'test@example.com') {
    echo "   ⚠ UYARI: Test email adresini belirtin!\n";
    echo "   Kullanım: php test_sendgrid.php email@example.com\n";
    echo "   Veya test_sendgrid.php dosyasını açın ve \$testEmail değişkenini düzenleyin\n";
    exit(1);
}

echo "   Email gönderiliyor: $testEmail\n";

$subject = 'SendGrid Test Email - ' . date('Y-m-d H:i:s');
$htmlContent = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; text-align: center; color: white; border-radius: 10px 10px 0 0; }
        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; border: 1px solid #e0e0e0; }
        .success { color: #28a745; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>CopyStar</h1>
        </div>
        <div class="content">
            <h2>SendGrid Test Email</h2>
            <p class="success">✓ SendGrid başarıyla çalışıyor!</p>
            <p>Bu bir test email\'idir. Eger bu email\'i aliyorsaniz, SendGrid kurulumunuz basarilidir.</p>
            <p><strong>Gönderim Zamanı:</strong> ' . date('d.m.Y H:i:s') . '</p>
        </div>
    </div>
</body>
</html>';

$textContent = "SendGrid Test Email\n\n";
$textContent .= "SendGrid başarıyla çalışıyor!\n";
$textContent .= "Bu bir test email'idir.\n";
$textContent .= "Gönderim Zamanı: " . date('d.m.Y H:i:s') . "\n";

// Get sender email info
$fromEmail = getenv('SENDGRID_FROM_EMAIL') ?: (defined('SENDGRID_FROM_EMAIL') ? SENDGRID_FROM_EMAIL : 'noreply@copystar.net');
$fromName = getenv('SENDGRID_FROM_NAME') ?: (defined('SENDGRID_FROM_NAME') ? SENDGRID_FROM_NAME : 'CopyStar');

echo "   Gönderen: $fromName <$fromEmail>\n";
echo "   Alıcı: $testEmail\n";

$result = sendEmail($testEmail, $subject, $htmlContent, $textContent);

if ($result) {
    echo "   ✓ Email başarıyla gönderildi! (HTTP 200-299)\n";
    echo "\n========================================\n";
    echo "GÖNDERİM BAŞARILI!\n";
    echo "========================================\n";
    echo "Ancak email gelmediyse şunları kontrol edin:\n\n";
    echo "1. 📧 SPAM/JUNK klasörünü kontrol edin\n";
    echo "   Email spam klasörüne düşmüş olabilir\n\n";
    echo "2. 🔍 SendGrid Activity kontrolü:\n";
    echo "   https://app.sendgrid.com/activity\n";
    echo "   - Email'in 'Delivered' durumunda olup olmadığını kontrol edin\n";
    echo "   - 'Bounced', 'Blocked' veya 'Dropped' durumunda ise nedenini görün\n\n";
    echo "3. ✅ Sender Email Doğrulama:\n";
    echo "   https://app.sendgrid.com/settings/sender_auth/senders\n";
    echo "   - '$fromEmail' adresinin doğrulandığından emin olun\n";
    echo "   - Doğrulanmamışsa, email'inize gelen doğrulama linkine tıklayın\n\n";
    echo "4. ⏱️  Email gecikmeli gelebilir:\n";
    echo "   Bazen email'ler birkaç dakika gecikmeyle gelebilir\n";
    echo "   Birkaç dakika bekleyip tekrar kontrol edin\n\n";
    echo "5. 📱 Email sağlayıcısı filtreleri:\n";
    echo "   Gmail, Outlook gibi sağlayıcılar bazen email'leri filtreleyebilir\n";
    echo "   'Tüm Postalar' klasörünü de kontrol edin\n\n";
    echo "========================================\n";
    echo "Detaylı bilgi için SendGrid Activity sayfasını ziyaret edin:\n";
    echo "https://app.sendgrid.com/activity\n";
} else {
    echo "   ✗ Email gönderilemedi\n";
    echo "\n========================================\n";
    echo "HATA!\n";
    echo "========================================\n";
    echo "Lütfen şunları kontrol edin:\n";
    echo "1. SendGrid API key'in doğru olduğundan emin olun\n";
    echo "2. Sender email'in doğrulandığından emin olun\n";
    echo "3. PHP error loglarını kontrol edin\n";
    echo "4. SendGrid Dashboard > Activity'den durumu kontrol edin\n";
    exit(1);
}

echo "\n";

