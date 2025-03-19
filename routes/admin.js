const express = require('express');
const router = express.Router();

// Middleware to check if the user is authenticated
function isAuthenticated(req, res, next) {
  if (req.isAuthenticated()) {
    return next();
  }
  res.redirect('/login');
}

// Render admin dashboard
router.get('/admin', isAuthenticated, (req, res) => {
  res.render('admin'); // Ensure you have an admin.ejs or admin.pug file in your views directory
});

module.exports = router;
