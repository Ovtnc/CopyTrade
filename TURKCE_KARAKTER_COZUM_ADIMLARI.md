# Türkçe Karakter Sorunu - Çözüm Adımları

## ✅ Tablolar Zaten Doğru Charset'te

Tüm tablolarınız zaten `utf8mb4_unicode_ci` charset'inde. Bu durumda sorun başka bir yerde olabilir.

## Kontrol Listesi

### 1. ✅ PHP Connection Charset (Düzeltildi)
`__cs/c.php` dosyasında charset ayarı eklendi:
```php
$conn->set_charset("utf8mb4");
```

**Kontrol:** Canlı sunucuda `__cs/c.php` dosyasının güncel olduğundan emin olun.

### 2. ✅ HTML Meta Charset
Tüm sayfalarda `<meta charset="UTF-8">` olmalı.

**Kontrol:** Browser'da sayfa kaynağını görüntüleyin (Ctrl+U) ve `<head>` bölümünde charset olduğundan emin olun.

### 3. ⚠️ PHP Dosyalarının Encoding'i
Tüm PHP dosyaları **UTF-8** encoding ile kaydedilmiş olmalı.

**Kontrol:**
- Editor'de (VS Code, PhpStorm, vb.) dosyaları açın
- Sağ alt köşede encoding'i kontrol edin
- Eğer UTF-8 değilse, "Save with Encoding" → "UTF-8" seçin

### 4. ⚠️ Mevcut Veriler Bozuk Olabilir
Eğer tablolar zaten doğru charset'teyse ama veriler bozuksa, bu eski verilerin yanlış charset ile kaydedilmiş olmasından kaynaklanır.

**Çözüm:**
- Yeni veriler doğru kaydedilecek
- Eski bozuk verileri manuel olarak düzeltmeniz gerekebilir

### 5. ⚠️ Browser Encoding
Browser'ın encoding'i yanlış olabilir.

**Kontrol:**
- Browser'da sayfayı yenileyin (Ctrl+F5)
- Browser ayarlarından encoding'i kontrol edin
- Farklı browser'da test edin

### 6. ⚠️ .htaccess Ayarları
`.htaccess` dosyasına charset ayarı eklenebilir:

```apache
AddDefaultCharset UTF-8
```

### 7. ⚠️ PHP Header'ları
AJAX response'larında charset belirtilmeli:

```php
header('Content-Type: application/json; charset=utf-8');
```

## Hızlı Test

1. **Yeni veri ekleyin** (örneğin: "İstanbul", "şğüöç")
2. **Veritabanında kontrol edin** - phpMyAdmin'de doğru görünüyor mu?
3. **Sayfada görüntüleyin** - Browser'da doğru görünüyor mu?

## Sorun Devam Ediyorsa

Eğer yeni veriler de bozuk görünüyorsa:

1. **PHP connection charset'i kontrol edin:**
   ```php
   // __cs/c.php dosyasında olmalı
   $conn->set_charset("utf8mb4");
   ```

2. **PHP dosyalarının encoding'ini kontrol edin:**
   - Tüm PHP dosyaları UTF-8 olmalı
   - Özellikle Türkçe karakter içeren dosyalar

3. **Browser console'u kontrol edin:**
   - F12 → Console
   - Encoding hataları var mı?

4. **Network tab'ı kontrol edin:**
   - F12 → Network
   - Response headers'da charset var mı?

## Örnek Test

phpMyAdmin'de şu sorguyu çalıştırın:
```sql
INSERT INTO users (email, name_surname) VALUES ('test@test.com', 'İstanbul şğüöç');
```

Sonra sayfada görüntüleyin. Eğer doğru görünüyorsa, sorun çözülmüştür.

