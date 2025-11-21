# SendGrid API Key 401 Hatası Çözümü

## Hata Mesajı
```
401 - The provided authorization grant is invalid, expired, or revoked
```

Bu hata, SendGrid API key'inin geçersiz, süresi dolmuş veya iptal edilmiş olduğunu gösterir.

## Çözüm Adımları

### 1. SendGrid'de Yeni API Key Oluşturun

1. **SendGrid Dashboard'a gidin**: https://app.sendgrid.com/
2. **Settings** > **API Keys** menüsüne gidin
3. **Create API Key** butonuna tıklayın
4. **API Key Name**: `CopyStar Production` (veya istediğiniz bir isim)
5. **API Key Permissions**: **Full Access** seçin (veya sadece **Mail Send** izni)
6. **Create & View** butonuna tıklayın
7. **ÖNEMLİ**: API key'i hemen kopyalayın! (Sadece bir kez gösterilir)

### 2. .env Dosyasını Güncelleyin

1. Proje kök dizinindeki `.env` dosyasını açın
2. `SENDGRID_API_KEY` satırını bulun
3. Yeni API key'i yapıştırın:

```env
SENDGRID_API_KEY=SG.yeni_api_key_buraya_yapistir
```

**ÖNEMLİ NOTLAR:**
- API key'in başında `SG.` olmalı
- API key'in sonunda boşluk veya yeni satır olmamalı
- Tırnak işareti kullanmayın
- Örnek format: `SENDGRID_API_KEY=SG.xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx`

### 3. .env Dosyası Kontrolü

`.env` dosyanız şu şekilde olmalı:

```env
# SendGrid Email Configuration
SENDGRID_API_KEY=SG.yeni_api_key_buraya
SENDGRID_FROM_EMAIL=noreply@copystar.net
SENDGRID_FROM_NAME=CopyStar
```

### 4. Test Edin

Terminal'de test scriptini çalıştırın:

```bash
php test_sendgrid.php okanvatanci@gmail.com
```

### 5. Yaygın Hatalar

#### Hata: API key hala çalışmıyor
- API key'i tekrar kopyalayıp yapıştırın
- `.env` dosyasında boşluk veya yeni satır olmadığından emin olun
- SendGrid Dashboard > API Keys'den key'in aktif olduğunu kontrol edin

#### Hata: "Sender email not verified"
- SendGrid Dashboard > Settings > Sender Authentication
- "Create a Sender" formunu doldurun ve email'i doğrulayın
- Email'inize gelen doğrulama linkine tıklayın

#### Hata: "Insufficient permissions"
- API key'in **Full Access** veya en azından **Mail Send** iznine sahip olduğundan emin olun
- Yeni bir API key oluştururken izinleri kontrol edin

## Hızlı Kontrol Listesi

- [ ] SendGrid'de yeni API key oluşturuldu
- [ ] API key kopyalandı (SG. ile başlıyor)
- [ ] .env dosyasına doğru yapıştırıldı (boşluk yok)
- [ ] Sender email doğrulandı
- [ ] Test scripti çalıştırıldı

## Yardım

Hala sorun yaşıyorsanız:
1. SendGrid Dashboard > Activity'den email gönderim durumunu kontrol edin
2. PHP error loglarını kontrol edin
3. SendGrid Support'a başvurun

