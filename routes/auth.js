const express = require('express');
const passport = require('passport');
const router = express.Router();

// Render login page
router.get('/login', (req, res) => {
  res.render('login'); // Ensure you have a login.ejs or login.pug file in your views directory
});

// Handle login form submission
router.post('/login', passport.authenticate('local', {
  successRedirect: '/admin',
  failureRedirect: '/login',
  failureFlash: true
}));

module.exports = router;
