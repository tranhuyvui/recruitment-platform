````markdown
# Recruitment Platform

Recruitment Platform là hệ thống tuyển dụng được xây dựng với **Laravel** cho Backend và **Vue 3 + TypeScript + Vite** cho Frontend.

Dự án hỗ trợ các chức năng chính như quản lý người dùng, quản lý công ty, nhà tuyển dụng, ứng viên, tin tuyển dụng, ứng tuyển công việc và nhắn tin realtime bằng Laravel Reverb.

## Công nghệ sử dụng

### Backend

- PHP 8.2+
- Laravel 12
- MySQL
- MongoDB
- Laravel Reverb
- JWT Authentication
- Laravel Sanctum
- Redis / Predis
- Cloudinary
- Gemini API
- Pinecone

### Frontend

- Vue 3
- TypeScript
- Vite
- Vue Router
- Pinia
- Axios
- Tailwind CSS
- Laravel Echo
- Pusher JS
- ApexCharts

### Deploy

- Railway

## Chức năng chính

- Đăng ký và đăng nhập người dùng
- Xác thực người dùng bằng JWT
- Phân quyền theo vai trò
- Quản lý thông tin công ty
- Gửi yêu cầu tham gia công ty
- Quản lý nhà tuyển dụng
- Quản lý tin tuyển dụng
- Ứng viên ứng tuyển công việc
- Upload ảnh/logo bằng Cloudinary
- Gửi và nhận tin nhắn realtime
- Đánh dấu tin nhắn đã đọc
- Tích hợp Redis để cache và hỗ trợ xử lý dữ liệu
- Tích hợp AI thông qua Gemini API
- Tích hợp Pinecone để phục vụ tìm kiếm AI

## Cấu trúc dự án

```bash
recruitment-platform/
│
├── backend/
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   ├── Middleware/
│   │   │   └── Requests/
│   │   │
│   │   ├── Models/
│   │   ├── Services/
│   │   └── Events/
│   │
│   ├── routes/
│   ├── config/
│   ├── database/
│   ├── composer.json
│   └── .env
│
└── frontend/
    ├── src/
    ├── public/
    ├── package.json
    ├── vite.config.ts
    └── .env
```

## Yêu cầu cài đặt

Trước khi chạy dự án, cần cài đặt:

- PHP >= 8.2
- Composer
- Node.js
- NPM
- MySQL
- MongoDB
- Redis hoặc Redis cloud
- Git

## Cài đặt Backend

### 1. Clone source code

```bash
git clone https://github.com/tranhuyvui/recruitment-platform.git
cd recruitment-platform/backend
```

### 2. Cài đặt thư viện PHP

```bash
composer install
```

### 3. Tạo file môi trường

```bash
cp .env.example .env
```

### 4. Tạo APP_KEY

```bash
php artisan key:generate
```

### 5. Cấu hình file `.env` Backend

Tạo file `.env` trong thư mục Backend và cấu hình theo mẫu sau:

```env
APP_NAME=Laravel
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=your_mysql_host
DB_PORT=your_mysql_port
DB_DATABASE=your_database_name
DB_USERNAME=your_database_username
DB_PASSWORD=your_database_password

MONGO_URL=your_mongodb_connection_string
MONGO_DATABASE=your_mongodb_database_name

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=sync
CACHE_STORE=redis
FILESYSTEM_DISK=local

REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_URL=your_redis_url

JWT_SECRET=your_jwt_secret

CLOUDINARY_CLOUD_NAME=your_cloudinary_cloud_name
CLOUDINARY_API_KEY=your_cloudinary_api_key
CLOUDINARY_API_SECRET=your_cloudinary_api_secret
CLOUDINARY_URL=your_cloudinary_url

GEMINI_API_KEY=your_gemini_api_key

PINECONE_API_KEY=your_pinecone_api_key
PINECONE_HOST=your_pinecone_host

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_USERNAME=your_email
MAIL_PASSWORD=your_email_app_password
MAIL_ENCRYPTION=smtps
MAIL_FROM_ADDRESS=your_email
MAIL_FROM_NAME="Job Portal"

REVERB_APP_ID=your_reverb_app_id
REVERB_APP_KEY=your_reverb_app_key
REVERB_APP_SECRET=your_reverb_app_secret
REVERB_HOST=127.0.0.1
REVERB_PORT=8090
REVERB_SCHEME=http
```

> Lưu ý: Không được đưa file `.env` thật lên GitHub vì file này chứa thông tin nhạy cảm như mật khẩu database, API key, JWT secret và email app password.

### 6. Chạy migrate database

```bash
php artisan migrate
```

### 7. Chạy Backend

```bash
php artisan serve
```

Mặc định Backend sẽ chạy tại:

```bash
http://localhost:8000
```

## Chạy Laravel Reverb

Dự án sử dụng Laravel Reverb để xử lý realtime chat.

Khi chạy local, cần mở thêm một terminal riêng để chạy Reverb:

```bash
php artisan reverb:start --host=0.0.0.0 --port=8090 --debug
```

Khi chạy local, bạn cần mở 2 terminal:

### Terminal 1: chạy Laravel Backend

```bash
php artisan serve
```

### Terminal 2: chạy Reverb

```bash
php artisan reverb:start --host=0.0.0.0 --port=8090 --debug
```

## Cài đặt Frontend

### 1. Di chuyển vào thư mục Frontend

```bash
cd ../frontend
```

### 2. Cài đặt package

```bash
npm install
```

### 3. Cấu hình file `.env` Frontend

Tạo file `.env` trong thư mục Frontend:

```env
VITE_API_BASE_URL=https://recruitment-platform-production-462b.up.railway.app/api
VITE_REVERB_APP_KEY=your_reverb_app_key
VITE_REVERB_HOST=supportive-essence-production-252e.up.railway.app
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

Khi chạy local, có thể dùng cấu hình sau:

```env
VITE_API_BASE_URL=http://localhost:8000/api
VITE_REVERB_APP_KEY=your_reverb_app_key
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8090
VITE_REVERB_SCHEME=http
```

### 4. Chạy Frontend

```bash
npm run dev
```

Frontend sẽ chạy tại:

```bash
http://localhost:5173
```

## Build Frontend

```bash
npm run build
```

## Preview Frontend sau khi build

```bash
npm run preview
```

## Cấu hình Railway

Dự án khi deploy lên Railway nên tách thành ít nhất 2 service:

- Service Backend Laravel
- Service Laravel Reverb

## Service Backend Laravel

Start command:

```bash
php artisan serve --host=0.0.0.0 --port=$PORT
```

Backend URL sau khi deploy:

```bash
https://recruitment-platform-production-462b.up.railway.app
```

API base URL:

```bash
https://recruitment-platform-production-462b.up.railway.app/api
```

## Service Laravel Reverb

Start command:

```bash
php artisan reverb:start --host=0.0.0.0 --port=$PORT
```

Reverb URL sau khi deploy:

```bash
supportive-essence-production-252e.up.railway.app
```

Khi dùng Reverb trên Railway với HTTPS, Frontend nên cấu hình:

```env
VITE_REVERB_HOST=supportive-essence-production-252e.up.railway.app
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

Lưu ý:

- `VITE_REVERB_HOST` không được ghi `https://`
- `VITE_REVERB_SCHEME=https`
- `VITE_REVERB_PORT=443`
- `REVERB_APP_KEY` ở Backend và Frontend phải giống nhau
- `REVERB_APP_SECRET` chỉ để ở Backend, không đưa sang Frontend
- Railway sẽ tự cấp biến `$PORT`, vì vậy khi deploy nên dùng `$PORT` thay vì hard-code port

## Biến môi trường quan trọng

### Backend

| Biến môi trường | Ý nghĩa |
|---|---|
| `APP_KEY` | Key bảo mật của Laravel |
| `DB_CONNECTION` | Loại database chính |
| `DB_HOST` | Host MySQL |
| `DB_PORT` | Port MySQL |
| `DB_DATABASE` | Tên database |
| `DB_USERNAME` | Tài khoản database |
| `DB_PASSWORD` | Mật khẩu database |
| `MONGO_URL` | Chuỗi kết nối MongoDB |
| `MONGO_DATABASE` | Tên database MongoDB |
| `JWT_SECRET` | Secret dùng để ký JWT |
| `REDIS_URL` | URL Redis cloud |
| `BROADCAST_CONNECTION` | Driver broadcast, dùng `reverb` |
| `REVERB_APP_ID` | ID của Reverb app |
| `REVERB_APP_KEY` | Key của Reverb app |
| `REVERB_APP_SECRET` | Secret của Reverb app |
| `REVERB_HOST` | Host Reverb local |
| `REVERB_PORT` | Port Reverb local |
| `REVERB_SCHEME` | Giao thức Reverb |
| `CLOUDINARY_CLOUD_NAME` | Cloudinary cloud name |
| `CLOUDINARY_API_KEY` | Cloudinary API key |
| `CLOUDINARY_API_SECRET` | Cloudinary API secret |
| `GEMINI_API_KEY` | API key của Gemini |
| `PINECONE_API_KEY` | API key của Pinecone |
| `PINECONE_HOST` | Host của Pinecone index |

### Frontend

| Biến môi trường | Ý nghĩa |
|---|---|
| `VITE_API_BASE_URL` | URL API Backend |
| `VITE_REVERB_APP_KEY` | Public key để kết nối Reverb |
| `VITE_REVERB_HOST` | Host của Reverb service |
| `VITE_REVERB_PORT` | Port kết nối Reverb |
| `VITE_REVERB_SCHEME` | Giao thức kết nối Reverb |

## API chính

### Auth

```http
POST /api/login
POST /api/register
GET  /api/me
POST /api/logout
```

### Company

```http
GET  /api/company
POST /api/company
GET  /api/company/{companyID}
POST /api/company/{companyID}/request/{employerID}
PUT  /api/company/{companyID}/{employerID}
```

### Admin Company

```http
GET /api/company/admin/all
GET /api/company/admin/{companyID}
PUT /api/company/admin/{companyID}/status
```

### Message

```http
GET  /api/messages
POST /api/messages
PUT  /api/messages/read
```

## Realtime Chat

Dự án sử dụng Laravel Reverb để xử lý realtime chat.

Luồng hoạt động cơ bản:

1. Người gửi gửi tin nhắn từ Frontend lên Backend.
2. Backend kiểm tra dữ liệu và xác thực người dùng.
3. Backend lưu tin nhắn vào database.
4. Backend broadcast event thông qua Laravel Reverb.
5. Frontend của người nhận lắng nghe channel realtime.
6. Người nhận nhận tin nhắn mà không cần reload trang.
7. Khi người nhận xem tin nhắn, hệ thống cập nhật trạng thái đã đọc.
8. Backend tiếp tục broadcast sự kiện đánh dấu đã đọc nếu cần.

## Lệnh thường dùng

### Backend

```bash
composer install
php artisan key:generate
php artisan migrate
php artisan serve
php artisan reverb:start --host=0.0.0.0 --port=8090 --debug
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### Frontend

```bash
npm install
npm run dev
npm run build
npm run preview
```

## Lỗi thường gặp

### 1. Frontend không kết nối được Reverb

Kiểm tra lại file `.env` Frontend:

```env
VITE_REVERB_HOST=supportive-essence-production-252e.up.railway.app
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

Không ghi như sau:

```env
VITE_REVERB_HOST=https://supportive-essence-production-252e.up.railway.app
```

Vì `VITE_REVERB_HOST` chỉ nhận host, không nhận cả giao thức `https://`.

### 2. Local chạy được nhưng deploy không nhận realtime

Cần kiểm tra:

- Backend và Reverb đã tách thành 2 service chưa
- Service Reverb có chạy đúng command chưa
- Frontend có dùng đúng domain Reverb chưa
- `REVERB_APP_KEY` ở Backend và Frontend có giống nhau không
- Railway có public domain cho service Reverb chưa
- Frontend có build lại sau khi sửa `.env` chưa

### 3. Lỗi auth khi subscribe private channel

Cần kiểm tra:

- Token đăng nhập có được gửi kèm khi gọi `/broadcasting/auth` không
- Cấu hình Laravel Echo có truyền header `Authorization: Bearer token` không
- Route broadcast auth có được cấu hình đúng không
- User hiện tại có quyền join channel đó không

## Bảo mật

Không được commit các file sau lên GitHub:

```bash
.env
.env.local
.env.production
```

Nên thêm vào `.gitignore`:

```gitignore
.env
.env.local
.env.production
```

Các thông tin không nên public:

- Database password
- MongoDB connection string
- Redis URL
- JWT secret
- Cloudinary API secret
- Gemini API key
- Pinecone API key
- Gmail app password
- Reverb app secret

Nếu các key này đã bị public hoặc gửi nhầm, nên tạo lại key mới để đảm bảo an toàn.

## Tác giả

Dự án được phát triển bởi:

**Trần Huy Vui**

GitHub: [tranhuyvui](https://github.com/tranhuyvui)
````
