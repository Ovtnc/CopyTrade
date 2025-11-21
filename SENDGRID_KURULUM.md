# SendGrid PHP Kurulum ve Kullanım Rehberi

## 1. Composer Kurulumu

Eğer sisteminizde Composer yoksa önce Composer'ı kurun:

### macOS için:
```bash
# Homebrew ile
brew install composer

# Veya manuel
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### Windows için:
[Composer Installer](https://getcomposer.org/Composer-Setup.exe) indirip çalıştırın.

## 2. SendGrid Paketini Yükleme

Proje klasörünüzde terminal açın ve şu komutu çalıştırın:

```bash
cd /Applications/MAMP/htdocs/cstar
composer install
```

Bu komut `composer.json` dosyasındaki bağımlılıkları yükleyecek ve `vendor/` klasörünü oluşturacak.

## 3. SendGrid API Key Alma

1. [SendGrid](https://sendgrid.com/) sitesine gidin
2. Ücretsiz hesap oluşturun (günde 100 email ücretsiz)
3. Dashboard'a giriş yapın
4. Sol menüden **Settings** > **API Keys** seçin
5. **Create API Key** butonuna tıklayın
6. API Key'e bir isim verin (örn: "CopyStar Email")
7. İzin seviyesini seçin:
   - **Full Access** (tüm izinler) - Önerilen
   - Veya sadece **Mail Send** izni
8. **Create & View** butonuna tıklayın
9. **ÖNEMLİ:** API Key'i hemen kopyalayın (bir daha gösterilmeyecek!)

## 4. Environment Dosyası Oluşturma

Proje klasöründe `.env` dosyası oluşturun:

```bash
cd /Applications/MAMP/htdocs/cstar
cp env.example.txt .env
```

`.env` dosyasını düzenleyin ve SendGrid bilgilerinizi ekleyin:

```env
# SendGrid Email Configuration
SENDGRID_API_KEY=jMJy5i2OTT24y8zSzewtxg
SENDGRID_FROM_EMAIL=noreply@copystar.net
SENDGRID_FROM_NAME=CopyStar
```

**ÖNEMLİ:** `.env` dosyasını `.gitignore`'a ekleyin (API key'iniz git'e commit edilmesin!)

## 5. SendGrid Sender Verification

SendGrid'de gönderen email adresini doğrulamanız gerekebilir:

1. SendGrid Dashboard > **Settings** > **Sender Authentication**
2. **Single Sender Verification** seçin
3. Email adresinizi ekleyin (örn: noreply@copystar.net)
4. Email'inize gelen doğrulama linkine tıklayın

**Not:** Domain doğrulaması yaparsanız daha iyi deliverability sağlarsınız.

## 6. Test Etme

Basit bir test scripti oluşturun:

```php
<?php
// test_email.php
require_once '__cs/c.php';
require_once '__cs/email.php';

// Test email gönder
$to = 'test@example.com'; // Kendi email adresiniz
$subject = 'Test Email';
$htmlContent = '<h1>Test Email</h1><p>Bu bir test email\'idir.</p>';
$textContent = 'Bu bir test email\'idir.';

$result = sendEmail($to, $subject, $htmlContent, $textContent);

if ($result) {
    echo "Email başarıyla gönderildi!";
} else {
    echo "Email gönderilemedi. Hata loglarını kontrol edin.";
}
?>
```

Terminal'de çalıştırın:
```bash
php test_email.php
```

## 7. Kullanım Örnekleri

### Email Doğrulama Email'i Gönderme

```php
require_once '__cs/email.php';

$userId = 123;
$email = 'user@example.com';
$token = generateVerificationToken($userId, $email);
$result = sendVerificationEmail($userId, $email, $token);
```

### Özel Email Gönderme

```php
require_once '__cs/email.php';

$to = 'user@example.com';
$subject = 'Hoş Geldiniz';
$htmlContent = '<h1>Hoş Geldiniz!</h1><p>Hesabınız oluşturuldu.</p>';
$textContent = 'Hoş Geldiniz! Hesabınız oluşturuldu.';

$result = sendEmail($to, $subject, $htmlContent, $textContent);
```

## 8. Sorun Giderme

### "SendGrid library not installed" hatası
```bash
composer install
```

### "SendGrid API key not configured" hatası
- `.env` dosyasının var olduğundan emin olun
- API key'in doğru olduğundan emin olun
- `c.php` dosyasının `.env` dosyasını yüklediğinden emin olun

### Email gönderilmiyor
1. SendGrid Dashboard > **Activity** bölümünden email durumunu kontrol edin
2. API key'inizin aktif olduğundan emin olun
3. Sender email'in doğrulandığından emin olun
4. PHP error loglarını kontrol edin

### Rate Limit
SendGrid ücretsiz planında:
- Günde 100 email gönderebilirsiniz
- Saniyede 1 email gönderebilirsiniz

## 9. Production İçin Öneriler

1. **API Key Güvenliği:**
   - `.env` dosyasını `.gitignore`'a ekleyin
   - Production'da environment variable kullanın
   - API key'i asla kod içine yazmayın

2. **Email Template'leri:**
   - `__cs/email.php` dosyasındaki template'leri özelleştirin
   - Branding ekleyin (logo, renkler, vb.)

3. **Error Handling:**
   - Email gönderim hatalarını loglayın
   - Kullanıcıya uygun hata mesajları gösterin
   - Retry mekanizması ekleyin

4. **Monitoring:**
   - SendGrid Dashboard'dan email istatistiklerini takip edin
   - Bounce ve spam şikayetlerini kontrol edin

## 10. Email Template Özelleştirme

`__cs/email.php` dosyasındaki `sendVerificationEmail()` fonksiyonunu düzenleyerek email template'ini özelleştirebilirsiniz:

- Logo ekleyin
- Renkleri değiştirin
- Daha fazla bilgi ekleyin
- Çoklu dil desteği ekleyin

## Hızlı Başlangıç Checklist

- [ ] Composer yüklü mü? (`composer --version`)
- [ ] `composer install` çalıştırıldı mı?
- [ ] SendGrid hesabı oluşturuldu mu?
- [ ] API Key alındı mı?
- [ ] `.env` dosyası oluşturuldu mu?
- [ ] API Key `.env` dosyasına eklendi mi?
- [ ] Sender email doğrulandı mı?
- [ ] Test email gönderildi mi?

## Destek

Sorun yaşarsanız:
1. PHP error loglarını kontrol edin
2. SendGrid Dashboard > Activity'den email durumunu kontrol edin
3. SendGrid dokümantasyonunu inceleyin: https://docs.sendgrid.com/

