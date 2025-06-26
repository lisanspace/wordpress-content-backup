# WordPress İçerik Yedekleme ve Transfer Sistemi

Bu sistem **sadece içeriklerinizi** yedekleyip başka bir WordPress sitesine taşımak için geliştirilmiştir.

## 📋 Bu Sistem Ne Yapar?

### ✅ Yedeklenen İçerikler:
- 📝 **Yazılar** (Posts)
- 📄 **Sayfalar** (Pages) 
- 🎨 **Elementor Sayfaları** ve tasarımları
- 📷 **Medya Dosyaları** (resimler, videolar)
- 🏷️ **Kategoriler ve Etiketler**

### ❌ Yedeklenmeyen İçerikler:
- Tema dosyaları
- Plugin dosyaları
- Veritabanı ayarları
- Kullanıcı hesapları
- WordPress core dosyaları

## 📁 Dosyalar

1. **`wp-content-export.php`** - İçerik yedekleme scripti
2. **`wp-content-import.php`** - İçerik import scripti
3. **`README-Content-Backup.md`** - Bu kılavuz

## 🚀 Kullanım Talimatları

### 1️⃣ İçerik Yedekleme

1. **Eski sitenizde:**
   - `wp-content-export.php` dosyasını WordPress root dizinine yükleyin
   - Tarayıcıda `https://eskisite.com/wp-content-export.php` adresine gidin
   - "İçerik Yedeklemeyi Başlat" butonuna tıklayın
   - İşlem tamamlandığında ZIP dosyasını indirin

2. **Yedekleme süreci:**
   ```
   📝 Yazılar ve sayfalar toplanıyor...
   🎨 Elementor verileri yedekleniyor...
   📷 Medya dosyaları kopyalanıyor...
   🏷️ Kategoriler ve etiketler kaydediliyor...
   📦 ZIP dosyası oluşturuluyor...
   ```

### 2️⃣ İçerik Import Etme

1. **Yeni sitenizde:**
   - WordPress'i temiz olarak kurun
   - Elementor plugin'ini kurun (Elementor sayfalarınız varsa)
   - `wp-content-import.php` dosyasını WordPress root dizinine yükleyin

2. **Import işlemi:**
   - Tarayıcıda `https://yenisite.com/wp-content-import.php` adresine gidin
   - Yedek ZIP dosyasını seçin
   - Onay kutusunu işaretleyin
   - "İçerik İmport İşlemini Başlat" butonuna tıklayın

3. **Import süreci:**
   ```
   📦 ZIP dosyası açılıyor...
   🏷️ Kategoriler ve etiketler import ediliyor...
   📷 Medya dosyaları import ediliyor...
   📝 Yazılar ve sayfalar import ediliyor...
   🎨 Elementor verileri import ediliyor...
   🔗 URL'ler güncelleniyor...
   🧹 Cache temizleniyor...
   ```

## ⚙️ Teknik Gereksinimler

- PHP 7.4+
- ZipArchive desteği
- 512MB+ PHP memory limit (büyük siteler için)
- 300 saniye+ execution time
- WordPress 5.0+

## 🎯 Kullanım Senaryoları

### 1. **Site Taşıma**
- Hosting değiştirme
- Domain değiştirme
- Alt domain'den ana domain'e taşıma

### 2. **Test Sitesi Oluşturma**
- Canlı siteden test sitesine içerik kopyalama
- Geliştirme ortamı hazırlama

### 3. **İçerik Yedekleme**
- Düzenli içerik yedeklemeleri
- Güvenlik amaçlı yedekler

### 4. **Site Klonlama**
- Aynı içerikle birden fazla site oluşturma
- Franchise/şube siteleri

## 📈 Avantajlar

✅ **Hızlı ve Basit** - Karmaşık ayarlar gerektirmez  
✅ **Güvenli** - Sadece içerik transfer edilir  
✅ **Elementor Uyumlu** - Tasarımlar korunur  
✅ **URL Güncelleme** - Otomatik URL değiştirme  
✅ **Seçici Transfer** - Sadece istenen içerikler  

## ⚠️ Önemli Notlar

### Yedekleme Öncesi:
- [ ] Sitenizin yedeğini alın
- [ ] Büyük siteler için PHP limitlerini artırın
- [ ] İşlem sırasında site yavaşlayabilir

### Import Öncesi:
- [ ] Yeni sitede temiz WordPress kurulumu yapın
- [ ] Gerekli plugin'leri kurun (Elementor vb.)
- [ ] Mevcut içeriklerin üzerine yazılabileceğini unutmayın

### Import Sonrası:
- [ ] Permalink ayarlarını kontrol edin
- [ ] Elementor > Araçlar > Regenerate CSS yapın
- [ ] Menüleri yeniden oluşturun
- [ ] Widget'ları kontrol edin

## 🔧 Sorun Giderme

### Yaygın Hatalar:

**"ZipArchive sınıfı bulunamadı"**
```php
// Hosting panel'iniz PHP extensionlarında ZIP desteğini aktif edin
```

**"Memory limit exceeded"**
```php
// wp-config.php dosyasına ekleyin:
ini_set('memory_limit', '1024M');
```

**"Maximum execution time exceeded"**
```php
// wp-config.php dosyasına ekleyin:
set_time_limit(600);
```

**"Medya dosyaları görünmüyor"**
- Upload klasörü izinlerini kontrol edin (755)
- .htaccess dosyasını kontrol edin

## 🔐 Güvenlik

- [ ] İşlem tamamlandıktan sonra script dosyalarını silin
- [ ] ZIP dosyasını güvenli bir yerde saklayın
- [ ] FTP şifrelerinizi düzenli olarak değiştirin

## 📞 Destek

Bu sistem hakkında sorularınız varsa:

1. **Log dosyalarını kontrol edin** - WordPress debug logları
2. **PHP error_log dosyasını inceleyin**
3. **Hosting sağlayıcınızla PHP limitlerini görüşün**

## 🔄 Sürüm Geçmişi

- **v1.0** - İlk sürüm
- Temel içerik yedekleme
- Elementor desteği
- URL güncelleme sistemi

---

**Not:** Bu sistem sadece içerik transferi içindir. Tam site yedeklemesi için diğer backup script'ini kullanın. 
