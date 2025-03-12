    </div>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5><i class="fas fa-building me-2"></i> <?php echo APP_NAME; ?></h5>
                    <p>Providing quality student accommodations for a better academic experience.</p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="/index.php" class="text-white"><i class="fas fa-home me-1"></i> Home</a></li>
                        <li><a href="/accommodations.php" class="text-white"><i class="fas fa-building me-1"></i> Accommodations</a></li>
                        <?php if (isLoggedIn() && hasRole(ROLE_STUDENT)): ?>
                            <li><a href="/student/applications.php" class="text-white"><i class="fas fa-file-alt me-1"></i> My Applications</a></li>
                            <li><a href="/student/maintenance.php" class="text-white"><i class="fas fa-tools me-1"></i> Maintenance</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contact Us</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-map-marker-alt me-1"></i> 123 University Avenue, Harambee</li>
                        <li><i class="fas fa-phone me-1"></i> +27 12 345 6789</li>
                        <li><i class="fas fa-envelope me-1"></i> info@harambee.com</li>
                    </ul>
                    <div class="mt-3">
                        <a href="#" class="text-white me-2"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-white me-2"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-linkedin fa-lg"></i></a>
                    </div>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="/js/script.js"></script>
</body>
</html>
