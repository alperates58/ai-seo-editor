# AI SEO Editor & Article Generator

WordPress yazılarını analiz eden, Yoast SEO uyumuna yaklaştıran, okunabilirliği artıran ve OpenAI API ile SEO içerik üreten profesyonel WordPress eklentisi.

## Özellikler

- **SEO Analizi** — 19 kriter, Yoast SEO uyumlu puanlama (0-100)
- **Okunabilirlik Analizi** — 8 kriter, cümle/paragraf/pasif anlatım kontrolü
- **AI İyileştirme** — 12 farklı iyileştirme işlemi (başlık, meta, giriş, yapı, FAQ, sonuç...)
- **Makale Üretici** — Sıfırdan SEO uyumlu makale üretimi
- **İç Link Motoru** — TF-IDF cosine similarity + AI destekli öneri
- **Toplu Analiz** — Tüm yazıları filtreleyerek analiz et
- **Yoast SEO Entegrasyonu** — Yoast meta alanlarını okur, opsiyonel senkronizasyon
- **GitHub Güncelleme** — Admin panelinden repo/branch ayarı yapıp eklentiyi GitHub'dan güncelle
- **Kullanım Logları** — Token takibi, aylık bütçe kontrolü

## Gereksinimler

- PHP 8.0+
- WordPress 6.0+
- OpenAI API anahtarı

## Kurulum

1. `ai-seo-editor/` klasörünü WordPress eklenti dizinine (`wp-content/plugins/`) kopyalayın
2. WordPress admin panelinden **Eklentiler → Etkinleştir**
3. **AI SEO Editor → Ayarlar** sayfasından OpenAI API anahtarınızı girin
4. **Bağlantıyı Test Et** butonuyla doğrulayın

## Güvenlik

- API anahtarı AES-256-CBC ile şifreli saklanır
- Tüm işlemlerde `manage_options` capability + nonce kontrolü
- Kullanıcı onayı olmadan hiçbir yazı değiştirilmez
- Her uygulama öncesi WordPress revision otomatik oluşturulur

## Admin Menüleri

| Sayfa | Açıklama |
|-------|----------|
| Dashboard | Genel istatistikler, skor dağılımı, son işlemler |
| Yazı Analizi | Yazı listesi, SEO + okunabilirlik skorları |
| Toplu Analiz | Toplu SEO taraması, filtreler |
| AI Makale Yaz | Sıfırdan makale üretimi |
| İç Link Önerileri | Benzerlik tabanlı link önerileri |
| Ayarlar | OpenAI API, model, token limitleri |
| GitHub Güncelleme | Repository bağlantısı, son commit kontrolü ve GitHub'dan güncelleme |
| Kullanım / Loglar | Token kullanım takibi |

## Lisans

GPL v2 or later
