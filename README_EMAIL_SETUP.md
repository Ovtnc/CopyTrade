# Email Doğrulama Sistemi - SendGrid Kurulumu

Bu sistem SendGrid kullanarak email doğrulama işlemlerini yönetir.

## Kurulum

### 1. Composer ile SendGrid Paketini Yükleyin

```bash
cd /Applications/MAMP/htdocs/cstar
composer install
```

### 2. Environment Değişkenlerini Ayarlayın

`.env` dosyası oluşturun veya `env.example.txt` dosyasını `.env` olarak kopyalayın:

```bash
cp env.example.txt .env
```

`.env` dosyasına SendGrid ayarlarını ekleyin:

```env
# SendGrid Configuration
SENDGRID_API_KEY=your_sendgrid_api_key_here
SENDGRID_FROM_EMAIL=noreply@copystar.net
SENDGRID_FROM_NAME=CopyStar
```

### 3. SendGrid API Key Alma

1. [SendGrid](https://sendgrid.com/) hesabı oluşturun
2. Dashboard'dan "Settings" > "API Keys" bölümüne gidin
3. "Create API Key" butonuna tıklayın
4. API key'e bir isim verin (örn: "CopyStar Email Verification")
5. "Full Access" veya "Mail Send" izni verin
6. Oluşturulan API key'i kopyalayın ve `.env` dosyasına ekleyin

### 4. Veritabanı Tablosu

Email verification token'ları için tablo otomatik olarak oluşturulacaktır. Eğer manuel oluşturmak isterseniz:

```sql
CREATE TABLE IF NOT EXISTS `email_verification_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  KEY `email` (`email`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Nasıl Çalışır?

1. **Kayıt İşlemi:**
   - Kullanıcı kayıt olduğunda email doğrulama token'ı oluşturulur
   - SendGrid ile doğrulama email'i gönderilir
   - Kullanıcı otomatik olarak giriş yapar ama email doğrulanmamış durumda kalır

2. **Email Doğrulama:**
   - Kullanıcı email'deki linke tıklar
   - Token kontrol edilir (geçerlilik süresi: 24 saat)
   - Email doğrulanır ve `email_verified` durumu `1` olur

3. **Tekrar Gönderme:**
   - Kullanıcı email'i almadıysa veya link süresi dolduysa
   - Dashboard'dan veya verify-email sayfasından tekrar gönderebilir

## Test Etme

1. Yeni bir kullanıcı kaydı oluşturun
2. Email kutunuzu kontrol edin
3. Doğrulama linkine tıklayın
4. Dashboard'da email doğrulandı mesajını görün

## Sorun Giderme

- **Email gönderilmiyor:** SendGrid API key'inizi kontrol edin
- **Composer hatası:** `composer install` komutunu çalıştırın
- **Token geçersiz:** Token'lar 24 saat geçerlidir, yeni token oluşturun

## Notlar

- SendGrid ücretsiz planında günde 100 email gönderebilirsiniz
- Production ortamında SendGrid API key'ini güvenli tutun
- Email template'lerini `__cs/email.php` dosyasından özelleştirebilirsiniz

