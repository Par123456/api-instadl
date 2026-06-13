# Instagram Downloader API - وب سرویس دانلود از اینستاگرام

<div dir="rtl">

## 📱 توضیحات کامل

این یک **API وب‌سرویس کامل و رایگان** برای دانلود پست‌ها و ریلزهای اینستاگرام است که با زبان PHP نوشته شده است.

### ✨ ویژگی‌ها

- ✅ **کاملاً رایگان** - بدون نیاز به API Key یا ثبت‌نام
- ✅ **پشتیبانی از پست‌ها** - دانلود عکس‌های معمولی
- ✅ **پشتیبانی از ریلزها** - دانلود ویدیوهای Reels
- ✅ **پشتیبانی از IGTV** - دانلود ویدیوهای طولانی
- ✅ **پشتیبانی از Carousel** - دانلود پست‌های چندتصویری/چندویدیویی
- ✅ **بدون نیاز به لاگین** - کار بدون نیاز به اکانت اینستاگرام
- ✅ **خروجی JSON** - پاسخ‌های ساختاریافته و آسان برای استفاده
- ✅ **CORS فعال** - قابل استفاده از مرورگر و اپلیکیشن‌ها
- ✅ **سرعت بالا** - بهینه‌سازی شده برای عملکرد سریع
- ✅ **خطایابی دقیق** - پیام‌های خطای واضح و مفید

### 🔧 نیازمندی‌ها

- PHP 7.4 یا بالاتر
- فعال بودن افزونه cURL در PHP
- دسترسی به اینترنت برای ارتباط با سرورهای اینستاگرام

### 🚀 نصب و راه‌اندازی

1. فایل‌ها را روی سرور خود آپلود کنید
2. مطمئن شوید که cURL در سرور شما فعال است
3. API آماده استفاده است!

```bash
# بررسی فعال بودن cURL
php -m | grep curl
```

### 📖 نحوه استفاده

#### درخواست GET

```
GET /api.php?url=https://www.instagram.com/p/ABC123xyz/
```

#### درخواست POST (JSON)

```json
POST /api.php
Content-Type: application/json

{
    "url": "https://www.instagram.com/reel/ABC123xyz/"
}
```

#### درخواست POST (Form)

```
POST /api.php
Content-Type: application/x-www-form-urlencoded

url=https://www.instagram.com/p/ABC123xyz/
```

### 💻 نمونه کد JavaScript

```javascript
// مثال با Fetch API
async function downloadInstagram(url) {
    try {
        const response = await fetch('api.php?url=' + encodeURIComponent(url));
        const data = await response.json();
        
        if (data.success) {
            console.log('دانلود موفق:', data.data);
            
            // دانلود ویدیو
            if (data.data.is_video) {
                window.open(data.data.video_url, '_blank');
            } else {
                window.open(data.data.image_url, '_blank');
            }
        } else {
            console.error('خطا:', data.error);
        }
    } catch (error) {
        console.error('خطا در درخواست:', error);
    }
}

// استفاده
downloadInstagram('https://www.instagram.com/reel/C1a2B3c4D5e/');
```

### 💻 نمونه کد PHP

```php
<?php
function downloadFromInstagram($url) {
    $apiUrl = 'http://your-domain.com/api.php?url=' . urlencode($url);
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($data['success']) {
        return $data['data'];
    } else {
        throw new Exception($data['error']);
    }
}

// استفاده
try {
    $media = downloadFromInstagram('https://www.instagram.com/p/ABC123xyz/');
    echo 'عنوان: ' . $media['caption'] . "\n";
    echo 'لینک دانلود: ' . $media['video_url'] ?? $media['image_url'];
} catch (Exception $e) {
    echo 'خطا: ' . $e->getMessage();
}
?>
```

### 💻 نمونه کد Python

```python
import requests
import json

def download_instagram(url):
    api_endpoint = "http://your-domain.com/api.php"
    params = {"url": url}
    
    response = requests.get(api_endpoint, params=params)
    data = response.json()
    
    if data["success"]:
        return data["data"]
    else:
        raise Exception(data["error"])

# استفاده
try:
    media = download_instagram("https://www.instagram.com/reel/C1a2B3c4D5e/")
    print(f"نوع: {media['type']}")
    print(f"یوزرنیم: {media['username']}")
    
    if media["is_video"]:
        print(f"لینک ویدیو: {media['video_url']}")
    else:
        print(f"لینک عکس: {media['image_url']}")
except Exception as e:
    print(f"خطا: {e}")
```

### 📊 ساختار پاسخ API

#### پاسخ موفقیت‌آمیز (200 OK)

```json
{
    "success": true,
    "data": {
        "type": "video",
        "shortcode": "C1a2B3c4D5e",
        "username": "example_user",
        "full_name": "Example User",
        "profile_pic_url": "https://...",
        "caption": "متن کپشن پست",
        "likes": 1234,
        "comments": 56,
        "timestamp": 1234567890,
        "is_video": true,
        "video_url": "https://scontent.cdninstagram.com/...",
        "video_view_count": 50000,
        "video_duration": 30.5,
        "thumbnail_url": "https://..."
    },
    "api_version": "2.0",
    "timestamp": "2024-01-15 10:30:00",
    "request_url": "https://www.instagram.com/reel/C1a2B3c4D5e/"
}
```

#### پاسخ با خطا (400 Bad Request)

```json
{
    "success": false,
    "error": "پیام خطا",
    "code": 400,
    "api_version": "2.0",
    "timestamp": "2024-01-15 10:30:00",
    "request_url": "https://www.instagram.com/reel/C1a2B3c4D5e/"
}
```

### 🎯 انواع محتوا

#### 1. پست عکس (Image Post)
```json
{
    "type": "image",
    "image_url": "https://...",
    "thumbnail_url": "https://..."
}
```

#### 2. ریلز/ویدیو (Reel/Video)
```json
{
    "type": "video",
    "video_url": "https://...",
    "thumbnail_url": "https://...",
    "video_view_count": 1000,
    "video_duration": 15.5
}
```

#### 3. پست چندتایی (Carousel)
```json
{
    "type": "carousel",
    "carousel": [
        {
            "type": "image",
            "display_url": "https://...",
            "is_video": false
        },
        {
            "type": "video",
            "display_url": "https://...",
            "video_url": "https://...",
            "is_video": true
        }
    ]
}
```

### ⚠️ نکات مهم

1. **محدودیت‌ها**: اینستاگرام ممکن است درخواست‌های زیاد را محدود کند
2. **حریم خصوصی**: فقط پست‌های عمومی قابل دانلود هستند
3. **کپی‌رایت**: محتوای دانلود شده فقط برای استفاده شخصی مجاز است
4. **آپدیت**: اینستاگرام مرتباً ساختار خود را تغییر می‌دهد، ممکن است نیاز به آپدیت باشد

### 🛠 عیب‌یابی

#### خطای "Failed to fetch Instagram page"
- اتصال اینترنت خود را بررسی کنید
- ممکن است اینستاگرام IP سرور شما را محدود کرده باشد
- از Proxy یا VPN استفاده کنید

#### خطای "Could not parse media data"
- لینک اینستاگرام را بررسی کنید
- ممکن است پست حذف شده باشد
- اینستاگرام ساختار خود را تغییر داده است

#### خطای cURL
```bash
# نصب cURL در اوبونتو
sudo apt-get install php-curl
sudo systemctl restart apache2

# نصب cURL در سنت‌او‌اس
sudo yum install php-curl
sudo systemctl restart httpd
```

### 📝 مجوز (License)

این پروژه تحت مجوز MIT منتشر شده است. استفاده تجاری و غیرتجاری آزاد است.

### 🤝 مشارکت

از مشارکت شما استقبال می‌کنیم! لطفاً برای گزارش باگ‌ها یا پیشنهاد ویژگی‌های جدید Issue ایجاد کنید.

---

<div align="center">

**ساخته شده با ❤️ توسط جامعه توسعه‌دهندگان**

[⭐ اگر خوشتان آمد ستاره دهید](../../stargazers)

</div>

</div>
