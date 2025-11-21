# SendGrid PHP Kurulumu - Adım Adım Rehber

## ADIM 1: Composer Kurulumu

### macOS için (Homebrew ile):
```bash
brew install composer
```

### macOS için (Manuel):
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer
```

### Kurulumu Test Edin:
```bash
composer --version
```

## ADIM 2: SendGrid Paketini Yükleme

Proje klasörünüzde terminal açın:

```bash
cd /Applications/MAMP/htdocs/cstar
composer install
```

Bu komut:
- `vendor/` klasörünü oluşturur
- SendGrid PHP kütüphanesini yükler
- Autoload dosyasını oluşturur

## ADIM 3: SendGrid Hesabı ve API Key

1. **SendGrid Hesabı Oluşturun:**
   - https://sendgrid.com/ adresine gidin
   - "Start for free" ile ücretsiz hesap oluşturun
   - Email doğrulaması yapın

2. **API Key Oluşturun:**
   - SendGrid Dashboard'a giriş yapın
   - Sol menüden: **Settings** > **API Keys**
   - **Create API Key** butonuna tıklayın
   - İsim verin: "CopyStar Email Verification"
   - İzin seviyesi: **Full Access** (veya sadece "Mail Send")
   - **Create & View** tıklayın
   - **ÖNEMLİ:** API Key'i hemen kopyalayın (bir daha gösterilmez!)

3. **Sender Email Doğrulama:**
   - Dashboard > **Settings** > **Sender Authentication**
   - **Single Sender Verification** seçin
   - Email adresinizi ekleyin (örn: noreply@copystar.net)
   - Email'inize gelen doğrulama linkine tıklayın

## ADIM 4: Environment Dosyası

1. **`.env` dosyası oluşturun:**
```bash
cd /Applications/MAMP/htdocs/cstar
cp env.example.txt .env
```

2. **`.env` dosyasını düzenleyin:**
```env
# SendGrid Email Configuration
SENDGRID_API_KEY=jMJy5i2OTT24y8zSzewtxg
SENDGRID_FROM_EMAIL=noreply@copystar.net
SENDGRID_FROM_NAME=CopyStar
```

**GÜVENLİK UYARISI:** 
- `.env` dosyasını asla git'e commit etmeyin!
- `.gitignore` dosyasına `.env` ekleyin
- API key'inizi kimseyle paylaşmayın

## ADIM 5: Test Etme

Basit bir test dosyası oluşturun:

```php
<?php
// test_sendgrid.php
require_once '__cs/c.php';
require_once '__cs/email.php';

$to = 'test@example.com'; // Kendi email adresiniz
$subject = 'SendGrid Test Email';
$htmlContent = '<h1>Test Başarılı!</h1><p>SendGrid çalışıyor.</p>';
$textContent = 'Test Başarılı! SendGrid çalışıyor.';

echo "Email gönderiliyor...\n";
$result = sendEmail($to, $subject, $htmlContent, $textContent);

if ($result) {
    echo "✓ Email başarıyla gönderildi!\n";
    echo "Email kutunuzu kontrol edin.\n";
} else {
    echo "✗ Email gönderilemedi.\n";
    echo "Hata loglarını kontrol edin: " . ini_get('error_log') . "\n";
}
?>
```

Terminal'de çalıştırın:
```bash
php test_sendgrid.php
```

## ADIM 6: Sistemin Çalıştığını Kontrol Etme

1. **Yeni kullanıcı kaydı oluşturun:**
   - `/register` sayfasına gidin
   - Yeni bir hesap oluşturun
   - Email kutunuzu kontrol edin

2. **Email doğrulama:**
   - Email'deki linke tıklayın
   - Email doğrulandı mesajını görün

3. **SendGrid Dashboard kontrolü:**
   - SendGrid Dashboard > **Activity** bölümüne gidin
   - Gönderilen email'leri görebilirsiniz
   - Bounce, spam şikayeti gibi durumları kontrol edin

## Sorun Giderme

### "Composer command not found"
- Composer'ın PATH'te olduğundan emin olun
- Terminal'i yeniden başlatın
- `which composer` ile konumunu kontrol edin

### "SendGrid library not installed"
```bash
cd /Applications/MAMP/htdocs/cstar
composer install
```

### "SendGrid API key not configured"
- `.env` dosyasının var olduğundan emin olun
- API key'in doğru kopyalandığından emin olun
- Boşluk veya özel karakter olmamalı

### Email gönderilmiyor
1. SendGrid Dashboard > Activity'den durumu kontrol edin
2. PHP error loglarını kontrol edin
3. Sender email'in doğrulandığından emin olun
4. API key'in aktif olduğundan emin olun

### Rate Limit Hatası
SendGrid ücretsiz planında:
- Günde 100 email
- Saniyede 1 email

## Kullanım Örnekleri

### 1. Basit Email Gönderme
```php
require_once '__cs/email.php';

sendEmail(
    'user@example.com',
    'Hoş Geldiniz',
    '<h1>Hoş Geldiniz!</h1>',
    'Hoş Geldiniz!'
);
```

### 2. Email Doğrulama Gönderme
```php
require_once '__cs/email.php';

$token = generateVerificationToken($userId, $email);
sendVerificationEmail($userId, $email, $token);
```

### 3. Özel Template ile Email
```php
require_once '__cs/email.php';

$html = '
<div style="font-family: Arial; padding: 20px;">
    <h1>Özel Email</h1>
    <p>İçerik buraya gelir</p>
</div>';

sendEmail('user@example.com', 'Konu', $html);
```

## Production İçin Öneriler

1. **API Key Güvenliği:**
   - `.env` dosyasını `.gitignore`'a ekleyin
   - Production'da environment variable kullanın
   - API key'i asla kod içine yazmayın

2. **Email Template:**
   - `__cs/email.php` dosyasındaki template'leri özelleştirin
   - Logo ve branding ekleyin

3. **Error Handling:**
   - Email gönderim hatalarını loglayın
   - Kullanıcıya uygun mesajlar gösterin

4. **Monitoring:**
   - SendGrid Dashboard'dan istatistikleri takip edin
   - Bounce ve spam şikayetlerini kontrol edin

