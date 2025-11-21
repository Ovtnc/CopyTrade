# Email Gelmedi - Çözüm Rehberi

## Email Gönderimi Başarılı Ama Email Gelmediyse

### 1. Spam/Junk Klasörünü Kontrol Edin ✅

Email'ler genellikle spam klasörüne düşer:
- **Gmail**: Spam klasörünü kontrol edin
- **Outlook**: Junk Email klasörünü kontrol edin
- **Diğer**: Spam/Junk/Gereksiz klasörlerini kontrol edin

**Çözüm**: Email'i spam değil olarak işaretleyin, böylece gelecekteki email'ler doğrudan gelen kutusuna gelir.

---

### 2. SendGrid Activity Kontrolü 🔍

SendGrid Dashboard'dan email durumunu kontrol edin:

1. **SendGrid Dashboard'a gidin**: https://app.sendgrid.com/
2. **Activity** menüsüne tıklayın
3. Son gönderilen email'i bulun
4. **Durum** sütununu kontrol edin:

#### Durumlar:
- ✅ **Delivered**: Email başarıyla teslim edildi (alıcının sunucusuna ulaştı)
- ⚠️ **Bounced**: Email geri döndü (geçersiz email adresi veya kutu dolu)
- 🚫 **Blocked**: Email engellendi (spam filtresi veya güvenlik)
- ❌ **Dropped**: Email atıldı (geçersiz alıcı veya politika ihlali)
- ⏳ **Processing**: Email işleniyor (birkaç dakika bekleyin)

**Çözüm**: 
- Eğer "Bounced" ise: Email adresini kontrol edin
- Eğer "Blocked" ise: Alıcının spam filtresini kontrol edin
- Eğer "Dropped" ise: SendGrid Dashboard'dan nedenini görün

---

### 3. Sender Email Doğrulama ✅

Sender email'in doğrulanmış olması gerekir:

1. **SendGrid Dashboard**: https://app.sendgrid.com/
2. **Settings** > **Sender Authentication** > **Single Sender Verification**
3. Sender email'inizin (`noreply@copystar.net`) durumunu kontrol edin

**Durumlar**:
- ✅ **Verified**: Doğrulanmış (yeşil tik)
- ⚠️ **Pending**: Beklemede (email'inize gelen doğrulama linkine tıklayın)
- ❌ **Unverified**: Doğrulanmamış

**Çözüm**: 
- Doğrulanmamışsa, "Create a Sender" formunu doldurun
- Email'inize gelen doğrulama linkine tıklayın
- Doğrulama tamamlandıktan sonra tekrar test edin

---

### 4. Email Gecikmesi ⏱️

Bazen email'ler birkaç dakika gecikmeyle gelebilir:
- SendGrid sunucularından alıcı sunucularına iletim zaman alabilir
- 5-10 dakika bekleyip tekrar kontrol edin

---

### 5. Email Sağlayıcısı Filtreleri 📱

Gmail, Outlook gibi sağlayıcılar email'leri filtreleyebilir:

**Gmail**:
- "Tüm Postalar" sekmesini kontrol edin
- "Önemli" sekmesini kontrol edin
- Gmail filtrelerini kontrol edin: https://mail.google.com/mail/u/0/#settings/filters

**Outlook**:
- "Diğer" klasörünü kontrol edin
- Outlook filtrelerini kontrol edin

---

### 6. Domain Authentication (İsteğe Bağlı) 🌐

Daha iyi deliverability için domain authentication yapabilirsiniz:

1. **SendGrid Dashboard**: https://app.sendgrid.com/
2. **Settings** > **Sender Authentication** > **Domain Authentication**
3. Domain'inizi ekleyin ve DNS kayıtlarını yapın

Bu, email'lerin spam klasörüne düşme olasılığını azaltır.

---

### 7. Test Email Adresini Değiştirin 🔄

Bazen belirli email adresleri sorun çıkarabilir:
- Farklı bir email adresiyle test edin
- Kişisel email yerine kurumsal email kullanmayı deneyin

---

## Hızlı Kontrol Listesi

- [ ] Spam/Junk klasörünü kontrol ettim
- [ ] SendGrid Activity'den email durumunu kontrol ettim
- [ ] Sender email'in doğrulandığını kontrol ettim
- [ ] 5-10 dakika bekledim ve tekrar kontrol ettim
- [ ] "Tüm Postalar" klasörünü kontrol ettim
- [ ] Farklı bir email adresiyle test ettim

---

## SendGrid Activity Linki

Detaylı bilgi için: https://app.sendgrid.com/activity

Bu sayfadan:
- Email gönderim geçmişini görebilirsiniz
- Email durumunu (Delivered, Bounced, Blocked) görebilirsiniz
- Email'in neden teslim edilmediğini öğrenebilirsiniz
- Alıcı sunucu yanıtlarını görebilirsiniz

---

## Sorun Devam Ediyorsa

1. **SendGrid Support**: https://support.sendgrid.com/
2. **SendGrid Status**: https://status.sendgrid.com/
3. **PHP Error Logları**: MAMP error loglarını kontrol edin

---

## Test Komutu

Tekrar test etmek için:

```bash
php test_sendgrid.php sizin-email@example.com
```

Script şimdi daha detaylı bilgi verecek ve kontrol listesini gösterecek.

