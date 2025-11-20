    </div>
    <!-- jQuery first, then Popper.js, then Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Additional JavaScript files -->
    <?php if (!empty($GLOBALS['additionalFooterJS'])): ?>
        <?php foreach ($GLOBALS['additionalFooterJS'] as $jsFile): ?>
            <script src="<?php echo htmlspecialchars($jsFile); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Initialize components -->
    <script>
    $(document).ready(function() {
        // Initialize Select2 if it exists on the page
        if (typeof $.fn.select2 !== 'undefined') {
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Select an option',
                allowClear: true
            });
        }
    });
    </script>
</body>
</html>