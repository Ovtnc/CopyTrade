# Canlı Sunucuda SendGrid Email Hatası Çözümü

## Hata: "Email gönderilemedi. Lütfen tekrar deneyin."

Bu hata genellikle şu nedenlerden kaynaklanır:

### 1. ✅ SendGrid Kütüphanesi Yüklü Değil

**Çözüm:**
```bash
cd /path/to/your/project
composer install
```

**Kontrol:**
- `vendor/` klasörü var mı?
- `vendor/autoload.php` dosyası var mı?
- `vendor/sendgrid/sendgrid/` klasörü var mı?

### 2. ✅ .env Dosyası Eksik veya Yanlış Konumda

**Kontrol:**
- `.env` dosyası proje root dizininde olmalı
- `__cs/c.php` dosyası `.env` dosyasını `__DIR__ . '/../.env'` yolundan okur

**Örnek .env içeriği:**
```env
SENDGRID_API_KEY=SG.xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
SENDGRID_FROM_EMAIL=noreply@copystar.net
SENDGRID_FROM_NAME=CopyStar
```

### 3. ✅ SendGrid API Key Yanlış veya Eksik

**Kontrol:**
- SendGrid hesabınızda API key oluşturulmuş mu?
- API key'in "Full Access" yetkisi var mı?
- `.env` dosyasındaki API key doğru mu?

**SendGrid'de API Key Oluşturma:**
1. SendGrid Dashboard → Settings → API Keys
2. "Create API Key" butonuna tıklayın
3. "Full Access" seçin
4. Oluşturulan key'i kopyalayın (bir daha gösterilmeyecek!)
5. `.env` dosyasına ekleyin

### 4. ✅ Sender Email Doğrulanmamış

**Kontrol:**
- SendGrid'de "Sender Authentication" yapılmış mı?
- Single Sender Verification veya Domain Authentication yapılmış mı?

**Çözüm:**
1. SendGrid Dashboard → Settings → Sender Authentication
2. Single Sender Verification yapın (test için)
3. Veya Domain Authentication yapın (production için)

### 5. ✅ PHP Error Log Kontrolü

**Hata detaylarını görmek için:**
```bash
tail -f /path/to/php/error.log
```

Veya PHP error log dosyasını kontrol edin:
- Genellikle: `/var/log/php/error.log` veya `/var/log/apache2/error.log`
- Veya hosting panelinden error log'u kontrol edin

### 6. ✅ Composer Autoload Eksik

**Kontrol:**
`__cs/c.php` veya `index.php` dosyasının başında:
```php
require_once __DIR__ . '/vendor/autoload.php';
```

Eğer yoksa ekleyin.

### 7. ✅ Dosya İzinleri

**Kontrol:**
```bash
chmod 644 .env
chmod -R 755 vendor/
```

### 8. ✅ Debug Modu Aktif Etme

Geçici olarak daha detaylı hata mesajları görmek için:

`__cs/p/register.php` dosyasında, hata mesajı döndüren yerde `debug` bilgileri de döner. Browser console'da kontrol edin.

## Hızlı Test

1. **Composer yüklü mü?**
   ```bash
   composer --version
   ```

2. **SendGrid kütüphanesi yüklü mü?**
   ```bash
   ls vendor/sendgrid/sendgrid/
   ```

3. **.env dosyası var mı?**
   ```bash
   cat .env | grep SENDGRID
   ```

4. **PHP'den test:**
   ```php
   <?php
   require_once 'vendor/autoload.php';
   var_dump(class_exists('\SendGrid\Mail\Mail'));
   var_dump(getenv('SENDGRID_API_KEY'));
   ?>
   ```

## En Yaygın Çözüm

Çoğu durumda sorun **composer install** yapılmamasından kaynaklanır:

```bash
cd /path/to/your/project
composer install --no-dev
```

Sonra `.env` dosyasını kontrol edin ve tekrar deneyin.

