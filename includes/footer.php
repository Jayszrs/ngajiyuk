<?php
declare(strict_types=1);

$footerUser = current_user();
$guideRole = $footerUser['role'] ?? 'guest';
$guideTitle = $guideRole === 'guru' ? 'PANDUAN GURU' : ($guideRole === 'admin' ? 'PANDUAN ADMIN' : 'PANDUAN ORANG TUA');
?>
<?php if ($footerUser): ?>
    </main>
    <div class="guide-modal" data-guide-modal data-guide-role="<?= e($guideRole) ?>" aria-hidden="true">
        <div class="guide-dialog" role="dialog" aria-modal="true" aria-labelledby="guide-heading">
            <header class="guide-header">
                <div class="guide-brand">
                    <img src="<?= url('assets/images/logo.png') ?>" alt="Logo sekolah">
                    <span><strong>Catatan Mengaji Digital</strong><small><?= e($guideTitle) ?></small></span>
                </div>
                <button class="guide-close" type="button" data-guide-close aria-label="Tutup panduan">&times;</button>
            </header>
            <div class="guide-content">
                <div class="guide-kicker-row">
                    <span class="guide-kicker" data-guide-kicker>SELAMAT DATANG</span>
                    <strong class="guide-counter" data-guide-counter>1 / 1</strong>
                </div>
                <div class="guide-main">
                    <span class="guide-step-icon" data-guide-icon><?= svg_icon('dashboard', 28) ?></span>
                    <div>
                        <p class="guide-greeting" data-guide-greeting>Halo, <?= e($footerUser['full_name']) ?>!</p>
                        <h2 id="guide-heading" data-guide-title>Mulai menggunakan sistem dengan lebih terarah</h2>
                        <p data-guide-description>Panduan singkat akan membantu Anda memahami alur aplikasi.</p>
                    </div>
                </div>
                <div class="guide-tips" data-guide-tips></div>
                <div class="guide-dots" data-guide-dots></div>
            </div>
            <footer class="guide-footer">
                <button class="guide-skip" type="button" data-guide-skip>Lewati panduan</button>
                <div class="guide-actions">
                    <button class="btn btn-outline" type="button" data-guide-prev>Sebelumnya</button>
                    <button class="btn btn-primary" type="button" data-guide-next>Berikutnya →</button>
                </div>
            </footer>
        </div>
    </div>
<?php endif; ?>
<div class="toast-region" data-toast-region aria-live="polite" aria-atomic="true"></div>
<div class="confirm-modal" data-confirm-modal aria-hidden="true">
    <div class="confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title">
        <span class="confirm-icon"><?= svg_icon('shield', 26) ?></span>
        <h2 id="confirm-title">Konfirmasi Tindakan</h2>
        <p data-confirm-message>Apakah Anda yakin ingin melanjutkan?</p>
        <div class="confirm-actions">
            <button class="btn btn-outline" type="button" data-confirm-cancel>Batal</button>
            <button class="btn btn-danger" type="button" data-confirm-accept>Ya, lanjutkan</button>
        </div>
    </div>
</div>
<script src="<?= url('assets/js/app.js') ?>"></script>
</body>
</html>
