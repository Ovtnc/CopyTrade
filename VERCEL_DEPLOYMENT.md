# Vercel Deployment Guide - Deposit Monitor

Bu rehber, CopyStar deposit monitor sistemini Vercel üzerinde çalıştırmak için gerekli adımları içerir.

## ⚠️ Önemli Notlar

1. **Vercel Serverless Limitation**: Vercel serverless function'lar maksimum 10 saniye (Hobby plan) veya 60 saniye (Pro plan) çalışabilir. Bu nedenle deposit monitor'ü sürekli çalışan bir process olarak değil, periyodik olarak çalışan bir cron job olarak yapılandırdık.

2. **Cron Schedule**: Vercel Cron ile her 5 dakikada bir kontrol yapılacak. Daha sık kontrol için Pro plan gerekir.

3. **Alternatif Çözümler**: 
   - Railway, Render, Heroku gibi platformlar sürekli çalışan process'ler için daha uygun
   - VPS sunucu üzerinde PM2 ile çalıştırmak en iyi performansı sağlar

## Kurulum Adımları

### 1. Vercel Projesi Oluşturma

```bash
# Vercel CLI'yi yükleyin
npm i -g vercel

# Projeyi Vercel'e bağlayın
vercel login
vercel link
```

### 2. Environment Variables Ayarlama

Vercel dashboard'da veya CLI ile environment variable'ları ekleyin:

```bash
vercel env add DB_HOST
vercel env add DB_PORT
vercel env add DB_USER
vercel env add DB_PASSWORD
vercel env add DB_NAME
vercel env add ETH_RPC_URL
vercel env add BSC_RPC_URL
vercel env add TRON_RPC_URL
vercel env add USDT_ETH_CONTRACT
vercel env add USDC_ETH_CONTRACT
vercel env add USDT_BSC_CONTRACT
vercel env add USDC_BSC_CONTRACT
vercel env add USDT_TRON_CONTRACT
vercel env add USDC_TRON_CONTRACT
vercel env add CRON_SECRET
```

**Gerekli Environment Variables:**

- `DB_HOST`: Veritabanı sunucu adresi
- `DB_PORT`: Veritabanı portu (varsayılan: 3306)
- `DB_USER`: Veritabanı kullanıcı adı
- `DB_PASSWORD`: Veritabanı şifresi
- `DB_NAME`: Veritabanı adı
- `ETH_RPC_URL`: Ethereum RPC endpoint (Infura, Alchemy, vb.)
- `BSC_RPC_URL`: Binance Smart Chain RPC endpoint
- `TRON_RPC_URL`: Tron RPC endpoint
- `USDT_ETH_CONTRACT`: Ethereum USDT contract adresi
- `USDC_ETH_CONTRACT`: Ethereum USDC contract adresi
- `USDT_BSC_CONTRACT`: BSC USDT contract adresi
- `USDC_BSC_CONTRACT`: BSC USDC contract adresi
- `USDT_TRON_CONTRACT`: Tron USDT contract adresi
- `USDC_TRON_CONTRACT`: Tron USDC contract adresi
- `CRON_SECRET`: Cron job için güvenlik secret'i (rastgele bir string)

### 3. Deploy

```bash
# Production'a deploy
vercel --prod
```

### 4. Cron Job Kontrolü

Vercel dashboard'da:
1. Settings > Cron Jobs bölümüne gidin
2. `/api/deposit-monitor` cron job'ının aktif olduğunu kontrol edin
3. Schedule: `*/5 * * * *` (her 5 dakikada bir)

### 5. Manuel Test

Cron job'ı manuel olarak test etmek için:

```bash
curl https://your-domain.vercel.app/api/deposit-monitor?secret=YOUR_CRON_SECRET
```

## Vercel Cron Schedule Formatı

- `*/5 * * * *` - Her 5 dakikada bir
- `*/1 * * * *` - Her 1 dakikada bir (Pro plan gerekir)
- `0 * * * *` - Her saat başı
- `0 */6 * * *` - Her 6 saatte bir

## Monitoring ve Logs

Vercel dashboard'da:
- **Functions** sekmesinden function loglarını görüntüleyebilirsiniz
- **Cron Jobs** sekmesinden cron job geçmişini kontrol edebilirsiniz

## Troubleshooting

### Function Timeout

Eğer function timeout alıyorsanız:
1. Kontrol edilen block sayısını azaltın (100'den daha az)
2. Kullanıcı sayısını sınırlayın
3. Vercel Pro plan'a geçin (60 saniye timeout)

### Database Connection Issues

- Veritabanı sunucunuzun Vercel'in IP adreslerinden bağlantı kabul ettiğinden emin olun
- SSL bağlantısı gerekebilir

### RPC Rate Limiting

- RPC provider'ınızın rate limit'ini kontrol edin
- Gerekirse daha yüksek limit'li bir plan kullanın

## Alternatif: Railway/Render Deployment

Eğer Vercel'in limitasyonları sorun yaratıyorsa, Railway veya Render gibi platformlar kullanabilirsiniz:

### Railway

1. Railway hesabı oluşturun
2. Yeni proje oluşturun
3. GitHub repo'yu bağlayın
4. Environment variables ekleyin
5. Start command: `node controllers/depositMonitor.js`

### Render

1. Render hesabı oluşturun
2. New > Background Worker
3. GitHub repo'yu bağlayın
4. Build command: `npm install`
5. Start command: `node controllers/depositMonitor.js`
6. Environment variables ekleyin

## Önerilen Çözüm

**En iyi performans için:**
- VPS sunucu üzerinde PM2 ile çalıştırın
- Veya Railway/Render gibi sürekli çalışan process destekleyen platformlar kullanın
- Vercel'i sadece geçici çözüm veya test için kullanın

