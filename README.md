# CopyStar - Copy Trading Platform

Copy trading platformu - Trader'ların işlemlerini otomatik olarak kopyalayın.

## 🚀 Özellikler

* ✅ Trader takip sistemi
* 💰 Otomatik işlem kopyalama
* 📊 Gerçek zamanlı PnL hesaplama
* 📱 Responsive tasarım
* 🔐 Güvenli kimlik doğrulama
* 📧 Email bildirimleri (SendGrid)
* 🔄 Otomatik deposit monitoring (Blockchain)
* 💳 Çoklu ağ desteği (Ethereum, BSC, Tron)
* 🎨 Modern glassmorphism tasarım

## 🛠️ Teknolojiler

* **Backend:** PHP 8.0+
* **Database:** MySQL/MariaDB
* **Frontend:** JavaScript (Vanilla), Bootstrap 5
* **Blockchain:** Web3.js, TronWeb
* **Email:** SendGrid
* **Deployment:** Vercel (Serverless Functions)

## 📋 Gereksinimler

* PHP 8.0 veya üzeri
* MySQL 5.7+ veya MariaDB 10.3+
* Node.js 18+ (Deposit monitor için)
* Composer
* SendGrid API Key (Email için)

## 🔧 Kurulum

### 1. Repository'yi klonlayın

```bash
git clone https://github.com/Ovtnc/CopyTrade.git
cd CopyTrade
```

### 2. Veritabanını oluşturun

```bash
mysql -u root -p < __cs/database.sql
```

### 3. Environment dosyasını oluşturun

`.env` dosyası oluşturun ve aşağıdaki değişkenleri ekleyin:

```env
# Database
DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASSWORD=your_password
DB_NAME=copystar_net

# Web URL
WEB_URL=http://localhost

# SendGrid
SENDGRID_API_KEY=your_sendgrid_api_key

# Blockchain RPC (Deposit Monitor için)
ETH_RPC_URL=https://mainnet.infura.io/v3/YOUR_PROJECT_ID
BSC_RPC_URL=https://bsc-dataseed.binance.org/
TRON_RPC_URL=https://api.trongrid.io

# Token Contracts
USDT_ETH_CONTRACT=0xdAC17F958D2ee523a2206206994597C13D831ec7
USDC_ETH_CONTRACT=0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48
USDT_BSC_CONTRACT=0x55d398326f99059fF775485246999027B3197955
USDC_BSC_CONTRACT=0x8AC76a51cc950d9822D68b83fE1Ad97B32Cd580d
USDT_TRON_CONTRACT=TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t
USDC_TRON_CONTRACT=TEkxiTehnzSmSe2XqrBj4w32RUN966rdz8n
```

### 4. Config dosyasını düzenleyin

`__cs/c.php` dosyasını oluşturun ve veritabanı bilgilerinizi ekleyin:

```php
<?php
$host = getenv('DB_HOST') ?: "localhost";
$port = getenv('DB_PORT') ?: "3306";
$user = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASSWORD') ?: "";
$database = getenv('DB_NAME') ?: "copystar_net";

$conn = new mysqli($host, $user, $password, $database, $port);
// ... rest of config
```

### 5. Composer bağımlılıklarını yükleyin

```bash
composer install
```

### 6. Node.js bağımlılıklarını yükleyin (Deposit Monitor için)

```bash
npm install
```

### 7. Admin kullanıcısı oluşturun

SQL sorgusu ile:

```sql
INSERT INTO `users` (
    `email`, `email_verified`, `name_surname`, `password`, 
    `account_level`, `kyc_verified`, `referral_code`, `status`
) VALUES (
    'admin@copystar.com', 1, 'Admin User', 
    'Y29weXN0YXIyMDI1', 10, 1, 'ADMIN2025', 'active'
);
```

Şifre: `copystar2025`

## 🚀 Vercel Deployment

### 1. Vercel CLI'yi yükleyin

```bash
npm i -g vercel
```

### 2. Vercel'e login olun

```bash
vercel login
```

### 3. Projeyi Vercel'e bağlayın

```bash
vercel link
```

### 4. Environment Variables ekleyin

Vercel dashboard'dan veya CLI ile:

```bash
vercel env add DB_HOST
vercel env add DB_PORT
vercel env add DB_USER
vercel env add DB_PASSWORD
vercel env add DB_NAME
vercel env add ETH_RPC_URL
vercel env add BSC_RPC_URL
vercel env add TRON_RPC_URL
vercel env add CRON_SECRET
```

### 5. Deploy edin

```bash
vercel --prod
```

## 📝 Kullanım

1. Admin panelinden trader ekleyin (`/admin/traders/add`)
2. Kullanıcılar trader'ları takip edebilir (`/traders`)
3. Trader işlem yaptığında otomatik olarak kopyalanır
4. Kar/zarar otomatik hesaplanır
5. Deposit monitor otomatik olarak blockchain'deki yatırımları kontrol eder

## 🔄 Deposit Monitor

Deposit monitor sistemi otomatik olarak:
- Kullanıcı wallet adreslerini tarar
- Blockchain'deki yeni transaction'ları bulur
- Kullanıcı bakiyelerini günceller

**Vercel'de:** Vercel Cron ile her 5 dakikada bir çalışır (`/api/deposit-monitor`)

**Lokal'de:** PM2 ile sürekli çalıştırın:

```bash
pm2 start controllers/depositMonitor.js --name deposit-monitor
```

## 📁 Proje Yapısı

```
CopyTrade/
├── __cs/              # Core PHP dosyaları
│   ├── p/             # Sayfalar
│   ├── admin/         # Admin paneli
│   └── auth.php       # Authentication
├── api/               # Vercel serverless functions
│   └── deposit-monitor.js
├── controllers/       # Node.js controllers
│   └── depositMonitor.js
├── vendor/            # Composer packages
├── vercel.json       # Vercel config
└── package.json      # Node.js dependencies
```

## 🔒 Güvenlik

* `.env` dosyasını asla commit etmeyin
* `__cs/c.php` dosyasını `.gitignore`'a ekleyin
* Production'da güçlü şifreler kullanın
* HTTPS kullanın
* Database bağlantılarını güvenli tutun

## 📄 Lisans

MIT

## 🤝 Katkıda Bulunma

Pull request'ler memnuniyetle karşılanır. Büyük değişiklikler için önce bir issue açarak neyi değiştirmek istediğinizi tartışın.

## 📞 İletişim

Sorularınız için issue açabilirsiniz.

