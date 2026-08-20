(function () {
  'use strict';

  /* =========================================================
     NGAJIYUK! - GLOBAL APP
     assets/js/app.js
     ========================================================= */

  const $ = (selector, root = document) =>
    root.querySelector(selector);

  const $$ = (selector, root = document) =>
    [...root.querySelectorAll(selector)];


  /* =========================================================
     TOAST
     ========================================================= */

  function toast(message, type = 'success') {
    const region = $('[data-toast-region]');

    if (!region || !message) {
      return;
    }

    const item = document.createElement('div');

    item.className = `toast ${type}`;

    item.setAttribute(
      'role',
      type === 'error'
        ? 'alert'
        : 'status'
    );

    item.textContent = message;

    region.appendChild(item);

    requestAnimationFrame(() => {
      item.classList.add('show');
    });

    window.setTimeout(() => {
      item.classList.remove('show');

      window.setTimeout(
        () => item.remove(),
        220
      );
    }, 4200);
  }

  window.appToast = toast;


  /* =========================================================
     DOM READY
     ========================================================= */

  document.addEventListener(
    'DOMContentLoaded',
    () => {

      /* =====================================================
         SIDEBAR MOBILE
         ===================================================== */

      const sidebar =
        $('.sidebar');

      const sidebarOverlay =
        $('.sidebar-overlay');

      const sidebarButtons =
        $$('[data-sidebar-toggle]');


      const syncSidebarState = () => {
        const opened =
          sidebar?.classList.contains('open')
          || false;

        sidebarButtons.forEach(button => {
          button.setAttribute(
            'aria-expanded',
            opened
              ? 'true'
              : 'false'
          );
        });

        sidebarOverlay?.classList.toggle(
          'open',
          opened
        );

        document.body.classList.toggle(
          'sidebar-open',
          opened
        );
      };


      const openSidebar = () => {
        sidebar?.classList.add('open');
        syncSidebarState();
      };


      const closeSidebar = () => {
        sidebar?.classList.remove('open');
        syncSidebarState();
      };


      sidebarButtons.forEach(button => {
        button.addEventListener(
          'click',
          () => {

            if (
              sidebar?.classList.contains('open')
            ) {
              closeSidebar();
            } else {
              openSidebar();
            }

          }
        );
      });


      sidebarOverlay?.addEventListener(
        'click',
        closeSidebar
      );


      $$('.sidebar .nav-link').forEach(link => {
        link.addEventListener(
          'click',
          () => {

            if (
              window.matchMedia(
                '(max-width: 820px)'
              ).matches
            ) {
              closeSidebar();
            }

          }
        );
      });


      window.addEventListener(
        'resize',
        () => {

          if (
            window.innerWidth > 820
          ) {
            closeSidebar();
          }

        }
      );


      /* =====================================================
         MODAL
         ===================================================== */

      let lastFocusedElement = null;


      const closeModal = modal => {
        if (!modal) {
          return;
        }

        modal.classList.remove('open');

        modal.setAttribute(
          'aria-hidden',
          'true'
        );

        document.body.classList.remove(
          'modal-open'
        );

        if (
          lastFocusedElement
          && document.contains(
            lastFocusedElement
          )
        ) {
          window.setTimeout(
            () => {
              lastFocusedElement.focus();
            },
            30
          );
        }
      };


      const openModal = modal => {
        if (!modal) {
          return;
        }

        lastFocusedElement =
          document.activeElement;

        modal.classList.add('open');

        modal.setAttribute(
          'aria-hidden',
          'false'
        );

        document.body.classList.add(
          'modal-open'
        );

        window.setTimeout(
          () => {

            const focusTarget =
              $(
                [
                  '[autofocus]',
                  'input:not([type="hidden"])',
                  'select',
                  'textarea',
                  'button',
                  'a[href]'
                ].join(','),
                modal
              );

            focusTarget?.focus();

          },
          50
        );
      };


      window.appOpenModal =
        openModal;

      window.appCloseModal =
        closeModal;


      $$('[data-modal-open]')
        .forEach(button => {

          button.addEventListener(
            'click',
            () => {

              const modalId =
                button.dataset.modalOpen
                || '';

              openModal(
                document.getElementById(
                  modalId
                )
              );

            }
          );

        });


      $$('[data-modal-close]')
        .forEach(button => {

          button.addEventListener(
            'click',
            () => {

              closeModal(
                button.closest(
                  '.modal'
                )
              );

            }
          );

        });


      $$('.modal')
        .forEach(modal => {

          modal.addEventListener(
            'click',
            event => {

              if (
                event.target === modal
              ) {
                closeModal(modal);
              }

            }
          );

        });


      /* =====================================================
         CONFIRMATION
         ===================================================== */

      const confirmModal =
        $('[data-confirm-modal]');

      let confirmCallback = null;


      function requestConfirmation(
        message,
        callback
      ) {

        if (!confirmModal) {
          callback();
          return;
        }

        const messageElement =
          $(
            '[data-confirm-message]',
            confirmModal
          );

        if (messageElement) {
          messageElement.textContent =
            message;
        }

        confirmCallback =
          callback;

        openModal(
          confirmModal
        );
      }


      $('[data-confirm-cancel]')
        ?.addEventListener(
          'click',
          () => {

            confirmCallback = null;

            closeModal(
              confirmModal
            );

          }
        );


      $('[data-confirm-accept]')
        ?.addEventListener(
          'click',
          () => {

            const callback =
              confirmCallback;

            confirmCallback = null;

            closeModal(
              confirmModal
            );

            callback?.();

          }
        );


      $$('[data-confirm]')
        .forEach(element => {

          element.addEventListener(
            'click',
            event => {

              if (
                element.dataset.confirmed
                === 'true'
              ) {

                delete element.dataset
                  .confirmed;

                return;
              }


              event.preventDefault();

              event.stopImmediatePropagation();


              requestConfirmation(
                element.dataset.confirm
                || 'Lanjutkan tindakan ini?',
                () => {

                  element.dataset
                    .confirmed = 'true';

                  element.click();

                }
              );

            },
            true
          );

        });


      /* =====================================================
         ESCAPE
         ===================================================== */

      document.addEventListener(
        'keydown',
        event => {

          if (
            event.key !== 'Escape'
          ) {
            return;
          }


          const openedGuide =
            $('.guide-modal.open');

          if (openedGuide) {
            closeModal(
              openedGuide
            );

            return;
          }


          const openedModal =
            $(
              '.modal.open, .confirm-modal.open'
            );

          if (openedModal) {
            closeModal(
              openedModal
            );

            return;
          }


          closeSidebar();

        }
      );


      /* =====================================================
         SEARCH TABLE
         ===================================================== */

      $$('[data-search-table]')
        .forEach(input => {

          input.addEventListener(
            'input',
            () => {

              const table =
                $(
                  input.dataset
                    .searchTable
                  || ''
                );

              const needle =
                input.value
                  .trim()
                  .toLowerCase();


              $$(
                'tbody tr',
                table
                || document.createElement(
                  'div'
                )
              )
                .forEach(row => {

                  row.hidden =
                    !row.textContent
                      .toLowerCase()
                      .includes(
                        needle
                      );

                });

            }
          );

        });


      /* =====================================================
         SEARCH ITEMS
         ===================================================== */

      $$('[data-search-items]')
        .forEach(input => {

          input.addEventListener(
            'input',
            () => {

              const root =
                $(
                  input.dataset
                    .searchItems
                  || ''
                )
                || document;

              const needle =
                input.value
                  .trim()
                  .toLowerCase();


              $$(
                '[data-search-text]',
                root
              )
                .forEach(item => {

                  const text =
                    String(
                      item.dataset
                        .searchText
                      || item.textContent
                    )
                      .toLowerCase();

                  item.hidden =
                    !text.includes(
                      needle
                    );

                });

            }
          );

        });


      /* =====================================================
         NUMBER WORDS
         ===================================================== */

      const numberWords = [
        'nol',
        'satu',
        'dua',
        'tiga',
        'empat',
        'lima',
        'enam',
        'tujuh',
        'delapan',
        'sembilan',
        'sepuluh',
        'sebelas'
      ];


      function terbilang(value) {
        const n =
          Math.max(
            0,
            Math.round(
              Number(value)
              || 0
            )
          );

        if (n < 12) {
          return numberWords[n];
        }

        if (n < 20) {
          return `${terbilang(
            n - 10
          )} belas`;
        }

        if (n < 100) {
          return `${
            terbilang(
              Math.floor(
                n / 10
              )
            )
          } puluh${
            n % 10
              ? ` ${terbilang(
                  n % 10
                )}`
              : ''
          }`;
        }

        if (n < 200) {
          return `seratus${
            n > 100
              ? ` ${terbilang(
                  n - 100
                )}`
              : ''
          }`;
        }

        if (n < 1000) {
          return `${
            terbilang(
              Math.floor(
                n / 100
              )
            )
          } ratus${
            n % 100
              ? ` ${terbilang(
                  n % 100
                )}`
              : ''
          }`;
        }

        return String(n);
      }


      function arabicDigits(value) {
        return String(
          Math.round(
            Number(value)
            || 0
          )
        )
          .replace(
            /[0-9]/g,
            digit =>
              '٠١٢٣٤٥٦٧٨٩'[
                Number(digit)
              ]
          );
      }


      function arabicWords(value) {
        const n =
          Math.max(
            0,
            Math.min(
              100,
              Math.round(
                Number(value)
                || 0
              )
            )
          );

        const units = [
          '',
          'واحد',
          'اثنان',
          'ثلاثة',
          'أربعة',
          'خمسة',
          'ستة',
          'سبعة',
          'ثمانية',
          'تسعة'
        ];

        const special = {
          10: 'عشرة',
          11: 'أحد عشر',
          12: 'اثنا عشر',
          13: 'ثلاثة عشر',
          14: 'أربعة عشر',
          15: 'خمسة عشر',
          16: 'ستة عشر',
          17: 'سبعة عشر',
          18: 'ثمانية عشر',
          19: 'تسعة عشر'
        };

        const tens = {
          20: 'عشرون',
          30: 'ثلاثون',
          40: 'أربعون',
          50: 'خمسون',
          60: 'ستون',
          70: 'سبعون',
          80: 'ثمانون',
          90: 'تسعون',
          100: 'مائة'
        };


        if (n < 10) {
          return units[n]
            || 'صفر';
        }

        if (special[n]) {
          return special[n];
        }

        if (tens[n]) {
          return tens[n];
        }

        const ten =
          Math.floor(
            n / 10
          ) * 10;

        return `${
          units[
            n % 10
          ]
        } و${
          tens[ten]
        }`;
      }


      function predicate(score) {
        if (score >= 90) {
          return 'Mumtaz';
        }

        if (score >= 80) {
          return 'Jayyid Jiddan';
        }

        if (score >= 65) {
          return 'Jayyid';
        }

        if (score >= 50) {
          return 'Maqbul';
        }

        if (score >= 35) {
          return 'Dhaif';
        }

        return 'Dhaif Jiddan';
      }


      /* =====================================================
         SCORE PREVIEW
         ===================================================== */

      $$('[data-score-group]')
        .forEach(group => {

          const update = () => {

            const inputs =
              $$(
                '[data-score]',
                group
              );


            const scores =
              inputs.map(input =>
                Math.min(
                  100,
                  Math.max(
                    0,
                    Number(
                      input.value
                      || 0
                    )
                  )
                )
              );


            const total =
              scores.reduce(
                (
                  sum,
                  value
                ) =>
                  sum + value,
                0
              );


            const average =
              scores.length
                ? total
                  / scores.length
                : 0;


            const scope =
              group.closest(
                '[data-preview-scope]'
              )
              || group;


            $$(
              '[data-average]',
              scope
            )
              .forEach(
                output => {

                  output.textContent =
                    average.toFixed(
                      2
                    );

                }
              );


            $$(
              '[data-total]',
              scope
            )
              .forEach(
                output => {

                  output.textContent =
                    total.toFixed(
                      0
                    );

                }
              );


            $$(
              '[data-predicate]',
              scope
            )
              .forEach(
                output => {

                  output.textContent =
                    predicate(
                      average
                    );

                }
              );


            inputs.forEach(
              (
                input,
                index
              ) => {

                const key =
                  input.dataset
                    .score
                  || String(
                    index
                  );


                $$(
                  `[data-score-value="${key}"]`,
                  scope
                )
                  .forEach(
                    output => {

                      output.textContent =
                        scores[index]
                          .toFixed(
                            0
                          );

                    }
                  );


                $$(
                  `[data-score-words="${key}"]`,
                  scope
                )
                  .forEach(
                    output => {

                      const text =
                        terbilang(
                          scores[index]
                        );

                      output.textContent =
                        text.replace(
                          /^./,
                          char =>
                            char.toUpperCase()
                        );

                    }
                  );


                $$(
                  `[data-score-arabic="${key}"]`,
                  scope
                )
                  .forEach(
                    output => {

                      output.textContent =
                        arabicDigits(
                          scores[index]
                        );

                    }
                  );


                $$(
                  `[data-score-arabic-words="${key}"]`,
                  scope
                )
                  .forEach(
                    output => {

                      output.textContent =
                        arabicWords(
                          scores[index]
                        );

                    }
                  );


                $$(
                  `[data-score-status="${key}"]`,
                  scope
                )
                  .forEach(
                    output => {

                      const minimum =
                        Number(
                          output.dataset
                            .minimum
                          || 75
                        );

                      output.textContent =
                        scores[index]
                        >= minimum
                          ? 'Tercapai'
                          : 'Perlu bimbingan';

                    }
                  );

              }
            );


            $$(
              '[data-total-words]',
              scope
            )
              .forEach(
                output => {

                  const text =
                    terbilang(
                      total
                    );

                  output.textContent =
                    text.replace(
                      /^./,
                      char =>
                        char.toUpperCase()
                    );

                }
              );


            $$(
              '[data-total-arabic]',
              scope
            )
              .forEach(
                output => {

                  output.textContent =
                    arabicDigits(
                      total
                    );

                }
              );


            $$(
              '[data-pass-label]',
              scope
            )
              .forEach(
                output => {

                  const minimum =
                    Number(
                      output.dataset
                        .minimum
                      || 75
                    );

                  output.textContent =
                    average
                    >= minimum
                      ? 'Naik'
                      : 'Mengulang';

                }
              );

          };


          $$(
            '[data-score]',
            group
          )
            .forEach(input => {

              input.addEventListener(
                'input',
                update
              );

            });


          update();

        });


      /* =====================================================
         AJAX FORMS
         ===================================================== */

      $$('form[data-ajax]')
        .forEach(form => {

          form.addEventListener(
            'submit',
            async event => {

              event.preventDefault();


              if (
                form.dataset.submitting
                === 'true'
              ) {
                return;
              }


              if (
                !form.checkValidity()
              ) {

                form.reportValidity();

                return;
              }


              const submitButton =
                event.submitter
                || $(
                  [
                    'button[type="submit"]',
                    'input[type="submit"]'
                  ].join(','),
                  form
                );


              const actionUrl =
                form.getAttribute(
                  'action'
                )
                || window.location.href;


              const method =
                (
                  form.getAttribute(
                    'method'
                  )
                  || 'POST'
                )
                  .toUpperCase();


              const formData =
                new FormData(
                  form
                );


              if (
                event.submitter
                ?.name
              ) {

                formData.set(
                  event.submitter.name,
                  event.submitter.value
                );

              }


              const originalText =
                submitButton
                ?.textContent;


              form.dataset.submitting =
                'true';

              submitButton
                ?.setAttribute(
                  'disabled',
                  'disabled'
                );

              form.classList.add(
                'loading'
              );


              if (
                submitButton
                && submitButton.tagName
                  === 'BUTTON'
              ) {

                submitButton.dataset
                  .originalText =
                  originalText
                  || '';

                submitButton.textContent =
                  'Memproses...';

              }


              try {

                let requestUrl =
                  actionUrl;


                const options = {

                  method,

                  headers: {
                    Accept:
                      'application/json'
                  },

                  credentials:
                    'same-origin'

                };


                if (
                  method === 'GET'
                ) {

                  const query =
                    new URLSearchParams(
                      formData
                    )
                      .toString();


                  requestUrl +=
                    (
                      requestUrl.includes(
                        '?'
                      )
                        ? '&'
                        : '?'
                    )
                    + query;

                } else {

                  options.body =
                    formData;

                }


                const response =
                  await fetch(
                    requestUrl,
                    options
                  );


                const contentType =
                  response.headers.get(
                    'content-type'
                  )
                  || '';


                if (
                  !contentType.includes(
                    'application/json'
                  )
                ) {

                  throw new Error(
                    `Server mengembalikan HTTP ${response.status}`
                  );

                }


                const payload =
                  await response.json();


                if (
                  !response.ok
                  || !payload.success
                ) {

                  toast(
                    payload.message
                    || 'Operasi gagal diproses.',
                    'error'
                  );

                  continueAjaxCleanup();

                  return;
                }


                toast(
                  payload.message
                  || 'Data berhasil disimpan.'
                );


                if (
                  form.dataset.redirect
                ) {

                  window.setTimeout(
                    () => {

                      window.location.assign(
                        form.dataset
                          .redirect
                      );

                    },
                    450
                  );

                } else if (
                  form.dataset.noReload
                  === undefined
                ) {

                  window.setTimeout(
                    () => {

                      window.location
                        .reload();

                    },
                    600
                  );

                }


              } catch (error) {

                console.error(
                  '[NGAJIYUK!]',
                  error
                );


                toast(
                  'Terjadi gangguan saat mengirim data. Periksa koneksi server dan database.',
                  'error'
                );

              } finally {

                continueAjaxCleanup();

              }


              function continueAjaxCleanup() {

                delete form.dataset
                  .submitting;

                submitButton
                  ?.removeAttribute(
                    'disabled'
                  );

                form.classList.remove(
                  'loading'
                );


                if (
                  submitButton
                  && submitButton.tagName
                    === 'BUTTON'
                  && submitButton.dataset
                    .originalText
                  !== undefined
                ) {

                  submitButton.textContent =
                    submitButton.dataset
                      .originalText;

                  delete submitButton.dataset
                    .originalText;

                }

              }

            }
          );

        });


      /* =====================================================
         GUIDE DATA
         ===================================================== */

      const guides = {


        /* ===================================================
           ADMIN
           =================================================== */

        admin: [

          {
            kicker:
              'SELAMAT DATANG',

            title:
              'Pantau seluruh sistem dari satu tempat',

            description:
              'Panduan Administrator membantu mengelola Guru, Orang Tua, siswa, kelas, laporan, akun, dan keamanan sistem NGAJIYUK!.',

            tips: [
              'Dashboard menampilkan kondisi sistem secara ringkas',
              'Semua tindakan penting dapat ditelusuri melalui Audit Aktivitas',
              'Panduan dapat dibuka kembali melalui menu Panduan'
            ],

            icon:
              'dashboard'
          },


          {
            kicker:
              'LANGKAH 1',

            title:
              'Gunakan Dashboard Monitoring',

            description:
              'Dashboard Monitoring menjadi pusat kendali Administrator untuk melihat jumlah Guru, Orang Tua, siswa, kelas, laporan, ujian, dan data yang perlu diperiksa.',

            tips: [
              'Periksa bagian Perlu Perhatian setiap hari',
              'Pantau kelengkapan laporan Guru',
              'Gunakan Aktivitas Terbaru untuk melihat perubahan terbaru'
            ],

            icon:
              'monitor'
          },


          {
            kicker:
              'LANGKAH 2',

            title:
              'Pantau aktivitas setiap Guru',

            description:
              'Menu Monitoring Guru menampilkan kelas yang ditangani, jumlah siswa, laporan hari ini, aktivitas tujuh hari, dan status setiap akun Guru.',

            tips: [
              'Periksa Guru yang belum memiliki kelas',
              'Pantau laporan yang belum lengkap',
              'Administrator dapat mengaktifkan atau menonaktifkan akun Guru'
            ],

            icon:
              'teacher'
          },


          {
            kicker:
              'LANGKAH 3',

            title:
              'Kelola hubungan Orang Tua dan anak',

            description:
              'Monitoring Orang Tua digunakan untuk memeriksa akun wali, siswa yang sudah terhubung, kelas anak, kelengkapan biodata, serta aktivitas login.',

            tips: [
              'Hubungkan akun Orang Tua berdasarkan NIS siswa',
              'Pastikan satu siswa tidak terhubung ke akun wali yang salah',
              'Periksa siswa yang belum memiliki akun Orang Tua'
            ],

            icon:
              'parent'
          },


          {
            kicker:
              'LANGKAH 4',

            title:
              'Kelola Siswa, Kelas, dan Wali Mengaji',

            description:
              'Menu Siswa & Kelas menampilkan seluruh kelas 1A–6B, distribusi level Tahfidz, nilai per surat, presensi, tadarus, serta wali kelas mengaji.',

            tips: [
              'Pilih tahun ajaran yang sesuai',
              'Gunakan filter Guru dan level untuk mempersempit data',
              'Klik kelas untuk melihat detail siswa'
            ],

            icon:
              'school'
          },


          {
            kicker:
              'LANGKAH 5',

            title:
              'Setujui dan kelola akun pengguna',

            description:
              'Persetujuan Akun digunakan untuk menerima pendaftaran Guru dan mengatur role, password, serta akun pengguna lainnya.',

            tips: [
              'Periksa identitas sebelum menyetujui Guru baru',
              'Jangan mengubah role tanpa memastikan kebutuhan akun',
              'Gunakan fitur hapus akun dengan hati-hati'
            ],

            icon:
              'account'
          },


          {
            kicker:
              'LANGKAH 6',

            title:
              'Periksa laporan dan Audit Aktivitas',

            description:
              'Gunakan Kelengkapan Laporan untuk memantau pelaporan Guru dan Audit Aktivitas untuk menelusuri perubahan penting di dalam sistem.',

            tips: [
              'Status Perlu Dicek menunjukkan data yang belum lengkap',
              'Audit membantu mengetahui siapa melakukan perubahan',
              'Password dan data rahasia tidak ditampilkan dalam audit'
            ],

            icon:
              'audit'
          }

        ],


        /* ===================================================
           GURU
           =================================================== */

        guru: [

          {
            kicker:
              'SELAMAT DATANG',

            title:
              'Kelola pembelajaran mengaji dengan lebih terarah',

            description:
              'Panduan Guru menjelaskan alur kerja utama NGAJIYUK!, mulai dari memantau siswa sampai menyimpan nilai dan mencetak laporan.',

            tips: [
              'Gunakan data sesuai kelas yang menjadi tanggung jawab Anda',
              'Pastikan setiap perubahan sudah disimpan',
              'Panduan dapat dibuka kembali kapan saja'
            ],

            icon:
              'teacher'
          },


          {
            kicker:
              'LANGKAH 1',

            title:
              'Mulai dari Dashboard Guru',

            description:
              'Dashboard menampilkan kondisi kelas, jumlah siswa, laporan harian, perkembangan mingguan, serta aktivitas penting yang perlu dilakukan.',

            tips: [
              'Periksa siswa yang belum memiliki laporan',
              'Gunakan tindakan cepat untuk mempercepat input data'
            ],

            icon:
              'dashboard'
          },


          {
            kicker:
              'LANGKAH 2',

            title:
              'Kelola Daftar Siswa',

            description:
              'Daftar Siswa digunakan untuk melihat identitas siswa, kelas, level, status, dan data akademik yang menjadi tanggung jawab Guru.',

            tips: [
              'Pastikan NIS siswa benar',
              'Periksa kelas dan level sebelum mengisi laporan'
            ],

            icon:
              'students'
          },


          {
            kicker:
              'LANGKAH 3',

            title:
              'Periksa Data Kelas',

            description:
              'Data Kelas menampilkan siswa per kelas, distribusi level Tahfidz, kurikulum surat, dan ringkasan akademik.',

            tips: [
              'Gunakan tahun ajaran yang sesuai',
              'Klik kelas untuk melihat data lebih rinci'
            ],

            icon:
              'school'
          },


          {
            kicker:
              'LANGKAH 4',

            title:
              'Isi Presensi dan Laporan Harian',

            description:
              'Catat presensi, kegiatan mengaji, tadarus, hafalan, serta catatan perkembangan untuk setiap siswa.',

            tips: [
              'Satu siswa memiliki satu laporan per tanggal',
              'Riwayat laporan lama tetap dapat diperiksa'
            ],

            icon:
              'report'
          },


          {
            kicker:
              'LANGKAH 5',

            title:
              'Simpan Nilai Tahsin dan Tahfidz',

            description:
              'Isi nilai per surat berdasarkan kelancaran, makhraj, tajwid, hafalan, serta keterangan perkembangan siswa.',

            tips: [
              'Periksa surat yang dinilai sebelum menyimpan',
              'Pastikan nilai sesuai hasil evaluasi siswa'
            ],

            icon:
              'book'
          },


          {
            kicker:
              'LANGKAH 6',

            title:
              'Catat Ujian Kenaikan Level',

            description:
              'Gunakan form Ujian Level untuk mencatat hasil evaluasi siswa sebelum naik ke level berikutnya.',

            tips: [
              'Periksa level asal dan level tujuan',
              'Sistem menghitung rata-rata penilaian secara otomatis'
            ],

            icon:
              'graduation'
          },


          {
            kicker:
              'LANGKAH 7',

            title:
              'Lengkapi Munaqosyah',

            description:
              'Form Munaqosyah digunakan untuk menyimpan hasil penilaian akhir, predikat, serta catatan perkembangan siswa.',

            tips: [
              'Pastikan seluruh komponen nilai sudah diisi',
              'Periksa preview sebelum data disimpan'
            ],

            icon:
              'award'
          },


          {
            kicker:
              'LANGKAH 8',

            title:
              'Periksa dan cetak rapor',

            description:
              'Setelah seluruh data lengkap, gunakan menu rapor untuk memeriksa hasil akhir sebelum dicetak atau disimpan.',

            tips: [
              'Pastikan identitas siswa benar',
              'Periksa nilai dan catatan sebelum mencetak rapor'
            ],

            icon:
              'print'
          }

        ],


        /* ===================================================
           ORANG TUA
           =================================================== */

        orang_tua: [

          {
            kicker:
              'SELAMAT DATANG',

            title:
              'Pantau perkembangan anak dengan lebih mudah',

            description:
              'Panduan Orang Tua hanya menampilkan informasi yang perlu dipantau. Seluruh catatan dan penilaian berasal dari data yang diisi Guru.',

            tips: [
              'Akun Orang Tua tidak dapat mengubah nilai',
              'Data dapat dilihat kapan saja dari dashboard',
              'Panduan dapat dibuka kembali dari menu Sistem'
            ],

            icon:
              'welcome'
          },


          {
            kicker:
              'LANGKAH 1',

            title:
              'Mulai dari Dashboard Anak',

            description:
              'Dashboard menjadi pusat informasi perkembangan anak. Anda dapat melihat level Tahfidz, laporan terbaru, nilai, dan perkembangan belajar.',

            tips: [
              'Informasi hanya menampilkan anak yang terhubung ke akun Anda',
              'Periksa dashboard secara berkala untuk melihat perkembangan terbaru'
            ],

            icon:
              'dashboard'
          },


          {
            kicker:
              'LANGKAH 2',

            title:
              'Pantau Laporan Harian',

            description:
              'Lihat laporan yang dibuat Guru, mulai dari presensi, kegiatan mengaji, tadarus, hafalan, hingga catatan perkembangan anak.',

            tips: [
              'Gunakan tanggal untuk melihat riwayat laporan',
              'Hubungi Guru jika ada catatan yang perlu diklarifikasi'
            ],

            icon:
              'report'
          },


          {
            kicker:
              'LANGKAH 3',

            title:
              'Lihat Nilai dan Target Surat',

            description:
              'Pantau nilai Tahsin dan Tahfidz per surat serta target hafalan atau bacaan berdasarkan level anak.',

            tips: [
              'Nilai berasal dari hasil evaluasi Guru',
              'Target surat mengikuti level Tahfidz anak'
            ],

            icon:
              'book'
          },


          {
            kicker:
              'LANGKAH 4',

            title:
              'Periksa Hasil Ujian Kenaikan Level',

            description:
              'Ketika anak mengikuti ujian level, hasil evaluasi akan tampil setelah Guru menyimpan hasil ujian.',

            tips: [
              'Perhatikan rata-rata nilai dan hasil ujian',
              'Level terbaru akan mengikuti hasil evaluasi yang tersimpan'
            ],

            icon:
              'graduation'
          },


          {
            kicker:
              'LANGKAH 5',

            title:
              'Pantau Hasil Munaqosyah',

            description:
              'Hasil Munaqosyah menampilkan nilai akhir, rata-rata, predikat, dan catatan dari Guru.',

            tips: [
              'Gunakan hasil sebagai gambaran perkembangan anak',
              'Perhatikan catatan Guru selain nilai angka'
            ],

            icon:
              'award'
          },


          {
            kicker:
              'LANGKAH 6',

            title:
              'Lengkapi Biodata dan gunakan Panduan',

            description:
              'Pastikan data Orang Tua selalu terbaru agar sekolah mudah menghubungi Anda. Panduan dapat dibuka kembali kapan saja.',

            tips: [
              'Perbarui nomor telepon dan alamat jika berubah',
              'Gunakan tombol Panduan jika membutuhkan petunjuk lagi'
            ],

            icon:
              'profile'
          }

        ]

      };


      /* =====================================================
         GUIDE ICONS
         ===================================================== */

      const guideIcons = {

        welcome: `
          <svg viewBox="0 0 24 24">
            <path d="M4 20 10 4l3 7 7 3-16 6Z"></path>
            <path d="m14 4 .5-2"></path>
            <path d="m18 7 2-1"></path>
            <path d="m17 11 2 1"></path>
          </svg>
        `,

        dashboard: `
          <svg viewBox="0 0 24 24">
            <rect x="3" y="3" width="7" height="7" rx="2"></rect>
            <rect x="14" y="3" width="7" height="7" rx="2"></rect>
            <rect x="3" y="14" width="7" height="7" rx="2"></rect>
            <rect x="14" y="14" width="7" height="7" rx="2"></rect>
          </svg>
        `,

        monitor: `
          <svg viewBox="0 0 24 24">
            <path d="M3 12h4l2-6 4 12 2-6h6"></path>
          </svg>
        `,

        teacher: `
          <svg viewBox="0 0 24 24">
            <path d="m3 8 9-4 9 4-9 4-9-4Z"></path>
            <path d="M7 10v5c3 2 7 2 10 0v-5"></path>
          </svg>
        `,

        parent: `
          <svg viewBox="0 0 24 24">
            <circle cx="9" cy="8" r="3"></circle>
            <circle cx="17" cy="9" r="2"></circle>
            <path d="M3 20v-2c0-3 2-5 6-5s6 2 6 5v2"></path>
            <path d="M16 14c3 0 5 2 5 5"></path>
          </svg>
        `,

        school: `
          <svg viewBox="0 0 24 24">
            <path d="M4 21V8l8-5 8 5v13"></path>
            <path d="M8 21v-6h8v6"></path>
            <path d="M8 10h2"></path>
            <path d="M14 10h2"></path>
          </svg>
        `,

        students: `
          <svg viewBox="0 0 24 24">
            <circle cx="9" cy="8" r="3"></circle>
            <circle cx="17" cy="8" r="2"></circle>
            <path d="M3 20v-2c0-3 2-5 6-5s6 2 6 5v2"></path>
            <path d="M16 13c3 0 5 2 5 5v2"></path>
          </svg>
        `,

        account: `
          <svg viewBox="0 0 24 24">
            <circle cx="9" cy="8" r="3"></circle>
            <path d="M3 20v-2c0-3 2-5 6-5"></path>
            <path d="M18 8v6"></path>
            <path d="M15 11h6"></path>
          </svg>
        `,

        report: `
          <svg viewBox="0 0 24 24">
            <rect x="5" y="4" width="14" height="17" rx="2"></rect>
            <path d="M9 4V2h6v2"></path>
            <path d="m9 12 2 2 4-4"></path>
            <path d="M9 17h6"></path>
          </svg>
        `,

        book: `
          <svg viewBox="0 0 24 24">
            <path d="M4 5c3-1 5 0 8 2v13c-3-2-5-3-8-2V5Z"></path>
            <path d="M20 5c-3-1-5 0-8 2v13c3-2 5-3 8-2V5Z"></path>
          </svg>
        `,

        graduation: `
          <svg viewBox="0 0 24 24">
            <path d="m3 9 9-5 9 5-9 5-9-5Z"></path>
            <path d="M7 12v4c3 2 7 2 10 0v-4"></path>
          </svg>
        `,

        award: `
          <svg viewBox="0 0 24 24">
            <circle cx="12" cy="8" r="5"></circle>
            <path d="m8.5 12-2 9 5.5-3 5.5 3-2-9"></path>
          </svg>
        `,

        profile: `
          <svg viewBox="0 0 24 24">
            <circle cx="12" cy="8" r="4"></circle>
            <path d="M4 21v-2c0-4 3-7 8-7s8 3 8 7v2"></path>
          </svg>
        `,

        print: `
          <svg viewBox="0 0 24 24">
            <path d="M6 9V3h12v6"></path>
            <rect x="6" y="14" width="12" height="7"></rect>
            <path d="M6 17H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"></path>
          </svg>
        `,

        audit: `
          <svg viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9"></circle>
            <path d="M12 7v5l3 2"></path>
          </svg>
        `

      };


      /* =====================================================
         GUIDE UI STYLE
         ===================================================== */

      const guideStyle =
        document.createElement(
          'style'
        );

      guideStyle.id =
        'ngajiyuk-guide-style';

      guideStyle.textContent = `

        body.guide-open {
          overflow: hidden;
        }

        .guide-modal {
          position: fixed;
          inset: 0;
          z-index: 9999;

          display: none;
          align-items: center;
          justify-content: center;

          padding: 24px;

          background:
            rgba(20, 45, 35, .67);

          backdrop-filter:
            blur(7px);

          -webkit-backdrop-filter:
            blur(7px);
        }

        .guide-modal.open {
          display: flex;
        }

        .guide-modal .guide-card,
        .guide-modal .modal-card {
          width: min(
            650px,
            calc(100vw - 30px)
          );

          max-height:
            calc(100vh - 40px);

          overflow: hidden;

          border:
            1px solid
            rgba(255,255,255,.55);

          border-radius: 24px;

          background: #fff;

          box-shadow:
            0 35px 90px
            rgba(10, 35, 25, .32);
        }

        .guide-modal
        .guide-header {
          min-height: 78px;

          padding: 17px 26px;

          display: flex;
          align-items: center;
          justify-content: space-between;

          gap: 18px;

          background:
            radial-gradient(
              circle at 85% 20%,
              rgba(255,255,255,.08),
              transparent 120px
            ),
            linear-gradient(
              135deg,
              #174f3d,
              #0d6448
            );

          color: #fff;
        }

        .guide-modal
        .guide-brand {
          display: flex;
          align-items: center;

          gap: 12px;
        }

        .guide-modal
        .guide-brand img {
          width: 42px;
          height: 42px;

          object-fit: contain;

          padding: 4px;

          border-radius: 11px;

          background: #fff;
        }

        .guide-modal
        .guide-brand strong {
          display: block;

          color: #fff;

          font-size: 13px;
          font-weight: 900;
        }

        .guide-modal
        .guide-brand small {
          display: block;

          margin-top: 3px;

          color: #bfe0d1;

          font-size: 8px;
          font-weight: 900;

          letter-spacing: .08em;

          text-transform: uppercase;
        }

        .guide-modal
        [data-guide-close] {
          width: 36px;
          height: 36px;

          display: grid;
          place-items: center;

          border: 0;
          border-radius: 50%;

          background:
            rgba(255,255,255,.13);

          color: #fff;

          cursor: pointer;

          font-size: 21px;
        }

        .guide-modal
        .guide-body {
          max-height:
            calc(100vh - 190px);

          overflow-y: auto;

          padding:
            28px
            30px
            22px;
        }

        .guide-modal
        .guide-topline {
          display: flex;
          align-items: center;
          justify-content: space-between;

          gap: 15px;

          margin-bottom: 18px;
        }

        .guide-modal
        [data-guide-kicker] {
          display: inline-flex;

          padding: 6px 12px;

          border-radius: 999px;

          background: #eafaf2;

          color: #00855b;

          font-size: 8px;
          font-weight: 900;

          letter-spacing: .16em;

          text-transform: uppercase;
        }

        .guide-modal
        [data-guide-counter] {
          color: #8793a5;

          font-size: 10px;
          font-weight: 900;
        }

        .guide-modal
        .guide-main {
          display: grid;

          grid-template-columns:
            60px 1fr;

          gap: 18px;
        }

        .guide-modal
        [data-guide-icon] {
          width: 60px;
          height: 60px;

          display: grid;
          place-items: center;

          border-radius: 16px;

          background: #eaf8f1;

          color: #087851;
        }

        .guide-modal
        [data-guide-icon] svg {
          width: 30px;
          height: 30px;

          fill: none;
          stroke: currentColor;
          stroke-width: 1.8;
          stroke-linecap: round;
          stroke-linejoin: round;
        }

        .guide-modal
        [data-guide-greeting] {
          margin:
            2px 0 7px;

          color: #13815a;

          font-size: 11px;
          font-weight: 900;
        }

        .guide-modal
        [data-guide-title] {
          margin: 0;

          color: #102b20;

          font-size:
            clamp(23px, 4vw, 30px);

          font-weight: 900;

          line-height: 1.12;

          letter-spacing: -.035em;
        }

        .guide-modal
        [data-guide-description] {
          margin:
            13px 0 0;

          color: #647287;

          font-size: 11.5px;

          line-height: 1.7;
        }

        .guide-modal
        [data-guide-tips] {
          margin-top: 23px;

          display: grid;

          grid-template-columns:
            repeat(2, minmax(0,1fr));

          gap: 10px;
        }

        .guide-modal
        .guide-tip {
          position: relative;

          min-height: 60px;

          padding:
            13px
            14px
            13px
            39px;

          display: flex;
          align-items: center;

          border:
            1px solid #e6ebe8;

          border-radius: 12px;

          background: #f8f9fa;

          color: #596678;

          font-size: 9.5px;
          font-weight: 700;

          line-height: 1.55;
        }

        .guide-modal
        .guide-tip::before {
          content: "✓";

          position: absolute;

          left: 14px;
          top: 15px;

          width: 16px;
          height: 16px;

          display: grid;
          place-items: center;

          border:
            1px solid #47b586;

          border-radius: 50%;

          color: #16825c;

          font-size: 9px;
          font-weight: 900;
        }

        .guide-modal
        [data-guide-dots] {
          margin-top: 25px;

          display: flex;

          gap: 6px;
        }

        .guide-modal
        .guide-dot {
          width: 17px;
          height: 5px;

          padding: 0;

          border: 0;
          border-radius: 999px;

          background: #e2e5e7;

          cursor: pointer;

          transition:
            width .18s ease,
            background .18s ease;
        }

        .guide-modal
        .guide-dot.active {
          width: 30px;

          background: #19784f;
        }

        .guide-modal
        .guide-footer {
          min-height: 70px;

          padding:
            14px
            30px;

          display: flex;
          align-items: center;
          justify-content: space-between;

          gap: 12px;

          border-top:
            1px solid #edf0ee;

          background: #fbfcfb;
        }

        .guide-modal
        [data-guide-skip] {
          border: 0;

          background: transparent;

          color: #8a96a6;

          cursor: pointer;

          font-size: 9.5px;
          font-weight: 850;
        }

        .guide-modal
        .guide-navigation {
          display: flex;

          gap: 8px;
        }

        .guide-modal
        [data-guide-prev] {
          min-height: 38px;

          padding: 8px 13px;

          border:
            1px solid #dce3df;

          border-radius: 10px;

          background: #fff;

          color: #526158;

          cursor: pointer;

          font-size: 9.5px;
          font-weight: 900;
        }

        .guide-modal
        [data-guide-next] {
          min-height: 38px;

          padding: 8px 16px;

          border: 0;

          border-radius: 10px;

          background:
            linear-gradient(
              135deg,
              #17603f,
              #087548
            );

          color: #fff;

          cursor: pointer;

          font-size: 9.5px;
          font-weight: 900;
        }

        @media (max-width: 600px) {

          .guide-modal {
            padding: 12px;
          }

          .guide-modal
          .guide-body {
            padding:
              22px
              18px
              18px;
          }

          .guide-modal
          .guide-main {
            grid-template-columns: 1fr;
          }

          .guide-modal
          [data-guide-icon] {
            width: 52px;
            height: 52px;
          }

          .guide-modal
          [data-guide-tips] {
            grid-template-columns: 1fr;
          }

          .guide-modal
          .guide-footer {
            padding:
              13px
              18px;
          }

        }

      `;


      if (
        !document.getElementById(
          guideStyle.id
        )
      ) {
        document.head.appendChild(
          guideStyle
        );
      }


      /* =====================================================
         GUIDE SYSTEM
         ===================================================== */

      const guideModal =
        $('[data-guide-modal]');


      if (guideModal) {

        /*
         * Ambil role dari modal.
         * Kalau kosong, baca class body.
         */

        let role =
          guideModal.dataset
            .guideRole
          || '';


        if (!role) {

          if (
            document.body.classList
              .contains(
                'role-admin'
              )
          ) {
            role = 'admin';
          } else if (
            document.body.classList
              .contains(
                'role-guru'
              )
          ) {
            role = 'guru';
          } else if (
            document.body.classList
              .contains(
                'role-orang_tua'
              )
          ) {
            role = 'orang_tua';
          }

        }


        if (
          !guides[role]
        ) {
          role = 'guru';
        }


        const steps =
          guides[role];

        let index = 0;


        /*
         * Ditampilkan satu kali selama
         * login/session.
         *
         * Saat logout key akan dihapus.
         */

        const sessionKey =
          `ngajiyuk-guide-seen-${role}`;


        /* ===============================================
           ROLE LABEL
           =============================================== */

        const roleLabels = {

          admin:
            'PANDUAN ADMINISTRATOR',

          guru:
            'PANDUAN GURU',

          orang_tua:
            'PANDUAN ORANG TUA'

        };


        /* ===============================================
           BRAND
           =============================================== */

        const brandTitle =
          $(
            '.guide-brand strong',
            guideModal
          );


        if (brandTitle) {
          brandTitle.textContent =
            'NGAJIYUK!';
        }


        const brandSub =
          $(
            '.guide-brand small',
            guideModal
          );


        if (brandSub) {
          brandSub.textContent =
            roleLabels[role]
            || 'PANDUAN';
        }


        /* ===============================================
           USER NAME
           =============================================== */

        const accountName =
          $(
            '.account-name strong'
          )
          ?.textContent
          ?.trim()
          || '';


        const firstName =
          accountName
            .split(/\s+/)
            .filter(Boolean)[0]
          || '';


        const greeting =
          $(
            '[data-guide-greeting]',
            guideModal
          );


        if (greeting) {

          greeting.textContent =
            firstName
              ? `Halo, ${firstName}!`
              : 'Halo!';

        }


        /* ===============================================
           ESCAPE HTML
           =============================================== */

        const escapeText =
          value => {

            const holder =
              document.createElement(
                'div'
              );

            holder.textContent =
              String(value);

            return holder.innerHTML;

          };


        /* ===============================================
           RENDER
           =============================================== */

        const renderGuide =
          () => {

            const step =
              steps[index];


            const kicker =
              $(
                '[data-guide-kicker]',
                guideModal
              );

            const counter =
              $(
                '[data-guide-counter]',
                guideModal
              );

            const title =
              $(
                '[data-guide-title]',
                guideModal
              );

            const description =
              $(
                '[data-guide-description]',
                guideModal
              );

            const tips =
              $(
                '[data-guide-tips]',
                guideModal
              );

            const dots =
              $(
                '[data-guide-dots]',
                guideModal
              );

            const icon =
              $(
                '[data-guide-icon]',
                guideModal
              );

            const previous =
              $(
                '[data-guide-prev]',
                guideModal
              );

            const next =
              $(
                '[data-guide-next]',
                guideModal
              );


            if (kicker) {
              kicker.textContent =
                step.kicker;
            }


            if (counter) {
              counter.textContent =
                `${index + 1} / ${steps.length}`;
            }


            if (title) {
              title.textContent =
                step.title;
            }


            if (description) {
              description.textContent =
                step.description;
            }


            if (tips) {

              tips.innerHTML =
                step.tips
                  .map(
                    tip => `
                      <div class="guide-tip">
                        ${escapeText(tip)}
                      </div>
                    `
                  )
                  .join('');

            }


            if (icon) {

              icon.innerHTML =
                guideIcons[
                  step.icon
                ]
                || guideIcons
                  .dashboard;

            }


            if (dots) {

              dots.innerHTML =
                steps
                  .map(
                    (
                      _,
                      dotIndex
                    ) => `
                      <button
                        type="button"
                        class="
                          guide-dot
                          ${
                            dotIndex === index
                              ? 'active'
                              : ''
                          }
                        "
                        data-guide-dot="${dotIndex}"
                        aria-label="Langkah ${dotIndex + 1}"
                      ></button>
                    `
                  )
                  .join('');


              $$(
                '[data-guide-dot]',
                guideModal
              )
                .forEach(dot => {

                  dot.addEventListener(
                    'click',
                    () => {

                      index =
                        Number(
                          dot.dataset
                            .guideDot
                        );

                      renderGuide();

                    }
                  );

                });

            }


            if (previous) {

              previous.hidden =
                index === 0;

            }


            if (next) {

              next.textContent =
                index
                === steps.length - 1
                  ? 'Selesai ✓'
                  : 'Berikutnya →';

            }

          };


        /* ===============================================
           OPEN GUIDE
           =============================================== */

        const openGuide =
          () => {

            index = 0;

            renderGuide();

            document.body
              .classList.add(
                'guide-open'
              );

            openModal(
              guideModal
            );

          };


        /* ===============================================
           FINISH GUIDE
           =============================================== */

        const finishGuide =
          () => {

            sessionStorage.setItem(
              sessionKey,
              'done'
            );

            document.body
              .classList.remove(
                'guide-open'
              );

            closeModal(
              guideModal
            );

          };


        /* ===============================================
           MANUAL OPEN
           =============================================== */

        $$('[data-guide-open]')
          .forEach(button => {

            button.addEventListener(
              'click',
              event => {

                event.preventDefault();

                openGuide();

              }
            );

          });


        /* ===============================================
           CLOSE
           =============================================== */

        $(
          '[data-guide-close]',
          guideModal
        )
          ?.addEventListener(
            'click',
            finishGuide
          );


        /* ===============================================
           SKIP
           =============================================== */

        $(
          '[data-guide-skip]',
          guideModal
        )
          ?.addEventListener(
            'click',
            finishGuide
          );


        /* ===============================================
           PREVIOUS
           =============================================== */

        $(
          '[data-guide-prev]',
          guideModal
        )
          ?.addEventListener(
            'click',
            () => {

              if (index > 0) {

                index -= 1;

                renderGuide();

              }

            }
          );


        /* ===============================================
           NEXT
           =============================================== */

        $(
          '[data-guide-next]',
          guideModal
        )
          ?.addEventListener(
            'click',
            () => {

              if (
                index
                < steps.length - 1
              ) {

                index += 1;

                renderGuide();

              } else {

                finishGuide();

              }

            }
          );


        /* ===============================================
           BACKDROP
           =============================================== */

        guideModal.addEventListener(
          'click',
          event => {

            if (
              event.target
              === guideModal
            ) {
              finishGuide();
            }

          }
        );


        /* ===============================================
           AUTO OPEN SETELAH LOGIN
           =============================================== */

        renderGuide();


        if (
          !sessionStorage.getItem(
            sessionKey
          )
        ) {

          window.setTimeout(
            () => {

              openGuide();

            },
            400
          );

        }

      }


      /* =====================================================
         RESET GUIDE SAAT LOGOUT
         ===================================================== */

      const clearGuideSession =
        () => {

          [
            'admin',
            'guru',
            'orang_tua'
          ]
            .forEach(roleName => {

              sessionStorage
                .removeItem(
                  `ngajiyuk-guide-seen-${roleName}`
                );

            });

        };


      $$(
        [
          '.logout-link',
          '[data-logout]',
          'a[href*="logout"]'
        ].join(',')
      )
        .forEach(logout => {

          logout.addEventListener(
            'click',
            clearGuideSession
          );

        });


      /* =====================================================
         BRAND TEXT
         ===================================================== */

      $$('.sidebar-brand .brand strong')
        .forEach(element => {

          if (
            element.textContent
              .trim()
              .toUpperCase()
              .includes(
                'CATATAN MENGAJI'
              )
          ) {
            element.textContent =
              'NGAJIYUK!';
          }

        });

    }
  );

})();