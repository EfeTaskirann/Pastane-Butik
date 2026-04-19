
            </div><!-- /.content-area -->
        </main><!-- /.main-content -->
    </div><!-- /.admin-wrapper -->

    <!-- Mobile Overlay -->
    <div class="overlay" id="overlay"></div>

    <?php $assetsBase = $assetsBase ?? '../assets'; /* header.php'den gelir, emniyet fallback */ ?>
    <script src="<?= e($assetsBase) ?>/js/admin.js" nonce="<?= e(getCspNonce()) ?>"></script>
    <script src="<?= e($assetsBase) ?>/js/components/toast.js?v=1.0" nonce="<?= e(getCspNonce()) ?>"></script>
    <script src="<?= e($assetsBase) ?>/js/components/loading.js?v=1.0" nonce="<?= e(getCspNonce()) ?>"></script>
    <script src="<?= e($assetsBase) ?>/js/components/form-validator.js?v=1.0" nonce="<?= e(getCspNonce()) ?>"></script>
    <script src="<?= e($assetsBase) ?>/js/theme-switcher.js?v=1.0" nonce="<?= e(getCspNonce()) ?>"></script>
</body>
</html>
