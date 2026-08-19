document.addEventListener('DOMContentLoaded', () => {
    /**
     * Sidebar
     */
    const sidebar = document.querySelector('.sidebar');

    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            sidebar?.classList.toggle('open');
        });
    });

    /**
     * Modal
     */
    document.querySelectorAll('[data-modal-open]').forEach((button) => {
        button.addEventListener('click', () => {
            const modalId = button.dataset.modalOpen;

            if (!modalId) {
                return;
            }

            document.getElementById(modalId)?.classList.add('open');
        });
    });

    document.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', () => {
            button.closest('.modal')?.classList.remove('open');
        });
    });

    document.querySelectorAll('.modal').forEach((modal) => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.classList.remove('open');
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            document.querySelectorAll('.modal.open').forEach((modal) => {
                modal.classList.remove('open');
            });
        }
    });

    /**
     * Confirmation
     */
    document.querySelectorAll('[data-confirm]').forEach((element) => {
        element.addEventListener('click', (event) => {
            const message =
                element.dataset.confirm ||
                'Lanjutkan tindakan ini?';

            if (!window.confirm(message)) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
    });

    /**
     * Search Table
     */
    document.querySelectorAll('[data-search-table]').forEach((input) => {
        input.addEventListener('input', () => {
            const selector = input.dataset.searchTable;

            if (!selector) {
                return;
            }

            const table = document.querySelector(selector);

            if (!table) {
                return;
            }

            const needle = input.value
                .trim()
                .toLowerCase();

            table.querySelectorAll('tbody tr').forEach((row) => {
                const text = row.textContent
                    .toLowerCase();

                row.hidden = !text.includes(needle);
            });
        });
    });

    /**
     * Score Average
     */
    document.querySelectorAll('[data-score-group]').forEach((group) => {
        const update = () => {
            const scores = [
                ...group.querySelectorAll('[data-score]')
            ].map((input) => {
                return Number(input.value || 0);
            });

            const average = scores.length
                ? scores.reduce((sum, value) => sum + value, 0) /
                  scores.length
                : 0;

            const output =
                group.querySelector('[data-average]');

            if (output) {
                output.textContent =
                    average.toFixed(2);
            }
        };

        group.querySelectorAll('[data-score]').forEach((input) => {
            input.addEventListener('input', update);
        });

        update();
    });

    /**
     * AJAX Forms
     *
     * PENTING:
     * Jangan menggunakan form.action secara langsung.
     *
     * Form di aplikasi mempunyai input:
     * <input name="action">
     *
     * Named form control dapat berbenturan dengan
     * properti form.action.
     *
     * Gunakan getAttribute('action').
     */
    document.querySelectorAll('form[data-ajax]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const submitButton =
                event.submitter ||
                form.querySelector(
                    'button[type="submit"], input[type="submit"]'
                );

            const actionUrl =
                form.getAttribute('action') ||
                window.location.href;

            const method =
                (
                    form.getAttribute('method') ||
                    'POST'
                ).toUpperCase();

            const formData =
                new FormData(form);

            /**
             * FormData(form) tidak selalu menyertakan
             * button submit yang diklik.
             *
             * Ini penting untuk tombol:
             *
             * name="action"
             * value="approve"
             *
             * name="action"
             * value="reject"
             */
            if (
                event.submitter &&
                event.submitter.name
            ) {
                formData.set(
                    event.submitter.name,
                    event.submitter.value
                );
            }

            submitButton?.setAttribute(
                'disabled',
                'disabled'
            );

            form.classList.add('loading');

            try {
                let requestUrl = actionUrl;

                const options = {
                    method,
                    headers: {
                        Accept: 'application/json'
                    },
                    credentials: 'same-origin'
                };

                if (method === 'GET') {
                    const query =
                        new URLSearchParams(formData);

                    requestUrl +=
                        (requestUrl.includes('?')
                            ? '&'
                            : '?') +
                        query.toString();
                } else {
                    options.body = formData;
                }

                console.debug(
                    '[NgajiYuk AJAX]',
                    method,
                    requestUrl
                );

                const response =
                    await fetch(
                        requestUrl,
                        options
                    );

                const contentType =
                    response.headers.get(
                        'content-type'
                    ) || '';

                /**
                 * Kalau PHP crash dan Apache memberikan
                 * HTML, jangan paksa response.json().
                 */
                if (
                    !contentType.includes(
                        'application/json'
                    )
                ) {
                    const responseText =
                        await response.text();

                    console.error(
                        '[NgajiYuk] Response bukan JSON',
                        {
                            url: requestUrl,
                            status:
                                response.status,
                            response:
                                responseText
                        }
                    );

                    throw new Error(
                        `Server mengembalikan HTTP ${response.status}`
                    );
                }

                const payload =
                    await response.json();

                if (!response.ok) {
                    console.error(
                        '[NgajiYuk API Error]',
                        {
                            url: requestUrl,
                            status:
                                response.status,
                            payload
                        }
                    );
                }

                if (payload.message) {
                    window.alert(
                        payload.message
                    );
                }

                if (!payload.success) {
                    return;
                }

                const redirect =
                    form.dataset.redirect;

                if (redirect) {
                    window.location.href =
                        redirect;
                    return;
                }

                window.location.reload();
            } catch (error) {
                console.error(
                    '[NgajiYuk AJAX Error]',
                    error
                );

                window.alert(
                    'Terjadi gangguan saat mengirim data. ' +
                    'Silakan cek Console atau Network browser.'
                );
            } finally {
                submitButton?.removeAttribute(
                    'disabled'
                );

                form.classList.remove(
                    'loading'
                );
            }
        });
    });
});