<?php
/**
 * WordPress İçerik İmport Scripti
 * Sadece: Yazılar, Sayfalar, Elementor İçerikleri, Medya
 * 
 * Kullanım: Bu dosyayı yeni WordPress sitenizin root dizinine koyup çalıştırın
 */

// WordPress yapılandırmasını yükle
require_once('wp-config.php');
require_once('wp-load.php');

// Hata raporlamayı aç
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Bellek ve zaman limitlerini artır
ini_set('memory_limit', '512M');
set_time_limit(300);

class WordPressContentImporter {
    
    private $import_dir;
    private $old_url;
    private $new_url;
    
    public function __construct() {
        $this->import_dir = ABSPATH . 'wp-content-imports/';
        $this->new_url = home_url();
        
        // Import dizinini oluştur
        if (!file_exists($this->import_dir)) {
            mkdir($this->import_dir, 0755, true);
        }
    }
    
    public function importContentFromZip($zip_file_path) {
        echo "<h2>WordPress İçerik İmport İşlemi Başlatılıyor...</h2>\n";
        echo "<div style='font-family: Arial; max-width: 800px; margin: 20px auto; padding: 20px; border: 1px solid #ccc;'>\n";
        
        try {
            // 1. ZIP dosyasını aç
            echo "<p>📦 ZIP dosyası açılıyor...</p>\n";
            flush();
            $this->extractZip($zip_file_path);
            
            // 2. Kategoriler ve etiketleri import et
            echo "<p>🏷️ Kategoriler ve etiketler import ediliyor...</p>\n";
            flush();
            $this->importTaxonomies();
            
            // 3. Medya dosyalarını import et
            echo "<p>📷 Medya dosyaları import ediliyor...</p>\n";
            flush();
            $this->importMediaFiles();
            
            // 4. Yazılar ve sayfaları import et
            echo "<p>📝 Yazılar ve sayfalar import ediliyor...</p>\n";
            flush();
            $this->importPostsAndPages();
            
            // 5. Elementor verilerini import et
            echo "<p>🎨 Elementor verileri import ediliyor...</p>\n";
            flush();
            $this->importElementorData();
            
            // 6. URL'leri güncelle
            echo "<p>🔗 URL'ler güncelleniyor...</p>\n";
            flush();
            $this->updateUrls();
            
            // 7. Cache temizle
            echo "<p>🧹 Cache temizleniyor...</p>\n";
            flush();
            $this->clearCache();
            
            // 8. Geçici dosyaları temizle
            $this->cleanup();
            
            echo "<h3 style='color: green;'>✅ İçerik İmport Tamamlandı!</h3>\n";
            echo "<p><strong>Önemli:</strong> Lütfen permalink ayarlarınızı kontrol edin.</p>\n";
            echo "<p><strong>Elementor:</strong> Elementor > Araçlar > Regenerate CSS yapın.</p>\n";
            
        } catch (Exception $e) {
            echo "<h3 style='color: red;'>❌ Hata Oluştu:</h3>\n";
            echo "<p>" . $e->getMessage() . "</p>\n";
        }
        
        echo "</div>\n";
    }
    
    private function extractZip($zip_file_path) {
        if (!file_exists($zip_file_path)) {
            throw new Exception("ZIP dosyası bulunamadı: " . $zip_file_path);
        }
        
        if (!class_exists('ZipArchive')) {
            throw new Exception("ZipArchive sınıfı bulunamadı.");
        }
        
        $zip = new ZipArchive();
        
        if ($zip->open($zip_file_path) !== TRUE) {
            throw new Exception("ZIP dosyası açılamadı: " . $zip_file_path);
        }
        
        // Extract dizinini temizle
        if (file_exists($this->import_dir)) {
            $this->deleteDirectory($this->import_dir);
        }
        mkdir($this->import_dir, 0755, true);
        
        $zip->extractTo($this->import_dir);
        $zip->close();
        
        echo "&nbsp;&nbsp;✓ ZIP dosyası açıldı\n";
    }
    
    private function importTaxonomies() {
        $taxonomy_file = $this->import_dir . '/taxonomies.json';
        
        if (!file_exists($taxonomy_file)) {
            echo "&nbsp;&nbsp;⚠️ Kategori/etiket dosyası bulunamadı, atlanıyor\n";
            return;
        }
        
        $data = json_decode(file_get_contents($taxonomy_file), true);
        
        if (!$data || !isset($data['terms'])) {
            echo "&nbsp;&nbsp;⚠️ Kategori/etiket verisi okunamadı\n";
            return;
        }
        
        global $wpdb;
        $imported_terms = 0;
        
        foreach ($data['terms'] as $term_data) {
            // Term var mı kontrol et
            $existing_term = term_exists($term_data['name'], $term_data['taxonomy']);
            
            if (!$existing_term) {
                // Yeni term oluştur
                $term_result = wp_insert_term(
                    $term_data['name'],
                    $term_data['taxonomy'],
                    array(
                        'description' => $term_data['description'],
                        'slug' => $term_data['slug'],
                        'parent' => $term_data['parent']
                    )
                );
                
                if (!is_wp_error($term_result)) {
                    $imported_terms++;
                    
                    // Term meta varsa ekle
                    if (isset($data['term_meta'])) {
                        foreach ($data['term_meta'] as $meta) {
                            if ($meta['term_id'] == $term_data['term_id']) {
                                update_term_meta($term_result['term_id'], $meta['meta_key'], $meta['meta_value']);
                            }
                        }
                    }
                }
            }
        }
        
        echo "&nbsp;&nbsp;✓ $imported_terms kategori/etiket import edildi\n";
    }
    
    private function importMediaFiles() {
        $media_file = $this->import_dir . '/media_library.json';
        $uploads_dir = $this->import_dir . '/uploads/';
        
        if (!file_exists($media_file)) {
            echo "&nbsp;&nbsp;⚠️ Medya dosyası bulunamadı, atlanıyor\n";
            return;
        }
        
        $data = json_decode(file_get_contents($media_file), true);
        
        if (!$data || !isset($data['attachments'])) {
            echo "&nbsp;&nbsp;⚠️ Medya verisi okunamadı\n";
            return;
        }
        
        // Uploads klasörünü kopyala
        if (file_exists($uploads_dir)) {
            $wp_upload_dir = wp_upload_dir();
            $this->copyDirectory($uploads_dir, $wp_upload_dir['basedir']);
        }
        
        // Medya library kayıtlarını oluştur
        global $wpdb;
        $imported_media = 0;
        
        foreach ($data['attachments'] as $attachment) {
            // Medya var mı kontrol et
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'attachment'",
                $attachment['post_title']
            ));
            
            if (!$existing) {
                // URL'leri güncelle
                $attachment['post_content'] = $this->replaceUrlInContent($attachment['post_content']);
                $attachment['post_excerpt'] = $this->replaceUrlInContent($attachment['post_excerpt']);
                $attachment['guid'] = $this->replaceUrlInContent($attachment['guid']);
                
                // Attachment ekle
                $attachment_id = wp_insert_post(array(
                    'post_title' => $attachment['post_title'],
                    'post_content' => $attachment['post_content'],
                    'post_excerpt' => $attachment['post_excerpt'],
                    'post_status' => $attachment['post_status'],
                    'post_type' => 'attachment',
                    'post_mime_type' => $attachment['post_mime_type'],
                    'guid' => $attachment['guid'],
                    'post_date' => $attachment['post_date']
                ));
                
                if ($attachment_id) {
                    $imported_media++;
                    
                    // Attachment meta ekle
                    if (isset($data['attachment_meta'])) {
                        foreach ($data['attachment_meta'] as $meta) {
                            if ($meta['post_id'] == $attachment['ID']) {
                                $meta_value = $this->replaceUrlInContent($meta['meta_value']);
                                update_post_meta($attachment_id, $meta['meta_key'], $meta_value);
                            }
                        }
                    }
                }
            }
        }
        
        echo "&nbsp;&nbsp;✓ $imported_media medya dosyası import edildi\n";
    }
    
    private function importPostsAndPages() {
        $posts_file = $this->import_dir . '/posts_and_pages.json';
        
        if (!file_exists($posts_file)) {
            throw new Exception("Yazılar/sayfalar dosyası bulunamadı");
        }
        
        $data = json_decode(file_get_contents($posts_file), true);
        
        if (!$data || !isset($data['posts'])) {
            throw new Exception("Yazılar/sayfalar verisi okunamadı");
        }
        
        // Eski URL'yi al
        $this->old_url = isset($data['site_url']) ? $data['site_url'] : '';
        
        global $wpdb;
        $imported_posts = 0;
        
        foreach ($data['posts'] as $post_data) {
            // Post var mı kontrol et
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = %s",
                $post_data['post_title'],
                $post_data['post_type']
            ));
            
            if (!$existing) {
                // URL'leri güncelle
                $post_data['post_content'] = $this->replaceUrlInContent($post_data['post_content']);
                $post_data['post_excerpt'] = $this->replaceUrlInContent($post_data['post_excerpt']);
                
                // Post ekle
                $post_id = wp_insert_post(array(
                    'post_title' => $post_data['post_title'],
                    'post_content' => $post_data['post_content'],
                    'post_excerpt' => $post_data['post_excerpt'],
                    'post_status' => $post_data['post_status'],
                    'post_type' => $post_data['post_type'],
                    'post_date' => $post_data['post_date'],
                    'post_name' => $post_data['post_name'],
                    'post_author' => 1, // Admin kullanıcısı
                    'menu_order' => $post_data['menu_order']
                ));
                
                if ($post_id) {
                    $imported_posts++;
                    
                    // Post meta ekle
                    if (isset($data['post_meta'])) {
                        foreach ($data['post_meta'] as $meta) {
                            if ($meta['post_id'] == $post_data['ID']) {
                                $meta_value = $this->replaceUrlInContent($meta['meta_value']);
                                update_post_meta($post_id, $meta['meta_key'], $meta_value);
                            }
                        }
                    }
                    
                    // Kategoriler ve etiketleri ata
                    $this->assignTermsToPost($post_id, $post_data['ID']);
                }
            }
        }
        
        echo "&nbsp;&nbsp;✓ $imported_posts yazı/sayfa import edildi\n";
    }
    
    private function assignTermsToPost($new_post_id, $old_post_id) {
        // Term relationships dosyasından ilişkileri al
        $taxonomy_file = $this->import_dir . '/taxonomies.json';
        
        if (!file_exists($taxonomy_file)) {
            return;
        }
        
        $data = json_decode(file_get_contents($taxonomy_file), true);
        
        if (!isset($data['term_relationships'])) {
            return;
        }
        
        $categories = array();
        $tags = array();
        
        foreach ($data['term_relationships'] as $rel) {
            if ($rel['object_id'] == $old_post_id) {
                // Term'i taxonomy'sine göre ayır
                foreach ($data['terms'] as $term) {
                    if ($term['term_taxonomy_id'] == $rel['term_taxonomy_id']) {
                        if ($term['taxonomy'] == 'category') {
                            $categories[] = $term['name'];
                        } elseif ($term['taxonomy'] == 'post_tag') {
                            $tags[] = $term['name'];
                        }
                    }
                }
            }
        }
        
        // Kategorileri ata
        if (!empty($categories)) {
            wp_set_post_categories($new_post_id, $categories, false);
        }
        
        // Etiketleri ata
        if (!empty($tags)) {
            wp_set_post_tags($new_post_id, $tags, false);
        }
    }
    
    private function importElementorData() {
        $elementor_file = $this->import_dir . '/elementor_data.json';
        
        if (!file_exists($elementor_file)) {
            echo "&nbsp;&nbsp;⚠️ Elementor dosyası bulunamadı, atlanıyor\n";
            return;
        }
        
        $data = json_decode(file_get_contents($elementor_file), true);
        
        if (!$data) {
            echo "&nbsp;&nbsp;⚠️ Elementor verisi okunamadı\n";
            return;
        }
        
        $updated_elementor = 0;
        
        // Elementor ayarlarını güncelle
        if (isset($data['elementor_settings'])) {
            foreach ($data['elementor_settings'] as $setting) {
                // Post ID'yi yeni post ile eşleştir
                $new_post_id = $this->findPostByTitle($setting['post_id']);
                
                if ($new_post_id) {
                    $meta_value = $this->replaceUrlInContent($setting['meta_value']);
                    update_post_meta($new_post_id, $setting['meta_key'], $meta_value);
                    $updated_elementor++;
                }
            }
        }
        
        // Elementor kit ayarlarını güncelle
        if (isset($data['elementor_kit'])) {
            foreach ($data['elementor_kit'] as $option) {
                $option_value = $this->replaceUrlInContent($option['option_value']);
                update_option($option['option_name'], $option_value);
            }
        }
        
        echo "&nbsp;&nbsp;✓ $updated_elementor Elementor ayarı güncellendi\n";
    }
    
    private function findPostByTitle($old_post_id) {
        // Önce posts_and_pages.json'dan eski post'un title'ını bul
        $posts_file = $this->import_dir . '/posts_and_pages.json';
        
        if (!file_exists($posts_file)) {
            return false;
        }
        
        $data = json_decode(file_get_contents($posts_file), true);
        
        if (!isset($data['posts'])) {
            return false;
        }
        
        $post_title = '';
        foreach ($data['posts'] as $post) {
            if ($post['ID'] == $old_post_id) {
                $post_title = $post['post_title'];
                break;
            }
        }
        
        if (empty($post_title)) {
            return false;
        }
        
        // Yeni sitede bu title'a sahip post'u bul
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s",
            $post_title
        ));
    }
    
    private function updateUrls() {
        if (empty($this->old_url) || $this->old_url === $this->new_url) {
            echo "&nbsp;&nbsp;✓ URL güncellemesi gerekmiyor\n";
            return;
        }
        
        global $wpdb;
        
        // Posts içeriğindeki URL'leri değiştir
        $wpdb->query($wpdb->prepare("
            UPDATE {$wpdb->posts} 
            SET post_content = REPLACE(post_content, %s, %s)
            WHERE post_type IN ('post', 'page')
        ", $this->old_url, $this->new_url));
        
        // Postmeta tablosundaki URL'leri değiştir (Elementor için önemli)
        $wpdb->query($wpdb->prepare("
            UPDATE {$wpdb->postmeta} 
            SET meta_value = REPLACE(meta_value, %s, %s)
        ", $this->old_url, $this->new_url));
        
        echo "&nbsp;&nbsp;✓ URL'ler güncellendi ({$this->old_url} → {$this->new_url})\n";
    }
    
    private function replaceUrlInContent($content) {
        if (empty($this->old_url) || empty($content)) {
            return $content;
        }
        
        return str_replace($this->old_url, $this->new_url, $content);
    }
    
    private function clearCache() {
        // WordPress cache temizle
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Rewrite rules'ları güncelle
        flush_rewrite_rules();
        
        // Elementor cache temizle (varsa)
        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
        
        echo "&nbsp;&nbsp;✓ Cache temizlendi\n";
    }
    
    private function copyDirectory($src, $dst) {
        if (!is_dir($src)) return;
        
        $dir = opendir($src);
        @mkdir($dst, 0755, true);
        
        while (($file = readdir($dir)) !== false) {
            if ($file == '.' || $file == '..') continue;
            
            $src_file = $src . '/' . $file;
            $dst_file = $dst . '/' . $file;
            
            if (is_dir($src_file)) {
                $this->copyDirectory($src_file, $dst_file);
            } else {
                copy($src_file, $dst_file);
            }
        }
        
        closedir($dir);
    }
    
    private function cleanup() {
        // Import dizinini temizle
        $this->deleteDirectory($this->import_dir);
        echo "&nbsp;&nbsp;✓ Geçici dosyalar temizlendi\n";
    }
    
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) return;
        
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') continue;
            
            $file_path = $dir . '/' . $file;
            if (is_dir($file_path)) {
                $this->deleteDirectory($file_path);
            } else {
                unlink($file_path);
            }
        }
        rmdir($dir);
    }
}

// Scripti çalıştır
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>WordPress İçerik İmport</title>
        <meta charset="utf-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; background: #f1f1f1; }
            .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .button { background: #0073aa; color: white; padding: 15px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; text-decoration: none; display: inline-block; }
            .button:hover { background: #005a87; }
            .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .info { background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 20px 0; color: #0c5460; }
            .file-input { width: 100%; padding: 10px; border: 2px dashed #ccc; border-radius: 5px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>📥 WordPress İçerik İmport</h1>
            
            <div class="info">
                <strong>📋 Bu Script Sadece İçerikleri İmport Eder:</strong>
                <ul>
                    <li>✅ Yazılar (Posts)</li>
                    <li>✅ Sayfalar (Pages)</li>
                    <li>✅ Elementor Sayfaları ve Ayarları</li>
                    <li>✅ Medya Dosyaları (Resimler, Videolar)</li>
                    <li>✅ Kategoriler ve Etiketler</li>
                </ul>
                <p><strong>NOT:</strong> Tema ve plugin ayarları import edilmez.</p>
            </div>
            
            <div class="warning">
                <strong>📋 İşlem Notları:</strong>
                <ul>
                    <li>Bu işlem 5-15 dakika sürebilir</li>
                    <li>Mevcut içerikler korunur, sadece yeniler eklenir</li>
                    <li>İşlem sırasında sayfayı kapatmayın</li>
                    <li>ZIP dosyası içerik yedek scriptiyle oluşturulmuş olmalı</li>
                </ul>
            </div>
            
            <p><strong>İmport Edilecek İçerikler:</strong></p>
            <ul>
                <li>📝 Yazılar ve sayfalar</li>
                <li>🎨 Elementor tasarımları</li>
                <li>📷 Medya dosyaları</li>
                <li>🏷️ Kategoriler ve etiketler</li>
                <li>🔗 Otomatik URL güncellemeleri</li>
            </ul>
            
            <form method="post" enctype="multipart/form-data">
                <p><strong>İçerik Yedek ZIP Dosyasını Seçin:</strong></p>
                <input type="file" name="content_zip" accept=".zip" required class="file-input">
                
                <br><br>
                
                <label>
                    <input type="checkbox" name="confirm" required> 
                    İçeriklerin mevcut siteye eklenebileceğini anlıyorum
                </label>
                
                <br><br>
                
                <button type="submit" name="start_content_import" class="button">🚀 İçerik İmport İşlemini Başlat</button>
            </form>
        </div>
    </body>
    </html>
    <?php
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_content_import'])) {
    echo "<!DOCTYPE html><html><head><title>İçerik İmport İşlemi</title><meta charset='utf-8'></head><body>";
    
    if (!isset($_POST['confirm'])) {
        echo "<h3 style='color: red;'>Onay kutusu işaretlenmedi!</h3>";
        echo "<a href='" . $_SERVER['PHP_SELF'] . "'>Geri Dön</a>";
        echo "</body></html>";
        exit;
    }
    
    if (!isset($_FILES['content_zip']) || $_FILES['content_zip']['error'] !== UPLOAD_ERR_OK) {
        echo "<h3 style='color: red;'>ZIP dosyası yüklenemedi!</h3>";
        echo "<a href='" . $_SERVER['PHP_SELF'] . "'>Geri Dön</a>";
        echo "</body></html>";
        exit;
    }
    
    $uploaded_file = $_FILES['content_zip']['tmp_name'];
    $target_file = ABSPATH . 'temp_content_' . time() . '.zip';
    
    if (move_uploaded_file($uploaded_file, $target_file)) {
        $importer = new WordPressContentImporter();
        $importer->importContentFromZip($target_file);
        
        // Geçici dosyayı sil
        unlink($target_file);
    } else {
        echo "<h3 style='color: red;'>Dosya yüklenemedi!</h3>";
    }
    
    echo "<br><a href='" . home_url() . "' style='display: inline-block; margin-top: 20px; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 5px;'>🏠 Siteye Git</a>";
    echo "<a href='" . admin_url() . "' style='display: inline-block; margin-top: 20px; margin-left: 10px; padding: 10px 20px; background: #00a32a; color: white; text-decoration: none; border-radius: 5px;'>⚙️ Admin Panel</a>";
    echo "</body></html>";
}
?> 