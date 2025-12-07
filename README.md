## PrensMedya — Sade PHP ile Sürükle-Bırak Site Oluşturucu (MVP)

Bu repo, **framework kullanmadan sade PHP** ile geliştirilmiş sürükle-bırak site oluşturucu MVP’sinin backend/frontend iskeletini içerir.

### 1. Kurulum

1. Depoyu klonla:

```bash
git clone <REPO_URL> prensmedya
cd prensmedya
```

2. Composer bağımlılıklarını yükle:

```bash
composer install
```

3. MySQL’de veritabanı oluştur ve şemayı yükle:

- `prensmedya` isminde bir veritabanı aç.
- `database/schema.sql` dosyasını çalıştır.

4. Ortam değişkenlerini ayarla (local için):

- Web sunucusunda veya terminal ortamında aşağıdaki değerleri tanımla:
  - `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

5. Geliştirme sunucusunu başlat:

```bash
php -S localhost:8000 -t public
```

Tarayıcıdan `http://localhost:8000` adresine git.

### 2. Temel endpoint’ler

- `POST /api/auth/register` → `{name, email, password}`
- `POST /api/auth/login` → `{email, password}`
- `GET /api/sites` / `POST /api/sites`
- `GET /api/sites/{siteId}/pages` / `POST /api/sites/{siteId}/pages`
- `PUT /api/sites/{siteId}/pages/{pageId}` / `POST .../publish`
- `POST /api/media/upload` (form-data `file`)
- `GET /s/{siteSlug}/{pageSlug}` → yayınlanmış sayfanın HTML çıktısı

