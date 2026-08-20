(function () {
  'use strict';

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

  function toast(message, type = 'success') {
    const region = $('[data-toast-region]');
    if (!region || !message) return;
    const item = document.createElement('div');
    item.className = `toast ${type}`;
    item.setAttribute('role', type === 'error' ? 'alert' : 'status');
    item.textContent = message;
    region.appendChild(item);
    window.setTimeout(() => item.remove(), 4200);
  }
  window.appToast = toast;

  document.addEventListener('DOMContentLoaded', () => {
    const sidebar = $('.sidebar');
    const sidebarOverlay = $('.sidebar-overlay');
    $$('[data-sidebar-toggle]').forEach((button) => {
      button.addEventListener('click', () => {
        sidebar?.classList.toggle('open');
        sidebarOverlay?.classList.toggle('open', sidebar?.classList.contains('open'));
      });
    });

    const closeModal = (modal) => {
      modal?.classList.remove('open');
      modal?.setAttribute('aria-hidden', 'true');
    };
    const openModal = (modal) => {
      modal?.classList.add('open');
      modal?.setAttribute('aria-hidden', 'false');
      window.setTimeout(() => $('input,select,textarea,button', modal)?.focus(), 30);
    };
    $$('[data-modal-open]').forEach((button) => button.addEventListener('click', () => {
      openModal(document.getElementById(button.dataset.modalOpen || ''));
    }));
    $$('[data-modal-close]').forEach((button) => button.addEventListener('click', () => closeModal(button.closest('.modal'))));
    $$('.modal').forEach((modal) => modal.addEventListener('click', (event) => {
      if (event.target === modal) closeModal(modal);
    }));

    const confirmModal = $('[data-confirm-modal]');
    let confirmCallback = null;
    function requestConfirmation(message, callback) {
      if (!confirmModal) {
        callback();
        return;
      }
      $('[data-confirm-message]', confirmModal).textContent = message;
      confirmCallback = callback;
      openModal(confirmModal);
    }
    $('[data-confirm-cancel]')?.addEventListener('click', () => {
      confirmCallback = null;
      closeModal(confirmModal);
    });
    $('[data-confirm-accept]')?.addEventListener('click', () => {
      const callback = confirmCallback;
      confirmCallback = null;
      closeModal(confirmModal);
      callback?.();
    });
    $$('[data-confirm]').forEach((element) => {
      element.addEventListener('click', (event) => {
        if (element.dataset.confirmed === 'true') {
          delete element.dataset.confirmed;
          return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        requestConfirmation(element.dataset.confirm || 'Lanjutkan tindakan ini?', () => {
          element.dataset.confirmed = 'true';
          element.click();
        });
      }, true);
    });

    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') return;
      $$('.modal.open, .guide-modal.open, .confirm-modal.open').forEach(closeModal);
    });

    $$('[data-search-table]').forEach((input) => {
      input.addEventListener('input', () => {
        const table = $(input.dataset.searchTable || '');
        const needle = input.value.trim().toLowerCase();
        $$('tbody tr', table || document.createElement('div')).forEach((row) => {
          row.hidden = !row.textContent.toLowerCase().includes(needle);
        });
      });
    });
    $$('[data-search-items]').forEach((input) => {
      input.addEventListener('input', () => {
        const root = $(input.dataset.searchItems || '') || document;
        const needle = input.value.trim().toLowerCase();
        $$('[data-search-text]', root).forEach((item) => {
          item.hidden = !String(item.dataset.searchText || item.textContent).toLowerCase().includes(needle);
        });
      });
    });

    const numberWords = ['nol', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
    function terbilang(value) {
      const n = Math.max(0, Math.round(Number(value) || 0));
      if (n < 12) return numberWords[n];
      if (n < 20) return `${terbilang(n - 10)} belas`;
      if (n < 100) return `${terbilang(Math.floor(n / 10))} puluh${n % 10 ? ` ${terbilang(n % 10)}` : ''}`;
      if (n < 200) return `seratus${n > 100 ? ` ${terbilang(n - 100)}` : ''}`;
      if (n < 1000) return `${terbilang(Math.floor(n / 100))} ratus${n % 100 ? ` ${terbilang(n % 100)}` : ''}`;
      return String(n);
    }
    function arabicDigits(value) {
      return String(Math.round(Number(value) || 0)).replace(/[0-9]/g, (digit) => '٠١٢٣٤٥٦٧٨٩'[Number(digit)]);
    }
    function arabicWords(value) {
      const n = Math.max(0, Math.min(100, Math.round(Number(value) || 0)));
      const units = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة'];
      const special = {10:'عشرة',11:'أحد عشر',12:'اثنا عشر',13:'ثلاثة عشر',14:'أربعة عشر',15:'خمسة عشر',16:'ستة عشر',17:'سبعة عشر',18:'ثمانية عشر',19:'تسعة عشر'};
      const tens = {20:'عشرون',30:'ثلاثون',40:'أربعون',50:'خمسون',60:'ستون',70:'سبعون',80:'ثمانون',90:'تسعون',100:'مائة'};
      if (n < 10) return units[n] || 'صفر';
      if (special[n]) return special[n];
      if (tens[n]) return tens[n];
      const ten = Math.floor(n / 10) * 10;
      return `${units[n % 10]} و${tens[ten]}`;
    }
    function predicate(score) {
      if (score >= 90) return 'Mumtaz';
      if (score >= 80) return 'Jayyid Jiddan';
      if (score >= 65) return 'Jayyid';
      if (score >= 50) return 'Maqbul';
      if (score >= 35) return 'Dhaif';
      return 'Dhaif Jiddan';
    }
    $$('[data-score-group]').forEach((group) => {
      const update = () => {
        const inputs = $$('[data-score]', group);
        const scores = inputs.map((input) => Math.min(100, Math.max(0, Number(input.value || 0))));
        const total = scores.reduce((sum, value) => sum + value, 0);
        const average = scores.length ? total / scores.length : 0;
        $$('[data-average]', group.closest('[data-preview-scope]') || group).forEach((output) => output.textContent = average.toFixed(2));
        $$('[data-total]', group.closest('[data-preview-scope]') || group).forEach((output) => output.textContent = total.toFixed(0));
        $$('[data-predicate]', group.closest('[data-preview-scope]') || group).forEach((output) => output.textContent = predicate(average));
        inputs.forEach((input, index) => {
          const key = input.dataset.score || String(index);
          $$(`[data-score-value="${key}"]`, group.closest('[data-preview-scope]') || group).forEach((out) => out.textContent = scores[index].toFixed(0));
          $$(`[data-score-words="${key}"]`, group.closest('[data-preview-scope]') || group).forEach((out) => out.textContent = terbilang(scores[index]).replace(/^./, (c) => c.toUpperCase()));
          $$(`[data-score-arabic="${key}"]`, group.closest('[data-preview-scope]') || group).forEach((out) => out.textContent = arabicDigits(scores[index]));
          $$(`[data-score-arabic-words="${key}"]`, group.closest('[data-preview-scope]') || group).forEach((out) => out.textContent = arabicWords(scores[index]));
          $$(`[data-score-status="${key}"]`, group.closest('[data-preview-scope]') || group).forEach((out) => {
            const minimum = Number(out.dataset.minimum || 75);
            out.textContent = scores[index] >= minimum ? 'Tercapai' : 'Perlu bimbingan';
          });
        });
        $$('[data-total-words]', group.closest('[data-preview-scope]') || group).forEach((out) => out.textContent = terbilang(total).replace(/^./, (c) => c.toUpperCase()));
        $$('[data-total-arabic]', group.closest('[data-preview-scope]') || group).forEach((out) => out.textContent = arabicDigits(total));
        $$('[data-pass-label]', group.closest('[data-preview-scope]') || group).forEach((out) => {
          const minimum = Number(out.dataset.minimum || 75);
          out.textContent = average >= minimum ? 'Naik' : 'Mengulang';
        });
      };
      $$('[data-score]', group).forEach((input) => input.addEventListener('input', update));
      update();
    });

    $$('form[data-ajax]').forEach((form) => {
      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!form.checkValidity()) {
          form.reportValidity();
          return;
        }
        const submitButton = event.submitter || $('button[type="submit"],input[type="submit"]', form);
        const actionUrl = form.getAttribute('action') || window.location.href;
        const method = (form.getAttribute('method') || 'POST').toUpperCase();
        const formData = new FormData(form);
        if (event.submitter?.name) formData.set(event.submitter.name, event.submitter.value);
        submitButton?.setAttribute('disabled', 'disabled');
        form.classList.add('loading');
        try {
          let requestUrl = actionUrl;
          const options = { method, headers: { Accept: 'application/json' }, credentials: 'same-origin' };
          if (method === 'GET') {
            requestUrl += (requestUrl.includes('?') ? '&' : '?') + new URLSearchParams(formData).toString();
          } else {
            options.body = formData;
          }
          const response = await fetch(requestUrl, options);
          const contentType = response.headers.get('content-type') || '';
          if (!contentType.includes('application/json')) throw new Error(`Server mengembalikan HTTP ${response.status}`);
          const payload = await response.json();
          if (!response.ok || !payload.success) {
            toast(payload.message || 'Operasi gagal diproses.', 'error');
            return;
          }
          toast(payload.message || 'Data berhasil disimpan.');
          if (form.dataset.redirect) {
            window.setTimeout(() => window.location.assign(form.dataset.redirect), 500);
          } else if (form.dataset.noReload === undefined) {
            window.setTimeout(() => window.location.reload(), 650);
          }
        } catch (error) {
          console.error('[Catatan Mengaji Digital]', error);
          toast('Terjadi gangguan saat mengirim data. Periksa koneksi Apache dan MySQL.', 'error');
        } finally {
          submitButton?.removeAttribute('disabled');
          form.classList.remove('loading');
        }
      });
    });

    const guides = {
      guru: [
        ['SELAMAT DATANG', 'Mulai alur kerja Guru dengan lebih terarah', 'Panduan ini menjelaskan urutan kerja dari menyiapkan data sampai mencetak rapor resmi.', ['Kerjakan sesuai urutan menu yang dijelaskan', 'Gunakan tombol Simpan sebelum berpindah halaman', 'Panduan dapat dibuka kembali dari menu Sistem']],
        ['LANGKAH 1', 'Pantau kondisi kelas dari Dashboard', 'Lihat total siswa, laporan yang sudah masuk, performa mingguan, dan tindakan cepat.', ['KPI berasal dari database', 'Gunakan tindakan cepat untuk input harian']],
        ['LANGKAH 2', 'Kelola Daftar Siswa', 'Cari, filter, tambah, edit, hapus, atau impor siswa sekolah dari Excel.', ['Pastikan NIS unik', 'Periksa kelas dan level setelah impor']],
        ['LANGKAH 3', 'Periksa Data Kelas', 'Siapkan kelas 1A–6B, atur wali kelas mengaji, dan lihat distribusi sembilan level Tahfidz.', ['Pilih tahun ajaran yang benar', 'Klik kelas untuk melihat rekap siswa']],
        ['LANGKAH 4', 'Isi Presensi & Laporan Harian', 'Pilih siswa dan tanggal, lalu isi presensi, kegiatan, tadarus, hafalan, serta catatan Guru.', ['Riwayat tanggal lama tetap tersedia', 'Simpan satu laporan untuk satu siswa per tanggal']],
        ['LANGKAH 5', 'Catat Ujian Kenaikan Level', 'Pilih surat sesuai level asal. Sistem menghitung rata-rata dan menaikkan level hanya jika lulus.', ['KKM mengikuti pengaturan database', 'Periksa prediksi sebelum menyimpan']],
        ['LANGKAH 6', 'Lengkapi Form Munaqosyah', 'Isi empat komponen nilai dan kepribadian. Preview angka, huruf, dan predikat berubah langsung.', ['Periksa periode rapor', 'Gunakan preview rapor resmi untuk mencetak']],
        ['LANGKAH 7', 'Kelola Data Surat dan Komposisi', 'Perbarui target surat per level dan salin kurikulum dengan aman ke tahun ajaran baru.', ['Hindari nama surat ganda', 'Target orang tua mengikuti level anak']],
        ['LANGKAH 8', 'Buka 3 Rapor Otomatis', 'Cari siswa, lalu pilih rapor Harian, Kenaikan Level, atau Munaqosyah untuk preview dan cetak A4.', ['Pastikan sumber nilai sudah tersimpan', 'Periksa identitas siswa sebelum mencetak']],
      ],
      admin: [
        ['SELAMAT DATANG', 'Pantau seluruh sistem dari satu tempat', 'Panduan Admin membantu mengelola akun, guru, orang tua, siswa, kelas, dan audit.', ['Gunakan dashboard untuk melihat peringatan', 'Setiap tindakan penting tercatat di audit']],
        ['AKUN', 'Setujui dan kelola pengguna', 'Verifikasi Guru, ubah role, reset sandi, aktifkan/nonaktifkan, dan hapus akun dengan aman.', ['Jangan hapus admin terakhir', 'Periksa target sebelum mengubah role']],
        ['GURU', 'Monitoring aktivitas Guru', 'Pantau kelas, jumlah siswa, laporan hari ini, dan aktivitas tujuh hari.', ['Periksa Guru yang belum mengisi', 'Atur wali kelas mengaji dari Siswa & Kelas']],
        ['ORANG TUA', 'Kelola hubungan anak', 'Hubungkan akun Orang Tua berdasarkan NIS dan pastikan satu siswa tidak diklaim dua akun aktif.', ['Kelas anak tampil dari biodata siswa', 'Putuskan relasi hanya jika diperlukan']],
        ['SISWA & KELAS', 'Kelola data sekolah', 'Periksa siswa per kelas, status aktif/pindah/lulus, level, dan wali kelas.', ['Data nilai tetap dipertahankan', 'Gunakan filter untuk pemeriksaan cepat']],
        ['LAPORAN', 'Pantau kelengkapan laporan', 'Bandingkan laporan harian, ujian level, dan Munaqosyah per Guru.', ['Gunakan data nyata dari MySQL', 'Tindak lanjuti status perlu dicek']],
        ['AUDIT', 'Telusuri perubahan penting', 'Filter waktu, pelaku, target, status, serta rincian aktivitas yang sudah dibuat mudah dibaca.', ['Password tidak pernah ditampilkan', 'Gunakan audit untuk investigasi']],
      ],
      orang_tua: [
        ['SELAMAT DATANG', 'Pantau perkembangan anak dengan mudah', 'Akun Orang Tua hanya menampilkan anak yang terhubung melalui NIS.', ['Nilai bersifat hanya-baca', 'Biodata akun sendiri dapat diperbarui']],
        ['DASHBOARD ANAK', 'Lihat perkembangan terbaru', 'Pantau laporan harian, ujian kenaikan level, Munaqosyah, dan catatan Guru.', ['Gunakan pilihan tanggal untuk riwayat', 'Hubungi Guru bila ada data yang perlu diklarifikasi']],
        ['KOMPOSISI', 'Pahami arti nilai', 'Pelajari rentang predikat agar perkembangan anak mudah dipahami.', ['Target bukan sekadar angka', 'Perhatikan catatan dan konsistensi']],
        ['DATA SURAT', 'Lihat target sesuai level anak', 'Daftar surat otomatis mengikuti level anak dan tahun ajaran aktif.', ['Data dikelola Guru', 'Orang Tua tidak dapat mengubah target']],
        ['BIODATA', 'Jaga data Orang Tua tetap lengkap', 'Perbarui nama, telepon, alamat, dan informasi profil melalui Biodata Orang Tua.', ['Pastikan nomor mudah dihubungi', 'Simpan setelah selesai mengubah']],
      ],
    };

    const guideModal = $('[data-guide-modal]');
    if (guideModal) {
      const role = guideModal.dataset.guideRole || 'guru';
      const steps = guides[role] || guides.guru;
      let index = 0;
      const renderGuide = () => {
        const step = steps[index];
        $('[data-guide-kicker]', guideModal).textContent = step[0];
        $('[data-guide-counter]', guideModal).textContent = `${index + 1} / ${steps.length}`;
        $('[data-guide-title]', guideModal).textContent = step[1];
        $('[data-guide-description]', guideModal).textContent = step[2];
        $('[data-guide-tips]', guideModal).innerHTML = step[3].map((tip) => `<div class="guide-tip">${tip}</div>`).join('');
        $('[data-guide-dots]', guideModal).innerHTML = steps.map((_, dot) => `<button type="button" class="guide-dot ${dot === index ? 'active' : ''}" data-guide-dot="${dot}" aria-label="Langkah ${dot + 1}"></button>`).join('');
        $('[data-guide-prev]', guideModal).hidden = index === 0;
        $('[data-guide-next]', guideModal).textContent = index === steps.length - 1 ? 'Selesai ✓' : 'Berikutnya →';
        $$('[data-guide-dot]', guideModal).forEach((dot) => dot.addEventListener('click', () => { index = Number(dot.dataset.guideDot); renderGuide(); }));
      };
      const finishGuide = () => {
        localStorage.setItem(`cmd-guide-${role}-v2`, 'done');
        closeModal(guideModal);
      };
      $$('[data-guide-open]').forEach((button) => button.addEventListener('click', () => { index = 0; renderGuide(); openModal(guideModal); }));
      $('[data-guide-close]', guideModal)?.addEventListener('click', finishGuide);
      $('[data-guide-skip]', guideModal)?.addEventListener('click', finishGuide);
      $('[data-guide-prev]', guideModal)?.addEventListener('click', () => { if (index > 0) { index -= 1; renderGuide(); } });
      $('[data-guide-next]', guideModal)?.addEventListener('click', () => { if (index < steps.length - 1) { index += 1; renderGuide(); } else finishGuide(); });
      guideModal.addEventListener('click', (event) => { if (event.target === guideModal) finishGuide(); });
      renderGuide();
      if (!localStorage.getItem(`cmd-guide-${role}-v2`)) window.setTimeout(() => openModal(guideModal), 300);
    }
  });
})();
