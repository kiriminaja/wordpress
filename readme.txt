=== KiriminAja Official ===
Contributors: kiriminaja
Donate link: https://developer.kiriminaja.com/kopi
Tags: shipping, WooCommerce, kiriminaja, e-commerce, cod, ongkir, pickup, resi, qris, ka credit, jne, sicepat, anteraja, lion parcel, ninja xpress, id express, j&t express, tiki, pos indonesia, sentral cargo, sap express, paxel, spx express
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 8.0
Stable tag: 2.2.5
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 8.0
WC tested up to: 10.8
Requires Plugins: woocommerce

Plugin pengiriman WooCommerce dengan ongkir otomatis, request pickup, cetak resi, COD, Non-COD, QRIS, KA Credit, dan TOP untuk merchant KiriminAja.

== Description ==

Atur pengiriman langsung dari WooCommerce dengan plugin KiriminAja Official. Plugin ini membantu toko online menampilkan ongkir real-time di halaman checkout, membuat transaksi pengiriman, request pickup, cetak resi, menerima update status paket, dan mengelola pembayaran pengiriman dari dashboard WordPress.

KiriminAja Official dibuat untuk merchant Indonesia yang ingin mengelola pengiriman COD maupun Non-COD tanpa harus berpindah-pindah dashboard. Cocok untuk operasional harian toko online yang membutuhkan proses checkout, pickup, pembayaran ongkir, dan tracking yang lebih rapi.

== Apa Perbedaan Dengan Plugin Pengiriman Biasa? ==

Plugin ini tidak hanya menampilkan ongkir di checkout. KiriminAja Official juga membantu operasional setelah order masuk, mulai dari request pickup, pembayaran pickup, cetak resi, hingga sinkronisasi status paket.

Untuk merchant TOP, plugin akan mengikuti properti akun KiriminAja dan otomatis memproses pickup sebagai pembayaran TOP tanpa menampilkan QRIS. Untuk merchant Non-TOP, pembayaran pickup Non-COD dapat menggunakan QRIS atau KA Credit sesuai konfigurasi akun.

== Untuk Siapa Plugin Ini? ==

KiriminAja Official cocok untuk pemilik toko WooCommerce yang:

* Ingin menampilkan ongkir otomatis dari berbagai ekspedisi di checkout.
* Mengelola pengiriman COD dan Non-COD dari WordPress.
* Membutuhkan request pickup dan cetak resi dari dashboard admin.
* Menggunakan QRIS, KA Credit, atau skema TOP untuk pembayaran pengiriman.
* Ingin status paket tersinkron melalui webhook KiriminAja.
* Membutuhkan tracking pengiriman untuk customer.

Dengan KiriminAja, proses pengiriman dari toko WooCommerce bisa lebih terpusat, hemat waktu, dan lebih mudah dipantau oleh tim operasional.

== Fitur Unggulan ==

* Ongkir otomatis di checkout WooCommerce.
* Pilihan kurir KiriminAja berdasarkan layanan yang tersedia untuk akun dan alamat tujuan.
* Support COD dan Non-COD.
* Request pickup langsung dari halaman transaksi KiriminAja di WordPress.
* Pembayaran pickup menggunakan QRIS untuk merchant Non-TOP.
* Pembayaran pickup menggunakan KA Credit jika akun sudah memiliki PIN dan saldo cukup.
* Merchant TOP otomatis diproses sebagai TOP dan tidak perlu scan QRIS.
* Cetak resi satuan dan bulk.
* Update status paket melalui webhook KiriminAja.
* Halaman tracking menggunakan shortcode.
* Pengaturan origin/pickup address, kurir aktif, callback URL, insurance, dan tracking page.
* Cache coverage region dan daftar kurir untuk performa admin.

== Kurir dan Layanan Yang Tersedia ==

Ketersediaan kurir dan layanan mengikuti area, alamat origin, alamat tujuan, konfigurasi akun KiriminAja, dan layanan yang sedang aktif di KiriminAja. Beberapa kurir yang umum tersedia melalui KiriminAja antara lain:

* JNE – REG, YES, OKE, Trucking/JTR sesuai area.
* SiCepat – Reguler, BEST, Gokil/Cargo sesuai area.
* SAP Express – Regular, One Day, Same Day, Cargo sesuai area.
* Lion Parcel – Regpack, Jagopack, dan layanan lain sesuai area.
* AnterAja – Reguler, Same Day, Next Day sesuai area.
* Ninja Xpress – Standard dan layanan lain sesuai area.
* ID Express – Regular dan layanan lain sesuai area.
* J&T Express – EZ/Regular dan layanan lain sesuai area.
* TIKI – REG, ONS, ECO, dan layanan lain sesuai area.
* POS Indonesia – layanan reguler/cargo sesuai area.
* J&T Cargo - layanan cargo sesuai area.
* SPX Express (Shopee) - layanan reguler/cargo sesuai area.
* Paxel - layanan reguler/cargo sesuai area.
* Sentral Cargo - layanan reguler/cargo sesuai area.
* NCS Courier - layanan reguler/cargo sesuai area.
* RPX - layanan reguler/cargo sesuai area.

Daftar kurir aktual dapat berubah mengikuti ketersediaan layanan KiriminAja dan konfigurasi akun merchant. Anda dapat memilih kurir aktif dari halaman pengaturan plugin.

== Pembayaran Pengiriman ==

KiriminAja Official mendukung beberapa skema pembayaran pickup:

* KA Credit - Fitur pembayaran ekslkusif untuk user KiriminAja.
* QRIS - Mendukung seluruh bank dan e-wallet yang terhubung dengan QRIS. Merchant Non-TOP dapat menggunakan QRIS untuk pembayaran pickup Non-COD.

== Installation ==

1. Download dan aktifkan plugin **KiriminAja Official**.
2. Pastikan plugin WooCommerce sudah aktif.
3. Buka menu **KiriminAja > Settings** di WordPress Admin.
4. Hubungkan akun KiriminAja menggunakan setup key/API key dari dashboard KiriminAja.
5. Lengkapi data origin/pickup address toko.
6. Pilih kurir yang ingin diaktifkan di checkout.
7. Pastikan shipping zone WooCommerce sudah mendukung Indonesia dan metode KiriminAja aktif.
8. Atur callback URL/webhook jika diperlukan.
9. Lakukan uji checkout untuk memastikan ongkir dan pilihan pengiriman tampil dengan benar.

== Frequently Asked Questions ==

= Apakah plugin ini membutuhkan WooCommerce? =

Ya. Plugin ini dibuat untuk WooCommerce dan membutuhkan WooCommerce aktif agar fitur ongkir, checkout, transaksi, pickup, dan pembayaran berjalan.

= Apakah plugin ini mendukung COD? =

Ya. Plugin mendukung transaksi COD sesuai layanan dan konfigurasi akun KiriminAja Anda.

= Apakah plugin ini mendukung Non-COD? =

Ya. Untuk Non-COD, merchant dapat menggunakan pembayaran pickup seperti QRIS atau KA Credit sesuai konfigurasi akun.

= Apa itu merchant TOP? =

TOP adalah properti akun/merchant di KiriminAja. Jika akun Anda menggunakan skema TOP, pembayaran pickup akan otomatis mengikuti skema TOP dan tidak meminta scan QRIS.

= Kenapa kurir tertentu tidak muncul? =

Kurir yang tampil dipengaruhi oleh origin, tujuan, berat/dimensi paket, konfigurasi akun KiriminAja, dan ketersediaan layanan di area tersebut.

= Bagaimana cara menampilkan halaman tracking? =

Buat halaman WordPress baru dan gunakan shortcode tracking KiriminAja yang tersedia dari pengaturan plugin.

== Screenshots ==

1. Pengaturan integrasi akun KiriminAja.
2. Pengaturan origin dan pilihan kurir.
3. Ongkir KiriminAja di halaman checkout WooCommerce.
4. Halaman transaksi dan request pickup.
5. Modal pembayaran QRIS untuk pickup Non-COD.
6. Cetak resi dan detail pickup.
7. Halaman Technical untuk cache dan tools teknis.

== Changelog ==

= 2.2.5 =

* Improve checkout district handling and shipping method behavior.
* Add pickup payment handling for QRIS, KA Credit, and TOP merchant flows.
* Add technical tools for cache management.
* Improve webhook, print resi, and payment status synchronization.
* Improve Bahasa Indonesia translations and plugin validation coverage.

= 2.2.4 =

* Improve shipping discount support and checkout compatibility.
* Improve callback/webhook setup and region cache handling.
* Improve admin transaction management and tracking page behavior.

= 2.2.3 =

* Improve WooCommerce checkout integration and shipment processing.
* Add stability fixes for district selection and shipping rate display.

== Upgrade Notice ==

= 2.2.5 =

Recommended update for improved pickup payment handling, TOP merchant behavior, diagnostic logs, and WooCommerce compatibility.
