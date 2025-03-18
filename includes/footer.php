<?php
include_once __DIR__ . '/../config/constants.php';
?>
</div><!-- /.container -->
    
    <footer class="bg-dark text-white mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><?= APP_NAME ?></h5>
                    <p>Providing quality student accommodation management services.</p>
                </div>
                <div class="col-md-3">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-white">Home</a></li>
                        <li><a href="accommodations.php" class="text-white">Accommodations</a></li>
                        <?php if (!isLoggedIn()): ?>
                            <li><a href="login.php" class="text-white">Login</a></li>
                            <li><a href="register.php" class="text-white">Register</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Contact</h5>
                    <address>
                        <p>123 Campus Drive<br>
                        Pretoria, South Africa<br>
                        <i class="fas fa-phone"></i> +27 12 345 6789<br>
                        <i class="fas fa-envelope"></i> <a href="mailto:info@harambee.co.za" class="text-white">info@harambee.co.za</a></p>
                    </address>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <p>&copy; <?= date('Y') ?> <?= APP_NAME ?>. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script src="js/script.js"></script>
</body>
</html>
