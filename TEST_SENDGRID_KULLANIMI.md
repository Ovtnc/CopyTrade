# SendGrid Test Script Kullanımı

## Adım 1: Test Email Adresini Ayarlayın

`test_sendgrid.php` dosyasını açın ve 8. satırdaki email adresini kendi email adresinizle değiştirin:

```php
$testEmail = 'sizin-email@example.com'; // Kendi email adresinizi yazın
```

## Adım 2: Terminal'de Çalıştırın

Terminal'i açın ve şu komutu çalıştırın:

```bash
cd /Applications/MAMP/htdocs/cstar
php test_sendgrid.php
```

## Adım 3: Sonuçları Kontrol Edin

Script şunları kontrol eder:
1. ✓ Composer ve vendor klasörü
2. ✓ SendGrid kütüphanesi
3. ✓ .env dosyası ve API key
4. ✓ Email helper fonksiyonları
5. ✓ Test email gönderimi

### Başarılı Olursa:
```
✓ Email başarıyla gönderildi!
Email kutunuzu kontrol edin: sizin-email@example.com
```

### Hata Olursa:
- Hata mesajını okuyun
- Önerilen çözümleri uygulayın
- SendGrid Dashboard > Activity'den durumu kontrol edin

## Alternatif: Web Tarayıcısından Test

Eğer terminal kullanmak istemiyorsanız, test scriptini web tarayıcısından da çalıştırabilirsiniz:

1. `test_sendgrid.php` dosyasını web tarayıcısından açılabilir hale getirin
2. Tarayıcıda şu adrese gidin: `http://localhost:8888/cstar/test_sendgrid.php`

**Not:** Web tarayıcısından çalıştırmak için script'i biraz düzenlemeniz gerekebilir (HTML output için).

## Hızlı Test Komutu

Tek satırda test etmek için:

```bash
php test_sendgrid.php
```

Eğer email adresini komut satırından değiştirmek isterseniz:

```bash
php -r "file_put_contents('test_sendgrid.php', str_replace('test@example.com', 'sizin-email@example.com', file_get_contents('test_sendgrid.php')));" && php test_sendgrid.php
```

## Sorun Giderme

### "vendor/autoload.php bulunamadı"
```bash
composer install
```

### "SENDGRID_API_KEY bulunamadı"
- `.env` dosyasının var olduğundan emin olun
- API key'in doğru kopyalandığından emin olun

### "Email gönderilemedi"
1. SendGrid Dashboard > Activity kontrol edin
2. Sender email'in doğrulandığından emin olun
3. PHP error loglarını kontrol edin

