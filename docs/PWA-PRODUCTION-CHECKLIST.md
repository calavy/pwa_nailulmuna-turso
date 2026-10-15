# Checklist verifikasi PWA di domain live

Jalankan setelah deploy ke `https://pwa.nailulmuna.id` (dan `https://wali.nailulmuna.id` jika dipakai).

- [ ] HTTPS valid (tidak mixed content di DevTools → Security)
- [ ] `manifest.php` mengembalikan JSON 200 (Application → Manifest di Chrome DevTools)
- [ ] `api/pwa/app-sw.php` terdaftar (Application → Service workers)
- [ ] Login staf berhasil; cookie sesi ter-set
- [ ] Halaman presensi/scan memuat offline banner saat airplane mode (setelah sekali buka online)
- [ ] Portal wali: `wali/manifest.php` + `wali/sw.php` jika subdomain wali aktif
- [ ] Push FCM (opsional): izin notifikasi + token terdaftar di `fcm_tokens`
