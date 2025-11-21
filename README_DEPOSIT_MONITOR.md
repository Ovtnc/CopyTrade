# CopyStar Deposit Monitor

Bu Node.js uygulaması, kullanıcıların blockchain ağlarından (Ethereum, BSC, Tron) yaptığı para yatırma işlemlerini otomatik olarak kontrol eder ve bakiyelerini günceller.

## Özellikler

- ✅ Ethereum (ETH) ve ERC20 token (USDT, USDC) transferlerini kontrol eder
- ✅ Binance Smart Chain (BNB) ve BEP20 token (USDT, USDC) transferlerini kontrol eder
- ✅ Tron (TRX) ve TRC20 token (USDT, USDC) transferlerini kontrol eder
- ✅ Pending deposit transaction'ları otomatik olarak kontrol eder
- ✅ Bulunan transaction'ları veritabanına kaydeder ve kullanıcı bakiyesini günceller

## Kurulum

1. **Bağımlılıkları yükleyin:**
```bash
npm install
```

2. **Environment dosyasını oluşturun:**
```bash
cp .env.example .env
```

3. **`.env` dosyasını düzenleyin:**
   - Veritabanı bilgilerinizi girin
   - Blockchain RPC endpoint'lerinizi ekleyin
   - Token contract adreslerini kontrol edin

## Kullanım

### Development Mode (Nodemon ile):
```bash
npm run dev
```

### Production Mode:
```bash
npm start
```

## Yapılandırma

### Database
- `DB_HOST`: Veritabanı sunucu adresi
- `DB_PORT`: Veritabanı portu (varsayılan: 8889)
- `DB_USER`: Veritabanı kullanıcı adı
- `DB_PASSWORD`: Veritabanı şifresi
- `DB_NAME`: Veritabanı adı

### Blockchain RPC Endpoints
- `ETH_RPC_URL`: Ethereum RPC endpoint (Infura, Alchemy, vb.)
- `BSC_RPC_URL`: Binance Smart Chain RPC endpoint
- `TRON_RPC_URL`: Tron RPC endpoint

### Token Contract Addresses
- `USDT_ETH_CONTRACT`: Ethereum USDT contract adresi
- `USDC_ETH_CONTRACT`: Ethereum USDC contract adresi
- `USDT_BSC_CONTRACT`: BSC USDT contract adresi
- `USDC_BSC_CONTRACT`: BSC USDC contract adresi
- `USDT_TRON_CONTRACT`: Tron USDT contract adresi
- `USDC_TRON_CONTRACT`: Tron USDC contract adresi

### Monitor Settings
- `CHECK_INTERVAL`: Kontrol aralığı (milisaniye, varsayılan: 30000 = 30 saniye)

## Nasıl Çalışır?

1. Uygulama başlatıldığında blockchain bağlantılarını kurar
2. Veritabanından `status = 'pending'` olan deposit transaction'larını alır
3. Her transaction için:
   - Kullanıcının wallet adresini kontrol eder
   - Blockchain'de ilgili ağda son 1000 bloğu tarar
   - Token transferlerini veya native token transferlerini kontrol eder
   - Miktar eşleşirse transaction'ı bulur
4. Bulunan transaction'lar için:
   - Transaction hash'i kaydeder
   - Transaction durumunu `completed` olarak günceller
   - Kullanıcının `balance` ve `withdrawable_balance` değerlerini artırır

## Notlar

- Uygulama sürekli çalışmalıdır (cron job veya PM2 ile)
- RPC endpoint'leriniz rate limit'e sahip olabilir, buna dikkat edin
- Production ortamında PM2 veya benzeri bir process manager kullanın

## PM2 ile Çalıştırma

```bash
# PM2'yi global olarak yükleyin
npm install -g pm2

# Uygulamayı başlatın
pm2 start controllers/depositMonitor.js --name deposit-monitor

# Logları görüntüleyin
pm2 logs deposit-monitor

# Uygulamayı durdurun
pm2 stop deposit-monitor
```

