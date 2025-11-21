# Türkçe Karakter Sorunu Çözümü

Canlı sunucuda Türkçe karakterlerin bozulması genellikle encoding sorunlarından kaynaklanır. Aşağıdaki adımları takip edin:

## 1. ✅ PHP Dosyalarının Encoding'i

Tüm PHP dosyalarının **UTF-8** encoding ile kaydedildiğinden emin olun:
- Editor'de (VS Code, PhpStorm, vb.) dosyaları açın
- "Save with Encoding" veya "Set File Encoding" seçeneğini kullanın
- **UTF-8** seçin ve kaydedin

## 2. ✅ Database Connection Charset

`__cs/c.php` dosyasında charset ayarı eklendi:
```php
$conn->set_charset("utf8mb4");
```

## 3. ✅ Database Charset Kontrolü

**ÖNEMLİ:** Eğer veritabanı seviyesinde yetki yoksa (shared hosting), sadece tablo seviyesinde charset değiştirin.

### Veritabanı Seviyesinde (Eğer yetkiniz varsa):
```sql
-- Veritabanı charset kontrolü
SHOW CREATE DATABASE copystar_net;

-- Eğer utf8mb4 değilse, değiştirin:
ALTER DATABASE copystar_net CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### Tablo Seviyesinde (Önerilen - Shared Hosting için):
Eğer veritabanı seviyesinde yetki yoksa, `fix_turkish_charset.sql` dosyasındaki komutları kullanın:

```sql
-- Tabloların charset kontrolü
SHOW TABLE STATUS;

-- Her tablo için charset kontrolü ve düzeltme
ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE traders CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE followed_traders CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- ... diğer tablolar için de aynısını yapın
```

**Not:** `fix_turkish_charset.sql` dosyasında tüm tablolar için hazır komutlar var.

## 4. ✅ HTML Meta Charset

Tüm HTML sayfalarında `<meta charset="UTF-8">` olduğundan emin olun:
- `index.php` ✅ (zaten var)
- `__cs/p/register.php` ✅
- `__cs/p/login.php` ✅
- Diğer tüm sayfalar

## 5. ✅ PHP Header Charset

AJAX response'larında charset belirtildi:
```php
header('Content-Type: application/json; charset=utf-8');
```

## 6. ✅ .htaccess Ayarları (Opsiyonel)

`.htaccess` dosyasına ekleyebilirsiniz:
```apache
AddDefaultCharset UTF-8
```

## 7. ✅ PHP.ini Ayarları

Canlı sunucuda `php.ini` dosyasında:
```ini
default_charset = "UTF-8"
```

## Hızlı Kontrol

Canlı sunucuda şu SQL sorgusunu çalıştırın:
```sql
-- Tüm tabloların charset'ini kontrol et
SELECT 
    TABLE_NAME,
    TABLE_COLLATION
FROM 
    information_schema.TABLES
WHERE 
    TABLE_SCHEMA = 'copystar_net';
```

Eğer `utf8mb4_unicode_ci` değilse, yukarıdaki `ALTER TABLE` komutlarını çalıştırın.

## Test

Türkçe karakter testi:
- "İstanbul" (büyük İ)
- "ığüşöç" (küçük harfler)
- "ŞĞÜÖÇ" (büyük harfler)

Bu karakterler doğru görünmeli.

