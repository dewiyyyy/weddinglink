WedLink – Wedding Organizer Service Platform

Final Project Rekayasa Perangkat Lunak  
Kelompok: 9 
Anggota:  
1. Nadhif Pandya Supriyadi (701230024)  
2. Novi Astina Wijayanti (701230088)  
3. Dewi Lupiani (701230054)  
Dosen Pengampu:Dila Nurlaila, M.Kom.

Deskripsi Aplikasi
WedLink adalah aplikasi berbasis web yang dirancang untuk mempermudah proses pemesanan layanan Wedding Organizer. Aplikasi ini menyediakan informasi paket, harga, vendor, hingga fitur booking dan jadwal acara, sehingga calon customer dapat melihat detail layanan tanpa harus menghubungi admin secara manual. WedLink juga membantu admin dalam mengelola paket, customer, dan laporan pemesanan dengan lebih efisien.


Tujuan & Masalah yang Diselesaikan
-Masalah:Informasi paket WO sering tersebar di media sosial dan harus ditanyakan manual ke admin. Proses booking, jadwal fitting, hingga budgeting masih dilakukan secara manual dan memakan waktu.  
-Solusi:WedLink menyediakan pusat informasi layanan WO yang jelas, sistem pemesanan online, penjadwalan acara, serta budgeting otomatis sehingga proses lebih cepat, praktis, dan transparan.

Teknologi yang Digunakan
- Backend: Laravel 10 (PHP 8.x)  
- Frontend: Blade Template + Bootstrap 5  
- Database: MySQL  
- Arsitektur: MVC + RESTful  
- Tools: Composer, Node.js, npm  
- Storage: Laravel Local Storage  

Cara Menjalankan Aplikasi (Lokal)
1.Clone Repository
git clone <https://github.com/nadhifpandyas/weddinglink>
cd wedlink

2.Instalasi Dependency
composer install
npm install

3.Konfigurasi Environment
Salin konfigurasi:
cp .env.example .env

Sesuaikan database di file `.env`.

4.Generate APP Key & Migrasi Database
php artisan key:generate
php artisan migrate

5.Jalankan Project
php artisan serve
npm run dev

Buka di browser:http://localhost:8000


Akun Demo (Untuk Pengujian)  
Jika ingin mencoba fitur tanpa mendaftar:

1.Admin:
- Email: admin@weddinglink.com 
- Password: password

2.Vendor: 
- Email:foto@weddinglink.com  
- Password:password

3.Customer: 
- Email:customer@weddinglink.com
- Password: password

Link Deployment & Demo
Link Deployment : https://weddinglink.ct.ws/
Link Demo Video :


Catatan Tambahan :
- Fitur lupa password membutuhkan konfigurasi SMTP agar email verifikasi berfungsi.  
- Upload gambar dibatasi maksimal 2MB (format JPG/PNG).  
- Sistem hanya tersedia dalam bentuk website (belum ada aplikasi mobile).  
- Beberapa fitur lanjutan seperti undangan digital dan analitik lengkap belum tersedia pada versi pertama.


Dibuat untuk memenuhi tugas Final Project mata kuliah Rekayasa Perangkat Lunak, Program Studi Sistem Informasi, UIN Sultan Thaha Saifuddin Jambi, 2025.
