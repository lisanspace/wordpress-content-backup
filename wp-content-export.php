<?php
/**
 * WordPress İçerik Yedekleme Scripti
 * Sadece: Yazılar, Sayfalar, Elementor İçerikleri, Medya
 * 
 * Kullanım: Bu dosyayı WordPress root dizinine koyup çalıştırın
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

class WordPressContentBackup {
    
    private $backup_dir;
    private $backup_name;
    private $zip_file;
    
    public function __construct() {
        $this->backup_name = 'wp_content_backup_' . date('Y-m-d_H-i-s');
        $this->backup_dir = ABSPATH . 'wp-content-backups/' . $this->backup_name;
        $this->zip_file = ABSPATH . 'wp-content-backups/' . $this->backup_name . '.zip';
        
        // Backup dizinini oluştur
        if (!file_exists(ABSPATH . 'wp-content-backups/')) {
            mkdir(ABSPATH . 'wp-content-backups/', 0755, true);
        }
        
        if (!file_exists($this->backup_dir)) {
            mkdir($this->backup_dir, 0755, true);
        }
    }
    
    public function createContentBackup() {
        echo "<h2>WordPress İçerik Yedekleme Başlatılıyor...</h2>\n";
        echo "<div style='font-family: Arial; max-width: 800px; margin: 20px auto; padding: 20px; border: 1px solid #ccc;'>\n";
        
        try {
            // 1. Yazılar ve Sayfalar
            echo "<p>📝 Yazılar ve sayfalar yedekleniyor...</p>\n";
            flush();
            $this->backupPostsAndPages();
            
            // 2. Elementor verilerini yedekle
            echo "<p>🎨 Elementor verileri yedekleniyor...</p>\n";
            flush();
            $this->backupElementorData();
            
            // 3. Medya dosyalarını yedekle
            echo "<p>📷 Medya dosyaları yedekleniyor...</p>\n";
            flush();
            $this->backupMediaFiles();
            
            // 4. Kategoriler ve etiketler
            echo "<p>🏷️ Kategoriler ve etiketler yedekleniyor...</p>\n";
            flush();
            $this->backupTaxonomies();
            
            // 5. ZIP oluştur
            echo "<p>📦 ZIP dosyası oluşturuluyor...</p>\n";
            flush();
            $this->createZip();
            
            // 6. Geçici dosyaları temizle
            $this->cleanup();
            
            echo "<h3 style='color: green;'>✅ İçerik Yedekleme Tamamlandı!</h3>\n";
            echo "<p><strong>İndirme Linki:</strong> <a href='" . home_url() . "/wp-content-backups/" . basename($this->zip_file) . "' download>📥 " . basename($this->zip_file) . "</a></p>\n";
            echo "<p><strong>Dosya Boyutu:</strong> " . $this->formatBytes(filesize($this->zip_file)) . "</p>\n";
            
        } catch (Exception $e) {
            echo "<h3 style='color: red;'>❌ Hata Oluştu:</h3>\n";
            echo "<p>" . $e->getMessage() . "</p>\n";
        }
        
        echo "</div>\n";
    }
    
    private function backupPostsAndPages() {
        global $wpdb;
        
        // Yazılar ve sayfaları al (trash hariç)
        $posts = $wpdb->get_results("
            SELECT * FROM {$wpdb->posts} 
            WHERE post_type IN ('post', 'page') 
            AND post_status NOT IN ('trash', 'auto-draft')
            ORDER BY ID
        ", ARRAY_A);
        
        // Post meta verilerini al
        $post_meta = $wpdb->get_results("
            SELECT pm.* FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type IN ('post', 'page')
            AND p.post_status NOT IN ('trash', 'auto-draft')
            ORDER BY pm.post_id, pm.meta_id
        ", ARRAY_A);
        
        // Dosyaya kaydet
        $posts_file = $this->backup_dir . '/posts_and_pages.json';
        $data = array(
            'posts' => $posts,
            'post_meta' => $post_meta,
            'export_date' => current_time('mysql'),
            'site_url' => home_url(),
            'total_posts' => count($posts)
        );
        
        file_put_contents($posts_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        echo "&nbsp;&nbsp;✓ " . count($posts) . " yazı/sayfa yedeklendi\n";
    }
    
    private function backupElementorData() {
        global $wpdb;
        
        // Elementor ile oluşturulan sayfaları bul
        $elementor_posts = $wpdb->get_results("
            SELECT p.*, pm.meta_value as elementor_data
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE pm.meta_key = '_elementor_data'
            AND p.post_status NOT IN ('trash', 'auto-draft')
            ORDER BY p.ID
        ", ARRAY_A);
        
        // Elementor ayarları
        $elementor_settings = $wpdb->get_results("
            SELECT pm.*
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key LIKE '_elementor_%'
            AND p.post_status NOT IN ('trash', 'auto-draft')
            ORDER BY pm.post_id, pm.meta_id
        ", ARRAY_A);
        
        // Elementor kit ayarları (tema ayarları)
        $elementor_kit = $wpdb->get_results("
            SELECT * FROM {$wpdb->options}
            WHERE option_name LIKE '%elementor%'
        ", ARRAY_A);
        
        $elementor_file = $this->backup_dir . '/elementor_data.json';
        $data = array(
            'elementor_posts' => $elementor_posts,
            'elementor_settings' => $elementor_settings,
            'elementor_kit' => $elementor_kit,
            'export_date' => current_time('mysql'),
            'total_elementor_pages' => count($elementor_posts)
        );
        
        file_put_contents($elementor_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        echo "&nbsp;&nbsp;✓ " . count($elementor_posts) . " Elementor sayfası yedeklendi\n";
    }
    
    private function backupMediaFiles() {
        $upload_dir = wp_upload_dir();
        $uploads_path = $upload_dir['basedir'];
        
        if (!file_exists($uploads_path)) {
            echo "&nbsp;&nbsp;⚠️ Uploads klasörü bulunamadı\n";
            return;
        }
        
        // Uploads klasörünü kopyala
        $media_backup_dir = $this->backup_dir . '/uploads/';
        $this->copyDirectory($uploads_path, $media_backup_dir);
        
        // Medya library bilgilerini kaydet
        global $wpdb;
        $attachments = $wpdb->get_results("
            SELECT * FROM {$wpdb->posts}
            WHERE post_type = 'attachment'
            AND post_status = 'inherit'
            ORDER BY ID
        ", ARRAY_A);
        
        $attachment_meta = $wpdb->get_results("
            SELECT pm.* FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = 'attachment'
            ORDER BY pm.post_id, pm.meta_id
        ", ARRAY_A);
        
        $media_file = $this->backup_dir . '/media_library.json';
        $data = array(
            'attachments' => $attachments,
            'attachment_meta' => $attachment_meta,
            'upload_url' => $upload_dir['baseurl'],
            'upload_path' => $uploads_path,
            'total_media' => count($attachments)
        );
        
        file_put_contents($media_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        echo "&nbsp;&nbsp;✓ " . count($attachments) . " medya dosyası yedeklendi\n";
    }
    
    private function backupTaxonomies() {
        global $wpdb;
        
        // Kategoriler ve etiketler
        $terms = $wpdb->get_results("
            SELECT t.*, tt.taxonomy, tt.description, tt.parent, tt.count
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            WHERE tt.taxonomy IN ('category', 'post_tag')
            ORDER BY t.term_id
        ", ARRAY_A);
        
        // Term ilişkileri
        $term_relationships = $wpdb->get_results("
            SELECT tr.* FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
            WHERE tt.taxonomy IN ('category', 'post_tag')
            AND p.post_type IN ('post', 'page')
            AND p.post_status NOT IN ('trash', 'auto-draft')
            ORDER BY tr.object_id
        ", ARRAY_A);
        
        // Term meta
        $term_meta = $wpdb->get_results("
            SELECT tm.* FROM {$wpdb->termmeta} tm
            INNER JOIN {$wpdb->term_taxonomy} tt ON tm.term_id = tt.term_id
            WHERE tt.taxonomy IN ('category', 'post_tag')
            ORDER BY tm.term_id, tm.meta_id
        ", ARRAY_A);
        
        $taxonomy_file = $this->backup_dir . '/taxonomies.json';
        $data = array(
            'terms' => $terms,
            'term_relationships' => $term_relationships,
            'term_meta' => $term_meta,
            'total_terms' => count($terms)
        );
        
        file_put_contents($taxonomy_file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        echo "&nbsp;&nbsp;✓ " . count($terms) . " kategori/etiket yedeklendi\n";
    }
    
    private function copyDirectory($src, $dst) {
        if (!is_dir($src)) return;
        
        $dir = opendir($src);
        @mkdir($dst, 0755, true);
        
        while (($file = readdir($dir)) !== false) {
            if ($file == '.' || $file == '..') continue;
            
            $src_file = $src . '/' . $file;
            $dst_file = $dst . '/' . $file;
            
            // Cache klasörlerini atla
            if (strpos($file, 'cache') !== false || strpos($file, 'backup') !== false) {
                continue;
            }
            
            if (is_dir($src_file)) {
                $this->copyDirectory($src_file, $dst_file);
            } else {
                copy($src_file, $dst_file);
            }
        }
        
        closedir($dir);
    }
    
    private function createZip() {
        if (!class_exists('ZipArchive')) {
            throw new Exception("ZipArchive sınıfı bulunamadı. ZIP oluşturulamıyor.");
        }
        
        $zip = new ZipArchive();
        
        if ($zip->open($this->zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            throw new Exception("ZIP dosyası oluşturulamadı: " . $this->zip_file);
        }
        
        $this->addDirectoryToZip($this->backup_dir, $zip, $this->backup_dir);
        
        $zip->close();
        
        echo "&nbsp;&nbsp;✓ ZIP dosyası oluşturuldu\n";
    }
    
    private function addDirectoryToZip($dir, $zip, $base_dir) {
        $files = scandir($dir);
        
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') continue;
            
            $file_path = $dir . '/' . $file;
            $relative_path = str_replace($base_dir . '/', '', $file_path);
            
            if (is_dir($file_path)) {
                $zip->addEmptyDir($relative_path);
                $this->addDirectoryToZip($file_path, $zip, $base_dir);
            } else {
                $zip->addFile($file_path, $relative_path);
            }
        }
    }
    
    private function cleanup() {
        $this->deleteDirectory($this->backup_dir);
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
    
    private function formatBytes($size, $precision = 2) {
        $base = log($size, 1024);
        $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
    }
}

// Scripti çalıştır
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>WordPress İçerik Yedekleme</title>
        <meta charset="utf-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; background: #f1f1f1; }
            .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .button { background: #0073aa; color: white; padding: 15px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; text-decoration: none; display: inline-block; }
            .button:hover { background: #005a87; }
            .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .info { background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 20px 0; color: #0c5460; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>📝 WordPress İçerik Yedekleme</h1>
            
            <div class="info">
                <strong>📋 Bu Script Sadece İçerikleri Yedekler:</strong>
                <ul>
                    <li>✅ Yazılar (Posts)</li>
                    <li>✅ Sayfalar (Pages)</li>
                    <li>✅ Elementor Sayfaları ve Ayarları</li>
                    <li>✅ Medya Dosyaları (Resimler, Videolar)</li>
                    <li>✅ Kategoriler ve Etiketler</li>
                </ul>
            </div>
            
            <div class="warning">
                <strong>⚠️ Önemli Notlar:</strong>
                <ul>
                    <li>Bu işlem 3-10 dakika sürebilir</li>
                    <li>Sadece içerik yedeklenir, tema/plugin yedeklenmez</li>
                    <li>İşlem sırasında sayfayı kapatmayın</li>
                    <li>Yedek dosyası <code>/wp-content-backups/</code> klasörüne kaydedilecek</li>
                </ul>
            </div>
            
            <p><strong>Yedeklenecek İçerikler:</strong></p>
            <ul>
                <li>📝 Tüm yazılar ve içerikleri</li>
                <li>📄 Tüm sayfalar ve içerikleri</li>
                <li>🎨 Elementor ile oluşturulan sayfalar</li>
                <li>📷 Medya library (resimler, videolar, dosyalar)</li>
                <li>🏷️ Kategoriler ve etiketler</li>
            </ul>
            
            <form method="post">
                <button type="submit" name="start_content_backup" class="button">🚀 İçerik Yedeklemeyi Başlat</button>
            </form>
        </div>
    </body>
    </html>
    <?php
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_content_backup'])) {
    echo "<!DOCTYPE html><html><head><title>İçerik Yedekleme İşlemi</title><meta charset='utf-8'></head><body>";
    
    $backup = new WordPressContentBackup();
    $backup->createContentBackup();
    
    echo "<br><a href='" . $_SERVER['PHP_SELF'] . "' style='display: inline-block; margin-top: 20px; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 5px;'>🔙 Geri Dön</a>";
    echo "</body></html>";
}
?> 